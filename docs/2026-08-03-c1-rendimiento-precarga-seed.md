# Lote C1 Rendimiento — Precarga en segundo plano y seed estable (2026-08-03)

## Contexto

Siguiente lote de optimización del catálogo, tras la auditoría de backend y
los lotes P0/P1. Dos objetivos:

1. **Precarga de la página siguiente en segundo plano**: al navegar el
   catálogo, el HTML de la página 2 se descarga en el `idle` del navegador y
   sus imágenes se cachean en batches. Al hacer clic en "Siguiente", la
   página pinta casi instantánea.
2. **Seed estable en juegos recomendados**: las secciones "Más de X" y
   "Juegos relacionados" de la ficha usaban `RANDOM()` en cada carga, lo que
   hacía que sus imágenes nunca se cachearan (cada visita mostraba otro orden).

Junto con el commit `877263e` (orden aleatorio estable por visitante en el
catálogo, vía cookie `rv_cat_seed`), C1 cierra el recorrido: el orden aleatorio
es ahora **estable por navegador en todo el sitio** para maximizar la reutilización
de caché.

---

## 1. Seed estable en juegos recomendados

### `models/Juego.php`

- `getRelated(int $juegoId, int $consolaId, int $categoriaId, int $limit = 8, string $seed = '')`:
  nuevo parámetro `$seed`, propagado a `relacionadosPorConsola()` y
  `relacionadosPorGenero()`.
- `relacionadosPorConsola()`:
  - Con seed, el `ORDER BY` pasa de `(j.downloads_count * RANDOM()) DESC`
    (aleatorio puro, imágenes no cacheables) a una **aproximación determinista**:

    ```sql
    (j.downloads_count * (('x' || left(md5(j.id::text || :seed), 8)))::bit(32)::bigint / 4294967295.0) DESC
    ```

    Los 8 primeros hex del `md5(id + seed)` se convierten en un número 0..1;
    multiplicado por `downloads_count` reproduce el sesgo de popularidad de
    `RANDOM()` pero de forma estable.
  - Detalle: `::bigint` (no `::int`) para que el hash de 32 bits nunca sea
    negativo (un `::int` con bit alto a 1 habría invertido el orden).
  - Sin seed (fallback) se conserva el `RANDOM()` original.
- `relacionadosPorGenero()` → `queryGenero()`:
  - Nuevo parámetro `$seed`; con seed usa `md5(j.id::text || :seed)` como
    `ORDER BY`; sin seed mantiene el comportamiento previo.
- `getWithRelationsPaginated(..., $seed)` (del commit `877263e`) se mantiene
  como fuente de la seed del catálogo.

### `views/components/related_games.php`

- La llamada a `getRelated()` pasa `Juego::getCatalogSeed()` como quinto
  argumento (misma seed de la cookie `rv_cat_seed` del catálogo).

Resultado: un visitante que abre una ficha varias veces ve siempre el mismo
orden de recomendados → sus portadas se sirven de caché en la segunda visita.

---

## 2. Precarga de la página siguiente en segundo plano

### `views/home/index.php` (JS inline del catálogo)

Se añadieron 4 funciones nuevas dentro de la IIFE:

- **`extractImageUrls(html)`**: parsea el HTML precargado con `DOMParser` y
  devuelve las URLs de sus `<img>`.
- **`preloadImagesInBackground(urls)`**: carga las imágenes en **batches de 4**
  con pausa de 150 ms entre lotes y `fetchPriority = 'low'` (cuando el
  navegador lo soporta). Evita ráfagas de red.
- **`prefetchNextPage(page)`**:
  - No hace nada si ya hay un prefetch programado (`prefetchScheduled`) ni si
    la página solicitada no existe en la paginación actual (comprueba
    `.pagination-link[data-page="N+1"]`).
  - `fetch('ajax_catalog.php?...', { priority: 'low' })` descarga el HTML en
    segundo plano.
  - Al recibirlo, guarda `prefetchedHtml` + `prefetchedParams` y dispara
    `preloadImagesInBackground()`.
  - **Descartes de respuestas obsoletas**: si el usuario cambió de filtros
    mientras el fetch volaba, `prefetchVersion` lo invalida.
  - `prefetchScheduled` se reinicia **solo en el catch** (si el fetch falla):
    tras un éxito la página precargada queda lista para usarse una sola vez.
- **`setupPrefetch(page)`** (llamada al renderizar cada página):
  - Incrementa `prefetchVersion` (invalida prefetches en vuelo).
  - Resetea `prefetchScheduled`.
  - Si existe página `page+1`:
    1. **Observer progresivo**: `IntersectionObserver` con `rootMargin: 800px`
       sobre la última tarjeta → al acercarse al viewport se precarga.
    2. **Refuerzo por idle**: `requestIdleCallback(..., { timeout: 4000 })`
       (o `setTimeout(1500)` como fallback) precarga aunque el usuario no
       llegue al final.

### Consumo del prefetch en `fetchResults()`

- Al construir la petición de una página, si `prefetchedParams === paramsStr`
  y `prefetchedHtml` no es null, se usa el HTML precargado directamente
  (sin fetch) y se limpian las variables.
- Al final de cada render se llama a `setupPrefetch(page)` para preparar la
  siguiente.

La precarga vive en el JS inline de `views/home/index.php` (no en
`public/js/script.js`, ver C1.4).

---

## 3. Limpieza de scripts y CLS

### `views/layout/footer.php`

- `pixelicons.js` y `rv-alerts.js` pasan a `defer`: dejan de bloquear el
  parseo del HTML y se ejecutan tras el DOM.
- Se eliminó la referencia a `public/js/script.js`.

### `public/js/script.js` (eliminado)

- Estaba **vacío (0 bytes)**: no aportaba funcionalidad, pero cada página
  hacía una petición HTTP síncrona innecesaria que bloqueaba brevemente el
  parseo. El resto de la lógica (catálogo, autocomplete, emulador) vive en
  scripts inline de las vistas.

### CLS

- No se añadieron `width/height` explícitos a las imágenes: el layout shift
  ya está cubierto por el CSS existente (`.game-cover` 1/1, `.related-cover`
  3/4, `.detail-cover-frame` fijan `aspect-ratio`).

---

## Verificación

Sin PHP CLI en el entorno: verificación **estática** (grep + revisión de diffs).

- `grep :seed models/Juego.php` → `bindValue` presente en `getWithRelationsPaginated`,
  `relacionadosPorConsola` y `queryGenero`.
- `grep RANDOM()` en `Juego.php` → solo queda en fallbacks sin seed y en el
  mapa de orden explícito `'random'`.
- `grep fetchpriority views/home/index.php` → `fetchpriority="high"` en la
  primera imagen del grid.
- `grep script.js` → 0 referencias en el código (archivo eliminado).
- `grep defer views/layout/footer.php` → ambos scripts con `defer`.

---

## Archivos del lote

| Acción | Archivo |
| --- | --- |
| MODIFICAR | `models/Juego.php` (getRelated + relacionadosPorConsola + queryGenero con `$seed`) |
| MODIFICAR | `views/components/related_games.php` (pasa `Juego::getCatalogSeed()`) |
| MODIFICAR | `views/home/index.php` (precarga en segundo plano + consumo del prefetch) |
| MODIFICAR | `views/layout/footer.php` (`defer` en pixelicons y rv-alerts; sin script.js) |
| ELIMINAR | `public/js/script.js` (estaba vacío) |

Commit: `0634d23`

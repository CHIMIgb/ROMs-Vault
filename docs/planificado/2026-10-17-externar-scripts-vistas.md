# Plan de implementación — Punto 4: separar los `<script>` embebidos en `.php`

**Fecha:** 2026-10-17 · **Estado:** aprobado y en ejecución · **Rama:** `refactor/externar-scripts-vistas` (desde `main` = `d5145f2`).
**Origen:** `docs/planificado/2026-10-12-plan-idiomas-db-uiux-scripts.md`, Bloque 4 (solo este bloque).

## 1. Objetivo

Sacar de las vistas PHP los bloques `<script>` con lógica y servirlos como ficheros
independientes en `public/js/`, sin cambiar el comportamiento. Los datos que hoy viajan
dentro del script (`<?= t(...) ?>`, `<?= $variable ?>`) pasan a una **isla JSON** en el HTML
que el fichero `.js` lee con `JSON.parse`. No se toca la CSP.

## 2. Inventario verificado

15 bloques `<script>` con lógica en 14 vistas (no se cuentan el JSON-LD de
`layout/header.php`, que es dato, ni los 3 `<script src>` ya externos de `layout/footer.php`):

| Vista | Línea | Datos PHP dentro del script |
|---|---|---|
| `home/play.php` (EmulatorJS) | 254 | 30 `<?= ?>` + 4 `<?php` (`window.EJS_*`, `$modoStreaming`, `$biosUrl`, `$needsThreads`, i18n) |
| `home/play.php` (share) | 502 | 4 `json_encode(t(...))` |
| `home/show.php` (share) | 306 | 0 |
| `home/show.php` (carrusel + lightbox) | 348 | 0 |
| `home/index.php` (filtros/autocomplete) | 168 | 3 `json_encode(t(...))` |
| `auth/login.php` | 52 | 0 |
| `errors/404.php` | 51 | 0 |
| `admin/add.php` | 202 | 6 `json_encode(t(...))` |
| `admin/edit.php` | 218 | 8 `json_encode(t(...))` |
| `admin/dashboard.php` | 278 | 14 `json_encode(t(...))` |
| `admin/categorias/index.php` | 157 | 13 `json_encode(t(...))` |
| `admin/categorias/edit.php` | 65 | 4 `json_encode(t(...))` |
| `admin/consolas/index.php` | 169 | 19 `json_encode(t(...))` |
| `admin/consolas/edit.php` | 89 | 4 `json_encode(t(...))` |
| `admin/emuladores/index.php` | 172 | 13 `json_encode(t(...))` |

## 3. Decisiones aprobadas

1. **Se externaliza también el arranque de EmulatorJS** (`play.php:254`) con `defer`.
2. **Sin versionado de caché**: los nuevos `.js` no llevan `?v=` (igual que los 3 JS
   actuales); el cache-busting de `public/js` queda como tarea aparte.
3. **Se incluye** el test `tests/Unit/VistasSinScriptInlineTest.php`.
4. **Docs**: se marca el Bloque 4 en el plan del 2026-10-12 y se crea este documento en
   `docs/planificado/`.

## 4. Diseño

- **Extracción 1:1** del contenido al fichero `.js`, sin cambios de comportamiento ni
  deduplicaciones (los `copyToClipboard` de `show.php`/`play.php` se mantienen duplicados
  como hoy; unificarlos va aparte).
- **Carga:** `<script src="public/js/views/..." defer></script>` (resuelve contra `/` por
  el `<base href="/">`).
- **Datos del servidor → JS:** una isla JSON por script, fuera de la lógica:

  ```php
  <script type="application/json" id="rv-data-play-emulator"><?= json_encode(
      $rvData,
      JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
  ) ?></script>
  ```

  y en el `.js`: `const data = JSON.parse(document.getElementById('rv-data-play-emulator').textContent || '{}');`

  `JSON_HEX_TAG` evita que una cadena con `</script>` rompa el HTML. Se conserva el
  `.replace('{n}', ...)` actual para los textos con placeholder.
- **CSP: sin cambios** (`script-src 'self' 'unsafe-inline' 'unsafe-eval' blob:`). Endurecerla
  exige antes retirar los manejadores `onclick/oninput/onerror` inline (10 en vistas) y una
  verificación en navegador; queda como tarea aparte.
- **`togglePassword`** sigue siendo función global declarada en `auth/login.js` (el `onclick`
  inline la encuentra).

## 5. Ficheros

**A crear (15 JS + 1 test):**

- `public/js/views/home/index.js`
- `public/js/views/home/show-share.js`, `home/show-carousel.js`
- `public/js/views/home/play-emulator.js`, `home/play-share.js`
- `public/js/views/auth/login.js`
- `public/js/views/errors/404.js`
- `public/js/views/admin/add.js`, `admin/edit.js`, `admin/dashboard.js`
- `public/js/views/admin/categorias-index.js`, `admin/categorias-edit.js`
- `public/js/views/admin/consolas-index.js`, `admin/consolas-edit.js`
- `public/js/views/admin/emuladores-index.js`
- `tests/Unit/VistasSinScriptInlineTest.php`

**A modificar (14 vistas):** `src/views/home/{index,show,play}.php`,
`src/views/auth/login.php`, `src/views/errors/404.php`,
`src/views/admin/{add,edit,dashboard}.php`, `src/views/admin/categorias/{index,edit}.php`,
`src/views/admin/consolas/{index,edit}.php`, `src/views/admin/emuladores/index.php`.

**NO se toca:** `layout/header.php`, `layout/footer.php`, `components/*`, `public/css`,
`vercel.json`, CSP, `router.php`, `.htaccess`.

## 5b. Pasos

1. Crear los 15 `.js` (extracción literal + `JSON.parse` de la isla + IIFE existente).
2. Editar las 14 vistas (isla JSON + etiqueta `<script src>`).
3. `php -l` de las 14 vistas y `composer test` (Unit + Integration).
4. Añadir `VistasSinScriptInlineTest`.
5. Smoke local con `php -S localhost:8000 router.php` y navegador.
6. Doc fechado (este documento) y marcar el Bloque 4 en el plan del 2026-10-12.

## 6. Riesgos y compatibilidad

- **Orden de carga** (el mayor): mitigado por `defer` (orden de documento), por el
  `MutationObserver` de `pixelicons.js` (los `<i data-i>` inyectados dinámicamente se siguen
  renderizando) y porque `RVAlerts` solo se usa dentro de manejadores de eventos. El bloque de
  EmulatorJS fija `window.EJS_*` y él mismo inyecta `loader.js` con `async`, así que el orden
  se mantiene. `PXI` no se usa en ninguna vista.
- **EmulatorJS condicional**: en `play.php` la etiqueta y su isla viven dentro de la rama
  `else` del `if ($hayError)`, igual que hoy.
- **Caché**: `.htaccess` aplica `Expires` de 1 semana a JS/CSS; los ficheros nuevos siguen la
  convención actual (sin `?v=`), según la decisión 2.
- **`togglePassword`**: se mantiene como función global accesible desde el `onclick` inline.
- **No romper** URLs públicas (`/rom_proxy.php`, `/ajax_*.php`), `vercel.json`
  (`"rewrites": []`) ni la CSP. Paginación sigue en 20 (4x5).

## 7. Verificación prevista

- `php -l` de las 14 vistas.
- `composer test` (Unit + Integration) completo.
- `tests/Unit/VistasSinScriptInlineTest.php`: recorre `src/views/**/*.php` y falla si queda
  algún `<script>` sin `src` que no sea `application/json` ni `application/ld+json`.
- Smoke con navegador: portada (filtros/autocomplete), ficha (share, carrusel, lightbox),
  `/jugar/{slug}` (EmulatorJS carga y hace Range Requests), 404, login (ojo de contraseña) y
  las 6 pantallas de admin (búsqueda, toggles con confirmación, modal de export y previews de
  capturas).

## 8. Ejecución (2026-10-17)

Hecho en la rama `refactor/externar-scripts-vistas`. Estado: **implementado y verificado**;
pendiente la revisión visual en navegador y el commit.

### 8.1 Extracción

- 15 ficheros `.js` creados en `public/js/views/` (sin `?v=`, cargados con `defer`) y 14 vistas
  editadas con **11 islas JSON**: `rv-data-admin-add`, `-admin-edit`, `-admin-dashboard`,
  `-admin-categorias-index`, `-admin-categorias-edit`, `-admin-consolas-index`,
  `-admin-consolas-edit`, `-admin-emuladores-index`, `-home-index`, `-play-emulator`,
  `-play-share`. No llevan isla los 4 scripts que no usan textos del servidor:
  `auth/login.js`, `errors/404.js`, `home/show-share.js` y `home/show-carousel.js`.
- `play.php`: los dos bloques se externalizaron por separado (`play-emulator.js`, 234 líneas;
  `play-share.js`, 51). En el de EmulatorJS los 9 interpolados (`EJS_core`, `EJS_gameName`,
  `EJS_gameUrl`, `EJS_pathtodata`, `EJS_language`, `loaderUrl`, `modoStreaming`, `totalBytes`,
  `downloadUrl`) pasan a campos de la isla, y los dos `<?php if (...): ?>` (`biosUrl`,
  `needsThreads`) a `if (data.biosUrl)` / `if (data.needsThreads)`. La isla y su
  `<script src>` siguen dentro de la rama `else` del `if ($hayError)`.
- La isla se lee con
  `JSON.parse((document.getElementById('rv-data-…') || {}).textContent || '{}')`; los textos
  con placeholder siguen resolviéndose con `.replace('{n}', …)` en el JS.

### 8.2 Evidencia

| Comprobación | Resultado |
| --- | --- |
| `php -l` de las 14 vistas | sin errores |
| `node --check` de los 15 `.js` | 15/15 OK |
| `phpunit --testsuite Unit` | 192 tests OK (incluye los 5 nuevos del guard) |
| `phpunit` completo (Unit + Integration + Security) | 320 tests, 15 129 aserciones, OK |
| `phpstan analyse` (nivel 5 + baseline) | sin errores |
| Guard `VistasSinScriptInlineTest` | 5 tests OK y **falla** al inyectar un `<script>` inline de prueba |
| Fidelidad de i18n | 365 llamadas `t(...)` comparadas con `HEAD`: 0 diferencias (clave y parámetros) |
| Smoke `php -S` + curl (PowerShell) | `/` 200 (isla + `home/index.js`); `/jugar/banjo-tooie` 200 (las 2 islas + `play-emulator.js`); `/auth/login` 200 (`login.js` + `togglePassword`); ruta inexistente 404 con `errors/404.js`; `/public/js/views/**` 200; `/rom_proxy.php` sin firma 403 JSON; `/src/**` y `/data/**` 403; **0 `<script>` sin `src`** y sin `Warning:`/`Notice:`/`Fatal error` en el HTML |
| Render de las 8 vistas de admin (test de integración temporal, ya borrado) | 8/8 → 200 con su isla y su `.js`, 0 `<script>` sin `src`, sin avisos PHP (74 aserciones) |

### 8.3 Observaciones

- **Sombreado de `data`**: 7 sitios del JS extraído declaran `const data = await res.json()`
  en un bloque anidado (respuesta AJAX) que ahora sombrea la constante de la isla. Revisados
  uno a uno: ningún bloque sombreado usa `data.t[…]` (solo `data.ok`, `data.activo`,
  `data.nombre`, `data.error…`), así que el comportamiento es idéntico. Queda como deuda de
  legibilidad (renombrar la isla a `rvData` y el payload a `payload` en una tarea aparte).
- **Ningún manejador inline llama a funciones extraídas**: los `onclick`/`oninput` de las
  vistas tocadas solo hacen `dispatchEvent`, `requestFullscreen`, `this.style.display` o
  `togglePassword()` (global en `login.js`). Por eso la CSP sigue igual.
- `scratch/smoke.ps1` y `scratch/smoke2.ps1` (throwaway, gitignorado) quedan como utilidad de
  smoke local.

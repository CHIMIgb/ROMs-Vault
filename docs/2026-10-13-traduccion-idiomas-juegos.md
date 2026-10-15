# Traducción de los idiomas de los juegos (`juegos.idiomas`)

**Fecha:** 2026-10-13
**Ámbito:** i18n (bloque 2), contenido de BD — `juegos.idiomas`
**Estado:** implementado y verificado

## 1. Objetivo

El campo `juegos.idiomas` (lista de idiomas disponibles del juego) se mostraba
siempre en español, tanto en el catálogo como en la ficha de detalle, aunque la
interfaz estuviera en otro idioma. Es contenido editorial guardado en la BD, así
que debe tratarse como el resto del contenido traducible: **normalizar el origen
ya registrado y persistir su traducción en `juegos_traducciones`**, igual que
`juegos.descripcion`.

### Decisión de diseño

Se descarta el mapa `IdiomasMap` en código previsto en el plan archivado
(`docs/_archivo/2026-10-12-plan-traduccion-contenido-bd.md`, secciones 4.6/6.5).
El campo es contenido de la BD: su traducción vive en `juegos_traducciones.idiomas`
y se resuelve con `TraduccionService`, con fallback a `es`.

## 2. Cambios

### 2.1 Normalización del origen (datos ya registrados)

- **`src/config/IdiomasNormalizer.php`** (nuevo): normaliza el texto libre a una
  lista canónica separada por comas.
  - Separa por coma, punto y punto y coma (mismo criterio que la vista de detalle).
  - Normaliza el token (minúsculas + sin acentos) y lo mapea a nombre canónico en
    español. Cubre las variantes reales: `Ingles/Inglés`, `Frances/Francés`,
    `Aleman/Alemán`, `italiano/Italiano`, `Japones/Japonese/Japoneses`.
  - Conserva el sufijo `(parche)`: `Español (parche)`.
  - Descarta basura (`NULL`, `11`, vacíos).
  - Token desconocido se conserva con inicial mayúscula (no se inventa).
  - Deduplica sin importar mayúsculas/acentos y preserva el orden.
  - Determinista: mapa explícito de diacríticos, sin depender de `iconv`/locale.

- **`scripts/normalizar-idiomas.php`** (nuevo, CLI): normaliza los juegos ya
  registrados. Idempotente. `--dry-run` previsualiza, `--limite=N` acota.

  Resultado sobre la BD local (123 juegos): **72 normalizados, 50 ya correctos,
  1 sin idiomas, 0 errores**. Segunda ejecución: **0 cambios** (idempotente).

### 2.2 Persistencia de la traducción

- **`data/migrations/2026-10-13-idiomas-en-juegos-traducciones.sql`** (nuevo):
  `ALTER TABLE juegos_traducciones ADD COLUMN IF NOT EXISTS idiomas TEXT`.
- **`data/roms-vaultDB-postgreSQL.sql`**: la columna `idiomas TEXT` se añade al
  `CREATE TABLE juegos_traducciones` (esquema base).
- **`src/models/Traduccion.php`**: `upsertJuego()` acepta `?string $idiomas` y lo
  escribe (INSERT + `ON CONFLICT ... DO UPDATE`). `getJuegos()` ya usa `SELECT *`.
- **`src/config/TraduccionService.php`**:
  - `resolverJuegos()` aplica `['descripcion', 'idiomas']`.
  - `traducirEntidad('juego', ...)` guarda el campo `idiomas` traducido.
  - `normalizarListaIdiomas()` unifica separadores de lista de otros idiomas
    (`、，；،`, `;`) a `, ` para que las vistas pinten los chips igual en todos
    los idiomas.
- **`src/controllers/AdminController.php`**: normaliza `$_POST['idiomas']` antes
  de guardar (alta y edición) y lo incluye en `autoTraducirJuego()`.
- **`scripts/backfill-traducciones.php`**: incluye `idiomas` en el `SELECT` y en
  los campos a traducir.
- **`endpoints/ajax_catalog.php`**: se añade `TraduccionService` y la resolución
  (`resolverJuegos` + `resolverCategoriasVinculadas`). Sin esto, las tarjetas que
  reemplaza el AJAX mostraban categoría, descripción e idiomas en español aunque
  la UI estuviera en otro idioma.

### 2.3 Presentación

**Sin cambios de presentación**: las vistas ya renderizaban el valor de
`juegos.idiomas` (catálogo y ficha de detalle). Al resolverse ahora el valor
traducido, se muestran los idiomas localizados automáticamente.

## 3. Verificación

- Sintaxis: `php -l` sobre todos los archivos nuevos/modificados — sin errores.
- **Unit:** `vendor/bin/phpunit --testsuite Unit` — **114 tests OK**, incluidos los
  9 nuevos de `IdiomasNormalizerTest` y `NoEmojiTest` (sin emojis en el repo).
- **Integration:** `vendor/bin/phpunit --testsuite Integration` — **70 tests OK**,
  incluidos los 3 nuevos de `TraduccionIdiomasTest` (resolución, fallback a `es`,
  y que una corrección manual no se pisa con una automática).
- **Smoke** con `php -S localhost:8123 router.php` contra la BD local:
  `/home/show/1` → 200 (chips con "Francés" normalizado), `/ajax_catalog.php` → 200
  (tarjetas), `/?lang=en` y `/?lang=ja` → 200.
- **Backfill** `--dry-run --solo=juegos` reconoce los juegos con pendientes.

## 4. Activación en producción

1. Aplicar la migración a la BD de producción:
   `data/migrations/2026-10-13-idiomas-en-juegos-traducciones.sql`.
2. Normalizar el origen ya registrado (idempotente):
   `php scripts/normalizar-idiomas.php` (con `.env` apuntando a producción).
3. Con el proveedor de traducción configurado (`TRANSLATE_PROVIDER=deepl`
   + `TRANSLATE_API_KEY`), generar las traducciones:
   `php scripts/backfill-traducciones.php --solo=juegos`.
   También se generan automáticamente al guardar cada juego desde el admin.

## 5. Notas

- La BD de test local (`roms-vault-test`) estaba desactualizada (faltaba
  `juegos_traducciones`); se aplicaron todas las migraciones de `data/migrations/`
  para dejarla al día. Un entorno nuevo queda cubierto por el esquema base.
- Existe el caso de datos sucio `NULL`/`11` (juego #156 "www"), que se normaliza a
  `NULL` (sin idiomas).

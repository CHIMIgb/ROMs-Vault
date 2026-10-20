# 2026-10-18 — Rediseño de la sección de información del juego en `/jugar`

Plan aprobado por el usuario (§10) el mismo día, con la aclaración de que el dato nuevo en rojo es **solo el año** (no el título).

## Contexto

La sección `.emulator-info` de la vista play (portada CRT + columna de datos) incluía descripción y peso — el usuario pidió eliminarlos y **rediseñar** el resto, manteniendo la identidad visual. Tras las eliminaciones la sección quedaba descompensada (hueco en la columna) y con dos botones azules compitiendo (Descargar y Compartir).

## Cambios

**`src/views/home/play.php`** (rama de emulador):

1. **Cabecera** `.emulator-title-row`: `h2` + año nuevo `.play-info-year` (`IntlHelper::formatYear($juego['fecha_lanzamiento'])` guardado con `!empty`; el query de play es `SELECT j.*`, el campo ya venía).
2. **Eliminados**: `<p class="emulator-description">` y el `.emulator-meta-row` completo (peso con icono `hard-drive` + idiomas con icono `globe`).
3. **Idiomas** pasa a chip neutro (`<span class="game-tag">`, estilo base cream-dark) al final de `.emulator-tags` — los chips de toda la app son solo texto, sin iconos.

**`public/css/modules/play.css`**:

4. `.emulator-title-row` (flex baseline, space-between, wrap); `.emulator-game-title` de 0.72rem → 0.9rem con `text-shadow: 2px 2px 0 var(--border-light)` y `word-break` (eco de `.detail-title`); `.play-info-year` P2P 1rem rojo con la misma sombra (eco de `.detail-year`).
5. `.emulator-actions` con `border-top: 3px solid var(--border-dark)` + padding (eco del divisor de `.detail-title-block`).
6. **Botones unificados** al lenguaje canónico (eco `.detail-btn`): P2P 0.55rem, borde 3px, sombra dura 3px, press `translate` (hover 2px/active 3px) para `.btn-dl-small`, `.btn-fullscreen` y `.btn-share`. Jerarquía: **Descargar = azul primario, Pantalla completa = slate, Compartir = ghost cream** (antes share era azul y competía con descargar). Se borró el bloque `.btn-share` viejo (duplicado al final del archivo y ganaba por cascada).
7. Eliminados `.emulator-description`, `.emulator-meta-row`, `.emulator-meta-item`.
8. `@media 576px`: título 0.7rem, año 0.8rem. En `600px` se mantienen los ajustes previos (acciones apiladas, share 0.5rem).

**`public/css/modules/components.css`**: borrada la regla `.emulator-meta-item svg.pxi` (huérfana; 0 usos fuera de play). El glifo `hard-drive` en `pixelicons.js` se conserva (lo usa la ficha "Cómo jugar").

**Sin cambios**: portada CRT, JS (listeners por id `btn-share-game`/`btn-fullscreen`), JSON de progreso (`tamano`/`totalBytes` alimentan la barra de carga — lo eliminado es el peso *visible*), i18n (0 claves nuevas), URLs, CSP.

**`scratch/smoke_play.ps1`**: +7 checks (sin descripción, sin meta/peso, con `emulator-title-row`, con `play-info-year`, 4 chips en el segmento tags→actions, divisor y año rojo en el CSS servido).

**`docs/2026-10-18-rediseño-info-panel-play.md`**: este documento.

## Verificación

- `php -l src/views/home/play.php` → sin errores.
- Llaves balanceadas: `play.css` 104/104, `components.css` 171/171.
- Greps: 0 restos de `emulator-description`/`emulator-meta-row`/`emulator-meta-item`/`hard-drive`/`globe` en la vista; 0 referencias en todo `src/` y `public/js/`.
- Suite Unit → OK (199 tests, 14828 assertions).
- `smoke_play` → 23/23 (incluidos los 7 nuevos).
- `smoke_panel` → SMOKE OK (ficha sin regresiones).
- Render real de `/jugar/sonic-advance-2-2` (fecha `2003` e idiomas presentes en los datos).

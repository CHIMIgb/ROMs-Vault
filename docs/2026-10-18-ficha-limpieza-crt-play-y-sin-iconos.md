# 2026-10-18 — Ficha: limpieza de CSS muerto, CRT en portada play y sin iconos de carga

Plan aprobado por el usuario (§10) el mismo día, con la única exclusión explícita de la propuesta "sin emulación online" (no se añade).

## Contexto

El usuario pidió: (a) analizar la sección de la ficha del juego (portada, nombre, idiomas, peso, región, sinopsis) proponiendo qué eliminar/modificar/añadir, (b) añadir el filtro CRT de la portada de `/juego` a la portada de `/jugar`, (c) eliminar el icono de la barra de tamaño de pantalla y (d) eliminar los iconos de reloj y palomita durante la carga del juego.

### Análisis de la ficha (resultado)

- **Se elimina** el resto de CSS muerto de la franja/spec-sheet borrada en rondas anteriores: `.detail-spec-sheet`, `.spec-label`, `.spec-value` (×2), `.tag-consola`, `.tag-region` (~30 líneas, 0 usos verificados en views/js/tests), y el helper global `formatBytes()` de `show.php` (sin uso; los tests usan `IntlHelper::formatBytes`, que se conserva).
- **No se devuelven** idiomas ni peso al panel: `game_card.php` ya muestra ambos en el catálogo (idiomas resumidos + tamaño con `IntlHelper::formatBytes`) — redundancia, misma razón de su eliminación en `aa3ab46`. La región ya vive como sello en la portada.
- **No se añade** chip de formato: la columna `formato_imagen` está sucia en la BD (mezcla `ISO`/`BIN/CUE` con `.gba`/`.nds` y basura como `1111`) — requiere limpieza de datos, fuera de alcance UI.
- **Se conserva todo lo resuelto a gusto del usuario**: CRT + sello de región + cintas de la portada, título + año rojo, sinopsis inline con capitular, tags género + chip compartir.

## Cambios

1. **`src/views/home/play.php`**:
 - `<div class="emulator-cover-scanlines"></div>` dentro de `.emulator-cover-frame` (antes del `<img>`, mismo patrón que la ficha; también cubre el placeholder).
 - Se elimina el `<svg>` inline del `<span class="size-control-label">` (queda solo el texto `play.tamano_pantalla`).
 - Se elimina el `<i data-i="clock">` de `#proxy-load-text` (queda solo `play.cargando_rom`). El `clock` del iconMap de errores de descarga (`quota`) **se conserva**: es icono de error, no de carga.
2. **`public/css/modules/play.css`**: `.emulator-cover-frame::after` (brillo diagonal 135°, z-index 2) y `.emulator-cover-scanlines` (repeating-linear-gradient de scanlines, z-index 3) — réplica exacta de `.detail-cover-frame::after` / `.detail-cover-scanlines` de la ficha. El frame ya tenía `position: relative` y `overflow: hidden`.
3. **`public/js/views/home/play-emulator.js`** (L151): se sustituye `loadText.innerHTML = '<svg…>' + clave` por `loadText.textContent = data.t['play.rom_cargada']` (la clave es solo texto: "ROM cargada — iniciando emulador…"). `play.procesando {valor}` sigue intacto en `loadDetail`.
4. **`public/css/modules/game-details.css`**: borrado de las 5 clases muertas (4 zonas: media 576px, bloque de tags y regla raíz `.spec-value` con su comentario).
5. **`src/views/home/show.php`**: borrado del helper `formatBytes()` sin uso.
6. **`scratch/smoke_play.ps1`**: +4 checks (`emulator-cover-scanlines` presente, sin `<i` en la barra de carga, sin `<svg` del label, JS sin path de palomita y con `loadText.textContent`).
7. **`scratch/smoke_panel.ps1`**: las 5 clases muertas añadidas a las listas `$dead` (HTML y CSS).
8. **`docs/2026-10-18-ficha-limpieza-crt-play-y-sin-iconos.md`**: este documento.

Sin cambios de URL, CSP, i18n ni tokens de diseño.

## Verificación

- `php -l` en `play.php` y `show.php` → sin errores.
- Suite Unit → OK (199 tests, 14828 assertions), exit=0. `IntlHelperTest` intacto (no depende del helper borrado).
- `smoke_play` → 16/16 (exit=0), incluidos los 4 checks nuevos.
- `smoke_panel` → SMOKE OK (todas las comprobaciones), incluidas las 5 nuevas de clases muertas.
- Llaves balanceadas: `play.css` 101/101, `game-details.css` 220/220.
- Greps: 0 restos de `tag-consola`/`tag-region`/`spec-*`/`detail-spec-sheet` en `src/`, `public/` y `tests/`.

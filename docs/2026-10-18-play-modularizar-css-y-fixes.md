# 2026-10-18 — Vista jugar online: modularización CSS y fixes funcionales

## Contexto

La vista `/jugar/{slug}` (`src/views/home/play.php`) mezclaba su CSS dentro de `game-details.css` (módulo de la ficha), con `components.css` (share) y con `responsive.css` (bloque emulador), dificultando el mantenimiento. Además acumulaba cinco hallazgos funcionales: título de pestaña genérico, `/jugar` sin slug devolvía 404, aviso experimental duplicado ×4 con clase nombrada para PSP (pero usado por PSX/N64/NDS), `onclick` inline para fullscreen, iconos sin `aria-hidden` y CSS muerto.

Plan aprobado por el usuario (§10) el mismo día; las cuatro decisiones entre paréntesis del plan (dejar `.share-toast` y `.game-tag` donde están, renombrar `.psp-warning`, generalizar el redirect) se aprobaron con el plan.

## Cambios

### A. Extracción a `public/css/modules/play.css` (630 líneas)

- **Desde `game-details.css`** (−420 líneas): bloque ERROR DE PROXY (L77–256), EMULATOR PLAY PAGE (L497–653), consola no soportada + `btn-download-big` (L677–702), PROXY LOAD BAR (L704–819) y SLIDER (L821–910). La media query `@media (max-width: 480px)` que mezclaba `.proxy-error-actions` y `.btn-retry/.btn-back-catalog` con reglas de la 404 se partió: lo de la 404 quedó en `game-details.css`, lo de play en `play.css`.
- **Desde `components.css`**: `.btn-share` + su media query a 600px. `.share-toast` se queda en `components.css` por compartirse con la ficha (`show.php`).
- **Desde `responsive.css`**: bloque `/* ─── Emulador ─── */` del `@media 576px` + `.proxy-error-actions` + `.btn-retry/.btn-back-catalog` (partiendo la regla compartida con `.btn-404-*`, que permaneció en responsive). `game-tag` permanece en `game-grid.css` (dashboard + game_card + play).
- **Responsive consolidado** en `play.css`: `@media 600px` unificando las dos medias duplicadas que había dentro de `game-details.css` más `.btn-share`, y `@media 576px` con las reglas móviles de play.
- **CSS muerto eliminado**: `.emulator-unsupported` no se migró (0 usos en el repo).
- `style.css` importa `./modules/play.css` inmediatamente después de `game-details.css`.

### B. Fixes funcionales

| Hallazgo | Corrección |
|---|---|
| `/jugar` sin slug → 404 | `index.php`: pretty routes sin segundo segmento → `Location: /` (aplica a `/juego`, `/jugar`, `/consolas`) |
| Título de pestaña genérico | `HomeController::renderPlay()` fija `$pageTitle = $juego['titulo'] . ' — ROMs Vault'` |
| Aviso experimental ×4 duplicado | `play.php`: array `ppsspp→psp, psx, n64, nds` + una sola rama; clave i18n reutilizadas (sin tocar diccionarios) |
| Clase `.psp-warning` (nombre engañoso) | Renombrada a `.experimental-warning` en `play.php` y `play.css` |
| `#fff8e1` hardcodeado | Token existente `var(--rv-warning-bg)` |
| `onclick` inline de fullscreen | Botón con `id="btn-fullscreen"`; listener en `play-emulator.js` con `?.` |
| 22 `<i data-i>` sin `aria-hidden` | `aria-hidden="true"` añadido en origen |

## Verificación

- `php -l`: `index.php`, `HomeController.php`, `play.php` → sin errores de sintaxis.
- Suite **Unit: OK (199 tests, 14828 assertions)** — incluye `NoEmojiTest` y `Vistas Sin Script Inline`.
- `scratch/smoke_panel.ps1` → **SMOKE OK (todas las comprobaciones)** (ficha intacta).
- `scratch/smoke_play.ps1` → **11/11 OK**: 3 redirects `302 /`, título dinámico, sin `onclick`, 33 `aria-hidden`, `experimental-warning` en CSS, sin `emulator-unsupported`, `id="btn-fullscreen"`, import en `style.css`, sin `.psp-warning` fuera de `play.css`.
- Render real `/jugar/sonic-advance-2-2`: `<title>Sonic Advance 2 — ROMs Vault</title>`.

## Riesgos / compatibilidad

- Ninguna URL pública cambia; `vercel.json` intacto; CSP intacta (mejora: un `onclick` inline menos).
- Riesgo de cascada por mover reglas entre imports: mitigado colocando `play.css` justo después de `game-details.css` y unificando medias query; verificado visualmente en la vista y con el smoke de ficha.
- El aviso experimental solo renderiza para cores psp/psx/n64/nds (GBA y otros no lo muestran), comportamiento idéntico al previo.

# 2026-10-18 — Vista jugar online: mismo ancho que la ficha

## Contexto

El usuario reportó que `/jugar` "toca casi los bordes" mientras que `/juego` muestra un ancho centrado con aire a ambos lados. Causa: la ficha envuelve todo su contenido en `<div class="game-detail-page">` (`max-width: 1060px; margin: 0 auto`) y la vista play no tenía ningún wrapper de página — `emulator-wrapper`, el error de proxy y los juegos relacionados ocupaban todo el `<main>` (sin estilos propios).

Plan aprobado por el usuario (§10) el mismo día.

## Cambios

1. **`public/css/modules/variables.css`**: token nuevo `--page-max-width: 1060px` — centraliza el ancho de las páginas de detalle para que ficha y play no puedan divergir.
2. **`public/css/modules/game-details.css`**: `.game-detail-page` usa `var(--page-max-width)` (mismo valor, 0 cambio visual).
3. **`public/css/modules/play.css`**: nuevo `.play-page` idéntico a `.game-detail-page` (`max-width: var(--page-max-width); margin: 0 auto; padding: 0.5rem 0 2rem`).
4. **`src/views/home/play.php`**: wrapper `<div class="play-page">` que abre antes de las ramas de error/emulador y cierra tras `related_games.php` → cubre error de proxy, emulador y relacionados (mismo alcance que `game-detail-page` en la ficha). Los scripts JSON/JS quedan fuera del wrapper.
5. **`scratch/smoke_play.ps1`**: check `class="play-page"` en el HTML renderido; de paso se corrigió el `exit 1` (buscaba exactamente `'FAIL'` y ahora detecta cualquier `FAIL*`).
6. **`docs/2026-10-18-play-mismo-ancho-que-ficha.md`**: este documento.

Sin media queries nuevas: la ficha tampoco las tiene para `.game-detail-page`, así que móvil y desktop quedan idénticos a la ficha.

## Verificación

- `php -l src/views/home/play.php` → sin errores.
- Suite Unit OK (incluye "Vistas Sin Script Inline").
- `smoke_panel` → OK (ficha intacta: el token tiene el mismo valor que el literal que reemplaza).
- `smoke_play` → 12/12 (incluye el nuevo check de `.play-page`).
- Render real de `/jugar/sonic-advance-2-2`: presencia del wrapper.

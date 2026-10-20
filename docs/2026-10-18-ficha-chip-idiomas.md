# 2026-10-18 — Ficha: chip de idiomas, chip de plataforma y reorganización en dos filas

Plan aprobado por el usuario (§10) el mismo día. La vista `/jugar` **no cambia**: la sección de info recién renovada se queda como está (decisión explícita del usuario). En una segunda vuelta el usuario pidió reubicar idiomas "abajo" y añadir el chip de consola → se optó por **dos filas** (opción elegida por el usuario).

## Contexto

Tras el rediseño de la sección de información de `/jugar` (chip de idiomas incluido), el usuario pidió que la ficha `/juego` muestre también los idiomas, y después prefería no competir en la misma fila con compartir: fila 1 con plataforma + categoría, fila 2 con idiomas a la izquierda y compartir anclado a la derecha. La región ya vive como sello en la portada; la consola no tenía chip en la ficha (solo aparecía en "Cómo jugar").

## Cambios

1. **`src/views/home/show.php`**: `.detail-tags` se divide en dos:
 - **Fila 1**: nuevo chip de **plataforma** `<span class="tag tag-plataforma">` con `$juego['consola_nombre']` (guard `!empty`, sin link — no se inventa URL de detalle de consola), seguido de categoría y hack (como estaban).
 - **Fila 2** `.detail-tags.detail-tags--data`: chip **idiomas** (`title` con el texto completo, guard `!empty`) + botón compartir (se movió desde la fila 1; conserva su id `btn-share-game` y `margin-inline-start: auto`, por lo que queda anclado al extremo derecho de la nueva fila).
2. **`public/css/modules/game-details.css`**:
 - `.detail-tags--data { margin-top: 0.5rem }` — separación entre filas (en móvil ambas se centran con el `justify-content: center` existente).
 - `.tag-plataforma`: azul (`blue-btn`/`cream-light`, borde `#1e4266`, sombra `#1a3a5a`) — mismo tono que el chip plataforma del catálogo y patrón de sombra de sus hermanos.
 - `.tag-idiomas` (del apartado anterior): neutro cream-dark/slate con ellipsis.
3. **`scratch/smoke_panel.ps1`**: +5 checks — chip idiomas x1, chip plataforma x1, fila `detail-tags--data` x1, orden idiomas < compartir, y CSS `.tag-plataforma` con `blue-btn`.
4. **`docs/2026-10-18-ficha-chip-idiomas.md`**: este documento (ampliado con la segunda vuelta).

Sin tokens nuevos, sin `.env`, sin paquetes, sin claves i18n (los chips son dato puro, sin iconos).

## Verificación

- `php -l src/views/home/show.php` → sin errores.
- Llaves balanceadas en `game-details.css`.
- Suite Unit → OK (199 tests, 14828 assertions).
- `smoke_panel` → SMOKE OK (incluidos los 5 checks nuevos).
- `smoke_play` → 23/23 (regresión de la rama play).
- Render real de `/juego/clock-tower-advance`: fila 1 con plataforma+categoría, fila 2 con idiomas antes del compartir.

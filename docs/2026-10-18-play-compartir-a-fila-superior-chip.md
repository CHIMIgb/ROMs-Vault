# 2026-10-18 — Compartir en `/jugar` movido a la fila de chips (estilo chip de la ficha)

## Objetivo

En `/jugar` el botón **Compartir** se mueve de la fila de acciones
(`.emulator-actions`) a la **fila de chips de metadatos** (`.emulator-tags`, junto a
consola/región/género/idiomas), anclado a la **derecha**, y adopta el **estilo del
chip de compartir de la ficha** (`/juego`): `.stat-chip .stat-chip--share`.
**Descargar ROM** y **Pantalla completa** permanecen sin cambios en la fila de acciones.

> Nota: la primera iteración lo colocó en `.emulator-title-row`; se corrigió a la fila
> de chips (`.emulator-tags`) por indicación del usuario, replicando el patrón de la
> ficha (`.detail-tags .stat-chip--share { margin-inline-start: auto }`).

## Cambios

- **`src/views/home/play.php`**
  - El `<button id="btn-share-game">` se traslada a `.emulator-tags` (tras los `game-tag`)
    con clases `stat-chip stat-chip--share emulator-share-chip` (reutiliza el estilo
    de la ficha). Conserva `id`, `data-url` y `title` → el JS `play-share.js`
    (que usa `getElementById`) sigue funcionando sin cambios.
  - `.emulator-actions` se queda solo con **Descargar ROM** (`btn-dl-small`) y
    **Pantalla completa** (`btn-fullscreen`).
- **`public/css/modules/play.css`**
  - Se eliminan las reglas huérfanas `.btn-share` (bloque de botones y media query);
    el bloque de botones pasa a cubrir solo `.btn-dl-small` y `.btn-fullscreen`.
  - `.emulator-title-row`: sin cambios respecto al estado previo (`justify-content:
    space-between`); el título vuelve a su layout de dos items (título + año).
  - `.emulator-tags`: añadido `align-items: center` y
    `.emulator-tags .emulator-share-chip { margin-inline-start: auto }` para anclar el
    chip a la derecha de la fila, igual que `.detail-tags` en la ficha.

## Por qué el chip no "choca" con los botones

El chip de compartir es un elemento de **jerarquía secundaria** (mismo lenguaje que los
chips neutros del sistema, igual que en la ficha), no un botón de acción. Convive con
Descargar/Fullescreen porque:
- **No compite por el rol primario**: Descargar (azul, brillo CRT) sigue siendo el CTA.
- **Vive en otra zona** (fila de chips de metadatos), no en la fila de acciones.
- **Usa el mismo vocabulario** de la ficha, reforzando la coherencia entre vistas.

No es una mezcla accidental de estilos: es una jerarquía deliberada.

## Verificación

- `php -l` OK.
- Llaves `play.css` 103/103 equilibradas.
- Render real `/jugar/clock-tower-advance`: `title-row` solo con título + año; chip en
  `.emulator-tags` (con `id`), junto a consola/idiomas; `actions` solo con descargar +
  fullscreen; `class="btn-share"` ya no existe.
- `smoke_play` 23/23 · `smoke_panel` SMOKE OK · Unit 199 tests, 14828 assertions.

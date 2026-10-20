# 2026-10-18 — Rediseño del panel de información de la ficha de detalle

## Contexto

El panel `.detail-info-panel` de `/juego/{slug}` (vista `src/views/home/show.php`) acumulaba
información que el usuario consideró redundante o mal jerarquizada: chips de descargas/jugadas
(visibles también en el catálogo), bloque de idiomas (ídem), una franja de caja
`consola · región · formato` fuera de lugar, un año de lanzamiento desproporcionado y una
sección "Sinopsis" independiente tras el hero que hacía crecer la página.

### Decisiones del usuario (2026-10-18, 1ª ronda)

1. Año en el panel (estilo original, ver 2ª ronda).
2. **Eliminar** descargas/jugadas del hero (ya se muestran en el catálogo completo).
3. **Eliminar** idiomas del hero (catálogo los muestra; candidatos a otra sección más adelante).
4. **Conservar** el tag de género.
5. Franja `consola · región · formato` → probada bajo el cover y **eliminada en la 2ª ronda**
   (no convenció).
6. **Eliminar la sección "Sinopsis"** y llevarla inline al panel, conservando estilos y tipografía
   idénticos, con la capitular roja actual.
7. Compartir: **opción b** — chip sutil al final de la fila de tags (género a la izquierda,
   compartir a la derecha); no se pierde la función.

### Decisiones (2ª ronda, mismo día)

- **Franja**: eliminar del todo (HTML + CSS).
- **Año**: restaurar el estilo original — Press Start 2P **1.6rem en rojo** con `text-shadow`
  retro (1.2rem en ≤576px), sin chip/borde/fondo (se descarta el chip pequeño de la ronda 1).
- **Sinopsis con scroll interno**: `max-height: 14rem` + `overflow-y: auto` → al superar ese
  alto se habilita **la barra retro global de la app** (`base.css`: 10px, track crema, thumb
  gris) en lugar de hacer crecer el contenedor. Contenedor accesible por teclado
  (`tabindex="0"` + `role="region"` + `aria-label` con la clave existente `show.sinopsis`);
  el anillo rojo de foco global (`:focus-visible` de `base.css`) lo cubre sin CSS extra.

Sinopsis sin clamp ni "Leer más": el comportamiento visual es texto completo con tope de alto
y scroll solo cuando procede; el 96% de las sinopsis tiene ≤140 chars (mediana 25) → la
mayoría ni siquiera ve scroll. No se añade JS ni claves i18n nuevas.

## Cambios

### [MODIFICAR] `src/views/home/show.php`

- Bloque `.detail-stats-bar` eliminado (chips descargas/jugadas + compartir).
- Botón compartir reubicado al final de `.detail-tags` (misma id `btn-share-game`, sin cambios
  en `show-share.js` ni el toast).
- Bloque `.detail-languages` eliminado; en consecuencia se eliminan el cálculo de `$idiomas`
  y el flag `$emulacionOnline` (muerto tras quitar las stats; `game_actions.php` define el suyo
  propio en la línea 17).
- Nueva sección de sinopsis inline: `.detail-panel-synopsis` (con `tabindex`, `role="region"`
  y `aria-label`) envolviendo `.detail-synopsis-content[synopsis-dropcap]` dentro del panel,
  con la misma PHP de siempre (`nl2br(htmlspecialchars())` y condición `\p{Latin}`).
- Sección `<section class="detail-synopsis">` (con su `h2` y badge hack) eliminada; la señal
  Hack ya vive en `.tag-hack` y en la cinta del cover.
- Franja `.detail-cover-labels` eliminada (probada bajo el cover en la ronda 1 y retirada en la 2).

### [MODIFICAR] `public/css/modules/game-details.css`

- `.detail-year` **restaurado al estilo original**: Press Start 2P, 1.6rem, `--red`,
  `text-shadow` retro; ≤576px → 1.2rem (sin reglas de chip).
- `.detail-panel-synopsis`: `min-width:0`, `overflow-wrap:anywhere`, **`max-height:14rem`**,
  **`overflow-y:auto`**, `padding-inline-end:0.35rem` (aire con la barra de 10px).
- `.detail-tags .stat-chip--share { margin-inline-start:auto }` para anclar el chip a la derecha;
  en ≤860px se pone a 0 para no romper el grupo centrado.
- CSS muerto eliminado (verificado por grep: 0 restos): `.detail-stats-bar`, `.stat-chip-num`,
  `.stat-chip-label`, `.detail-languages*`, `.language-tag`, `.detail-cover-labels`,
  `.detail-synopsis` (tarjeta, `::before`, animación `detailRise`, padding responsive) y
  `.synopsis-hack-badge`. Se conservan `.stat-chip` base + `.stat-chip--share`,
  `.detail-synopsis-content`, `.synopsis-dropcap` y `.detail-section-title` (siguen en uso).

## Datos de justificación

- Sinopsis de los 124 juegos activos: 0 vacías; 119 (96%) ≤140 chars (mediana 25),
  4 de 141-600, 1 de >600 (máx. 991). Con `max-height:14rem` (~7-8 líneas) solo la sinopsis
  de 991 chars (y quizá alguna de ~600) activa el scroll; la mayoría ni lo ve.
- Franja (ya eliminada): el peor caso real era `Game Boy Advance` + `NTSC-U` + `BIN/CUE` = 32
  chars ≈ 277px < 326px del marco — cabía, pero se retiró por decisión estética del usuario.

## Verificación (2026-10-18, 2ª ronda)

- `php -l src/views/home/show.php` → sin errores.
- Suite Unit (`vendor/bin/phpunit --testsuite Unit`): **OK — 199 tests, 14828 assertions**
  (incluye `VistasSinScriptInline` y `NoEmoji`).
- Smoke `scratch/smoke_panel.ps1` contra `php -S localhost:8000 router.php`: **39/39 PASS**
  - Ficha: sinopsis inline con `tabindex/role/aria-label`, orden
    `panel < tags < compartir < sinopsis < acciones`, 0 restos de franja/stats/idiomas/badge,
    año ×1, chip compartir ×1.
  - CSS: `max-height: 14rem` + `overflow-y: auto`, año restaurado (1.6rem, rojo, sin fondo),
    capitular y tipografía conservadas, 0 clases muertas, llaves 310/310,
    scrollbar global de `base.css` presente.
  - Ruta legacy `/home/show/1` y catálogo `/` (20 tarjetas) sin regresiones.

## Notas y pendientes

- Verificación visual en navegador pendiente por el usuario (no hay browser en este entorno);
  el servidor de pruebas corre en `localhost:8000`.
- Umbral de scroll (`max-height: 14rem`) es un único valor en CSS, ajustable a petición.
- Helper global `formatBytes()` en `show.php` ya estaba sin uso antes de este cambio
  (preexistente, fuera de alcance).
- Claves i18n que `show.php` dejó de usar (`show.descargas`, `show.jugadas_label`,
  `show.idiomas`, etc.) se conservan intactas: no se tocaron los diccionarios.
- Pendiente futuro (no aprobado): arreglo del fallback de capturas ×4 (117/124 juegos = 94%
  muestran la portada recortada como "capturas").

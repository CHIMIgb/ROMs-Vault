# Contenedor de portada con tamaño variable en la vista de jugar online

**Fecha:** 2026-08-02
**Archivos modificados:**
- `views/home/play.php` — Portada envuelta en `.emulator-cover-frame`; clases nuevas `.emulator-cover-img` y `.emulator-cover-placeholder`
- `public/css/modules/game-details.css` — Marco CRT de tamaño variable, centrado vertical, tamaños responsive
- `public/css/modules/responsive.css` — Tamaño móvil de la portada
- `public/css/modules/components.css` — Eliminado CSS muerto de `.emulator-cover--placeholder`

## Qué se hizo

La portada de la vista de jugar online se mostraba en un contenedor fijo que recortaba la imagen. Ahora usa el mismo patrón que el detalle (`.detail-cover-frame`) pero en menor tamaño:

- **Contenedor de tamaño variable:** `.emulator-cover-frame` usa `width/height: max-content`, así el marco se adapta a cada imagen y la portada se muestra completa y nítida, sin recortes. Marco CRT: fondo oscuro `#0a0a0a`, borde 3px `--border-dark`, sombra interior `inset 0 0 20px rgba(0,0,0,0.5)` y anillo `0 0 0 2px var(--border-mid)`, con `overflow: hidden`.
- **Centrado vertical en desktop:** `.emulator-game-meta` vuelve a fila con `align-items: center`, alineando la portada con el título y las etiquetas.
- **Tamaños:** imagen máx. 170×220px (desktop), 160px (≤600px) y 140px (≤576px en `responsive.css`), siempre con `width/height: auto` para no distorsionar.
- **Placeholder** (juego sin imagen): `.emulator-cover-placeholder` de 150×200px con icono de disco y fondo `--slate`; tamaños reducidos en responsive.
- **Limpieza:** eliminadas las clases obsoletas `.emulator-cover` y `.emulator-cover--placeholder` (markup y CSS).

## Verificación

- Revisión estática: ninguna referencia restante a `.emulator-cover` (suelto) ni `.emulator-cover--placeholder` en vistas ni CSS.
- En producción: abrir la vista de jugar online con un juego con portada y con un juego sin portada; confirmar que la imagen se ve completa (sin recortes), centrada verticalmente en desktop y legible en móvil.

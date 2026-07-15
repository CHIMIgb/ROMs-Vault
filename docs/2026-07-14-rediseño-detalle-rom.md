# Rediseño de la vista de detalle de ROMs

**Fecha:** 2026-07-14
**Archivos modificados:**
- `views/home/show.php` — Reestructuración del HTML
- `public/css/modules/game-details.css` — Nuevos estilos para la ficha de detalle

## Qué se hizo

Se rediseñó la página de detalle de ROMs (`show.php`) para mejorar la jerarquía visual y la experiencia de usuario, manteniendo la estética retro del proyecto.

### Cambios en el HTML (`show.php`)
- Se añadió un enlace "Volver al catálogo" al inicio de la página
- Se reemplazó el layout `flex` por un `CSS Grid` de dos columnas (cover + info)
- Se sustituyó la grilla de metadata por un diseño "spec sheet" con líneas punteadas entre label y valor
- Se reemplazaron los stats por chips inline con iconos
- Se mejoraron los botones de acción con clases semánticas (`detail-btn--play`, `detail-btn--download`)
- Se añadió un título de sección con icono para la sinopsis

### Cambios en el CSS (`game-details.css`)
- **Cover con marco CRT**: Borde negro con scanlines sutiles y gradiente de brillo, evocando una pantalla de consola
- **Spec sheet**: Estilo de ficha técnica con `label ····· valor`, reminiscente de la caja de un juego
- **Botones de acción**: Más grandes, con sombra 3D acentuada y efecto `::before` de brillo
- **Sinopsis**: Sección con borde inferior en el título y espaciado mejorado
- **Responsive**: Breakpoints en 860px y 576px para layout de una columna en móvil

### Qué no se cambió
- La paleta de colores y tipografía del sistema existente
- El componente de juegos relacionados (`related_games.php`)
- Los estilos de la página del emulador (`play.php`)

## Decisiones de diseño
- El marco CRT del cover es el elemento "firma" de esta página — un toque atmosférico que refuerza la temática retro sin ser invasivo
- El spec sheet con líneas punteadas es más escaneable que la grilla de metadata anterior
- Los botones de acción usan el sistema de sombras 3D existente pero con más peso visual para destacar como CTA primarios

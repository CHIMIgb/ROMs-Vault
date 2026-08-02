# Idiomas en la vista de detalle (frontend)

**Fecha:** 2026-08-02
**Archivos modificados:**
- `views/home/show.php` — Parseo de `$juego['idiomas']` + fila de chips en el panel de info
- `public/css/modules/game-details.css` — Estilos `.detail-languages` / `.language-tag` y responsive

## Qué se hizo

La ficha de un juego (`show.php`) no mostraba los idiomas disponibles, aunque la columna `idiomas` existe en BD (`VARCHAR(200)`, lista separada por comas) y ya se renderizaba en crudo en `play.php` y el catálogo.

Ahora el detalle muestra una fila **Idiomas** en el panel de info ("caja de coleccionista"), entre las etiquetas de caja (consola / región / formato) y los botones de acción.

### Parseo y normalización
- La BD tiene datos históricos inconsistentes: separadores por comas y por puntos, mayúsculas mezcladas y listas vacías.
- `$idiomas` se arma en `show.php` con:
  - Split por `[,.;]`
  - `trim` y descarte de vacíos
  - Dedupe sin distinguir mayúsculas (`strtolower` — seguro, los nombres de idioma empiezan con ASCII)
  - `ucfirst` (mayúscula inicial para mostrar)
  - **Preservando el orden original** de la BD: el primer idioma suele ser el principal del juego
- No se corrigen typos del dato (p. ej. "Japonese" → "Japonés"): eso es limpieza de datos, no de la vista.

### Diseño
- Etiqueta **IDIOMAS** en Press Start 2P con icono `globe` (el mismo que ya usa `play.php`).
- Chips compactos con la paleta de `.tag-region` (slate / cream, borde 2px, sombra dura 2px): sin colores nuevos, coherente con el tema retro.
- `flex-wrap` para listas largas (hay juegos con 10 idiomas); centrado en el media query de 860px y tamaño de chip reducido en 576px.
- Accesibilidad: `aria-label="Idiomas disponibles"` en el contenedor, icono `aria-hidden`, texto real en cada chip (no depende del color).

## Qué no se cambió
- `play.php`, catálogo y panel admin: siguen mostrando el dato crudo; la normalización es solo visual en el detalle.
- El modelo `Juego` y el controlador: no se tocaron.

## Verificación
- Sin PHP CLI en el entorno de desarrollo: revisión estática del PHP (parseo con `preg_split`, `strtolower`, `ucfirst` — sin dependencia de mbstring) y compatibilidad del marcado con el sistema de iconos `data-i="globe"`.
- En producción: abrir la ficha de un juego con idiomas (p. ej. uno con "Español, Inglés, Frances, Alemán, italiano") y confirmar los chips; uno sin idiomas (`NULL`) no debe mostrar la fila.

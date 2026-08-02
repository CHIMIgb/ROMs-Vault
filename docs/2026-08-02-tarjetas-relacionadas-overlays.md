# Tarjetas de juegos relacionados: limpieza de overlays y ensanchado

**Fecha:** 2026-08-02
**Archivos modificados:**
- `views/components/related_games.php` — Eliminación de overlays redundantes
- `public/css/modules/game-grid.css` — Anchura de tarjetas + limpieza de CSS muerto
- `public/css/modules/responsive.css` — Limpieza de reglas huérfanas

## Qué se hizo

Las tarjetas de juegos relacionados mostraban la región y la consola **tres veces** cada una. La información ya se presenta en los tags debajo del título de la tarjeta (`.related-game-meta`), por lo que los overlays sobre el cover eran redundantes.

### Cambios en el HTML (`related_games.php`)
- Se eliminó el sello de región de la esquina superior derecha del cover (`.related-region-seal`)
- Se eliminó la etiqueta de consola al pie del cover, "debajo de la imagen" (`.related-cart-label`)
- Se mantiene el área de info de la tarjeta (título + tags de plataforma e idioma), que ya cubre esos datos

### Cambios en el CSS (`game-grid.css`)
- Las tarjetas pasaron de `minmax(140px, 1fr)` a `minmax(150px, 1fr)` (≈10px más anchas por columna de la grilla)
- Se eliminaron las reglas `.related-region-seal` y `.related-cart-label` que quedaron sin uso
- Se eliminó la regla huérfana equivalente en `responsive.css`

## Decisiones de diseño
- El cover ahora queda limpio: solo la imagen (o placeholder) con el marco CRT y scanline
- La grilla `auto-fill, minmax(150px, 1fr)` absorbe el nuevo ancho: en pantallas amplias caben las mismas columnas y en tamaños medios una menos pero más respirable
- Se evitó CSS muerto en el código para mantener el módulo `game-grid.css` sin reglas huérfanas

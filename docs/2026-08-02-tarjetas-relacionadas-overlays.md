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

## Ajuste posterior: 5 columnas fijas
- Se reemplazó `repeat(auto-fill, minmax(150px, 1fr))` por `repeat(5, 1fr)` en `.related-grid`
- Con `auto-fill` y el ancho del contenedor el navegador cabía 6 columnas; ahora el grid de escritorio muestra exactamente 5 por fila (los 8 relacionados quedan en 5 + 3)
- El breakpoint móvil (≤576px → 2 columnas) en `responsive.css` no se tocó

## Ajuste posterior: hover coherente con la app
- Se eliminó el `border-color: var(--red)` del hover de `.related-card` — el rojo en la app se reserva para CTAs, errores y focus, no para tarjetas
- El hover ahora es idéntico al de `.game-card` del catálogo: elevación con `translate(-2px, -2px)` + sombra 3D más profunda
- Se eliminó el zoom `scale(1.05)` de la portada, que solo existía en las relacionadas
- Se quitó `border-color` de la lista de propiedades en `transition` (ya no se anima)

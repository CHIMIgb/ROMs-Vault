# 2026-10-18 — Visor de capturas: tamaño reducido y adaptativo con márgenes

## Problema

Al ampliar una captura en el lightbox de la ficha de detalle (`/juego/{slug}`), la X de cierre
**salía del borde superior de la pantalla** y el visor ocupaba casi todo el viewport sin
respiración en los bordes.

Causa raíz (CSS puro — `show-carousel.js` no fija tamaños):

- `.screenshot-lightbox-close` estaba con `top: -3.2rem` (−51px): la X vive fuera del
  contenedor, hacia arriba.
- El contenido llegaba a `img { max-height: 76vh }` + marco (~22px) + miniaturas (~60px)
  ≈ 76vh + 130px. En 1366×768 eso deja el borde superior del contenido a ~27px del viewport
  → la X (51px) se salía. En ≤620px era peor: `padding: 0.6rem` (9.6px) frente a `top: -2.9rem`
  (46px).
- `max-width: 1000px` + `width:100%` llenaba casi todo el ancho (solo 24px laterales).

## Cambio (aprobado por el usuario, 2026-10-18)

CSS únicamente, en `public/css/modules/game-details.css` (bloque VISOR/lightbox):

| Propiedad | Antes | Ahora |
|---|---|---|
| `.screenshot-lightbox` padding | `1.5rem` (móvil `0.6rem`) | `clamp(1rem, 3vw, 2rem)` (móvil `1rem`) |
| `.screenshot-lightbox-content` | `max-width: 1000px` | `max-width: min(920px, 100%)` |
| `.screenshot-lightbox-close` top | `-3.2rem` (móvil `-2.9rem`) | `-3.5rem` (sin override móvil) |
| `.screenshot-lightbox-close` lado | `inset-inline-end: 0` | arriba-**derecha** (`inset-inline-end: 0`), tras un paso intermedio por la izquierda |
| `img` lightbox `max-height` | `76vh` (móvil `62vh`) | `min(70vh, calc(100vh - 240px))` (móvil `min(55vh, calc(100vh - 230px)))` |

El `calc(100vh - 240px)` reserva: padding doble del visor + marco del viewport + fila de
miniaturas + holgura para la X. Con la X ahora a solo −1.5rem del marco, el hueco superior
resultante es ≥ ~55px en cualquier pantalla → **la X nunca sale**.

**Ajuste posterior (mismo día, a petición del usuario, disposición final):**

1. *Primer paso:* con la X arriba-derecha se solapaba con el contador de capturas
   (`.screenshot-counter`: `top: 18px; inset-inline-end: 18px`). Se movió la X al lado
   izquierdo como medida provisional.
2. *Petición final del usuario:* **intercambiar** — X de cerrar a la **derecha**
   (`.screenshot-lightbox-close { inset-inline-end: 0; top: -1.5rem }`) y contador del visor
   a la **izquierda** mediante regla scoped
   `.screenshot-carousel--lightbox .screenshot-counter { inset-inline-start: 18px;
   inset-inline-end: auto }`. Geometría: X = −24..+20px a la derecha, contador =
   +18..+51px a la izquierda → **cero solape**.
3. El contador del **carrusel embebido en la ficha** (fuera del visor) **no cambia**
   (arriba-derecha): la regla está scoped con `--lightbox`. Si se desea consistencia,
   aplicar el mismo swap a la regla base queda como mejora pendiente de aprobación.

## Verificación de números (hueco superior = (100vh − contenido) / 2)

| Pantalla | Imagen | Contenido | Hueco superior | X (24px) |
|---|---|---|---|---|
| 1366×768 | 528px | ≈610 | ~79px | ✓ |
| 1920×1080 | 756px | ≈838 | ~121px | ✓ |
| 1024×600 | 360px | ≈442 | ~79px | ✓ |
| Móvil 640h | 352px | ≈480 | ~80px | ✓ |

Ancho: `min(920px, 100%)` con padding `clamp` → laterales ≥16px (móvil) / ≥32px (escritorio).

## Verificación (2026-10-18)

- `php -l src/views/home/show.php` → sin errores (sin cambios PHP en esta ronda, por rutina).
- Suite Unit (`vendor/bin/phpunit --testsuite Unit`): **OK — 199 tests, 14828 assertions**.
- Smoke `scratch/smoke_panel.ps1`: checks nuevos del lightbox (padding clamp, 920px,
  top −1.5rem, X arriba-derecha `inset-inline-end: 0`, contador del visor arriba-izquierda
  scoped, `min(70vh, calc(...))` y móvil) + ausencia de los valores viejos (`1000px`, `76vh`,
  `-3.2rem`, `-2.9rem`, `padding 0.6rem` del visor, `inset-inline-start` en la regla de la X).

## Notas

- Sin cambios de HTML, JS, i18n ni tokens.
- Los tres valores (`920px`, `70vh`, `-1.5rem`) son fácilmente ajustables si la revisión
  visual pide otro equilibrio.
- Revisión visual en navegador pendiente por el usuario (servidor dev en `localhost:8000`).

# 2026-10-18 — Contador del preview de capturas: arriba-izquierda

## Problema

En el **preview** de capturas de la ficha de detalle (carrusel embebido,
`data-carousel-counter`), el contador `1 / N` estaba arriba-**derecha**
(`inset-inline-end: 18px` en la regla base `.screenshot-counter`), mientras que en el
visor (lightbox) ya estaba a la izquierda — petición del usuario: moverlo a la
izquierda en el preview, igual que en el visor.

## Cambio (aprobado por el usuario, 2026-10-18)

CSS únicamente, en `public/css/modules/game-details.css`:

1. **Regla base** `.screenshot-counter` (preview + visor): `inset-inline-end: 18px` →
   `inset-inline-start: 18px; inset-inline-end: auto` → arriba-izquierda.
2. **Media query móvil**: `inset-inline-end: 14px` → `inset-inline-start: 14px;
   inset-inline-end: auto` (mantiene `top: 14px` y `font-size`).
3. **Eliminada** la regla scoped `.screenshot-carousel--lightbox .screenshot-counter`
   (añadida en el ajuste anterior del visor): tras unificar la base era 100 %
   redundante — mismos valores —, así que se evita código duplicado.

Resultado: **ambos contadores a la izquierda**, en espejo con la X del visor
(arriba-derecha). El aspecto del visor **no cambia** (el scoped producía exactamente
los mismos valores que la base unificada).

## Verificación (2026-10-18)

- `php -l src/views/home/show.php` → sin errores (sin cambios PHP, por rutina).
- Suite Unit (`vendor/bin/phpunit --testsuite Unit`): **OK — 199 tests, 14828 assertions**.
- Smoke `scratch/smoke_panel.ps1`: checks de la base (`inset-inline-start: 18px`,
  `inset-inline-end: auto`), media móvil (`inset-inline-start: 14px`) y ausencia del
  scoped eliminado; resto de checks intactos.
- Llaves CSS balanceadas.

## Notas

- Sin cambios de HTML, JS ni i18n. URLs, CSP y `vercel.json` intactos.
- Revisión visual en navegador pendiente por el usuario (servidor dev en `localhost:8000`).

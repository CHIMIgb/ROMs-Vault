# Tarjeta del catálogo: alt decorativo en portada + reglas CSS muertas

**Fecha:** 2026-10-18
**Rama:** `mejora/uiux-lote-c-design-system`
**Ámbito:** accesibilidad (a11y) + mantenimiento CSS. Sin cambio visual.

## Objetivo

Dos correcciones de higiene detectadas en la auditoría manual de `src/views/components/game_card.php`:

1. **`alt` duplicado en la portada.** El enlace `.game-detail-link` contiene `<img alt="<titulo>">` y, justo debajo, el `<h3 class="game-title">` con ese mismo título. Un lector de pantalla anunciaba el título **dos veces por tarjeta**. La imagen es decorativa en este contexto (el título visible está en el mismo enlace), así que pasa a `alt=""`.
2. **CSS muerto `.game-download span`.** La regla era un residuo de la marca anterior del botón de descarga (cuando el icono era un `<span>`). Hoy `game_actions.php` genera `<i data-i="download">` + nodo de texto plano, y pixelicons.js inyecta un `<svg class="pxi">`: no existe ningún `<span>` dentro de `.game-download`. Se eliminaron sus 2 ocurrencias (`game-grid.css` escritorio y `responsive.css` ≤576px).

## Archivos afectados

```
[MODIFICAR] src/views/components/game_card.php        — alt="" con comentario de motivo
[MODIFICAR] public/css/modules/game-grid.css          — bloque .game-download span eliminado
[MODIFICAR] public/css/modules/responsive.css         — regla .game-download span (≤576) eliminada
[CREAR]     docs/2026-10-18-tarjeta-alt-decorativo-y-css-muerto.md — este documento
```

## Verificación (evidencia)

- `php -l src/views/components/game_card.php` → OK.
- `grep "game-download span" public/css/` → **0 ocurrencias**.
- `vendor/bin/phpunit --testsuite Unit` → **OK (199 tests, 14828 assertions)**.
- Smoke `php -S localhost:8000 router.php` → `/` = 200, **20 tarjetas**, 20 con `alt=""` en portada, **0** con `alt` no vacío dentro de `.game-cover`.

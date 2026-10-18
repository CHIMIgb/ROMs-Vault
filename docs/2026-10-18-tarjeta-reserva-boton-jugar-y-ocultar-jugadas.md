# Tarjeta: reserva del hueco del botón Jugar + ocultar "Jugadas" sin emulación online

**Fecha:** 2026-10-18
**Rama:** `mejora/uiux-lote-c-design-system`
**Ámbito:** catálogo (SSR + AJAX), ficha de detalle, CSS (game-grid/responsive)
**Causa raíz analizada:** el espacio entre la caja `.game-info` y los botones de `.game-actions` no es un margen fijo: `.game-actions { margin-top: auto }` ancla los botones al fondo de la tarjeta y absorbe la holgura que deja la fila del grid (`align-items: stretch`). El hueco visible = margen fijo de `.game-info` (0.5rem / 0.3rem móvil) + holgura de la fila.

## Síntoma reportado

- **Filtro PC:** las 2 tarjetas PC son idénticas (3 filas de info + 1 solo botón, porque PC tiene `emulacion_online = false`) → holgura 0 → hueco = solo 0.5rem → aspecto "pegado".
- **Navegación manual:** las filas mezclan tarjetas de distinta altura (global: 4 juegos con 2 filas de info, 35 con 3, 85 con 4; además 1 vs 2 botones según `consola.emulacion_online`) → la vecina más alta infla la fila → las tarjetas PC absorben sobrante → hueco visible. Mismo HTML, mismas reglas: cambian los vecinos.

## Cambios

1. **Reserva de la altura del botón Jugar (Opción 1 aprobada).**
   - `game-grid.css` `.game-actions`: `justify-content: flex-end` + `min-height: calc(0.42rem*2 + 4px + 11px + 0.35rem + 0.38rem*2 + 4px + 11px)` (= 61.2px: padding + borde + icono 11px + gap de ambos botones). Descarga queda anclada y alineada al fondo de la fila.
   - `responsive.css` ≤576px: `min-height` con métricas móviles (`0.5/0.48rem`, gap `0.28rem`, icono sigue en 11px) = 65.84px. En ≤360 no hace falta override (el icono de 11px sigue gobernando el contenido → natural ≥ este valor).
   - **Garantía de cero cambio en tarjetas con Jugar + Descargar:** su alto natural es siempre ≥ el cálculo (el contenido de un botón es ≥ icono de 11px; el `line-height` heredado 1.6 solo puede añadir) → el `min-height` no llega a actuar. `justify-content:flex-end` tampoco tiene efecto cuando el contenido llena el bloque.
2. **Fila "Jugadas" solo con emulación online.** Sin botón Jugar = sin contador de jugadas:
   - `game_card.php`: `$emulacionOnline = (bool)($juego['consola_emulacion_online'] ?? true)` (mismo fallback que `game_actions.php`); la fila se renderiza solo si `$emulacionOnline && plays_count > 0`.
   - `show.php` (ficha): el stat-chip de jugadas se envuelve en el mismo `$emulacionOnline`. Chip de descargas intacto (la descarga siempre está disponible).
   - Impacto de datos (Neon): **39 juegos** dejan de mostrar "Jugadas" — DS 19, PSP 14, PSX 6 (todos con `plays_count > 0` y sin emulación online). PC: 0 (ya no mostraban, `plays = 0`).

## Archivos afectados

```
[MODIFICAR] src/views/components/game_card.php   — flag + condición de fila Jugadas
[MODIFICAR] src/views/home/show.php              — flag + envuelve stat-chip jugadas
[MODIFICAR] public/css/modules/game-grid.css     — .game-actions: flex-end + min-height (escritorio)
[MODIFICAR] public/css/modules/responsive.css    — .game-actions: min-height ≤576
[CREAR]     docs/2026-10-18-tarjeta-reserva-boton-jugar-y-ocultar-jugadas.md — este documento
```

Sin i18n nueva (claves `home.jugadas`/`show.jugadas_label` ya existentes), sin tocar `game_actions.php` (compartido: la ficha de detalle no cambia en sus botones), sin tocar URLs/CSP/`vercel.json`.

## Verificación (evidencia)

- `php -l` → OK en `game_card.php` y `show.php`.
- `vendor/bin/phpunit --testsuite Unit` → **OK (199 tests, 14828 assertions)**.
- Smoke `php -S localhost:8000 router.php` (script `scratch/smoke-tarjeta.ps1`, cookie `rv_lang=es`) → **16/16 PASS**:
  - `GET /` → 200, 20 tarjetas.
  - `ajax_catalog.php?consola=16` (PC) → 2 tarjetas, **0** filas "Jugadas".
  - `ajax_catalog.php?consola=1` (PSP, 14 juegos con `plays>0`) → 20 tarjetas, **0** filas "Jugadas".
  - `ajax_catalog.php?consola=4&orden=jugados` (GBA, online) → **20** filas "Jugadas" (caso positivo).
  - Ficha `/juego/carnivores-dinosaur-hunter` (PSP, plays=6, sin online) → 2 stat-chip (descargas+compartir), **0** botón Jugar.
  - Ficha `/juego/clock-tower-advance` (GBA, plays=11, online) → 3 stat-chip (incluye jugadas), 1 botón Jugar.
  - `public/css/modules/game-grid.css` y `responsive.css` → 200 y con los nuevos `min-height`.

Nota: los recursos estáticos se piden como `/public/css/*` (el `<link>` es `href="public/css/style.css"` con `<base href="/">`); el router los resuelve contra `<raíz>/public/...` y `return false` los sirve desde la raíz del proyecto.

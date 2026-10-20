# Tarjeta de juego: tamaño MB/GB unificado + mejora de UX

**Fecha:** 2026-10-17  
**Rama:** mejora/uiux-lote-c-design-system  
**Ámbito:** catálogo (index + AJAX), CSS, i18n, IntlHelper

## Objetivo

Aplicar lo aprobado:
- Unificar la visualización del tamaño usando `IntlHelper::formatBytes()` con redondeo MB/GB (opción A) tanto en SSR como en AJAX.
- Extraer el markup de la tarjeta a un componente compartido `src/views/components/game_card.php`.
- Añadir badge **NUEVO** (ribbon a la derecha de la portada) si la ROM fue creada hace < 30 días (mismo criterio que `show.php`).
- Añadir ribbon **HACK** (a la izquierda) si `formato_imagen === 'Hack'` (coherente con la ficha: `detail-hack-ribbon`, fondo `--amber-dark`, AA 6.78:1).
- En "Idiomas", si hay ≥2, mostrar `"{count} idiomas"` y el listado completo en `title`; si 0-1, mostrar el nombre (o fallback).
- Eliminar el bloque `.game-hack-info` (no era información del hack, era la descripción repetida).

## Archivos afectados

```
[CREAR] src/views/components/game_card.php
[CREAR] tests/Unit/IntlHelperTest.php
[MODIFICAR] src/config/IntlHelper.php
[MODIFICAR] src/views/home/index.php
[MODIFICAR] endpoints/ajax_catalog.php
[MODIFICAR] public/css/modules/game-grid.css
[MODIFICAR] public/css/modules/responsive.css
[MODIFICAR] src/i18n/*.json  (añade component.idiomas_count, paridad I18nTest)
[CREAR] docs/2026-10-17-tarjeta-juego-tamano-y-analisis.md
```

## ¿Por qué componente compartido?

La tarjeta se pintaba dos veces con HTML casi idéntico: `home/index.php` (SSR) y `endpoints/ajax_catalog.php` (AJAX). Eso provocaba divergencias: el AJAX usaba `number_format($bytes/1048576,2).' MB'` (hardcodeado), el SSR usaba `IntlHelper::formatBytes()`. Al moverlo a un único archivo, el formato de tamaño es el mismo en ambos caminos y no volverá a desincronizarse. Además, se añade onerror a la imagen en la versión AJAX y se homogeneizan los textos (`region`, `consola_nombre`, etc.) con la versión SSR.

## Cambio en IntlHelper::formatBytes()

- Unidades: GB (1 decimal), MB/KB/B (sin decimales). Umbrales binarios.
- Si el redondeo alcanza la unidad superior (ej. 1073741823 B → "1024 MB"), se promociona a la unidad mayor ("1,0 GB").
- Sincrónico con el resto de la app (usa `NumberFormatter` con locale o fallback numérico coherente con el idioma activo).

## Nuevas clases/estilos

CSS en `game-grid.css`:
- `.game-hack-ribbon` — esquina superior izquierda, ámbar oscuro, texto/ícono pequeños, acorde a la tarjeta.
- `.game-new-badge` — esquina superior derecha, rojo.
Ambos `position:absolute`, `z-index:4`, `pointer-events:none`, sobre `.game-cover` (`position:relative; overflow:hidden`).

## Traducciones

Añadida clave `component.idiomas_count` en los 12 idiomas: `"{count} idiomas"` etc. Esto mantiene la paridad estricta que exige `I18nTest::testDiccionariosJsonValidosYParitarios`.

## Verificación

- php -l: OK para los archivos tocados.
- `tests/Unit/IntlHelperTest.php`: 7 tests, 28 assertions (OK).
- `tests/Unit/I18nTest.php`: 19 tests (OK).
- `tests/Unit/VistasSinScriptInlineTest.php`: 5 tests (OK).
- Suite completa: **327 tests, 15205 assertions (OK)**.
- Smoke-wave1: **SMOKE: TODO OK** (6/6).

## Nota sobre datos

Hoy ningún juego tiene `formato_imagen = 'Hack'` ni creado hace < 30 días, por lo que los ribbons no aparecerán en SSR ni AJAX hasta que haya datos que los cumplan. No es un cambio visual activo ahora, es una mejora estructurada.

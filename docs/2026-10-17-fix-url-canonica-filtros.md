# Fix — URL canónica tras limpiar filtros (`?page=1&orden=recientes` → `/`)

**Fecha:** 2026-10-17
**Rama:** `mejora/uiux-lote-c-design-system`
**Origen:** bug reportado por el usuario + plan §10 aprobado (2026-10-17)
**Commit:** `9f2dc5d` (5 JS)

---

## 1. Síntoma

Al usar un filtro del catálogo y pulsar "Limpiar", la URL pasaba a ser
`http://localhost:8000/?page=1&orden=recientes` en lugar del index literal
`http://localhost:8000/`. Mismo patrón en admin: `?page=1` tras limpiar.

## 2. Causa raíz

- `getUiParams()` era una copia de `getAjaxParams()`, que **siempre** incluye
  `page` (`page || 1` ≥ 1) y — en home — `orden` (el `<select>` siempre tiene
  valor). `history.replaceState('?' + params)` por tanto nunca podía producir
  una query vacía.
- Además, `btnLimpiar` de home forzaba `orden = 'recientes'`, mientras que el
  estado inicial del index es **`random`** (default del servidor en
  `HomeController:41` y `ajax_catalog.php:38`, y opción seleccionada en el
  select con `$_GET['orden'] ?? 'random'`).
- El servidor ya soportaba URL limpia: los 5 controladores hacen
  `page ?? 1` y `orden` default `'random'` (verificado).

## 3. Cambios (aprobados en el plan)

| Archivo | Cambio |
|---|---|
| `public/js/views/home/index.js` | `getUiParams` elimina `page=1` y `orden=random`; `replaceState` con ternario → `location.pathname` si query vacía; `btnLimpiar` → `orden='random'`; `updateLimpiar` incluye `orden !== 'random'` |
| `public/js/views/admin/dashboard.js` | `getUiParams` elimina `page=1`; `replaceState` con ternario |
| `public/js/views/admin/emuladores-index.js` | Ídem + fallback de error `window.location.href` con el mismo ternario |
| `public/js/views/admin/consolas-index.js` | Ídem |
| `public/js/views/admin/categorias-index.js` | Ídem |

- **`getAjaxParams` intacto** — el AJAX sigue enviando `page`/`orden`
  explícitos: cero cambio en el fetching.
- **Comportamiento aprobado:** tras "Limpiar" el orden queda en **Aleatorio**
  (estado inicial del index) en vez de "Más recientes" — URL + select +
  servidor coherentes. Alternativa descartada: cambiar el default global del
  catálogo a `recientes` (habría sido decisión de producto sobre `/` entera).

## 4. Comportamiento resultante (casos)

| Acción | URL |
|---|---|
| Limpiar filtros (home) | `/` |
| Limpiar (admin dashboard/listados) | `/admin/dashboard`, `/admin/emuladores`… sin query |
| Página 2+ | `?page=2` (se conserva) |
| Orden no-default (A-Z…) | `?orden=titulo` (se conserva) |
| Búsqueda/filtros activos | sus params (se conservan) |
| Solo page=1 sin más filtros | `/` (sin `?page=1`) |

Fuera de alcance deliberado: normalizar en carga inicial URLs heredadas
(`/?page=1&orden=recientes` de bookmarks viejos) — se reescriben solas al
primer filtro; añadirlo en init chocaría con la entrada legacy `index.php?...`.

## 5. Verificación (evidencia)

- `vendor/bin/phpunit` → **OK (320 tests, 15153 assertions)**.
- Smoke `scratch/smoke-wave1.ps1` → **SMOKE: TODO OK** (6/6).
- Greps: residuos `'?' + getUiParams` = 0; `ordenEl.value = 'recientes'` = 0;
  `uiQs` presente en los 5 ficheros (13 usos).
- Confirmación visual pendiente del usuario: limpiar → `/` exacta y orden en
  "Aleatorio"; paginar y orden no-default conservan su query.

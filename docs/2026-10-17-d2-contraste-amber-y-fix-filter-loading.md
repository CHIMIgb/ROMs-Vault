# Fix de "buscando" permanente + D2 — contraste ámbar

**Fecha:** 2026-10-17
**Rama:** `mejora/uiux-lote-c-design-system`
**Origen:** plan §10 aprobado por el usuario (2026-10-17) — corrección del bug reportado + D2 del Bloque 3 (`docs/planificado/2026-10-17-plan-uiux-lotes-pendientes-bloque3.md`)
**Auditoría:** `docs/2026-10-17-auditoria-uiux-bloque3-lote-a-d.md`

**Commits:**

| # | Commit | Contenido |
|---|--------|-----------|
| ① | `ad9b3bf` | Fix `.filter-loading` (base `display:none`) |
| ② | `1386a3d` | D2 — los 3 fondos ámbar → `--amber-dark` |
| ③ | — | Este doc + estados + ajuste de comentarios de contraste a valores exactos |

---

## 1. Bug: la animación de "buscando" se veía siempre

**Síntoma reportado:** al entrar a la aplicación, al limpiar los filtros o al volver atrás, la barra "buscando" aparecía en la barra de filtros con los filtros vacíos y nunca desaparecía.

**Causa raíz — regresión del Lote C:** la base `.filter-loading` se quedó en `display: flex` (`game-details.css:15`). Las olas 1 y 3 de C1 borraron los **5** inline `style="display:none"` de las vistas (`home/index`, `admin/dashboard`, `admin/emuladores`, `admin/consolas`, `admin/categorias`) sin flippear la base a `none` — fue el **único base-state omitido** de todo el Lote C.

**Por qué el JS no lo ocultaba:** el JS es correcto (`fetchResults` escribe `flex` al empezar y `none` en el `finally`), pero en carga inicial **no se ejecuta ningún fetch**, así que nada escribía `none`. Tras un back con bfcache se restauraba el mismo estado visible. (Al limpiar filtros sí corre un fetch y se oculta al terminar, pero cada entrada/back lo revivía → percepción de "nunca se quita").

**Inventario de rigor:** se revisaron los **35 `display` inline borrados** en los commits ③ (`d72a28c`) y ④ (`66b863d`): `.filter-loading` (×5) era el **único** estado-oculto sin base `none`. Los demás (`clear-filters` ×5, `rv-modal-overlay`, `eye-off-icon`, `proxy-load-bar`, `game-detail-link`, estructurales flex/grid) tienen su base correcta.

**Fix:** 1 línea — `display: flex → none` con comentario. Cobre las 5 vistas de una vez (clase compartida). JS intacto. Restaura el comportamiento pre-Lote C.

## 2. D2 — fondos ámbar → `--amber-dark` (opción 1, la recomendada)

Solo existen **3 fondos `--amber`** en el proyecto (grep verificado: 0 restantes tras el cambio) = los 3 objetivos de D2.

| Componente | Uso | Texto | Antes | Después |
|---|---|---|---|---|
| `.tag-hack` | badge "HACK" en la ficha (`show.php:106`), 12px bold | `cream-light` | 2.77:1 ✗ | **6.78:1** ✓ |
| `.detail-hack-ribbon` | cinta "HACK" sobre el cover, 0.42rem | `cream-light` | 2.77:1 ✗ | **6.78:1** ✓ |
| `.rv-btn--warning` | botones de confirmación *warning* de alertas admin | `#fff` | 3.26:1 ✗ | **7.98:1** ✓ (hover `#4a3606`: 11.5:1) |

Notas:
- La auditoría citaba 3.25:1 para `.tag-hack`; ese valor era el del texto **blanco** sobre ámbar (ratio del botón). `cream-light` sobre `--amber` estaba peor: **2.77:1**. El ratio 6.78:1 del fondo nuevo coincide con el token comment de A3 en `variables.css`.
- `.detail-hack-ribbon` se oscureció también (decisión aprobada en el plan): es texto de 0.42rem — no exento de AA — y debe ser coherente con el badge. Sus bordes `--border-dark` (#3a3a3a) ya eran más oscuros que el fondo nuevo.
- `.tag-hack`: borde/sombra `#8a6a00` → `#4a3606` para que sigan siendo más oscuros que el fondo (misma relación de luminancia ≈0.6× y patrón de los `.tag` hermanos, que usan valores fijos más oscuros: `#1e4266`, `#1a1a1a`).
- `.rv-btn--warning:hover`: `#9a6f09` → `#4a3606` mantiene el patrón "hover más oscuro" de la familia `rv-btn`.

## 3. Verificación (evidencia)

- `grep "background: var(--amber)"` → **0**; `--amber-dark` → **3**.
- `vendor/bin/phpunit` (Unit + Integration) → **OK (320 tests, 15153 assertions)**.
- Smoke `scratch/smoke-wave1.ps1` → **SMOKE: TODO OK** (6/6).
- Confirmación visual del usuario pendiente: carga home/admin sin barra "buscando", limpiar y back sin reaparición, ficha con badge/cinta oscuros, toast *warning* de admin.

## 4. Fuera de alcance

- Deudas del Lote C ya registradas: `endpoints/` (≈22 `style=`), manejadores `onclick=`/`oninput=` inline, 4.º parámetro de `Alert::render` (3 `style=` restantes en `src/views`, de los 144 iniciales).
- No se editó `docs/planificado/...bloque3.md` (no estaba entre los archivos aprobados de este plan); el estado de D2 queda en la auditoría.

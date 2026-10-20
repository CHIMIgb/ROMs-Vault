# Lote C — Design system / mantenimiento (Bloque 3)

**Fecha:** 2026-10-17
**Rama:** `mejora/uiux-lote-c-design-system` (apilada sobre `mejora/uiux-lote-b-i18n-visual`)
**Plan origen:** `docs/planificado/2026-10-17-plan-uiux-lotes-pendientes-bloque3.md` (Lote C, plan §10 aprobado — D2 excluido)
**Auditoría:** `docs/2026-10-17-auditoria-uiux-bloque3-lote-a-d.md` (C1, C2, C3, C5)
**Principio rector:** mantenimiento del design system **sin cambio visual** — mismos valores, mismos estados, cero restyling.

---

## 1. Commits

| # | Commit | Contenido |
|---|--------|-----------|
| 1 | `5b55cd1` | **C5** (cabecera `variables.css` + `position:relative` redundante) + **C3** (sombras hardcoded → `--raise-shadow`, `--raise-shadow-sm`, `--card-raise-lg`; únicos documentados con comentario) |
| 2 | `982398d` | **C2** — 55 usos de alias → tokens canónicos hex-idénticos en 15 archivos; bloque de 14 alias eliminado de `variables.css`; grep alias = 0 |
| 3 | `d72a28c` | **C1 ola admin-1** — `tfa.php` (25 → 0) y `dashboard.php` (24 → 2 dinámicos) |
| 4 | `66b863d` | **C1 olas admin-2 + front** — 79 + 14 estilos → clases |
| 5 | — | Este documento + estados en auditoría y plan |

## 2. C1 — inventario final

De **144** ocurrencias `style="…"` en `src/views` (auditoría: "~145 en 19 vistas") a **3**:

- `admin/dashboard.php` ×2 — `width:<?= $pct ?>%` (dinámico, por diseño).
- `components/Alert.php` ×1 — API del componente (4.º parámetro `$style`), fuera del alcance del grep de vistas.

Fuera de alcance (documentado, no modificado): `endpoints/` (≈22 `style=`, criterio solo `src/views`), manejadores `onclick=`/`oninput=` inline (el test `VistasSinScriptInline` solo revisa etiquetas `<script>`).

## 3. Clases creadas (por módulo)

**`admin.css`** — `stat-card--mb-15/--mb-1`, `ranking-title--tight`, `admin-hint` (+`--tight`, `--lead`), `stat-value--ok/--muted`, `ranking-bar-fill--plays`, `admin-table-wrap`, `.admin-table .col-center`, `.admin-table .col-num`, `.admin-table code`, `export-note`, `export-select`, `tfa-secret-row/-label/-code/-code--sm`, `cell-meta`, `cell-ellipsis` (+`--wide`).

**`components.css`** — `btn-primary--success/--danger/--ghost/--delete` (tras `:hover` con doble clase: conservan el comportamiento de hover pisado que tenían los inline), `text-muted`, `text-underline`, `btn-block`, `.pagination .pagination-ellipsis`, `clear-filters--nowrap`, `rv-modal-body--export`; estados base: `.clear-filters` y `.rv-modal-overlay` → `display:none`; `.pagination` margen superior 2.5→1.5rem y `.pagination-info` 0.6→0.75rem (valores que el componente siempre sobreescribía con inline: render idéntico).

**`forms.css`** — `form-row`, `form-group--grow/--grow-sm`, `form-lead`, `form-section`/-`title`/`-sub`, `form-grid-2`, `check-row`, `check-label`, `check-card` (+ descendientes checkbox/label/small), `img-preview` (+ `img`, `-text`, `-label`, `-aviso`), `auth-hint` (+`--tfa`), `#eye-off-icon` (estado inicial del toggle de `login.js`).

**`game-details.css`** — `.proxy-load-bar` base `display:none` (solo `play-emulator.js` escribe `style.display`).
**`game-grid.css`** — `game-detail-link`.

## 4. Verificación (evidencia)

- `php -l` — todos los PHP tocados, 0 errores (olas 1/2/3: 2 + 11 + 5 archivos).
- `vendor/bin/phpunit --testdox` (Unit + Integration) — **OK (320 tests, 15153 assertions)**, 0 fallos, en cada ola.
- `grep -ro 'style="' src/views/ | wc -l` → **3** (objetivo §10: ≤40).
- Smoke `php -S localhost:8000 router.php` (6/6): `/` (200 + `lang-menu`), `/pagina-inexistente-xyz` (404 + `data-back`), `/home/show/1` (301 canonical → `/juego/<slug>`, 200 + `synopsis-dropcap`), `/rom_proxy.php` sin firma (400), `/admin/dashboard` y `/admin/tfa` sin sesión (302 → login).
- Compatibilidad JS revisada a mano: todos los JS solo **escriben** `style.display` (nunca leen de la hoja) en los elementos movidos a base-oculto (`clear-filters`, `filter-loading`, `rv-modal-overlay`, `proxy-load-bar`, `eye-off-icon`).

## 5. Notas y decisiones

- **D2 (contraste ámbar) excluido** de este lote por decisión del usuario — se aplicó por separado el 2026-10-17 (ver `docs/2026-10-17-d2-contraste-amber-y-fix-filter-loading.md`).
- **Corrección post-cierre (2026-10-17):** `.filter-loading` quedó con base `display:flex` al borrar sus inline en las olas 1 y 3 (único base-state omitido del lote) — la barra "buscando" se veía siempre en carga inicial, back o limpieza de filtros. Corregido en `ad9b3bf` (base → `display:none`); el JS era correcto.
- Cambios de valor en CSS base (márgenes de `.pagination`, `display:none` de estados): solo seguros porque el uso inline anterior **siempre** ganaba en todos los renderizados — el resultado efectivo no cambia.
- Los inline de `Alert::render(..., $style)` y de `endpoints/` quedan como deuda menor registrada; moverlos exige tocar la API del componente o criterios de alcance ampliados (pendiente de decisión futura).

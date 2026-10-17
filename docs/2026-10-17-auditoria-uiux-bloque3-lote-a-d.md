# Auditoría UI/UX Bloque 3 — Reparaciones de accesibilidad y estado vacío (Lote A + D)

**Fecha:** 2026-10-17
**Rama:** `mejora/uiux-lote-a-accesibilidad`
**Plan origen:** `docs/planificado/2026-10-12-plan-idiomas-db-uiux-scripts.md` (Bloque 3 — Correcciones y ajustes de UI/UX)
**Principio rector:** reparar reglas rotas (contraste, foco, targets, estados) **sin alterar la identidad visual** — paleta, fuentes, sombras sólidas, radius 0 y estética cartucho/CRT intactas.

---

## 1. Auditoría completa (hallazgos priorizados)

### P0 — Accesibilidad (WCAG 2.2 AA)

| ID | Hallazgo | Ubicación | Estado |
|----|----------|-----------|--------|
| A1 | Sin `:focus-visible` global: el foco de teclado es invisible en toda la app (solo 2 scopes parciales) | `base.css` (ausente) | **APLICADO** |
| A2 | `--slate-light` #8a8a8a = 2.93:1 sobre cream-light y 2.09:1 sobre body (falla AA) | `variables.css` + usos en 8 módulos | **APLICADO** |
| A3 | Ámbar (#b8860b) como texto = 2.77:1 sobre cream-light (falla AA) | `game-details.css` (.synopsis-hack-badge, .proxy-tip-label) | **APLICADO** |
| A4 | Targets táctiles < 24×24px en móvil (botones de tarjeta ~21px, paginación ~22px) | `responsive.css` ≤576px | **APLICADO** |

### P1 — i18n visual

| ID | Hallazgo | Estado |
|----|----------|--------|
| B1 | `.lang-menu` sin `max-height` con 12 idiomas (viewport bajo → opciones inalcanzables) | **APLICADO** (2026-10-17, Lote B) |
| B2 | `nowrap + ellipsis` desborda cadenas largas (de/ru) en tarjetas y tags | **DECISIÓN:** mantener el clamp — es arquitectura deliberada de altura fija de tarjeta. Documentado en `game-grid.css` |
| B3 | `javascript:history.back()` en `errors/404.php` | **APLICADO** (2026-10-17, Lote B) — ampliado a `errors/generic.php` y al `location.reload()` de `home/play.php` (criterio `grep → 0`) |
| B4 | Drop-cap `::first-letter` con scripts CJK/RTL | **APLICADO** (2026-10-17, Lote B) |

### P2 — Design system / mantenimiento

| ID | Hallazgo | Estado |
|----|----------|--------|
| C1 | ~145 estilos inline en 19 vistas (concentrados en admin) | **PENDIENTE** (lote C) |
| C2 | Doble sistema de tokens (alias `--primary/--text-light/...` + paleta canónica) | **PENDIENTE** (lote C) |
| C3 | Sombras hardcoded (~30 sitios, mismo valor que tokens existentes) | **PENDIENTE** (lote C) |
| C4 | Radios 8/6/4px en carrusel de capturas | **DECISIÓN:** efecto "bisel de TV/CRT" intencional. Documentado en `game-details.css` |
| C5 | Cabecera `variables.css:1` dice "style.css"; `style="position:relative"` redundante en `home/index.php:5` | **PENDIENTE** (menor) |
| D1 | Estado vacío del catálogo = alerta genérica sin identidad | **APLICADO** (nuevo componente CRT) |

---

## 2. Cambios aplicados en esta rama

### Lote A — Accesibilidad base

**A1 — Focus visible global** (`public/css/modules/base.css`)
- Regla `:focus-visible` global: `outline: 3px solid var(--red); outline-offset: 2px` — anillo retro rojo coherente con el design system, solo teclado (ratón no lo ve).
- Los elementos con `outline:none` en `:focus` (búsqueda, filtros, lang-trigger) conservan su indicador propio (borde rojo): no se tocan.

**A2 — Contraste de grises** (`public/css/modules/variables.css`, `layout.css`, `components.css`)
- `--slate-light`: #8a8a8a → **#666666** (AA 4.88:1 sobre cream-light; era 2.93:1).
- `--slate-mid`: #5a5a5a → **#4f4f4f** (AA 4.96:1 sobre cream-dark del body; era 4.18:1). Casi invisible, jerarquía preservada (#3a3a3a → #4f4f4f → #666666).
- Alias `--text-light` acompañe al token (#666666).
- Overrides puntuales donde el token no aplica por contexto:
  - `.footer-note`: usa `--slate-mid` (vive sobre cream-dark del body).
  - `.rv-toast-close`: usa `--cream-light` (el toast tiene fondo slate oscuro; el viejo slate-light daba 2.93:1 ahí — también estaba roto).

**A3 — Ámbar como texto** (`variables.css`, `game-details.css`)
- Token nuevo `--amber-dark: #664d0a` (AA 4.83:1 sobre cream-dark, 6.78:1 sobre cream-light).
- `.synopsis-hack-badge` y `.proxy-tip-label`: color (y borde del badge) → `--amber-dark`. El ámbar brillante se reserva para bordes decorativos y fondos.

**A4 — Targets táctiles móvil** (`responsive.css`, ≤576px)
- `.game-play` padding 0.38/0.3rem → 0.5/0.35rem; `.game-download` 0.3/0.3rem → 0.48/0.35rem (~24-25px de alto).
- Paginación: padding 0.3/0.45rem → 0.45/0.55rem, min-width 24→28px (~25px de alto).
- En ≤360px solo baja el font-size; el padding hereda de 576 → el target se mantiene.

### Lote D — Estado vacío del catálogo (nuevo componente CRT)

**Componente** `src/views/components/EmptyState.php` (patrón Alert: `render()` estático, traducciones ya resueltas por el caller)
- Pantalla oscura (`--slate`) + scanlines + icono disco + título Press Start 2P + mensaje Courier + botón "Limpiar".
- `role="status"` para lectores de pantalla; botón con estilo retro (cream-light + sombra sólida).

**CSS** (`components.css`): bloque `.empty-state*` con el mismo lenguaje CRT que `.screenshot-viewport` (scanlines idénticos, sombra `--raise-shadow`).

**Vistas** — sustituye la alerta genérica en ambos puntos de render del catálogo:
- `src/views/home/index.php` (render inicial del servidor)
- `endpoints/ajax_catalog.php` (respuestas AJAX)

**JS** (`public/js/views/home/index.js`): delegación de clic en `#catalog-results` para `.empty-state-clear` → misma acción que `#btn-limpiar` (limpia filtros y recarga). La delegación sobrevive a los reemplazos de `innerHTML`.

**i18n** — clave nueva `catalog.vacio_titulo` en los 12 diccionarios (es/en/fr/it/pt/de/ru/ja/zh/ko/ar/tr). El mensaje reutiliza `catalog.sin_resultados` y el botón `home.limpiar` (ya existentes). El newline final añadido a 4 diccionarios corrige una violación de `.editorconfig` (`insert_final_newline = true`).

### Decisiones documentadas (sin cambio visual)

- **B2** (`game-grid.css`): nowrap + ellipsis de tags y clamp de 2 líneas del título son deliberados — priorizan la arquitectura de altura fija de la tarjeta (grid 4×5) sobre cadenas largas.
- **C4** (`game-details.css`): radios 8/6/4px del carrusel imitan el biselado de una pantalla CRT. Intencional.

---

## 3. Verificación

- `php -l` sobre los PHP tocados (`EmptyState.php`, `home/index.php`, `ajax_catalog.php`, `index.js` no aplica).
- `vendor/bin/phpunit --testsuite Unit` — incluye `NoEmojiTest` y `I18nTest::testDiccionariosJsonValidosYParitarios` (paridad de las 12 claves).
- Ratios de contraste recalculados con script WCAG (ver §2).
- Smoke: `php -S localhost:8000 router.php` → home, búsqueda sin resultados (estado vacío CRT), detalle de juego (badge hack), play con proxy (tip label).

## 4. Pendiente (próximos lotes)

- **Lote B:** cerrado el 2026-10-17 (B1 + B3 + B4) — ver `docs/2026-10-17-lote-b-i18n-visual.md`.
- **Lote C:** C1 estilos inline → clases, C2 unificación de tokens, C3 sombras → tokens, C5 limpieza menor.
- Auditoría completa de contraste en componentes con fondos amber (tag-hack con texto cream-light = 3.25:1 — requiere decisión sobre si oscurecer el fondo o aceptar como texto grande/decorativo).

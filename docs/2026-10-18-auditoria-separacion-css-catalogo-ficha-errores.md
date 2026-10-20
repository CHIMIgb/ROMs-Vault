# 2026-10-18 — Auditoría y reorganización de la separación CSS (catálogo, ficha, errores)

Plan aprobado por el usuario (§10). Alcance: `public/css/modules/*` de **catálogo**, **ficha**,
**juego/play** y **páginas de error**. El dashboard y `admin.css` quedan **fuera**
(el usuario los revisará aparte). Objetivo: cada bloque vive en el módulo que le
corresponde por uso real; cero cambios visuales y eliminación del CSS muerto.

## Hallazgos de la auditoría

1. **`game-details.css` contenía CSS ajeno en su primer tercio:**
   - **L1-76** — filtros en tiempo real del catálogo (`.filters-row`, `.filter-loading*`,
     `@keyframes filterScan/filterBlink`, transición `#catalog-results`/`#admin-results`).
     Los usan `home/index.php` y los listados admin.
   - **L77-315** — bloque completo de páginas de error (404 Game Over + error técnico genérico).
     Solo los usan `errors/404.php` y `errors/generic.php`.
2. **`game-grid.css` contenía la familia "juegos relacionados"** (`.related-*`, "estante de
   cartuchos") que solo usa `components/related_games.php`, incluido únicamente en `show.php`
   (la ficha) → pertenece a `game-details.css`.
3. **`game-grid.css` definía `.game-title` y `.game-tag*`** (chips/título de tarjeta), usados
   también por `components/game_card.php`, `play.php` y `dashboard.php` → componente compartido,
   pertenece a `components.css`.
4. **CSS muerto verificado (0 usos en `src/` + `public/js/`):** `.alert-danger/info/success/warning`,
   `.btn-delete`, `.btn-secondary`, `.search-form`, `.form-checkbox*`, `.has-file`, `.footer-note`
   y la regla `.proxy-error-icon` de `components.css` (no-op: `play.css` la anula por completo).
   *No se tocaron las familias `rv-modal--*`/`rv-toast--*`/`rv-inline--*`: se construyen
   dinámicamente en `rv-alerts.js`/`Alert.php`.*

## Cambios

| # | Acción | Archivo |
|---|---|---|
| M1 | **[CREAR]** `errors.css` con el bloque de errores (404 + técnico + créditos) verbatim | `public/css/modules/errors.css` |
| M2 | Importar `errors.css` tras `game-details.css` | `public/css/style.css` |
| M3 | Quitar filtros y errores; **añadir** familia `.related-*` (con cabecera) | `public/css/modules/game-details.css` |
| M4 | Quitar familia `.related-*` (movida a game-details) | `public/css/modules/game-grid.css` |
| M5 | Añadir bloque de filtros en la sección FILTROS; borrar CSS muerto | `public/css/modules/components.css` |
| M6 | Borrar `.game-title`/`.game-tag*` (movidos a components) | `public/css/modules/game-grid.css` |
| M7 | Borrar `.form-checkbox*`, `.has-file` | `public/css/modules/forms.css` |
| M8 | Borrar `.footer-note` | `public/css/modules/layout.css` |
| M9 | Este documento | `docs/2026-10-18-auditoria-separacion-css-catalogo-ficha-errores.md` |

Sin tokens nuevos, sin variables de entorno, sin paquetes, sin claves i18n.

## Por qué no cambia nada visual (cascada verificada)

- **Filtros** → `components` solo compite con `responsive` (que carga después y mantiene su victoria).
  `#catalog-results`/`#admin-results` eran selectores únicos y se movieron idénticos.
- **Errores** → módulo nuevo con definiciones únicas; nadie más define esas clases.
- **Relacionados** → pasa de cargarse en `game-grid` (#5) a `game-details` (#6); ningún módulo
  intermedio redefine `.related-*`.
- **Chips/título** → pasan de `game-grid` (#5) a `components` (#4); mismo texto, sin competencia.

## Verificación

- Llaves balanceadas en los 12 archivos CSS (todas OK).
- Cada clase movida resuelve ahora en su módulo nuevo; las muertas ya no existen.
- `smoke_panel.ps1` → SMOKE OK (71 PASS, incluye filtros del catálogo y chips).
- `smoke_play.ps1` → 23/23.
- Suite Unit → OK (199 tests, 14828 assertions).
- Render real: ficha (2 `detail-tags`, `related-section`, `tag-plataforma`), catálogo
  (`filters-row`, `filter-loading`, `game-card`), 404 (`error-404-page`, `error-404-code`),
  play (`emulator-title-row`), y `style.css` sirve el import de `errors.css`.

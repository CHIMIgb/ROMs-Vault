# Ítem 7.2 — Slugs de contenido y URLs limpias

**Fecha:** 2026-10-11
**Actualizado:** 2026-10-15 (slug 100 % automático)
**Plan de origen:** `docs/_archivo/2026-10-07-plan-mejoras-seguridad-y-rendimiento.md` (§7.2)

## Actualización (2026-10-15)

El slug pasó a ser **100 % automático**:

- Se eliminó el campo `slug` de los formularios de **edición** (`admin/edit.php`,
  `admin/consolas/edit.php`); ya no se expone en ningún formulario.
- Se quitó la rama de "slug manual" de `resolverSlugJuego()` /
  `resolverSlugConsola()`: el slug se **genera** al crear y se **conserva** al
  editar (D2).
- Se borraron las claves i18n `admin.juego.slug*` y `admin.consola.slug*` de los
  12 idiomas.
- `AdminSlugTest`: la prueba de edición ahora verifica que se **conserva** el
  slug actual (un `slug` enviado por POST se ignora).

Los apartados siguientes (Decisiones, Código, i18n, Tests) describen el estado
original del 2026-10-11.

## Objetivo

URLs limpias y legibles basadas en el contenido (dato del registro, no tabla de
rutas — ver ADR `docs/2026-10-10-adr-routing-sin-tabla-de-rutas.md`):

- `/juego/{slug}`   → ficha del juego
- `/jugar/{slug}`   → emulador online (EmulatorJS)
- `/consolas/{slug}` → catálogo filtrado por consola

Las URLs legacy (`/home/show/{id}`, `/home/play?file_id=…` y sus equivalentes
`index.php?…`) siguen funcionando y responden **301** a la URL canónica por slug.

## Decisiones (aprobadas por el usuario)

- **D1** — Se incluye `consolas.slug` y la página pública `/consolas/{slug}`.
- **D2** — El slug es **estable**: se genera **automáticamente** al crear (desde
  el título/nombre); editar el título/nombre NO cambia la URL. *(Originalmente el
  slug era editable a mano desde el formulario de edición; esa edición manual se
  retiró el 2026-10-15 — ver «Actualización» —, así que el slug ya no se expone en
  ningún formulario.)*
- **D3** — El endurecimiento del `file_id` (firma por slug + proxy) se trata como
  ítem aparte.
- **Nota de ruta** — Se usa `/consolas` (plural) para la página pública porque
  `/consola/*` es el CRUD de administración (`ConsolaController`, exigido por
  `tests/Integration/CrudTest.php`). No se podía reutilizar `/consola/{slug}`.

## Cambios

### Base de datos
- `data/roms-vaultDB-postgreSQL.sql`: columnas `slug` en `juegos` (`VARCHAR(220)`)
  y `consolas` (`VARCHAR(120)`) + índices UNIQUE parciales
  (`uq_juegos_slug`, `uq_consolas_slug`, `WHERE slug IS NOT NULL`).
- `data/migrations/2026-10-11-add-slugs.sql`: migración idempotente (aplicada a
  `roms-vault` y `roms-vault-test`).
- `scripts/backfill-slugs.php`: relleno idempotente desde `titulo`/`nombre`
  (solo filas con slug NULL/''), con dedupe. Ejecutado: 123 juegos + 16 consolas,
  0 nulos, 0 duplicados.

### Código
- `src/config/Slug.php` [nuevo]: `Slug::make()` (transliteración **manual** de
  latinos: á→a, ñ→n, ü→u; NO depende de `iconv`/`intl`) y `Slug::unique()`.
  Motivo del mapa manual: en el build de Windows/XAMPP
  `iconv('UTF-8','ASCII//TRANSLIT')` no translitera bien (`Español` → `espa-nol`);
  el mapa es determinista y portable (Windows, Debian/Docker).
- Modelos: `Juego::findBySlug()`/`slugExists()`, `Consola::findBySlug()`/`slugExists()`;
  `Juego::autocomplete()` ahora selecciona `j.slug`.
- `index.php`: rutas pretty resueltas antes de la convención controller/action
  (`juego`→`showBySlug`, `jugar`→`playBySlug`, `consolas`→`consolaBySlug`).
- `HomeController`: `showBySlug()`/`playBySlug()`/`consolaBySlug()`; refactor a
  `renderShow()`/`renderPlay()`/`renderPlayError()`/`renderNotFound()`; **301**
  desde legacy (el de `play` se hace antes de contar la jugada para no duplicar).
  Canonical/`og:url` → `/juego/{slug}`.
- Admin (`AdminController`, `ConsolaController`): generación de slug al crear y
  conservación al editar (D2); `slugify()` delega en `Slug::make()`. *(2026-10-15:
  se eliminó el override manual y el campo `slug` de los formularios de edición,
  así que el slug no se expone en ningún formulario.)*
- Vistas/endpoints: enlaces migrados a slug (con fallback a legacy) en
  `home/index.php`, `components/related_games.php`, `components/game_actions.php`,
  `endpoints/ajax_catalog.php`, `endpoints/ajax_autocomplete.php`.
- i18n: *(2026-10-15)* se eliminaron las claves `admin.juego.slug*` y
  `admin.consola.slug*`, ya sin uso, de los 12 idiomas.

### Tests
- `tests/Integration/SlugRoutingTest.php` (9 pruebas): 200 por slug, 404 en slug
  inexistente, 301 desde `/home/show/{id}` y `index.php?controller=home&action=show`,
  301 desde `/home/play?file_id=`, y catálogo por consola.
- `tests/Integration/AdminSlugTest.php`: slug generado desde nombre/título al
  crear, un `slug` enviado se ignora, y al editar se conserva el slug actual.

## Verificación

- `composer test` → **182 tests / 7566 assertions**, verde.
- `vendor/bin/phpstan analyse` → `[OK] No errors`.
- `vendor/bin/phpcs src/config/Slug.php` → limpio (ver
  `docs/2026-10-11-ajuste-gate-phpcs.md` para el ajuste del ruleset).
- Backfill: verificados `slug IS NULL` = 0 y duplicados = 0.

# Auditoría de `fetchAll()` y limpieza de dead code en `Juego` — ítems 4.1/4.2

**Fecha:** 2026-10-08
**Rama:** `optimizacion-arquitectura`
**Ítems del roadmap:** [4.1 Auditoría de fetchAll() y 4.2 Dead code de Juego](_archivo/2026-10-07-plan-mejoras-seguridad-y-rendimiento.md)
**Regla de negocio aplicada:** *ningún listado de la UI debe traer todos los registros*; los
únicos `fetchAll()` sin `LIMIT` permitidos son dropdowns pequeños (consolas/categorías/
emuladores activos) y los exports de administrador.

## 4.1 — Auditoría de `fetchAll()`/queries sin `LIMIT` en `src/models/*.php`

Revisado método por método (Juego.php completo, Auditoria.php, Model.php, Consola,
Categoria, Emulador, Usuario, Export). Clasificación:

| Método / consulta | Clasificación |
|---|---|
| `Model::all()` (consolas/categorías) → usos: `HomeController::index()` (filtros) y `AdminController` (selectores) | **Legítimo** — dropdowns ≤ decenas de filas |
| `Consola::allActivas()`, `Categoria::allActivas()` | **Legítimo** — dropdowns |
| `Emulador::porConsola()`, `withConsole()`, `consolasConEmuladores()`, `sinEmulador()` | **Legítimo** — conjuntos pequeños |
| `Export::exportJuegos/consolas/categorias/personas/roles/descargas` | **Legítimo** — exports admin (excepción explícita) |
| `Juego::getAllPaginated` (LIMIT/OFFSET) + `countAll` | ✅ paginado |
| `Juego::getWithRelationsPaginated` (LIMIT/OFFSET) + `countWithFilters` | ✅ paginado (catálogo público, 20 por página) |
| `Juego::getAllPaginatedFiltered` (LIMIT/OFFSET) + `countAllFiltered` | ✅ paginado (dashboard admin) |
| `Juego::getTopByDownloads` / `getTopByPlays` (`LIMIT :n`, default 5) | ✅ limitado |
| `Juego::relacionadosPorConsola` (`LIMIT :lim`, 8) y `queryGenero` (`LIMIT :lim`, 8) | ✅ limitado |
| `Juego::autocomplete` (`LIMIT :lim`, 8) | ✅ limitado |
| `Auditoria::recientes()` (`LIMIT :limite`, 50) y `contarEventos()` (COUNT) | ✅ limitado |
| `Juego::getGlobalStats`, `getCatalogStats` | agrupación/1 fila — N/A |

**Conclusión:** no queda ningún `fetchAll()` sin `LIMIT` en listados de UI que deba paginarse.
Los pendientes reales de la sección son **4.4 (keyset pagination)**, futura.

## 4.2 — Dead code eliminado de `src/models/Juego.php`

Eliminados dos métodos **sin ningún uso** en `src/`, `endpoints/`, `public/js/`, vistas ni
tests (verificado con grep global):

1. **`getWithRelations($filters)`** — `SELECT` sin `LIMIT` que traería la colección completa;
   duplicaba a `getWithRelationsPaginated()`. Riesgo si alguien lo reutilizaba: carga total.
2. **`getDownloadLink($fileId)`** — devolvía `https://drive.google.com/uc?export=download&id=...&confirm=t`,
   URL directa de Drive **sin firma HMAC**; anti-patrón (rompía la protección del proxy firmado
   y las URLs públicas debían pasar siempre por `rom_proxy.php`).

## Verificación

- `php -l src/models/Juego.php` → sin errores.
- `grep -rn 'getWithRelations(\|getDownloadLink(' src/ endpoints/ public/js` → **0 usos**.
- Suite completa: **121 tests / 316 assertions OK** (sin regresiones).

## 4.3 — Verificación de paginación en listados admin (verificado, 1 dead code extra)

Verificado tras la auditoría 4.1 (2026-10-08):

| Listado admin | Estado |
|---|---|
| Juegos del dashboard + búsqueda | ✅ ya pagina (`getAllPaginatedFiltered` + `countAllFiltered`) |
| Consolas con emuladores (panel admin) | ✅ ya pagina (`getConsolasPaginated` + `countConsolas`, usado por `EmuladorController::index` y `ajax_emulador.php`) |
| Emuladores por consola (`getByConsolaIds`) | ✅ acotado al set de la página (≤ decenas) |
| Dropdowns: `Consola::all()`, `Categoria::all()`, `getConsolasSinEmulador()`, `getByConsola()` | ✅ legítimos (dropdowns pequeños) |

**Hallazgo y limpieza:** `Emulador::getAllWithConsolas()` — `SELECT` sin `LIMIT` que repetía la
vista del panel admin sin paginar; **sin usos** en `src/`, `endpoints/`, `public/js`, vistas ni
tests (grep global). Eliminado (mismo patrón que 4.2). El listado admin real usa la versión
paginada, así que el cambio es transparente.

## Commits

- `refactor(modelos):` eliminar dead code de Juego (getWithRelations + getDownloadLink) — 4.2.
- `docs(rendimiento):` auditoría fetchAll() y dead code — 4.1/4.2 (hash del refactor en el
  Registro de progreso).
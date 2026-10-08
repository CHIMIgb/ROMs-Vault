# Reorganización de la estructura de archivos (raíz limpia + `src/` + `endpoints/`)

**Fecha:** 2026-10-07
**Alcance:** Raíz + mover el MVC a `src/`
**Roadmap:** Fase 3.1 (#137) — parcial (solo reubicación; PSR-4/namespaces/autoload quedan fuera de alcance, ítems 135/136/138)

## Objetivo

Limpiar la raíz del repositorio, que concentraba código de aplicación, entrypoints públicos y plan viejos:

- `config/`, `controllers/`, `models/`, `views/` → `src/` (convención estándar de la industria).
- `rom_proxy.php` y los 6 `ajax_*.php` (entrypoints que **no** pasan por `index.php`) → `endpoints/`.
- `implementation_plan.md` (plan ya implementado) → `docs/_archivo/`.

**Premisa no negociable:** las URLs públicas no cambian. `/rom_proxy.php?...` (firmas HMAC usadas por EmulatorJS), `/ajax_*.php?...` (fetch del JS de las vistas) y las URLs limpias `/controlador/accion/id` funcionan igual que antes. `vercel.json` mantiene `"rewrites": []` (en Vercel el request llega tal cual a `.htaccess`/PHP).

## Decisiones tomadas

| Decisión | Opción elegida |
|----------|----------------|
| Alcance | Raíz + mover MVC a `src/` (no tocar public/, tests, data) |
| Nombre de la carpeta de entrypoints | `endpoints/` |
| `implementation_plan.md` | Movido a `docs/_archivo/implementation_plan.md` |
| Rutas internas | `require`/`require_once` cwd-relativos convertidos a `__DIR__` (el código movido queda location-independent) |

## Mapa de cambios

### Archivos movidos

| Antes (raíz) | Ahora |
|--------------|-------|
| `config/` | `src/config/` |
| `controllers/` | `src/controllers/` |
| `models/` | `src/models/` |
| `views/` | `src/views/` |
| `rom_proxy.php` | `endpoints/rom_proxy.php` |
| `ajax_admin.php` | `endpoints/ajax_admin.php` |
| `ajax_autocomplete.php` | `endpoints/ajax_autocomplete.php` |
| `ajax_catalog.php` | `endpoints/ajax_catalog.php` |
| `ajax_categoria.php` | `endpoints/ajax_categoria.php` |
| `ajax_consola.php` | `endpoints/ajax_consola.php` |
| `ajax_emulador.php` | `endpoints/ajax_emulador.php` |
| `implementation_plan.md` | `docs/_archivo/implementation_plan.md` |

### Rutas internas actualizadas

- **`index.php`**: requires de `config/`, `views/` → `src/...`; `$controllerFile = 'controllers/'...` → `'src/controllers/'...`.
- **`router.php`**: antes de caer al front controller, mapea los 7 basenames a `endpoints/` (URL pública idéntica) y devuelve **403** ante cualquier `/src/...` (el código fuente nunca se sirve por HTTP).
- **`.htaccess`**: `RewriteRule ^rom_proxy\.php$ endpoints/rom_proxy.php [L,QSA]`, `^ajax_[a-z]+\.php$ endpoints/$0 [L,QSA]` y `^src/ - [F,L]` (mismas reglas que en `router.php`, para Apache).
- **`tests/bootstrap.php`** y **`phpunit.xml`**: `config/` → `src/config`.
- **`endpoints/*.php`**: Dotenv apunta a la raíz (`__DIR__ . '/../'`), `vendor/` → `__DIR__ . '/../vendor/autoload.php'`, requires a `../src/config|models|views`.
- **`src/config/*`**: Dotenv/autoload suben un nivel (`__DIR__ . '/../../'`); requires de vistas de `CsrfService`/`RateLimiter`/`AuthMiddleware` → `__DIR__ . '/../../views/...'`.
- **`src/controllers/*`**: requires cwd `'models/'`, `'config/'`, `'views/'` → `__DIR__ . '/../...'`.
- **`src/views/*`**: requiere de `views/components/...` según profundidad (`/../` en home/auth/admin, `/../../` en admin/consolas|categorias|emuladores); `components/related_games.php` → `__DIR__ . '/../../models/Juego.php'`.

## Verificación

- `php -l` de todos los archivos movidos/editados.
- `vendor/bin/phpunit --testsuite Unit` (sin BD).
- Smoke con `php -S localhost:8000 router.php`:
  - `/` y `/home/show/1` → 200 HTML.
  - `/ajax_catalog.php?page=1`, `/ajax_autocomplete.php?q=super` → 200 JSON.
  - `/ajax_admin.php` (sin sesión) → 403.
  - `/rom_proxy.php` (sin firma) → 403 JSON.
  - `/src/config/database.php` → 403.
  - `index.php?controller=home&action=index` (legacy) → 200.

## Estado final

- [ ] Commit del código + rellenar `<commit>` en el Registro de progreso del roadmap (#137).
- [ ] Pendiente decidir: PSR-4/namespaces (roadmap ítems 135/136/138) en una fase posterior.
- En pausa (externo): migración de imágenes a Cloudflare R2 (requiere tarjeta con fondos); plan en `docs/2026-10-07-migrar-imagenes-a-cloudflare-r2.md`.
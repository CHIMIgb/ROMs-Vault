# AGENTS.md

## Stack

PHP 8.1+ vanilla MVC, no framework — custom routing in `index.php` (clean URLs `/controller/action/id` AND legacy `index.php?controller=X&action=Y&id=Z`, both must keep working). PostgreSQL 16+ (Neon in prod, SSL; local dev/test on Windows). Composer for deps only: **no PSR-4 autoloader for app code** — controllers/models are wired with `require_once` chains; only the `Tests\` namespace is autoloaded (`autoload-dev`). CSS is vanilla, modular, no build step. **Language: Spanish** (UI, code comments, commit messages, DB).

## Commands

- **Dev server**: `php -S localhost:8000 router.php` — `router.php` is REQUIRED for clean URLs; bare `php -S` only serves legacy query-string routes.
- **Install**: `composer install`
- **Tests**: `composer test` (PHPUnit, testdox). Runs BOTH suites — Integration **throws if `TEST_DB_PASSWORD` is unset**. Unit-only: `vendor/bin/phpunit --testsuite Unit`.
  - Integration prereqs (local, NOT Neon): DB `roms-vault-test` = `data/roms-vaultDB-postgreSQL.sql` + `data/test_seeds.sql` (admin user `admin`/`admin123`, `rol_id=1` = `AuthMiddleware::ADMIN_ROLE_ID`). Run with `TEST_DB_PASSWORD=<localsuperuser>`.
  - On this machine PHP is on Windows only: `C:\xampp\php\php.exe`, Postgres `C:\Program Files\PostgreSQL\18\bin`. WSL has no `psql`/`php`; invoke via `powershell.exe -NoProfile -Command '$env:PGPASSWORD=...; & "C:\...\psql.exe" ...'`.
- **DB schema/data import**: `psql -f data/roms-vaultDB-postgreSQL.sql` (schema incl. indexes/triggers), then `data/import_data.sql` (explicit ids + `setval` on sequences), then `data/emuladores.sql`. Order matters (FKs). `data/migrations/*.sql` are idempotent and already merged into the base schema.
- **Docker**: `docker build -t roms-vault . && docker run -p 8080:80 --env-file .env roms-vault`.
- **No lint / typecheck / CI exist** (roadmap FASE 2 plans PHPStan/phpcs/GitHub Actions) — don't invent those commands.

## Architecture

- **Routing**: `index.php` resolves `controller/action/id` from query string or path segments; sanitizes with regex (alphanumeric), 404s otherwise; enforces CSRF on EVERY POST (`CsrfService::verify()`); sets global security headers incl. a CSP that intentionally allows `unsafe-eval`/`blob:` for EmulatorJS (Emscripten) — **don't tighten it blindly**.
- **Standalone entrypoints** bypass `index.php` (own auth/CSRF): `rom_proxy.php` (Google Drive streaming proxy) and `ajax_admin.php`, `ajax_autocomplete.php`, `ajax_catalog.php`, `ajax_categoria.php`, `ajax_consola.php`.
- **`vercel.json` MUST keep `"rewrites": []`.** Edge rewrites drop the query string when proxying to the container and clean URLs 404. Routing lives in `.htaccess` (Apache) / `router.php` (php -S).
- **Key dirs**: `config/` = singletons (`database.php`, `JWTService.php`, `CsrfService.php`, `RateLimiter.php`, `UrlSigner.php`, `AuthMiddleware.php`); `models/` = raw PDO, no ORM; `views/layout/header.php` + `footer.php` wrap every page; `public/css/style.css` is imports-only over `public/css/modules/*`; `public/bios/` = emulator BIOS (PS1: `ps1/scph1001.bin`); `data/backup registros DB/` = Excel dumps; `scratch/` = throwaway (gitignored).

## Environment

`.env` is loaded independently with `Dotenv::createImmutable()->safeLoad()` in several files (`config/database.php`, `config/JWTService.php`, `rom_proxy.php`). Docker entrypoint regenerates `.env` at runtime from container env. Key vars: `DB_HOST/DB_PORT/DB_NAME/DB_USER/DB_PASSWORD`, `DB_SSLMODE` (default `require` for Neon; tests set `disable`), `JWT_SECRET`, `SESSION_SECRET`, `RATE_LIMIT_MAX/WINDOW`, `ALLOWED_ORIGINS`. Tests override `JWT_SECRET` with a ≥32-byte fake (firebase/php-jwt enforces length); they never touch real `.env`/Neon.

## Conventions

- **MVC layering**: DB access in Models, logic/routing in Controllers, UI in Views — no SQL in views, no HTML in models.
- **CSS**: all styles in `public/css/modules/*`; new styles go in a module file imported by `style.css`, never loose CSS there.
- **Git**: never `git push`; commit locally with **Spanish** messages.
- **Docs**: every task gets a dated file under `docs/` (e.g. `docs/2026-08-05-plan-...md`).
- **Roadmap**: `docs/2026-08-05-plan-mejoras-nivel-senior.md` is the single source of truth for planned improvements. When implementing one, tick the checkbox, note the commit hash, and update the "Registro de progreso" table **in the same commit**.
- **Line endings**: LF enforced by `.gitattributes`/`.editorconfig`; don't commit CRLF (Windows checkout).

## Gotchas

- Proxy URLs (HMAC-signed) expire after **2 hours** (`SIGNED_URL_TTL`, `rom_proxy.php:114`); drive-URL cache TTL 10 min.
- Rate limiting is **file-based** in `sys_get_temp_dir()` — not shared across processes; tests clear `rv_rate_limit/`.
- Integration tests **TRUNCATE + reseed** `roms-vault-test` before each class (`IntegrationTestCase::resetDatabase()`); HTTP POSTs must send `Server::csrfToken()`.
- Pagination hardcoded to 20 items (4×5 grid).
- Neon needs SSL + endpoint-ID workaround in DSN (`config/database.php`, `neon.tech` hosts).
- `public/uploads/` needs write permissions for cover uploads.
- `.gitignore` ignores `.env*` (`.env.example` is tracked), `.agents/`, `scratch/`, `vendor/`, `*.log`, `generate_imports.py`.
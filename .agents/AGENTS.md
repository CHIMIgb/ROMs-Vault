# AGENTS.md

## Stack

PHP 8.1+ MVC app. No framework — custom routing via `index.php` with query string params (`?controller=X&action=Y&id=Z`). PostgreSQL 16+ (Neon). Composer for deps only (no autoloader beyond vendor). CSS is vanilla, no build step. **Language: Spanish** (UI, code comments, DB).

## Commands

- **Dev server**: `php -S localhost:8000` (from repo root)
- **Install deps**: `composer install`
- **DB import**: `psql -U postgres -d roms-vault -f data/roms-vaultDB-postgreSQL.sql`
- **Docker**: `docker build -t roms-vault . && docker run -p 8080:80 --env-file .env roms-vault`

No lint, typecheck, or test suite exists. No CI/CD.

## Architecture

Routing: `index.php` reads `$_GET['controller']` + `$_GET['action']`, maps to `controllers/{Controller}.php`. Admin routes are behind `AuthMiddleware::requireAuth()`.

**Standalone entrypoints** (not routed through `index.php`):
- `rom_proxy.php` — Google Drive ROM streaming proxy with HMAC-signed URLs, rate limiting, CORS
- `ajax_admin.php`, `ajax_autocomplete.php`, `ajax_catalog.php`, `ajax_categoria.php`, `ajax_consola.php` — AJAX endpoints

**Key directories**:
- `config/` — database.php (singleton PDO), JWTService.php, AuthMiddleware.php
- `models/` — Model.php (abstract base) + domain models. All use raw PDO, no ORM.
- `views/` — layout/header.php + footer.php wrap all pages; components/ has Alert.php, Pagination.php
- `public/css/modules/` — modular CSS files; `style.css` is the entry (imports modules)
- `public/bios/` — BIOS files for emulator cores (PS1 requires `public/bios/ps1/scph1001.bin`)
- `data/` — SQL schema files (PostgreSQL and MySQL variants)
- `scratch/` — throwaway scripts, not part of app

## Environment

`.env` is loaded independently in multiple files (database.php, JWTService.php, rom_proxy.php) via `Dotenv::createImmutable()`. All use `safeLoad()`. The Docker entrypoint generates `.env` at runtime from container env vars.

Key vars: `DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USER`, `DB_PASSWORD`, `JWT_SECRET`, `SESSION_SECRET`, `RATE_LIMIT_MAX`, `RATE_LIMIT_WINDOW`, `ALLOWED_ORIGINS`.

## CSS conventions

- **Modular CSS architecture.** All styles live in `public/css/modules/`. To add new styles, modify the corresponding module file or create a new one and import it in `style.css`. Never add loose CSS in `style.css`.

## MVC conventions

- **Respect the MVC architecture.** Data access stays in Models, business logic and routing in Controllers, and UI in Views. Do not mix layers (e.g., no SQL queries in views, no HTML rendering in models).

## Git conventions

- **Never push.** Only commit locally. Do not run `git push` under any circumstances.
- **Write commit messages in Spanish.** All commit messages must be written in Spanish to match the project's language conventions.

## Task documentation

- **Document every task.** After completing a task, create or update a file in the `docs/` folder describing what was done, why, and any relevant context (files changed, decisions made, side effects).

## Gotchas

- Neon PostgreSQL requires SSL + endpoint ID workaround in `config/database.php:26-29`
- Proxy URLs expire after 2 hours (HMAC TTL in `rom_proxy.php:113`)
- Rate limiting is file-based in `sys_get_temp_dir()` — not shared across processes
- Images upload to `public/uploads/` — needs write permissions
- Pagination is hardcoded to 20 items (4×5 grid)
- No Composer autoloader for app code — controllers/models use `require_once` chains
- `rom_proxy.php` sets `set_time_limit(0)` for large ROM streaming
- `bios_debug.log` is written to repo root by HomeController — add to .gitignore if committing

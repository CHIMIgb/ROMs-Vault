# CI/CD con GitHub Actions — FASE 2 (P0)

**Fecha:** 2026-10-11
**Rama:** `pendientes-9.2-y-restantes`
**Ítem del roadmap:** [FASE 2 — CI/CD con GitHub Actions](2026-08-05-plan-mejoras-nivel-senior.md)

## Cambio

```
[CREAR]      .github/workflows/ci.yml       # lint / static / tests / audit / build
[CREAR]      .github/dependabot.yml         # composer + github-actions (semanal)
[CREAR]      phpstan.dist.neon              # PHPStan nivel 5, scope src/ + endpoints/
[CREAR]      phpstan-baseline.neon          # 77 errores congelados del código existente
[CREAR]      phpcs.xml.dist                 # PSR-12, src/ + endpoints/ (views excluidas)
[MODIFICAR]  composer.json                  # dev deps (phpstan, php_codesniffer) + scripts analyse/style
[MODIFICAR]  README.md                      # badge de CI
[MODIFICAR]  tests/Integration/Server.php   # portabilidad Linux: 'NUL' → '/dev/null'
```

No se toca `vercel.json` (mantiene `"rewrites": []`), ni la CSP, ni ninguna URL pública.
El CI **solo lee** el repositorio (`permissions: contents: read`) y **no despliega**:
Vercel sigue desplegando por su integración Git nativa.

## Jobs del workflow

| Job | Runner | Qué hace |
|---|---|---|
| `lint` | ubuntu | `php -l` sobre todo `src/`, `endpoints/`, `index.php` y `router.php` |
| `static` | ubuntu (`fetch-depth: 0`) | `composer install` → **PHPStan** (nivel 5 + baseline) → **phpcs PSR-12** sobre los ficheros PHP cambiados |
| `tests` | ubuntu + servicio `postgres:16` | carga el esquema con `psql -f data/roms-vaultDB-postgreSQL.sql` → `composer test` (Unit + Integration + Security) |
| `audit` | ubuntu | `composer audit` + `composer update --dry-run` (informativo) |
| `build` | ubuntu | `docker build -t roms-vault:ci .` |

Triggers: push a `main` y pull requests. `concurrency` cancela ejecuciones obsoletas.

## Por qué el gate de estilo va sobre "ficheros cambiados"

PHP_CodeSniffer **eliminó el soporte de baseline** (3.13/4.0), y la única versión sin el
CVE-2026-67434 (inyección de comandos) es ≥ 3.13.6, que ya no lo tiene. Por eso:

- `phpcs.xml.dist` fija **PSR-12** y excluye `src/views/` (plantillas con HTML embebido).
- El job `static` ejecuta `phpcs` **solo sobre los `.php` de `src/` o `endpoints/`
  modificados en el PR/push** (`git diff --name-only --diff-filter=ACMR <base> HEAD`).
- El código legado no se reescribe ahora: un run completo da **938 errores + 270 avisos
  PSR-12 en 61 ficheros** (908 auto-corregibles con `phpcbf`), deuda asumida y aplazada.

Ruta de mejora futura: normalizar por bloques (`phpcbf`) y, cuando el volumen lo permita,
quitar la restricción a "ficheros cambiados".

## PHPStan

- Nivel **5**, scope `src/` + `endpoints/`, con **baseline** de 77 errores del código
  existente (`phpstan-baseline.neon`). Hoy: `[OK] No errors`.
- El roadmap pedía empezar en nivel 5 y **subir a 6 después**: la subida queda como mejora
  futura (reducir el baseline y elevar el nivel).

## Secrets requeridos en GitHub

El job `tests` falla si faltan (el bootstrap de integración **exige** `TEST_DB_PASSWORD`).
Configurar en *Settings → Secrets and variables → Actions*:

| Secret | Obligatorio | Uso |
|---|---|---|
| `TEST_DB_PASSWORD` | Sí | password del servicio PostgreSQL efímero + bootstrap de integración |
| `TEST_ADMIN_PASSWORD` | Sí | resuelve el placeholder del seed de admin |
| `TEST_TFA_SECRET` | Sí | resuelve el placeholder TOTP del seed |
| `TEST_JWT_SECRET` | No | si falta, se autogenera (≥32 bytes) |

> Son credenciales **efímeras de test** (BD desechable del runner), nunca las de Neon.

## Verificación (local)

- `php -l` sobre `src/` + `endpoints/` + `index.php` + `router.php` → **0 errores**.
- `vendor/bin/phpstan analyse` → **`[OK] No errors`**.
- `vendor/bin/phpunit` (Unit + Integration + Security) → **OK (170 tests, 7495 assertions)**.
- `phpcs.xml.dist`: `src/views/*` excluido (0 ficheros); ficheros de `src/` sí analizados.
- YAML de `ci.yml` y `dependabot.yml` validado (`yaml.safe_load`).
- `composer update --dry-run` → exit 0 (audit limpio).

## Pendientes / acciones del usuario

1. **Configurar los 4 secrets** `TEST_*` en GitHub (si no, el job `tests` quedará rojo).
2. Activar **branch protection / required status checks** en `main` para forzar el verde
   antes de mergear (criterio 2.4 restante).
3. (Backlog) Subir PHPStan a nivel 6; normalizar PSR-12 por bloques.

## Portabilidad

`tests/Integration/Server.php` usaba `'NUL'` (dispositivo nulo solo de Windows) como stdin
del servidor `php -S` hijo. Se sustituye por `PHP_OS_FAMILY === 'Windows' ? 'NUL' : '/dev/null'`
para que la suite corra en el runner **ubuntu** del CI sin afectar al desarrollo en Windows.

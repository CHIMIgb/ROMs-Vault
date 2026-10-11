# Eliminación de credenciales y hashes hardcodeados del repo

> Fecha: 2026-10-10 · Commit de código: `f51eab5` · Rama: `optimizacion-arquitectura` (PR #7)
> Relacionado: [`2026-10-08-credenciales-por-defecto.md`](./2026-10-08-credenciales-por-defecto.md) · [`2026-10-08-suite-security-tests.md`](./2026-10-08-suite-security-tests.md) · [`2026-10-10-gitguardian-exclusiones-tests.md`](./2026-10-10-gitguardian-exclusiones-tests.md)

## Resumen

Se eliminan del repositorio **todos los hashes y secretos** que estaban
versionados, tanto de la BD de prueba como del ajuste por defecto del admin de
producción:

1. **Producción** — `data/import_data.sql` (y su gemelo MySQL) contenían el
   bcrypt de una **contraseña por defecto conocida** para el usuario `admin`.
   Cualquiera con acceso al repo tenía la credencial del panel.
2. **Tests** — `data/test_seeds.sql` guardaba el hash del admin de test y el
   secreto TOTP; los propios tests repetían esos literales en el código.

La causa raíz es la misma en ambos casos: **secretos embebidos en archivos
versionados**. La política nueva es que ningún hash ni secreto vive en el repo:
los de test se centralizan en `tests/.env.test` (gitignored) y los de producción
se fijan tras el despliegue.

## Hallazgos de la auditoría (verificados con `password_verify`)

| Archivo | Qué había | Veredicto |
| --- | --- | --- |
| `data/import_data.sql` | bcrypt de `password` para el admin | 🔴 credencial por defecto conocida, versionada |
| `data/roms-vaultDB-MySQL.sql` | el mismo bcrypt de `password` | 🔴 idem |
| `data/test_seeds.sql` | bcrypt de `admin123` + `tfa_secret` | 🟡 solo BD de test, pero versionado |
| `tests/Security/SecurityTest.php` | `'admin123'`, `SERVER_JWT_SECRET` | 🟡 fixture (GitGuardian lo marcó) |
| `tests/Integration/TwoFactorFlowTest.php` | `TFA_SECRET` (vector RFC 6238) | 🟡 fixture (GitGuardian lo marcó) |
| `tests/Unit/*Test.php` | JWT secret de prueba | 🟡 fixture |

`README.md` no documentaba ninguna de estas credenciales (sin cambios).

## Cambios aplicados

### 1. Secretos de test centralizados

- **`[CREAR] tests/.env.test`** (gitignored) con `TEST_DB_PASSWORD`,
  `TEST_ADMIN_PASSWORD`, `TEST_TFA_SECRET` y `TEST_JWT_SECRET`.
- **`[CREAR] tests/.env.test.example`** (versionado) — plantilla sin valores
  reales; documenta cada variable.
- **`[MODIFICAR] .gitignore`** — se añade `!tests/.env.test.example` para que la
  plantilla no quede atrapada por la regla `.env.*`.

### 2. Carga de `.env.test` en el bootstrap de tests

- **`[MODIFICAR] tests/bootstrap.php`** — carga `tests/.env.test` con
  `Dotenv::createUnsafeImmutable()` (puebla `$_ENV`/`putenv`) antes de requerir
  `src/config/*`, de modo que el `.env` real no pise estas claves. Añade el
  helper global `test_env($key, $default)`. El `JWT_SECRET` de test ya no es un
  literal: sale de `TEST_JWT_SECRET` o, si falta, se genera uno efímero
  (`random_bytes`) solo para esa ejecución.

### 3. Seed de test con placeholders resueltos en runtime

- **`[MODIFICAR] data/test_seeds.sql`** — hash del admin → `__ADMIN_PASSWORD_HASH__`;
  `tfa_secret` → `__TFA_SECRET__`; eliminado el comentario con la contraseña.
- **`[MODIFICAR] tests/Integration/IntegrationTestCase.php`** — `resetDatabase()`
  sustituye los placeholders por `password_hash($adminPass)` y el secreto TOTP
  leídos de `tests/.env.test`; falla con mensaje claro si faltan. Se sustituye la
  constante `ADMIN_PASS` por `adminPassword()`/`tfaSecret()`.

### 4. Datos de producción sin credencial por defecto

- **`[MODIFICAR] data/import_data.sql`** — el hash del admin → placeholder
  `__CAMBIAR_PASSWORD_HASH__` (no es un bcrypt válido: el login falla cerrado
  hasta asignar una contraseña real) + comentario con el procedimiento.
- **`[MODIFICAR] data/roms-vaultDB-MySQL.sql`** — idem.

### 5. Tests sin literales

- **`[MODIFICAR]`** `tests/Integration/{Server,TwoFactorFlowTest,AuthFlowTest}.php`,
  `tests/Security/SecurityTest.php`,
  `tests/Unit/{JWTServiceTest,UrlSignerTest,TfaServiceTest,CsrfServiceTest}.php` —
  los secretos y contraseñas se leen de `tests/.env.test` (vía `test_env()`), no
  del código.

### 6. Documentación

- `AGENTS.md`, `docs/2026-08-05-plan-mejoras-nivel-senior.md`,
  `docs/2026-10-08-{credenciales-por-defecto,lockout-y-2fa,suite-security-tests}.md`,
  `docs/2026-10-09-i18n-deteccion-idioma-diccionarios.md`,
  `docs/2026-10-10-gitguardian-exclusiones-tests.md` y
  `docs/_archivo/2026-10-07-plan-mejoras-seguridad-y-rendimiento.md` — se sustituyen
  los literales por referencias a `tests/.env.test` y placeholders.

## Verificación

- `php -l` sin errores en los 9 archivos PHP modificados.
- `grep` de `admin123`, `secret-de-prueba-phpunit`, `GEZDGNBVGY3TQOJQ`, `$2y$10$`
  y `92IXUNpkjO0rOQ5byMi` → **0 coincidencias** en el repo.
- `password_verify('password'|'admin123'|'', '__CAMBIAR_PASSWORD_HASH__')` → **false**
  (el placeholder de producción falla cerrado).
- **Suite completa verde**: `OK (166 tests, 7489 assertions)` con los secretos
  leídos de `tests/.env.test`.

## Impacto y compatibilidad

- **Runtime de producción**: sin cambios de código de aplicación. El único efecto
  es que un import **desde cero** ya no crea el admin con contraseña por defecto;
  hay que fijarla después del import (comportamiento deseado).
- **URLs públicas, CSP, `vercel.json`**: intactos.
- **CI futura (FASE 2)**: los jobs deberán inyectar `TEST_DB_PASSWORD`,
  `TEST_ADMIN_PASSWORD`, `TEST_TFA_SECRET` y `TEST_JWT_SECRET` (GitHub secrets);
  la suite ya no depende de literales.

## Pendientes (acción del propietario)

- **Rotar la contraseña del admin en Neon** si sigue siendo la de por defecto;
  rotar `JWT_SECRET` invalida los JWT vivos (el cambio de contraseña por sí solo
  no los revoca).
- **GitGuardian**: el check del PR sigue escaneando los commits antiguos con los
  literales; requiere la *filepath exclusion* `**/tests/**` (scoped a
  `CHIMIgb/ROMs-Vault`) en el dashboard, ya documentada en
  `2026-10-10-gitguardian-exclusiones-tests.md`.
- **Vercel**: el bloqueo del deployment es config de cuenta (email autor /
  Deployment Protection), ajeno al código.

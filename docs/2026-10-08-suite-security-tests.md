# Suite `Security` — tests de integración de seguridad (sección 5 del plan)

Fecha: 2026-10-08

## Objetivo

Añadir al proyecto una suite **`Security`** en `phpunit.xml` (además de `Unit` e
`Integration`) que mide el nivel de seguridad real de la aplicación haciendo
peticiones HTTP contra el servidor PHP real (`php -S router.php`) y la BD de
prueba (`roms-vault-test`). Cierra la sección 5 del plan archivado
`docs/_archivo/2026-10-07-plan-mejoras-seguridad-y-rendimiento.md`.

## Archivos

- `phpunit.xml` — nueva `<testsuite name="Security">` sobre `tests/Security`.
- `tests/Security/SecurityTest.php` — clase heredera de `IntegrationTestCase`
  (mismo server y BD que la suite Integration).

## Matriz ítem → prueba

| Ítem | Caso de prueba | Cobertura |
|---|---|---|
| 5.1 Accesos sin sesión | `testAdminDashboardSinSesionRedirigeAlLogin` (302 → `/auth/login`); `testAjaxAdminSinSesionResponde403` (`ajax_admin|consola|categoria|emulador.php` → 403 + "Acceso denegado") | Directa |
| 5.2 CSRF | `testPostSinTokenCsrfResponde403` (POST sin `csrf_token`); `testPostConTokenCsrfInvalidoResponde403` (token falso) | Directa |
| 5.3 Rate limit de login | `AuthFlowTest` (`testLoginRateLimitSuperadoResponde429` + lockout) y `RateLimiterTest` | Ya existente |
| 5.4 Firma de enlaces | `testProxySinFirmaRechazado403`; `testProxyFirmaTamperRechazada403`; `testProxyFirmaVencidaRechazada410`; `testProxyFirmaValidaConFileInexistente404` | Directa (HTTP real) |
| 5.5 `/src/*` no servible | `testSrcNoServiblePorHttp` (`/src`, `/src/config/database.php`, `/src/models/Juego.php` → 403) | Directa |
| 5.6 Método no permitido / validación | `testIdNoNumericoDevuelve404` (id no numérico ya no produce fatal); `testControladorNoAlfanumericoDevuelve404`; `testAjaxAutocompleteRespondeJsonCoherente` | Directa |
| 5.7 Intento de inyección SQL | `testBusquedaSqlInjectionNoProduceErrorNiResultadosAnomalos` (`' OR 1=1 --`); `testLoginSqlInjectionNoAutoriza` (no 302, sin sesión) | Directa |
| 5.8 Cabeceras de seguridad | `testCabecerasDeSeguridadPresentesEnHome` (CSP con `unsafe-eval`/`blob:`, `nosniff`, `X-Frame-Options`, `Referrer-Policy`, `Permissions-Policy`) | Directa |
| 5.9 Host allowlist del proxy | `GDriveAllowlistTest` (unit: host permitido/no permitido, redirect) + defensa en profundidad verificada en 5.4/5.5 | Ya existente (no se hace red real) |

## Hallazgos corregidos durante la implementación

### H1 — PDOException fatal por id no numérico (5.6)

`GET /home/show/abc` (o cualquier id no numérico) lanzaba una `PDOException`
fatal con stack trace expuesto por PostgreSQL (`22P02 invalid input syntax for
type integer`) y el servidor devolvía HTTP 200 con el error visible. Corregido
con guard `ctype_digit()` en:

- `src/models/Model.php` — `find($id)` devuelve `null` si el id no es numérico
  (protege todos los `find()` del panel).
- `src/models/Juego.php` — `findWithDetails($id)` idem (protege `/home/show/:id`).

Ahora los ids no numéricos devuelven **404** limpio, no fatal. El test
`testIdNoNumericoDevuelve404` lo fija para no regresar.

### H2 — `$_ENV['JWT_SECRET']` modificado por la suite Unit (acoplamiento de orden)

`UrlSignerTest` usaba un `JWT_SECRET` propio y lo restauraba a **ese** valor al
final de la clase. En suite completa, `Security` corría después y firmaba con un
secret distinto al del servidor de integración (`Server.php` fija
`secret-de-prueba-phpunit-2026-muy-largo-y-seguro`) → el proxy rechazaba la
firma (403) aunque fuera correcta (fallos intermitentes según el orden de
suites).

Fix en dos capas:

- `tests/Security/SecurityTest.php` — usa `self::SERVER_JWT_SECRET` (constante
  que DEBE coincidir con el secret del server), inmune al estado de `$_ENV`.
- `tests/Unit/UrlSignerTest.php` — captura el secret del bootstrap en
  `setUpBeforeClass()` y lo restaura en `tearDownAfterClass()`, para no dejar
  efecto colateral a las otras suites.

## Política de credenciales

- El seed `admin/admin123` sigue existiendo **solo** para la BD local de tests
  (`roms-vault-test`); nunca hay credenciales reales en el repo.
- `TEST_DB_PASSWORD` se inyecta por entorno; `JWT_SECRET` de tests es fijo y
  falso (nunca el de `.env` real).
- `.dockerignore` evita que `tests/` viaje al artefacto Docker (pendiente de
  crear en la FASE 2 de CI/CD del roadmap maestro).

## Verificación

- `php -l` en los ficheros nuevos/modificados: sin errores.
- Suite `Security` sola: `OK (15 tests, 41 assertions)`.
- Suite completa: `OK (138 tests, 361 assertions)`.
- Smoke manual previo al fix de H1 confirmó el fatal reproducible con
  `curl /home/show/abc` (200 + `PDOException`); tras el fix responde 404.
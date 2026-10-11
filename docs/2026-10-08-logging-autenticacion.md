# Logging de autenticación (item 2.1 — OWASP A09)

- **Fecha:** 2026-10-08
- **Estado:** implementado
- **Fuente:** `docs/_archivo/2026-10-07-plan-mejoras-seguridad-y-rendimiento.md` — sección 2.1
- **Roadmap relacionado:** FASE 2 — observabilidad (`docs/2026-08-05-plan-mejoras-nivel-senior.md`)

---

## 1. Objetivo

Registrar los eventos de autenticación (intentos fallidos, éxitos, bloqueos por
rate limit y logout) para poder detectar ataques de fuerza bruta, accesos
anómalos y cambios de credenciales, algo que hoy no era posible (no existía
ningún logging en la aplicación).

## 2. Diseño

Servicio **`src/config/LoggerService.php`**, estático y sin framework, siguiendo
el patrón de `RateLimiter`:

- **Persistencia (fuente de verdad):** tabla `public.auditoria` en PostgreSQL.
  Columnas estructuradas (`evento`, `nivel`, `username`, `user_id`, `rol_id`,
  `ip`, `created_at`) + `contexto JSONB` para datos extra. Permite consultas y
  paneles de auditoría (`Auditoria::recientes()`, `contarEventos()`,
  `podarAntiguos()`).
- **Fallback a archivo JSON Lines** si la BD no está disponible (fail-open real:
  `Database::tryGetInstance()` devuelve `null` en vez de `die()`; `write()`
  intenta BD primero y cae al archivo solo si falla).
- **Formato fallback:** JSON Lines, una línea JSON por evento → legible por
  herramientas externas: `{"ts":"...","level":"...","event":"...","context":{...}}`.
- **Rotación:** por día (`auth-YYYY-MM-DD.log`) y por tamaño (`LOG_MAX_BYTES`,
  default 10 MB) con `auth-YYYY-MM-DD-N.log`.
- **Ruta configurable:** `AUTH_LOG_DIR` (vacío = `sys_get_temp_dir()/rv_logs`).
- **Eventos:**
  - `login_success` → user_id, username, rol_id, ip.
  - `login_failed` (level warning) → username, ip.
  - `rate_limited` (level warning) → username, ip (fuerza bruta).
  - `logout` → user_id, username, rol_id, ip.
  - `write(evento, contexto, nivel)` genérico para flujos futuros
    (cambios de rol/credenciales cuando exista esa funcionalidad).

### Garantías

- **Fail-open:** si la escritura falla (BD y archivo), se registra `false` y
  NUNCA se corta el flujo del login/logout (el logging no es bloqueante).
- **Higiene:** nunca se loguean contraseñas ni hashes; la capa de BD filtra
  `password`, `password_hash` y `token` incluso si el llamante se descuida;
  usernames con caracteres de control se sanitizan (evita inyección de líneas
  falsas); IP inválida → vacío.
- **Permisos:** directorio 0700, archivo 0600 (solo fallback).

## 3. Cambios realizados

| Archivo | Cambio |
|---------|--------|
| `src/config/LoggerService.php` | **Nuevo** servicio de logging: BD (vía `Auditoria`) + fallback a archivo |
| `src/models/Auditoria.php` | **Nuevo** modelo de auditoría (INSERT/consultas/podado, fail-open) |
| `src/config/database.php` | `tryGetInstance()` fail-open + caché solo de conexiones exitosas; `die()` queda en `getInstance()` |
| `data/migrations/2026-10-08-auditoria.sql` | **Nuevo**: tabla `public.auditoria` + 4 índices (idempotente) |
| `data/roms-vaultDB-postgreSQL.sql` | Tabla `auditoria` + índices integrados en el schema base |
| `src/controllers/AuthController.php` | Loguea rate limit, login fallido, login exitoso y logout |
| `.env.example` | Añade `AUTH_LOG_DIR` y `LOG_MAX_BYTES` |
| `docker-entrypoint.sh` | Fija `AUTH_LOG_DIR=/var/log/roms-vault` (default), lo crea y otorga a `www-data` |
| `tests/bootstrap.php` | Carga `LoggerService`; fija `AUTH_LOG_DIR`, DB_* inertes (protegen Neon) y `ROMV_TESTING` |
| `tests/Integration/Server.php` | Pasa `AUTH_LOG_DIR` al servidor hijo |
| `tests/Integration/bootstrap.php` | Limpia el log de test en cada ejecución |
| `tests/Unit/LoggerServiceTest.php` | **Nuevo**: 11 tests unitarios (fallback a archivo) |
| `tests/Integration/AuditoriaModelTest.php` | **Nuevo**: 7 tests de persistencia/consultas/podado en BD |
| `tests/Integration/AuthFlowTest.php` | Verifica `login_failed` y `login_success` en la **tabla BD** |
| `tests/Integration/IntegrationTestCase.php` | TRUNCATE incluye `public.auditoria` |

## 4. Verificación

- `php -l`: 0 errores en los 7 archivos PHP tocados.
- Suite Unit: 46/46 OK (11 de LoggerService; el fallback a archivo es el camino
  que se prueba por la BD inerte de Unit).
- Suite completa: **79/79 OK** (una vez añadidos los 7 tests de
  `AuditoriaModelTest`) — testdox.
- Evidencia real en la BD de prueba:

```
 id |    evento     |  nivel  | username | user_id | rol_id |     ip     | created_at
----+---------------+---------+----------+---------+--------+------------+-------------------------------
  3 | logout        | info    | admin    |       1 |      1 | 192.0.2.3  | 2026-10-08 12:54:35.809404-06
  2 | login_failed  | warning | admin    |         |        | 192.0.2.2  | 2026-10-08 12:54:35.807953-06
  1 | login_success | info    | admin    |       1 |      1 | 192.0.2.1  | 2026-10-08 12:54:35.783561-06
```

## 5. Pendiente / decisiones

- **Retención/podado:** `Auditoria::podarAntiguos($dias)` existe, pero el
  programado (cron/scheduler) se deja para FASE 2 (observabilidad); el índice
  `idx_auditoria_created_at (DESC)` permite podado y orden eficientes.
- **Alertas activas** (email/telegram) y consumo del log por un observador
  externo: se deja para la FASE 2 (observabilidad), cuando exista CI/CD que
  pueda entregar esas alertas.
- Los cambios de rol/credenciales de administradores se loguearán con
  `LoggerService::write()` en cuanto exista esa funcionalidad (hoy no hay UI que
  lo permita).
- En Vercel (contenedor efímero) los logs **de archivo** no persisten entre
  instancias; la **tabla `auditoria` en Neon sí persiste** (motivo por el que se
  eligió BD como fuente de verdad). El fallback a archivo queda solo para
  supervivencia ante caída de BD.
- `Database::getInstance()` mantiene el `die()`/503 histórico para los flujos
  normales; solo `tryGetInstance()` es fail-open (usado por `Auditoria`).
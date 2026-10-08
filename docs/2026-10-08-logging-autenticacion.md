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

- **Formato:** JSON Lines (una línea JSON por evento) → legible por
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

- **Fail-open:** si la escritura falla, se registra `false` y NUNCA se corta el
  flujo del login/logout (el logging no es bloqueante).
- **Higiene:** nunca se loguean contraseñas ni hashes; usernames con caracteres
  de control se sanitizan (evita inyección de líneas falsas); IP inválida → vacío.
- **Permisos:** directorio 0700, archivo 0600.

## 3. Cambios realizados

| Archivo | Cambio |
|---------|--------|
| `src/config/LoggerService.php` | **Nuevo** servicio de logging |
| `src/controllers/AuthController.php` | Loguea rate limit, login fallido, login exitoso y logout |
| `.env.example` | Añade `AUTH_LOG_DIR` y `LOG_MAX_BYTES` |
| `docker-entrypoint.sh` | Fija `AUTH_LOG_DIR=/var/log/roms-vault` (default), lo crea y otorga a `www-data` |
| `tests/bootstrap.php` | Carga `LoggerService` y fija `AUTH_LOG_DIR` de test |
| `tests/Integration/Server.php` | Pasa `AUTH_LOG_DIR` al servidor hijo |
| `tests/Integration/bootstrap.php` | Limpia el log de test en cada ejecución |
| `tests/Unit/LoggerServiceTest.php` | **Nuevo**: 11 tests unitarios |
| `tests/Integration/AuthFlowTest.php` | Verifica `login_failed` y `login_success` en el log real |

## 4. Verificación

- `php -l`: 0 errores en los 7 archivos PHP tocados.
- Suite Unit: 46/46 OK (11 nuevos de LoggerService).
- Suite completa: 72/72 OK (testdox).
- Evidencia real del log generado por los tests de integración
  (`rv_logs_test/auth-2026-10-08.log`):

```
{"ts":"...19:43:05+02:00","level":"warning","event":"login_failed","context":{"username":"admin","ip":"127.0.0.1"}}
{"ts":"...19:43:05+02:00","level":"info","event":"login_success","context":{"user_id":1,"username":"admin","rol_id":1,"ip":"127.0.0.1"}}
{"ts":"...19:43:07+02:00","level":"warning","event":"rate_limited","context":{"username":"admin","ip":"127.0.0.1"}}
```

## 5. Pendiente / decisiones

- **Alertas activas** (email/telegram) y consumo del log por un observador
  externo: se deja para la FASE 2 (observabilidad), cuando exista CI/CD que
  pueda entregar esas alertas.
- Los cambios de rol/credenciales de administradores se loguearán con
  `LoggerService::write()` en cuanto exista esa funcionalidad (hoy no hay UI que
  lo permita).
- En Vercel (contenedor efímero) los logs no persisten entre instancias; se
  recomienda redirigir a stdout/obj. de logs en FASE 2.
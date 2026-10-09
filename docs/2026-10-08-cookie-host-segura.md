# Cookie de sesión JWT con prefijo `__Host-` — ítem 2.5

**Fecha:** 2026-10-08
**Rama:** `optimizacion-arquitectura`
**Ítem del roadmap:** [2.5 Endurecer cookie JWT (`__Host-`)](_archivo/2026-10-07-plan-mejoras-seguridad-y-rendimiento.md)

## Cambio

`src/config/JWTService.php`:

- `COOKIE_NAME`: `rv_token` → **`__Host-rv_token`**
- `secure` pasa a **siempre `true`** en `setTokenCookie()` y `clearTokenCookie()`,
  independiente de `$_SERVER['HTTPS']` (la detección tras el edge de Vercel era poco
  fiable).

## Qué protege el prefijo `__Host-` (RFC 6265bis)

- Exige `Secure` siempre y `Path=/` (ya estaba). Con `Path=/`, el prefijo impide que la
  cookie sea **fijada o sobrescrita desde cualquier subdominio** (`evil.roms-vault.com` no
  puede inyectar `__Host-rv_token`), cerrando session-fixation y devaluación por
  subdominio comprometido.
- Navegadores rechazan cookies `__Host-` que no cumplan las dos reglas → garantía
  estructural, no solo de convención.

## Impactos

- **Logout forzado único en producción**: cambiar el nombre invalida las sesiones
  emitidas como `rv_token` (comportamiento esperado en endurecimiento, equivalente a
  rotar `JWT_SECRET`).
- **Dev local**: funciona en `http://localhost:8000` (Chrome y Firefox tratan `localhost`
  como *secure context*, aceptan cookies `Secure`). **NO funciona en `http://IP`** (p. ej.
  `192.168.x.x` o `127.0.0.1`), donde los navegadores rechazan `Secure`. Documentado para
  el desarrollo por IP.
- **Tests**: actualizados `tests/Unit/JWTServiceTest.php` y
  `tests/Integration/Server.php` al nuevo nombre. El parsing de `Set-Cookie` del Server es
  dinámico, compatible con el prefijo.

## Verificación

- `php -l src/config/JWTService.php` OK; grep `rv_token` → solo `__Host-rv_token`.
- Suite completa: **121 tests / 316 assertions OK** (sin regresiones).
- Pendiente smoke manual en navegador: login en `http://localhost:8000` y verificar en
  DevTools que la cookie `__Host-rv_token` se envía pese a `Secure` sobre localhost.
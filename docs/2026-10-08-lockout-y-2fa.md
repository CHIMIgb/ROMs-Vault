# Lockout por usuario + 2FA TOTP opcional (ítems 2.2 / A07)

- **Fecha:** 2026-10-08
- **Plan de origen:** `docs/_archivo/2026-10-07-plan-mejoras-seguridad-y-rendimiento.md` (ítem 2.2)
- **Estado:** implementado y verificado
- **Relacionado:** ítem 2.1 (logging de autenticación: `docs/2026-10-08-logging-autenticacion.md`),
  sección 12 del plan (IP real tras proxy/Vercel — pendiente de despliegue)

---

## 1. Objetivo

Cerrar la brecha A07 (Falla de identificación y autenticación) del acceso admin:

1. **Lockout por cuenta:** bloquear temporalmente la cuenta tras N fallos de contraseña,
   complementando el rate limit por IP ya existente.
2. **2FA TOTP opcional (RFC 6238) para el rol administrador:** segundo factor en el login,
   gestión desde el panel (activar/confirmar/desactivar) y refuerzo por defensa en profundidad
   en el middleware.

Decisiones tomadas en plan aprobado: **Fase A (lockout) + Fase B (2FA) juntas**; TOTP
**en PHP puro** (sin dependencia Composer; solo se recurriría a librería si se complicara,
no hizo falta).

## 2. Modelo de datos

Migración idempotente `data/migrations/2026-10-08-lockout-usuario.sql` (aplicada a
`roms-vault` y `roms-vault-test`; ya fusionada en `data/roms-vaultDB-postgreSQL.sql`):

```sql
ALTER TABLE public.usuarios ADD COLUMN IF NOT EXISTS login_failed_attempts INT NOT NULL DEFAULT 0;
ALTER TABLE public.usuarios ADD COLUMN IF NOT EXISTS locked_until TIMESTAMPTZ;
ALTER TABLE public.usuarios ADD COLUMN IF NOT EXISTS tfa_secret VARCHAR(64);
ALTER TABLE public.usuarios ADD COLUMN IF NOT EXISTS tfa_enabled BOOLEAN NOT NULL DEFAULT FALSE;
CREATE INDEX IF NOT EXISTS idx_usuarios_locked_until ON public.usuarios (locked_until);
```

- `login_failed_attempts`: contador de fallos en la ventana actual.
- `locked_until`: instante en que la cuenta vuelve a estar disponible (`NULL` = sin bloqueo).
- `tfa_secret`: secreto base32 del TOTP (solo si `tfa_enabled`).
- `tfa_enabled`: flag de segundo factor activo.

**Nota de despliegue (Neon):** antes del release aplicar la migración a la BD de producción:
`psql -f data/migrations/2026-10-08-lockout-usuario.sql`.

## 3. Lockout por cuenta (Fase A)

### Reglas implementadas

1. Se comprueba el bloqueo **antes** de verificar la contraseña (corta el intento incluso con
   credenciales correctas).
2. Mensaje **genérico** al fallar o al estar bloqueado (no revela la existencia de la cuenta ni
   el motivo).
3. Al alcanzar el máximo se bloquea la cuenta y **se resetea el contador**: al expirar
   `locked_until`, la cuenta vuelve con la ventana limpia.
4. El login correcto **desbloquea** la cuenta y el contador.
5. No interfiere con el rate limit por IP (se mantiene): el 5º fallo devuelve la alerta de
   bloqueo/´429`; estando bloqueada la cuenta, incluso tras expirar la ventana IP devuelve
   el aviso genérico 200.

### Parámetros (`.env.example`)

```ini
AUTH_LOCKOUT_MAX=5        # intentos fallidos consecutivos (por cuenta)
AUTH_LOCKOUT_SECONDS=900  # duración del bloqueo de la cuenta
```

### Código

- `src/models/Usuario.php`: `estaBloqueado()`, `fallosActuales()`, `incrementarFallos()`,
  `bloquear()`, `desbloquear()`, `registrarSecretTfa()`, `habilitarTfa()`, `deshabilitarTfa()`,
  `obtenerSecretTfa()`.
- `src/config/LoggerService.php`: `accountLocked()`, `loginBlockedAccount()`,
  `tfaFailed()` (eventos `account_locked`, `login_blocked_account`, `tfa_failed`).
- `src/controllers/AuthController.php`: integración del flujo de login.

## 4. 2FA TOTP (Fase B)

### Servicio `src/config/TfaService.php` (PHP puro, RFC 6238)

- `generarSecret()` (32 bytes base32), `base32Encode()`/`base32Decode()`,
  `codigo(string $secret, ?int $timestamp = null, int $digits = 6)` (HMAC-SHA1,
  6 dígitos en producción; `8` disponible para los vectores del RFC), `verificar()`.
- `provisionUri()`: URI `otpauth://` (emisión manual de clave base32 + URI; se descartó el
  QR vía API externa porque filtraría el secreto a un tercero).
- Estado pendiente de 2ª factor: `iniciarPending()`, `usuarioPendiente()`, `limpiarPending()`
  basado en **cookie `rv_tfa_pending`** httpOnly `SameSite=Strict` firmada con HMAC-SHA256
  (`JWT_SECRET`), TTL `TFA_PENDING_TTL` (default 120 s).

### Flujo de login con 2FA

```
POST /auth/login (usuario con tfa_enabled)
  → contraseña correcta → set cookie rv_tfa_pending → 302 /auth/twoFactor (sin JWT)
GET /auth/twoFactor → formulario (solo si existe pending válido; si no, 302 /auth/login)
POST /auth/twoFactor { code }
  → TOTP válido → emite JWT con claim tfa=true → 302 /admin/dashboard
  → TOTP inválido → 200 con alerta, evento tfa_failed, sin sesión
```

- Rate limit **por IP** dedicado al segundo factor: namespace `two_factor`,
  `TFA_RATE_LIMIT_MAX=10` / `TFA_RATE_LIMIT_WINDOW=900` (defaults inline).
- Parámetros en `.env.example`: `TFA_PENDING_TTL=120`, `TFA_ISSUER=ROMs Vault`.

### Claim `tfa` en el JWT y defensa en profundidad

- `JWTService::generate(array $user, bool $tfa = false)` añade el claim `tfa`;
  `refresh()` lo propaga; `getCurrentUser()` lo expone.
- `AuthMiddleware::requiereSegundoFactor()` consulta la BD: si la cuenta tiene `tfa_enabled`
  pero el token **no** declara `tfa`, obliga a pasar el segundo factor (redirect
  `requireAdmin()` / 403 `requireAdminAjax()`). Fail-open si la BD no responde (no degradar
  el login por un fallo de infrestructura).

### Gestión (panel admin)

- Enlace **"Seguridad 2FA"** en `src/views/admin/dashboard.php` → `/admin/tfa`.
- `AdminController::tfa()` (`src/views/admin/tfa.php`):
  - `activar`: genera/rota un secreto y lo muestra (clave base32 + URI otpauth de escritura manual).
  - `confirmar`: exige el TOTP actual y habilita el 2FA.
  - `desactivar`: exige la contraseña y deshabilita el 2FA (no pedir 2FA para quitarlo: el
    propio TOTP ya lo demuestra).
  - Al activar/desactivar se fuerza logout (`JWTService::clearTokenCookie()`): la sesión
    actual queda obsoleta o sin claim `tfa`.
- La acción de ruta se llama `twoFactor` (no `2fa`) porque los métodos PHP no pueden empezar
  por dígito y el router llama el método dinámicamente.

## 5. Tests

- `tests/Unit/TfaServiceTest.php` (11 tests): vectores RFC 6238 Apéndice B (8 dígitos),
  base32 roundtrip, normalización/ventana de tiempo, provisional URI.
- `tests/Integration/AuthFlowTest.php` (+2): bloqueo tras máximo de fallos (rechaza el login
  correcto durante el lockout) y desbloqueo al expirar.
- `tests/Integration/TwoFactorFlowTest.php` (5 tests): login con 2FA → 302 al segundo factor
  sin sesión; GET `/auth/twoFactor` sin pending → redirect login; código incorrecto → 200
  sin sesión + `tfa_failed`; código correcto → 302 dashboard + JWT y dashboard 200; y
  **defensa en profundidad**: JWT sin claim `tfa` con cuenta 2FA activa → bloqueado por el
  panel.
- `IntegrationTestCase::tearDownAfterClass()` ahora limpia también el rate dir `two_factor`.

**Resultados:** suite completa **97 tests, 268 assertions, OK** (antes 81/219).
`php -l` sin errores en todos los archivos tocados.

## 6. Smoke manual (evidencia en vivo)

Server `php -S 0.0.0.0:8011 router.php` + BD local `roms-vault` (usuario temporal `smoke`,
rol admin, contraseña `admin123`, creado y **borrado** al terminar; `admin` real intacto):

| Prueba | Resultado |
|--------|-----------|
| Login normal `smoke/admin123` | 302 `/admin/dashboard` + JWT (`tfa:false`) → dashboard 200 |
| 5 fallos de contraseña | 200 con alerta; 6º con password correcta → **200 rechazo** (cuenta bloqueada); BD: `locked_until` activo, contador reseteado |
| Login con 2FA habilitado | 302 `/auth/twoFactor` + cookie `rv_tfa_pending` firmada (Max-Age=120, HttpOnly, Strict), sin `rv_token` |
| GET `/auth/twoFactor` | 200 formulario "Verificación en dos pasos" |
| Código incorrecto `000000` | 200, sin sesión |
| Código correcto (TOTP real `381686` vía `TfaService::codigo()`) | 302 `/admin/dashboard` + JWT (`tfa:true`) → dashboard 200 |

## 7. Pendientes relacionados (no bloqueantes)

- Sección 12 del plan: verificar la IP real en el primer despliegue (Vercel) y
  `TRUSTED_PROXIES` (se resolverá con el ítem 3.2).
- Los administradores existentes no tienen 2FA hasta que lo activen desde el panel.
- El QR vinculante (mostrar URI como QR) requeriría una librería cliente (p. ej. JS) o un
  endpoint propio de imágenes; hoy se ofrece clave manual + URI, sin dependencias.
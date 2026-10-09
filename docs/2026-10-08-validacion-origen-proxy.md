# Validación opcional de Origin/Referer en el proxy (anti-hotlink) — ítem 3.3

**Fecha:** 2026-10-08
**Rama:** `optimizacion-arquitectura`
**Ítem del roadmap:** [3.3 Validación opcional Referer/Origin](_archivo/2026-10-07-plan-mejoras-seguridad-y-rendimiento.md)
**Plan aprobado:** 2026-10-08. Decisiones del usuario:
- **P1 → Modo B (anti-hotlink)**: sin la política, se rechaza solo cuando las peticiones *declaran* un `Origin`/`Referer` de un origen no permitido; peticiones sin headers se permiten (descargas `<a rel="noopener noreferrer">`, descargadores CLI con enlace firmado).
- **P2 → `.env` local**: `ALLOWED_ORIGINS` pasa a ser un **listado** que conserva `https://roms-vault.vercel.app` y añade `http://localhost:8000` para que el emulador funcione en desarrollo.

## Problema

Los enlaces firmados del proxy (TTL 15 min, HMAC) podían embeberse desde un tercer sitio
(`<img>`, `<video>`, fetch cross-site) y consumir ancho de banda / simular descargas
(hotlink / CSRF de descarga). El preflight OPTIONS ya validaba `Access-Control-Allow-Origin`,
pero la petición GET/HEAD real no tenía validación de origen.

## Solución implementada

### Nueva clase `src/config/OriginPolicy.php` (estática, patrón `GDriveAllowlist`)

- `origenesPermitidos(): array` — lee `ALLOWED_ORIGINS` (coma-separada, tolera espacios),
  normaliza a `scheme://host[:puerto-no-default]` con dedupe. Vacío → `[]`.
- `extraerOrigen(string $url): ?string` — extrae el origin de un Referer o URL
  (normaliza minúsculas y puerto por defecto 80/443).
- `verificar(?string $origin, ?string $referer): bool` — modo anti-hotlink (B):
  1. `ALLOWED_ORIGINS` vacío → `true` (sin validación; comportamiento de desarrollo).
  2. `Origin: null` (sandbox/iframe ajeno) y allowlist activa → `false` siempre.
  3. Sin Origin ni Referer → `true` (descargas/CLI; la firma HMAC+TTL sigue mandando).
  4. Con algún header → basta con que `Origin` **o** el origin extraído de `Referer`
     coincida con un permitido para pasar.

### `endpoints/rom_proxy.php`

- `require_once` de `OriginPolicy`.
- Bloque de validación **tras el TTL (410) y antes del rate limit y de la BD**: si
  `verificar()` es `false` → `403` + JSON `{"error_type":"origin"}`. Así los rechazos no
  gastan rate limit ni tocan PostgreSQL/Google.

### Configuración

- `.env.example`: ampliado el comentario de `ALLOWED_ORIGINS` (con valor, el proxy valida
  Origin/Referer en GET/HEAD; vacío = sin validación; recordatorio de incluir el origen dev).
- `.env` local (no se commitea): `ALLOWED_ORIGINS=https://roms-vault.vercel.app,http://localhost:8000`.

No se tocan las vistas de descarga (`rel="noopener noreferrer"` intacto — funciona con el
modo B) ni el preflight OPTIONS existente.

## Verificación con evidencia

### Unit

- Nuevo `tests/Unit/OriginPolicyTest.php` — **13 tests, 21 assertions OK**: sin allowlist
  no valida; sin headers permitido (modo B); Origin/Referer permitidos pasan; ajenos se
  rechazan; basta un header que coincida; `Origin: null` rechazado (y no rechazado sin
  allowlist); normalización de puertos y mayúsculas; env coma-separada con espacios;
  extracción de origin desde path.
- `tests/bootstrap.php`: `require_once` de `OriginPolicy` (patrón sin autoload de app).
- **Suite completa: 121 tests / 316 assertions OK** (antes 108/295).

### Smoke manual (servidor `php -S 0.0.0.0:8014 router.php` en Windows, file_id inexistente)

| # | Petición | Resultado |
|---|----------|-----------|
| 1 | Sin firma (regresión) | 403 `auth` |
| 2 | Firma OK + `Origin: https://evil.com` | **403 `origin`** |
| 3 | Firma OK + `Referer: https://evil.com/robo.html` | **403 `origin`** |
| 4 | Firma OK + `Origin: https://roms-vault.vercel.app` | pasa → 404 BD (rom inexistente) |
| 5 | Firma OK + `Origin: http://localhost:8000` (dev) | pasa → 404 BD |
| 6 | Firma OK + `Referer: http://localhost:8000/juegos/1` | pasa → 404 BD |
| 7 | Firma OK + **sin headers (modo B)** | pasa → 404 BD |
| 8 | `t` de hace 1 h (regresión TTL) | 410 `expired` |

El 404 tras pasar la política confirma que la petición siguió el flujo normal (BD); los
rechazos (2 y 3) ocurren antes de tocar BD/Google. Servidor parado y `scratch/` limpio.

## Commits

- `feat(seguridad): validación de Origin/Referer en el proxy (anti-hotlink, ítem 3.3)` — código.
- `docs(seguridad): doc + roadmap validación de origen del proxy (3.3)` — doc fechado, tick
  3.3 y Registro de progreso (el hash del primer commit se anota abajo).
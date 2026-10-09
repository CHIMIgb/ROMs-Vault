# Allowlist de hosts del proxy (anti-SSRF / A10) — ítem 3.2

**Fecha:** 2026-10-08
**Rama:** `optimizacion-arquitectura`
**Ítem del roadmap:** [3.2 Allowlist de hosts ante redirects (SSRF / A10)](_archivo/2026-10-07-plan-mejoras-seguridad-y-rendimiento.md)
**Plan aprobado:** 2026-10-08 (decisión del usuario: **Fase B de `TRUSTED_PROXIES` en `RateLimiter` queda fuera** de esta tarea y se trabaja aparte; la allowlist es configurable vía variable de entorno para hosts futuros).

## Problema

El proxy (`endpoints/rom_proxy.php`) sigue redirecciones de Google Drive rebotando sobre el
host actual sin ninguna validación de destino. Un `file_id` manipulado o una respuesta
maliciosa podían hacer que el servidor conectara a **cualquier host** (red interna,
`169.254.169.254`, `metadata.google.internal`, etc.), exponiendo el clásico vector **SSRF
(A10)**.

## Solución implementada

### Nueva clase `src/config/GDriveAllowlist.php` (estática)

- `HOSTS_DEFAULT = ['drive.google.com', 'drive.usercontent.google.com']`.
- `hostsPermitidos(): array` — lee `$_ENV['GDRIVE_ALLOWED_HOSTS']` (coma-separada, con
  espacios tolerados). Si está vacía/ausente → defaults. Permite añadir hosts de entrega
  futuros **sin tocar código**, solo `.env`.
- `hostPermitido(string $host): bool` — comparación **exacta** e insensitive a mayúsculas:
  no admite subdominios, ni trailing dot, ni IPs no listadas explícitamente.

### `endpoints/rom_proxy.php` — tres puntos de control (defensa en profundidad)

1. **URL inicial:** tras construir `GDRIVE_BASE + file_id`, se valida el host (por
   construcción es `drive.google.com`; queda explícito y no depende de ediciones futuras).
2. **Cada redirect** de `resolveGDriveUrl()`: tras resolver URLs relativas, se valida el
   host de la `Location` **antes** de `$currentUrl = $location; continue;`. Un redirect a
   cualquier host no permitido corta la cadena y devuelve `error = host_no_permitido`.
3. **URL final** (venga de la caché `romproxy_cache` o de la resolución): validación única
   antes de abrir el stream. **Este punto es el que cubre el caso caché** (el smoke prueba
   exactamente eso).

Ante rechazo, siempre: `HTTP 502` + JSON **genérico** («El destino de la ROM no es
válido.», mismo shape que el error de red existente) — el detalle del host va solo a
`error_log` (anti-fuga de información/OWASP A05). También se eliminó el `detail` con el
error interno de resolución que antes viajaba en el JSON.

### Configuración

`.env.example`:

```
SIGNED_URL_TTL=900
GDRIVE_ALLOWED_HOSTS=drive.google.com,drive.usercontent.google.com
```

## Verificación con evidencia

### Unit

- Nuevo `tests/Unit/GDriveAllowlistTest.php` — **7 tests, 19 assertions OK**:
  defaults sin env, env vacío → defaults, coma-separada con espacios, host extra
  configurado en env permitido, hosts Drive permitidos, case-insensitive, y rechazo de
  `169.254.169.254`, `metadata.google.internal`, `evil.com`, `google.com`,
  `sub.drive.google.com`, `drive.google.com.evil.com`, `drive.google.com.`, `''`.
- `tests/bootstrap.php`: añadido `require_once` de `GDriveAllowlist.php` (sin autoload de
  app, patrón del proyecto).
- **Suite completa: 108 tests / 295 assertions OK** (antes 101/276).

### Smoke manual (servidor `php -S 0.0.0.0:8013 router.php` en Windows, vía `172.20.32.1`)

Preparación: juego temporal en `roms-vault` con `google_drive_file_id=SMOKEFILEPROXY123`
(la comprobación de BD ocurre antes que la caché) y caché inyectada en
`sys_get_temp_dir()/romproxy_cache/<md5(file_id)>.json` apuntando a
`http://169.254.169.254/latest/meta-data/` (expira en 600 s).

| Prueba | Resultado |
|--------|-----------|
| A) Caché maliciosa + firma válida reciente | **HTTP 502**, `{"error":"No se pudo resolver la URL de Google Drive.","error_type":"network","detail":"El destino de la ROM no es válido."}` — el proxy **no conectó** a la metadata cloud |
| B) Sin firma (regresión) | HTTP 403 `auth` |
| C) `t` de hace 1 h (regresión TTL) | HTTP 410 `expired` |
| D) Firma manipulada (regresión) | HTTP 403 `auth` |

Limpieza tras el smoke: juego temporal borrado (verificado `count(*) → 0`), caché
maliciosa borrada, servidor parado, `scratch/` limpio (gitignored).

## Fase B (NO incluida por decisión del usuario)

El plan original proponía `TRUSTED_PROXIES` en `RateLimiter` (fuente de IP real tras
proxy/Vercel). El usuario decidió dejarlo **fuera de esta tarea**; se tratará por
separado (relacionado con la «Verificación pendiente en despliegue» de la sección 12 del
plan). No se tocó `src/config/RateLimiter.php` ni `tests/Unit/RateLimiterTest.php`.

## Commits

- `feat(seguridad): allowlist de hosts del proxy (anti-SSRF, ítem 3.2)` — código.
- `docs(seguridad): doc + roadmap allowlist hosts proxy (3.2)` — doc fechado, tick 3.2 y
  Registro de progreso (el hash del primer commit se anota abajo).
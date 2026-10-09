# TTL de enlaces firmados 7200 → 900 s configurable (ítem 3.1 / A02)

- **Fecha:** 2026-10-08
- **Plan de origen:** `docs/_archivo/2026-10-07-plan-mejoras-seguridad-y-rendimiento.md` (ítem 3.1)
- **Estado:** implementado y verificado
- **Relacionado:** ítem 3.2 (allowlist de hosts, pendiente; se resolverá junto a la
  sección 12 / `TRUSTED_PROXIES`), ítem 3.4 (revocación por rotación de `JWT_SECRET`)

---

## 1. Objetivo

Reducir la ventana de replay de los enlaces firmados HMAC del proxy de streaming y de
las descargas **de 2 horas (7200 s) a 900 s (15 minutos)** por defecto, y hacerla
**configurable vía `.env`** con `SIGNED_URL_TTL`.

## 2. Qué era y cómo funcionaba (recordatorio stateless)

El enlace firmado no se almacena en ningún sitio (ni BD, ni sesión, ni caché): la URL
lleva `file_id`, `t` (timestamp de firma) y `sig = HMAC_SHA256(JWT_SECRET, fileId . '|' . t)`.
El servidor **recalcula** la firma y compara `time() - t` contra el TTL **en el momento
de verificar**. No hay filas que migrar ni listas que depurar al cambiar el TTL.

## 3. Cambios

### `src/config/UrlSigner.php`

- `public const TTL = 7200` se elimina y se sustituye por el método estático
  `UrlSigner::ttl(): int` → lee `$_ENV['SIGNED_URL_TTL']` (default **900**); si el valor
  no es un entero positivo se aplica el default (saneo).
- `verify()` pasa a `int $ttl = null` y resuelve dentro `$ttl ??= self::ttl()`
  (el TTL se lee en cada llamada, no cacheado en la firma del método). El skew de
  +300 s por reloj adelantado se mantiene.

### `endpoints/rom_proxy.php`

- `require_once src/config/UrlSigner.php` (junto al bloque de BD).
- `define('SIGNED_URL_TTL', UrlSigner::ttl());` en sustitución del valor hardcodeado
  `7200`. El proxy conserva su propia verificación de firma (mismo formato HMAC); el TTL
  queda centralizado en `UrlSigner::ttl()` como fuente única del valor.

### `.env.example`

- Se añade `SIGNED_URL_TTL=900` con comentario explicativo (antes no existía).

### `tests/Unit/UrlSignerTest.php`

- `UrlSigner::TTL` → `UrlSigner::ttl()` en el test de expiración (la constante dejó de
  existir; era su único uso en el repo).
- Tests nuevos: default 900 sin env; lectura del env con saneo de inválidos (`0`, `-5`,
  no-enteros → 900); `verify()` usa el TTL del env en el momento de la llamada; TTL
  explícito por parámetro sigue funcionando (compat).

## 4. Comportamiento resultante

- Cualquier enlace firmado (jugar `proxyUrl`, descargar `downloadUrl`, `checkProxyAccess`)
  se rechaza con **410 expirado** cuando `time() - t > SIGNED_URL_TTL`.
- Sin `SIGNED_URL_TTL` en `.env` → 900 s.
- Un enlace generado antes del cambio deja de ser válido 15 min después de su `t`
  (no al instante del despliegue).

## 5. Verificación

- `php -l` sin errores en `UrlSigner.php`, `rom_proxy.php` y `UrlSignerTest.php`.
- `UrlSignerTest`: **12/12 OK (26 assertions)**.
- Suite completa: **101 tests / 276 assertions OK** (antes 97/268).
- Smoke manual `php -S router.php` (`.env` local **sin** `SIGNED_URL_TTL`,
  evidencia del default 900):

| Caso | Resultado |
|------|-----------|
| `/rom_proxy.php?file_id=ABC123abc` (sin firma) | 403 `auth` |
| Firma correcta con `t` hace 3600 s | **410 `expired`** (con el TTL viejo de 7200 habría pasado) |
| Firma correcta con `t` reciente | supera firma+TTL → 404 BD (local); no es `expired` |
| Firma manipulada con `t` reciente | 403 `auth` |

## 6. Impacto y riesgos conocidos

- **Página de juego abierta > 15 min** antes de pulsar Jugar/Descargar: el enlace queda
  vencido (HTTP 410); recargar la página regenera el enlace. El emulador carga al render.
- Todas las URLs públicas (`/rom_proxy.php`, `/home/download`, `/home/play`, `/ajax_*`)
  y el formato de la firma **no cambian**; solo la ventana de expiración.
- El `.env` local sin la variable usa el default 900 (ninguna sorpresa al desplegar).

## 7. Pendientes relacionados (no bloqueantes)

- Ítem 5.4 del plan (suite `Security`): añadir tests de integración HTTP que cubran
  firma ausente/válida/vencida/tamper del proxy de forma automatizada (FASE 2 / CI).
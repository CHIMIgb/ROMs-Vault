# Allowlist `TRUSTED_PROXIES` en `RateLimiter` (§12 — Fase A implementada)

- **Fecha:** 2026-10-10
- **Rama:** `pendientes-seguridad-rendimiento`
- **Commit de código:** `3b40655`
- **Plan origen:** `docs/_archivo/2026-10-07-plan-mejoras-seguridad-y-rendimiento.md` (§12; complementa el ítem 3.2 anti-SSRF)

---

## 1. Problema

`RateLimiter::clientIp()` confiaba en `X-Forwarded-For` / `X-Real-IP` cuando
`REMOTE_ADDR` estaba en rangos RFC1918 (heurístico `esIpProxyConfiable()`).
Riesgo: un cliente cuya conexión llegue a Apache desde una IP privada (LAN,
hosting mal expuesto, ingress interno) podía **falsear esas cabeceras** para
eludir el rate limiting y envenenar `public.auditoria.ip`, además de llenar el
disco temporal de contadores.

Todas las capturas de IP pasan por un único punto: `RateLimiter::clientIp()`
(login `AuthController::12,81,137`, 2FA, logout, admin `AdminController::373`,
descargas `HomeController::425` y proxy `rom_proxy.php:154`).

## 2. Solución (decisión del usuario: **Opción A — deny-by-default**)

Se elimina el heurístico por completo. `clientIp()` solo lee cabeceras de proxy
cuando `REMOTE_ADDR` coincide con la allowlist **explícita** `TRUSTED_PROXIES`:

- Nueva variable en `.env`: **`TRUSTED_PROXIES`** (lista separada por comas de
  IPs sueltas o redes CIDR IPv4/IPv6).
- **Vacío/ausente = deny-by-default:** no se confía en ninguna cabecera;
  `clientIp()` devuelve la IP del peer TCP.
- `docker-entrypoint.sh` la propaga al `.env` regenerado en cada arranque del
  contenedor (clave para Vercel).
- Nuevos unit tests: match por IP suelta, por CIDR v4 e IPv6, rechazo de
  forwarded inválido y deny-by-default.

### Comportamiento en local

No hace falta configurarla: `php -S`/XAMPP no tienen proxy y `clientIp()`
devuelve `::1`/`127.0.0.1`, como hasta ahora.

---

## 3. Qué hacer en **Vercel** (pasos concretos)

1. **Añadir la variable de entorno** en el dashboard de Vercel →
   *Project → Settings → Environment Variables* (Production; y Preview si se
   quiere igual): **`TRUSTED_PROXIES`**.
2. **Valor inicial:** tras el **primer deploy**, el contenedor Apache verá
   `REMOTE_ADDR` de la infraestructura de Vercel. Para descubrirlo:
   - Opción rápida: desplegar primero con `TRUSTED_PROXIES` **vacía**, hacer un
     login de prueba y consultar en Neon:
     `SELECT ip, evento, created_at FROM public.auditoria ORDER BY id DESC LIMIT 5;`
   - La `ip` que aparezca es el `REMOTE_ADDR` que ve Apache (si sale la IP
     pública del visitante, no hay proxy intermedio y no hace falta nada).
3. **Configurar el valor final:** listar SOLO los rangos/redes del edge que
   aparezcan (p. ej. si la auditoría muestra `10.x.y.z` → `TRUSTED_PROXIES=10.0.0.0/8`).
   **No** volcar RFC1918 completos: reintroduce parte del riesgo que cerramos.
4. **Redesplegar** (la variable se regenera en `.env` vía `docker-entrypoint.sh`).
5. **Verificar:** nuevo login de prueba y comprobar que
   `public.auditoria.ip` muestra la IP pública real del visitante.
   Si aparece la IP del edge o `::1`, ajustar `TRUSTED_PROXIES` y repetir.

> Alternativa CLI: `vercel env add TRUSTED_PROXIES` (seleccionar entorno) y
> `vercel redeploy`.

---

## 4. Riesgos a tener en cuenta

1. **Olvidar `TRUSTED_PROXIES` en un entorno con proxy:** `clientIp()` devuelve
   la IP del edge → el rate limiting de login/2FA **agrupa a todos los
   visitantes en un contador compartido** (posible denegación por acumulación
   de fallos) y la auditoría registra la IP del proxy. No es una brecha, pero
   degrada la operación. Detección: apartado 3 punto 5.
2. **Allowlist demasiado amplia:** listar RFC1918 completos vuelve a confiar en
   cualquier IP privada (el caso original). Configurar **solo** las redes que
   la auditoría confirme.
3. **Cambios silenciosos de infraestructura en Vercel:** si el `REMOTE_ADDR`
   cambia de red, la allowlist deja de coincidir → degradación silenciosa a la
   IP del edge. Mitigación: revisar `auditoria` en cada deploy importante.
4. **CIDR mal escritos / IPv6:** validar con los unit tests; una entrada
   inválida (`inet_pton` false) simplemente no matchea (fail-closed).
5. **Fail-closed siempre:** ante cualquier error de configuración, el peor caso
   es auditoría degradada o rate limiting compartido; **nunca** evasión del
   límite por cabeceras falsas.
6. **No es un secreto** (son rutas de red), pero mantenerla en las variables de
   entorno del proyecto, no mezclada con credenciales reales.

## 5. Verificación (evidencia)

- `php -l` de `RateLimiter.php` → sin errores.
- Unit tests `RateLimiterTest`: **12/12** (4 casos nuevos: CIDR v4, CIDR IPv6,
  deny-by-default, forwarded inválido).
- `composer test` → **OK (170 tests, 7495 assertions)**.
- Smoke local (`scratch/smoke_12.php`): sin lista `8.8.8.8` (ignora XFF);
  con lista `127.0.0.1` → `200.1.2.3`; local IPv6 devuelve `::1`; CIDR
  `2001:db8::/32` → `203.0.113.7`. Todos correctos.
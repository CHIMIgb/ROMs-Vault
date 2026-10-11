# §12 Fase B — Verificación en producción y activación de `TRUSTED_PROXIES`

- **Fecha:** 2026-10-11
- **Origen:** `docs/_archivo/2026-07-plan-mejoras-seguridad-y-rendimiento.md` §12 /
  `docs/2026-10-10-trusted-proxies.md` (Fase A y guía de Vercel)
- **Estado:** completada (verificación + activación en el primer despliegue real)

---

## 1. Contexto

La Fase A implementó `RateLimiter::clientIp()` con **deny-by-default** (solo lee
`X-Forwarded-For`/`X-Real-IP` si `REMOTE_ADDR` coincide con la allowlist
`TRUSTED_PROXIES`). Quedaba pendiente **verificar en producción** qué IP ve el
contenedor Apache detrás del edge de Vercel y fijar la allowlist si correspondía.

Riesgo contemplado: sin `TRUSTED_PROXIES`, todos los visitantes comparten el
`REMOTE_ADDR` del proxy → el rate limiting de login/2FA agrupa a **todos en un
contador compartido** (posible denegación por acumulación de fallos) y la
auditoría registra la IP del proxy en lugar de la del visitante.

## 2. Diagnóstico en producción

Con la app ya desplegada y `TRUSTED_PROXIES` vacía, la auditoría registraba
`127.0.0.1` para **todos** los eventos (logins desde laptop y desde móvil en la
misma red):

```sql
SELECT id, evento, username, ip, created_at FROM public.auditoria ORDER BY id DESC LIMIT 15;
-- todos los eventos → ip = '127.0.0.1'
```

Conclusión: el contenedor Apache recibe las conexiones desde el **proxy loopback
local del edge de Vercel** (`REMOTE_ADDR = 127.0.0.1`). Con deny-by-default,
`clientIp()` devolvía esa IP para todos.

## 3. Seguridad de la configuración elegida

- Vercel **reescribe** `X-Forwarded-For` con la IP pública del visitante y **no
  reenvía IPs externas** (restricción documentada de Vercel para prevenir IP
  spoofing); `x-real-ip` es idéntico a `x-forwarded-for`.
- Ningún cliente externo puede presentar `REMOTE_ADDR = 127.0.0.1` (el peer TCP
  es establecido por el proxy local), por lo que confiar únicamente en loopback
  no abre vector de spoofing: la única IP que `clientIp()` puede leer de las
  cabeceras es la que Vercel ya reescribió.

## 4. Cambio aplicado (sin cambios de código)

1. **Vercel → Settings → Environment Variables** (Production):
   - `TRUSTED_PROXIES = 127.0.0.1`
2. **Redeploy** (los environment variables solo aplican a nuevos deploys;
   `docker-entrypoint.sh` regenera `.env` en el arranque).

## 5. Verificación

1. Login de prueba desde la laptop.
2. Auditoría:
   `ip = 153.67.119.76` (login_success).
3. `https://api.ipify.org` desde el mismo navegador:
   `153.67.119.76` → **coincide** (IP pública real del visitante).

Resultado: la auditoría registra la IP pública real y el rate limiting vuelve a
ser **por IP individual** (se elimina el riesgo del contador compartido).

## 5b. Prueba diferencial (WiFi vs datos móviles)

Prueba adicional para demostrar que la IP capturada es la **del origen real** (y no
un valor fijo del proxy): el mismo usuario inició sesión desde dos redes distintas el
2026-10-11.

| Origen | IP pública registrada en `public.auditoria` |
|---|---|
| WiFi (casa) | `153.67.119.76` |
| Datos móviles (operador) | `200.68.167.185` |

Dos redes distintas → dos IP distintas: `clientIp()` resuelve la IP pública del
visitante y cada origen real tiene su propio contador de rate limit. Los eventos
previos con `127.0.0.1` quedan como evidencia del estado anterior al fix. Nota:
`200.68.167.185` es con alta probabilidad **CGNAT** del operador (compartida entre
varios clientes móviles), coherente con el comportamiento esperado.

## 6. Notas y seguimiento

- El `TRUSTED_PROXIES` mínimo (`127.0.0.1`) es fail-closed: si el proxy cambia
  de dirección/capa, se degrada silenciosamente al `REMOTE_ADDR` del edge (nunca
  es una brecha), se detecta revisando `public.auditoria` en cada deploy
  importante (riesgo documentado en `docs/2026-10-10-trusted-proxies.md`).
- Pendiente operativo: verificar de nuevo la IP tras cambios relevantes de
  infraestructura de Vercel y, si apareciera `::1`/otra red, ampliar la allowlist
  con los únicos rangos observados.
- La activación no requiere commits de código; los cambios de este documento
  quedan registrados abajo.

## 7. Referencias

- Fase A: commit `3b40655`, `docs/2026-10-10-trusted-proxies.md`
- Vercel: Request headers — `x-forwarded-for` / `x-real-ip`
  (https://vercel.com/docs/headers/request-headers)
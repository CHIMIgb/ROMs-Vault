# Revocación de firmas de enlaces del proxy — ítem 3.4

**Fecha:** 2026-10-08
**Rama:** `optimizacion-arquitectura`
**Ítem del roadmap:** [3.4 Revocación de firmas](_archivo/2026-10-07-plan-mejoras-seguridad-y-rendimiento.md)
**Esfuerzo:** bajo (doc). No requiere cambios de código.

## Cómo funciona la firma hoy

- URL firmada del proxy: `rom_proxy.php?file_id=<id>&t=<timestamp>&sig=<hmac>` donde
  `sig = hash_hmac('sha256', fileId . '|' . timestamp, JWT_SECRET)` (mismo formato en
  `UrlSigner::sign/verify` y en `endpoints/rom_proxy.php`).
- TTL: `SIGNED_URL_TTL` (900 s / 15 min, ítem 3.1) — la firma expira sola; `t` más de 15 min
  atrás devuelve **410 `expired`**, firma manipulada → **403 `auth`**.
- `JWT_SECRET` se usa además para: sesiones JWT de login (firma HS256) y la cookie TFA
  pendiente (`rv_tfa_pending`, HMAC, TTL 120 s).

## Qué es la revocación por rotación de `JWT_SECRET`

Actualmente **no hay lista de revocación individual por `file_id`** (ni kid/versión de
clave). La única forma de invalidar un enlace comprometido de forma **inmediata** es rotar
`JWT_SECRET`: al cambiar el secreto, el hash calculado con el secreto viejo deja de
coincidir → toda URL firmada emitida antes de la rotación recibe **403 `auth`**.

La rotación es una **revocación GLOBAL, no selectiva**. Impacto real:

| Ámbito | Efecto tras rotar |
|--------|-------------------|
| URLs firmadas del proxy (descargas, `EJS_gameUrl`, botones de descarga ya renderizados) | **403 `auth`** inmediato para todos los enlaces emitidos con el secreto anterior |
| Sesiones JWT de login | **Desconexión inmediata** de todos los usuarios autenticados |
| Cookie TFA pendiente (`rv_tfa_pending`) | Invalida la verificación en curso (TTL 120 s auto-expira de todos modos) |
| Enlaces emitidos DESPUÉS de la rotación | Funcionan con el nuevo secreto (hay que recargar la página para regenerar la URL firmada con el secreto actual) |

## Procedimiento operativo recomendado

1. **Elegir ventana de bajo tráfico** y avisar (la rotación desconecta a los usuarios).
2. **Generar** el nuevo secreto (≥ 32 bytes, aleatorio; firebase/php-jwt exige longitud
   mínima para HS256):
   ```bash
   openssl rand -base64 48
   ```
3. **Actualizar** `JWT_SECRET` en el `.env` local y en el despliegue (en Docker el
   `docker-entrypoint.sh` regenera `.env` desde las variables del contenedor).
4. **Verificar** (ej. con curl y una URL firmada que existiera antes de la rotación):
   - URL vieja → **403** `auth` (revocada).
   - URL recién firmada → fluye (404/streaming según haya ROM o no).
   - Login nuevo → sesión válida.
5. **Comunicar**: los usuarios en medio de una partida online perderán la ROM y deben
   recargar la página (EmulatorJS pide la URL firmada de nuevo).

## Refuerzos que ya existen (mitigan la necesidad de rotar)

- **TTL corto (15 min, ítem 3.1)**: una URL comprometida caduca sola; la ventana de abuso
  es pequeña.
- **Firma HMAC con secreto solo en servidor** (nunca en el HTML/JS): los enlaces no
  permiten forjar nuevas firmas.
- **Rate limit por IP** no-Range y **validación de origen** (ítem 3.3) dificultan el abuso
  masivo de una URL robada.
- **Allowlist de hosts** (ítem 3.2) impide que un redirect malicioso exfiltre nada.

## Nota de diseño futura (NO implementado)

Si en el futuro se quiere **revocación selectiva por `file_id`** (o rotación sin desconectar
sesiones), el camino es versionar la clave en la firma — p. ej.
`sig = hash_hmac('sha256', fileId . '|' . t . '|' . kid, JWT_SECRET_actual)` con soporte de
`kid` en la URL y retención temporal de la clave anterior en el servidor (verificar con
`kid` → elegir secreto). Eso convertiría la rotación en un mecanismo de
**key-rotation con overlapping** (la clave vieja solo firma URLs antiguas). Requiere
ampliar `UrlSigner` + `rom_proxy.php` + `.env` (p.ej. `JWT_SECRET_PREV`). Queda como
evolución posible, no como compromiso.

## Decisiones y estado

- Ítem 3.4 cerrado como **documentación** (tal como lo define el roadmap: "esfuerzo bajo
  (doc)"). `JWT_SECRET` no se rota en este commit — el procedimiento queda documentado para
  el operador.
- Commit: ver Registro de progreso del plan archivado.
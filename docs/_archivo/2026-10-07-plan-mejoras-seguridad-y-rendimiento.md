# Plan de mejoras de seguridad y rendimiento (ROMs Vault)

- **Fecha:** 2026-10-07
- **Estado:** propuesto (pendiente de implementar por fases)
- **Roadmap relacionado:** `docs/2026-08-05-plan-mejoras-nivel-senior.md` (FASE 2 — observabilidad/CI, FASE 3.3 — i18n #219)
- **Estructura:** consecuencia de la reorganización `docs/2026-10-07-reorganizacion-estructura-archivos.md`

---

## 1. Objetivo

Endurecer el acceso a la cuenta de administrador y a los enlaces de descarga de juegos,
paginando además las consultas a la base de datos para que la aplicación no cargue cientos
de registros de golpe. Se añade una suite de tests de integración que **mida el nivel de
seguridad** de forma automatizada.

Marco de referencia: OWASP Top 10 (2021). El estado actual ya cubre varias categorías
(SQLi con PDO preparado, XSS con CSP + escapado, CSRF en todo POST, rate limit de login,
firma HMAC de enlaces); este plan cubre las brechas restantes con prioridad en:

1. Acceso a cuenta de administrador (A07, A09, A01).
2. Acceso directo a enlaces de juegos (A02, A10, A01).
3. Rendimiento de consultas (paginación real de todos los listados).
4. Verificación automatizada del nivel de seguridad.

---

## 2. Seguridad — acceso administrador

- [x] **2.1 Logging y alertas de autenticación (A09)** — registrar en un log (archivo
      rotado, ruta en `RATE_LIMIT`/logs lista en `.env.example`): intentos fallidos de
      login, éxitos, cambios de rol/credenciales de administradores. Hoy no hay logging:
      no se detectaría un ataque de fuerza bruta. *Esfuerzo: medio.*
      → Implementado en `docs/2026-10-08-logging-autenticacion.md`.
- [ ] **2.2 Lockout por usuario + 2FA opcional (A07)** — hoy el rate limit es solo por IP
      (`AuthController.php:13`). Añadir bloqueo temporal por cuenta tras N fallos y,
      como fase opcional, TOTP (2FA) para el rol administrador. *Esfuerzo: medio-alto.*
- [ ] **2.3 Revisión IDOR / objeto directo (A01)** — auditar todos los `action` y endpoints
      que reciben `id` de la URL: comprobar que ninguna acción sirva datos o ejecute
      cambios validando solo el `id` sin verificar sesión y rol (`AuthMiddleware::requireAdmin()`).
      *Esfuerzo: bajo (auditoría).*
- [ ] **2.4 No mostrar errores en producción (A05)** — `docker-entrypoint.sh` no configura
      `display_errors` ni `error_reporting`. Forzar `display_errors=Off`,
      `error_reporting=E_ALL` (para logs) en la imagen Docker/YAML de Vercel
      (`php_value display_errors Off` en `.htaccess` aplicable). *Esfuerzo: bajo.*
- [ ] **2.5 Endurecer cookie JWT (`__Host-`)** — `JWTService::setTokenCookie()`
      (`src/config/JWTService.php:139`) ya usa `httpOnly`, `SameSite=Strict` y
      `Secure` condicional. Migrar al prefijo `__Host-` (exige `Secure` siempre + `Path=/`,
      ya `/`) para blindar contra fijación e inyección de cookies en subdominios. *Esfuerzo: bajo.*
- [ ] **2.6 Revisar credenciales por defecto** — confirmar que `admin/admin123` solo existe
      en `data/test_seeds.sql` (BD local de tests) y que producción usa credenciales
      rotadas y generadas en `.env`. *Esfuerzo: bajo (verificación).*

## 3. Seguridad — enlaces a juegos (proxy)

- [ ] **3.1 Acortar TTL de firma** — `SIGNED_URL_TTL=7200` (`endpoints/rom_proxy.php:114`)
      → 900 s configurable (mantener `SIGNED_URL_TTL` en `.env.example`). Reduce la ventana
      de replay de enlaces firmados. *Esfuerzo: bajo.*
- [ ] **3.2 Allowlist de hosts ante redirects (SSRF / A10)** — `GDRIVE_BASE` apunta a
      `drive.google.com` y el proxy rebota sobre el host actual sin allowlist
      (`rom_proxy.php:273-274`). Añadir validación explícita de `host` contra
      `drive.google.com`/`drive.usercontent.google.com` antes de seguir cualquier redirect.
      *Esfuerzo: medio.*
- [ ] **3.3 Validación opcional Referer/Origin** — rechazar peticiones al proxy sin
      `Origin`/`Referer` del propio sitio (aplicable con `ALLOWED_ORIGINS`). Cuidado con
      navegadores/descargadores que no envían header. *Esfuerzo: medio.*
- [ ] **3.4 Revocación de firmas** — opcional: permitir invalidar un `file_id` vía la
      siguiente rotación de `JWT_SECRET` (documentar impacto). *Esfuerzo: bajo (doc).*

## 4. Rendimiento — paginación de consultas a base de datos

Regla de negocio: **ningún listado de la UI debe traer todos los registros**; los únicos
`fetchAll()` sin `LIMIT` permitidos son dropdowns pequeños (consolas/categorías/emuladores
activos) y los exports de administrador.

- [ ] **4.1 Auditoría de `fetchAll()`/queries sin `LIMIT`** (`src/models/*.php`) — clasificar
      cada uno en *legítimo* (≤ decenas de filas) o *a paginar*. Pendientes claro: catálogo
      y dashboard ya pagan (LIMIT/OFFSET + COUNT). *Esfuerzo: bajo.*
- [ ] **4.2 Eliminar dead code de `Juego`** — `Juego::getWithRelations()` (sin `LIMIT`, sin
      usos en `src/`) y `Juego::getDownloadLink()` (construye URL directa de Google Drive
      **sin firma HMAC**, sin usos — anti-patrón si alguien lo reutiliza). *Esfuerzo: bajo.*
- [ ] **4.3 Paginar listados admin restantes** — verificar `Consola::all()` (LIMIT ✓),
      `Categoria::all()` (LIMIT ✓) y cualquier lista de juegos de dashboard/búsqueda;
      aplicar LIMIT/OFFSET + COUNT cuando corresponda. *Esfuerzo: medio.*
- [ ] **4.4 Keyset pagination (fase futura)** — para un catálogo grande, sustituir
      OFFSET por keyset (`WHERE j.id < :cursor ORDER BY id DESC LIMIT n`) e índices
      cubrientes. *Esfuerzo: medio-alto.*

## 5. Tests de integración que miden el nivel de seguridad

Nueva suite **`Security`** en `phpunit.xml` (además de Unit e Integration). Casos mínimos:

- [ ] **5.1 Accesos sin sesión** — ruta admin y `ajax_admin.php`/`ajax_consola.php` etc.
      sin sesión → 302/403.
- [ ] **5.2 CSRF** — POST sin token (`Server::csrfToken()`) → 403.
- [ ] **5.3 Rate limit de login** — N intentos → 429.
- [ ] **5.4 Firma de enlaces** — firma ausente/válida/vencida/tamper → rechazo
      (400/403/`expired`).
- [ ] **5.5 `/src/*` no servible** — HTTP → 403 (rutas internas nunca expuestas).
- [ ] **5.6 Método no permitido / validación** — respuestas JSON coherentes.
- [ ] **5.7 Intento de inyección SQL** — parámetro malicioso no altera resultados
      (helper sobre PDO preparado).
- [ ] **5.8 Cabeceras de seguridad** — presencia de CSP, `X-Content-Type-Options`,
      `X-Frame-Options`, `Referrer-Policy`.
- [ ] **5.9 Host allowlist del proxy** — mock de redirect a host no permitido → bloqueo.

**Política de credenciales (decisión):** los tests permanecen **tracked** en el repositorio
(AGENTS.md exige `composer test`); lo que se blinda son los secretos:

- El seed `admin/admin123` queda **solo** para la BD local de tests (`roms-vault-test`).
- `TEST_DB_PASSWORD`, `JWT_SECRET`, etc. se inyectan por entorno; nunca se commitean
  credenciales reales (`.env*` ya en `.gitignore`).
- Se añade `.dockerignore` (tests/ no viajan al artefacto Docker aunque estén en git).

## 6. i18n — aplicación multidioma

- [ ] **6.1 Cargador de diccionarios** — `src/i18n/es.php`, `en.php` + función global
      `t('clave', params)` con placeholders (roadmap #219). *Esfuerzo: medio.*
- [ ] **6.2 Detección de idioma** — cookie + `Accept-Language`; atributo `lang` en HTML.
      (Decisión: cookie, no subdirectorio `/en/`, para no duplicar URLs; `hreflang` si algún
      día se quiere SEO multidioma.) *Esfuerzo: medio.*
- [ ] **6.3 Migrar UI por secciones** — header/home/show/play primero; admin y errors
      después; mensajes de controladores y errores JSON (CSRF/rate-limit/proxy).
      Los datos (nombres de juegos/consolas/categorías) se quedan sin traducir. *Esfuerzo: alto.*

## 7. Rutas — decisiones documentadas

- [ ] **7.1 No usar tabla de rutas en BD** — el routing por convención
      (`controller/action/id` en `index.php`) + regex es más seguro y legible; una tabla de
      rutas en BD añadiría query/caché y superficie de ejecución arbitraria (riesgo RCE).
      Se documenta como ADR. *Esfuerzo: bajo (doc).*
- [ ] **7.2 Slugs de contenido (fase futura)** — `juegos.slug`/`consolas.slug` para URLs
      limpias tipo `/juego/mario-bros` (dato del registro, no tabla de rutas). *Esfuerzo: medio.*

## 8. Otras mejoras pendientes (enlace externo)

- **Migrar imágenes a Cloudflare R2** → ver [`docs/2026-10-07-migrar-imagenes-a-cloudflare-r2.md`](./2026-10-07-migrar-imagenes-a-cloudflare-r2.md)
  (almacenamiento y entrega de portadas/capturas; estado propuesto).

## 9. Hallazgos adicionales detectados

- [ ] **9.1 Warning preexistente en `/home/show/1`** — `related_games.php` llama
      `Juego::getCatalogSeed()` que ejecuta `setcookie()` **después** de emitir HTML
      (`src/views/home/show.php:56`). Fix: calcular la seed en el controlador antes de
      renderizar (o `ob_start()`). No fue introducido por la reorganización. *Esfuerzo: bajo.*
- [ ] **9.2 `display_errors` sin configurar en Docker** (ver 2.4).
- [ ] **9.3 No existe `.dockerignore`** — crear excluyendo `tests/`, `scratch/`, `.env*`,
      `data/backup registros DB/`, `docs/` (los tests siguen en git, pero no salen en la
      imagen). *Esfuerzo: bajo.*
- [ ] **9.4 Cabeceras de seguridad** — ya presentes (`X-Content-Type-Options`,
      `X-Frame-Options`, `Referrer-Policy`, `Permissions-Policy`, CSP con excepción
      intencional `unsafe-eval`/`blob:` para EmulatorJS → **no apretar**). Añadir
      `Cache-Control: no-store` a páginas de admin si no existe. *Esfuerzo: bajo.*
- [ ] **9.5 `composer audit`** — `composer.lock` está trackeado ✓; añadir `composer audit`
      como paso previo al commit/deploy y dependabot/GitHub Actions (FASE 2 del roadmap). *Esfuerzo: bajo.*

## 10. Priorización

| # | Mejora | Prioridad | Esfuerzo | OWASP / Roadmap |
|---|--------|-----------|----------|-----------------|
| 2.1 | Logging de autenticación | Alta | Medio | A09 |
| 2.2 | Lockout + 2FA admin | Alta | Medio-alto | A07 |
| 2.4 / 9.2 | `display_errors=Off` en prod | **Crítica** | Bajo | A05 |
| 2.5 | Cookie `__Host-` | Media | Bajo | A02/A01 |
| 2.6 | Sin credenciales por defecto | **Crítica** | Bajo | A07 |
| 3.1 | TTL 7200 → 900 s | Alta | Bajo | A02 |
| 3.2 | Allowlist redirects Drive | Alta | Medio | A10 |
| 4.1-4.3 | Paginación real de DB | Alta | Medio | Rendimiento |
| 4.2 | Dead code `Juego` sin LIMIT/sin firma | Alta | Bajo | A02/A03 |
| 5 | Suite de tests `Security` | Alta | Alto | CI/OWASP |
| 6 | i18n | Media | Alto | #219 |
| 7 | ADR rutas + slugs | Baja | Bajo | SEO |

## 11. Registro de progreso

| Fecha | Mejora | Estado | Commit |
|-------|--------|--------|--------|
| 2026-10-08 | 2.1 Logging y alertas de autenticación (A09) | Implementada | `a3be845` |
| 2026-10-08 | 2.1 Ampliación: auditoría en BD (`public.auditoria`) + fallback archivo | Implementada | `fd7c2cf` |

## 12. Verificación pendiente en despliegue — IP real del cliente tras proxy/Vercel

**Origen:** revisión del ítem 2.1 (auditoría) — 2026-10-08.

### Contexto

La IP se captura en `public.auditoria.ip` (y en `contexto.ip`). El cálculo está en
`RateLimiter::clientIp()`:

- Confía en `REMOTE_ADDR` (IP del peer TCP que Apache ve).
- Solo lee `X-Forwarded-For` / `X-Real-IP` cuando `REMOTE_ADDR` es un proxy confiable
  (`esIpProxyConfiable()`: rangos `127.0.0.0/8`, `::1`, `10.0.0.0/8`, `172.16.0.0/12`,
  `192.168.0.0/16`).

En local (`php -S localhost:8000`) la IP registrada es `::1`/`127.0.0.1` — correcto (no hay proxy).

### Supuesto a confirmar en el primer despliegue real (Vercel)

El contenedor Apache corre **detrás del edge de Vercel** (`Dockerfile` + `vercel.json` con
`"rewrites": []`). La cadena esperada es:

```
Cliente ──► Vercel Edge ──► Apache (contenedor)
            X-Forwarded-For: <IP pública real del cliente>
```

Se asume que el `REMOTE_ADDR` que ve Apache será una IP de la red interna de Vercel
(rango privado RFC1918), por lo que `esIpProxyConfiable()` devolverá `true` y
`clientIp()` retornará la IP pública real del cliente.

**Si el hosting entrega `REMOTE_ADDR` como IP pública** (no reconocida como proxy),
`clientIp()` no leería `X-Forwarded-For` y auditaría la IP del edge en lugar de la del
visitante. Eso no bloquea el login (solo degrada el dato de auditoría), pero hay que
detectarlo en el primer deploy.

### Acción planificada (mitigación)

Reemplazar el heurístico `esIpProxyConfiable()` por una **allowlist explícita de proxies
de confianza** vía `.env` (`TRUSTED_PROXIES`, host/redes de Vercel): más seguro y
predecible (evita que un cliente que conecte desde una IP privada por error influya en la
decisión, y blinda el endpoint ante falsos `X-Forwarded-For`). Complementa el ítem 3.2
(allowlist de hosts del proxy / anti-SSRF) y se resolverá junto a él.

### Check en producción (FASE 2 / CI-CD)

1. Hacer un login de prueba real en el dominio desplegado.
2. Consultar `SELECT ip, evento, created_at FROM public.auditoria ORDER BY id DESC LIMIT 5;`
   en la BD de producción (Neon).
3. Verificar que la `ip` es la IP pública real del cliente (no la del edge/proxy ni `::1`).
4. Si aparece la IP del proxy → activar `TRUSTED_PROXIES` en `.env` y re-verificar.

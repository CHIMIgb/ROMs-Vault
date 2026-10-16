# Drive privado con service account (acceso solo desde la app)

**Fecha:** 2026-10-14
**Estado:** en ejecución — Fases 1-4 completas (Fase 1 `9b2d3c9`, Fase 2 `2702048`,
Fase 3 `afe7baa`, Fase 4 en el commit que añade esta nota); falta la Fase 5 (alta de
la cuenta de servicio, compartir la carpeta y verificar end-to-end).
**Ámbito:** streaming de ROMs / acceso a los archivos de Google Drive
**Ruta elegida:** Opción 1 (mantener Drive, blindar el proxy)
**Relacionados:** `endpoints/rom_proxy.php`, `src/config/UrlSigner.php`,
`src/config/GDriveAllowlist.php`, `src/controllers/HomeController.php`,
`.env.example`, `docker-entrypoint.sh`

---

## 1. Resumen del cambio

Hoy **todos los archivos de Drive son públicos** ("cualquiera con el enlace"):
basta conocer el `file_id` para descargar la ROM sin pasar por la web. Eso
permite hotlinking y filtraciones, y deja la biblioteca accesible a cualquiera.

Objetivo: que los archivos **dejen de ser públicos** y que la única vía de
acceso sea el proxy de la web. Se consigue con:

1. **Privatizar los archivos** en Drive (solo los ve el propietario y quien se
   los comparta explícitamente).
2. Compartirlos con una **cuenta de servicio (service account)** de Google.
3. Que `rom_proxy.php` se autentique contra la **Drive API** con esa cuenta
   (OAuth 2.0, flujo JWT-bearer) y sirva el contenido en streaming, con
   **Range Requests** para el emulador.

El visitante **no cambia**: sigue sin loguearse. La restricción la dan la
**firma HMAC** de las URLs (solo la web genera URLs válidas) y la cuenta de
servicio (solo el servidor puede leer los archivos).

> Un `API key` **no** sirve para esto: no controla el acceso a los archivos.
> La restricción real la da que los archivos son privados y solo la cuenta de
> servicio tiene permiso de lectura.

---

## 2. Contexto actual (cómo funciona hoy)

- `src/config/UrlSigner.php` genera URLs del proxy firmadas con HMAC:
  `/rom_proxy.php?file_id=...&t=...&sig=...`, con `sig = HMAC-SHA256(fileId|t,
  JWT_SECRET)` y vigencia `SIGNED_URL_TTL` (default 900 s).
- `endpoints/rom_proxy.php` valida firma, TTL, `OriginPolicy`, rate limit (con
  exención de Range) y que el `file_id` exista y esté activo en BD. Después
  **construye una URL pública de Drive** (`GDRIVE_BASE`, sigue redirects con
  anti-SSRF y resuelve el HTML de confirmación de archivos grandes) y hace
  streaming con soporte de `Range`.
- `HomeController::download()` **redirige (302) directamente a Drive**
  (`https://drive.google.com/uc?export=download&id=...`), sin pasar por el proxy.
- `HomeController::play()` usa `UrlSigner::proxyUrl()` y un HEAD de preflight
  (`checkProxyAccess`).

Hoy, la única barrera es la publicidad del archivo: cualquiera con el `file_id`
descarga directo.

---

## 3. Diseño propuesto (modo dual con selección explícita)

El proxy tendrá **dos modos**, elegidos con la variable de entorno `GDRIVE_MODE`
(**sin degradación automática**):

- **`api` (por defecto, producción):** pide el archivo a la Drive API
  `GET https://www.googleapis.com/drive/v3/files/{id}?alt=media&supportsAllDrives=true`
  con `Authorization: Bearer <token>`; soporta `Range` (206). Sin credenciales o
  si el token falla, responde **error claro** (503 JSON + `error_log`): nunca cae
  a la vía pública.
- **`public` (desarrollo / CI / rollback):** mantiene el comportamiento actual
  (`GDRIVE_BASE`, resolución de redirects, página de confirmación de ficheros
  grandes y caché de URL).

Por qué explícito y **no** automático: un fallback implícito convertiría un fallo
de la cuenta de servicio (clave rotada, Drive API deshabilitada, carpeta
descompartida) en un comportamiento degradado **silencioso**, que además
reintroduciría el bug de los ficheros >100 MB y serviría por la vía que se quiere
eliminar. Con el modo explícito, en producción un fallo **se ve**.

Ventajas del dual controlado: dev/CI funcionan sin credenciales
(`GDRIVE_MODE=public`) y el rollback es cambiar una variable y redeployar (el
`file_id` no cambia, no hay migración de datos).

> **Probar en producción sin privatizar** no requiere el modo público: la Drive
> API también lee archivos que siguen siendo públicos, así que se puede desplegar
> con `GDRIVE_MODE=api`, verificar 200/206 y después restringir el acceso.

---

## 4. Autenticación (JWT-bearer de la cuenta de servicio)

Con `firebase/php-jwt` (ya presente en `composer.json`):

1. Se lee el JSON de la cuenta de servicio (`client_email`, `private_key`).
2. Se construye una **aserción JWT** (RS256) con:
   - header `{ "alg": "RS256", "typ": "JWT" }`
   - claims `{ iss: client_email, scope: https://www.googleapis.com/auth/drive.readonly,
     aud: https://oauth2.googleapis.com/token, iat: now, exp: now+3600 }`
3. Se canjea en `POST https://oauth2.googleapis.com/token`
   (`grant_type=urn:ietf:params:oauth:grant-type:jwt-bearer&assertion=<jwt>`).
4. Se obtiene un `access_token` (~3600 s) y se **cachea en disco**
   (`sys_get_temp_dir()`, igual que la caché del proxy), con refresco anticipado.

La `private_key` nunca sale del servidor. Scope mínimo: `drive.readonly`.

---

## 5. Archivos afectados

```
[CREAR] src/config/GoogleServiceAccount.php
  - isConfigured(): bool
  - accessToken(): ?string            (con caché en disco y refresco)
  - carga credenciales de GOOGLE_SERVICE_ACCOUNT_FILE (ruta) o
    GOOGLE_SERVICE_ACCOUNT_JSON_B64 (base64)
  - firma la aserción RS256 y la canjea en oauth2

[CREAR] src/config/GDriveClient.php
  - mode(): string                    (GDRIVE_MODE: 'api' por defecto | 'public')
  - enabled(): bool                   (mode() === 'api' y hay credenciales)
  - mediaUrl(string $fileId): string  (.../drive/v3/files/{id}?alt=media&supportsAllDrives=true)
  - authHeaders(): array              ['Authorization: Bearer <token>']
  - centraliza "API vs público" para no dispersar la lógica en el proxy

[CREAR] tests/Unit/GoogleServiceAccountTest.php
  - construye la aserción RS256 con un par de claves de prueba (sin red)
    y verifica iss/scope/aud/exp

[MODIFICAR] endpoints/rom_proxy.php
  - require de las dos clases nuevas
  - elegir upstream con GDriveClient::enabled() (GDRIVE_MODE + credenciales)
      -> Drive API (host www.googleapis.com) + Authorization: Bearer
      si no, con GDRIVE_MODE=public -> rama pública (GDRIVE_BASE + confirm token)
  - con GDRIVE_MODE=api y token no disponible: error 503 JSON (no fallback)
  - en modo API no se usa el resolvedor de redirects/confirm token; se sigue el
    302 con cURL sin reenviar el Bearer al host final (googleusercontent)
  - soporte de ?dl=1 -> Content-Disposition: attachment
  - mapear errores 401/403 de Google a los tipos actuales (private/quota/...)

[MODIFICAR] src/config/GDriveAllowlist.php
  - añadir 'www.googleapis.com' a HOSTS_DEFAULT
  - si el 302 final cae en otro *.googleusercontent.com, se añade vía
    GDRIVE_ALLOWED_HOSTS (env), sin tocar código

[MODIFICAR] src/controllers/HomeController.php
  - download(): dejar de redirigir a Drive. Redirige SIEMPRE al proxy firmado
    con ?dl=1 (opción A, ver §13): el proxy decide la vía según GDRIVE_MODE y
    responde 503 si está mal configurado (sin fallback silencioso)
  - el contador de descargas y el rate limit se mantienen ANTES del redirect

[MODIFICAR] src/config/UrlSigner.php
  - proxyUrl() con parámetro opcional $download -> añade ?dl=1 (la firma NO cambia)

[MODIFICAR] docker-entrypoint.sh
  - añadir al .env del contenedor GDRIVE_MODE, GOOGLE_SERVICE_ACCOUNT_FILE,
    GOOGLE_SERVICE_ACCOUNT_JSON_B64, GDRIVE_ALLOWED_HOSTS y SIGNED_URL_TTL
    (el resto de variables que el heredoc ya escribía, sin cambios)
  - aviso de arranque si GDRIVE_MODE=api y no hay credenciales (sin abortar)

[MODIFICAR] .env.example
  - GOOGLE_SERVICE_ACCOUNT_FILE= / GOOGLE_SERVICE_ACCOUNT_JSON_B64=
  - GDRIVE_MODE= (api por defecto; public para dev/CI/rollback)
  - nota del canal por el que el contenedor recibe estas claves

[MODIFICAR] .gitignore
  - ignorar la ruta local del secreto (p. ej. *.service-account.json)
```

---

## 6. Descarga local (HomeController::download)

Hoy: `302 -> https://drive.google.com/uc?export=download&id=...` (público). Con
archivos privados ese redirect **deja de funcionar**, así que:

- Firmar (ya se hace) y redirigir al **proxy** con `?dl=1`.
- `rom_proxy.php` con `dl=1` emite `Content-Disposition: attachment`,
  reutilizando toda la lógica de Drive (API, Range, errores).
- El `<a target="_blank" rel="noopener noreferrer">` no manda Referer, así que
  `OriginPolicy` lo permite (igual que hoy).

Resultado: una sola ruta de acceso a Drive (menos duplicación y menos superficie).

---

## 7. Configuración y secretos

| Variable | Para qué | Dónde |
|---|---|---|
| `GDRIVE_MODE` | `api` (defecto) o `public`; elige la vía de acceso | `.env` local / Environment Variables (Vercel/Docker) |
| `GOOGLE_SERVICE_ACCOUNT_FILE` | ruta al JSON (dev local) | `.env` local / Environment Variables (Docker, opcional) |
| `GOOGLE_SERVICE_ACCOUNT_JSON_B64` | JSON en base64 (prod) | Vercel + `docker-entrypoint.sh` |
| `SIGNED_URL_TTL` | vigencia de las firmas del proxy (default 900 s) | `.env` local / Environment Variables (Docker) |
| `GDRIVE_ALLOWED_HOSTS` | allowlist anti-SSRF del proxy (vacío = defaults) | `.env` local / Environment Variables (Docker) |

- **Canal en el contenedor (Fase 4):** `docker-entrypoint.sh` escribe el `.env` de
  `/var/www/html` en el arranque; las cinco variables de la tabla se copian del
  entorno de la plataforma (`GDRIVE_MODE` con default `api`, `SIGNED_URL_TTL` con
  900 s). Sin `PassEnv`/`SetEnv` en Apache: **si una variable no está en ese
  heredoc, PHP no la ve**. Variables sin definir quedan escritas como vacías
  (el código aplica entonces su valor por defecto).

- **Base64** en producción: el JSON tiene saltos de línea y comillas que rompen
  el formato `.env`/heredoc; base64 es seguro y se decodifica en PHP.
- El JSON es un **secreto crítico** (lectura de todo lo compartido con la
  cuenta): nunca al repo, nunca en logs.
- **Sin dependencias nuevas**: `firebase/php-jwt` ya está.

---

## 8. Pasos del usuario (una sola vez, en Google)

> Detalle ampliado, con enlaces y notas, en **§14**.

1. Crear/elegir un proyecto en Google Cloud Console.
2. Habilitar la **Google Drive API**.
3. Crear una **cuenta de servicio**.
4. Crear una **clave JSON** para esa cuenta y descargarla.
5. Compartir la **carpeta de ROMs** con el email de la cuenta de servicio
   (`...@....iam.gserviceaccount.com`), permiso **Lector**.
6. (Tras verificar) quitar "cualquiera con el enlace" de los archivos.

---

## 9. Orden de ejecución

1. `GoogleServiceAccount` + `GDriveClient` (con `GDRIVE_MODE`) + test unitario.
2. `rom_proxy.php` (modo dual + `GDRIVE_MODE`) + `GDriveAllowlist`.
3. `HomeController::download()` + `UrlSigner`.
4. `.env.example` + `docker-entrypoint.sh` + `.gitignore`.
5. Poner el secreto en Vercel y **probar sin privatizar** (archivos públicos).
6. Compartir con la cuenta de servicio y **quitar el acceso público**.
7. Verificar descarga + emulador (Range).
8. Actualizar docs/roadmap.

---

## 10. Impacto y compatibilidad

- URLs públicas: **intactas** (`/rom_proxy.php`, `/home/download`); se mantiene
  `vercel.json` con `"rewrites": []`.
- CSP: sin cambios.
- BD: sin cambios de esquema (el `file_id` sigue igual).
- Local/dev: sin cuenta de servicio sigue funcionando con archivos públicos.

---

## 11. Verificación

- Unit: `GoogleServiceAccountTest` (firma RS256, sin red).
- Manual: `curl -H "Range: bytes=0-1023" ...` -> `206` + `Content-Range`.
- Emulador: carga y seek; descarga con `Content-Disposition: attachment`.
- Con el archivo privado y compartido a la cuenta de servicio: 200/206.
- Con el archivo privado y NO compartido: error controlado (no 200).
- `php -l` en todos los archivos tocados; `composer test` en verde.

---

## 12. Riesgos, rollback y notas

- **Rollback:** poner `GDRIVE_MODE=public` y redeployar (rama pública intacta); o,
  si algo falla tras privatizar, volver a compartir como público temporalmente.
- **Token expirado** sin refresco: mitigado con caché y refresco anticipado.
- **Host final distinto:** si el 302 cae en otro `*.googleusercontent.com`, se
  añade vía `GDRIVE_ALLOWED_HOSTS` (env), sin recompilar.
- **Nota sobre la sesión JWT:** hoy la sesión es solo del admin; los visitantes
  juegan sin login. Por eso la restricción correcta es HMAC (URL firmada) +
  archivos privados, **sin exigir sesión**. No habrá login de usuarios, así que
  esta fase queda descartada.

---

## 13. Decisiones finales (2026-10-15)

- **Los usuarios NO crean cuenta ni inician sesión.** La cuenta de servicio es
  *server-side* y el visitante juega/descarga igual que hoy. La barrera sigue
  siendo la **firma HMAC** de la URL + los **archivos privados** (solo la cuenta
  de servicio los lee). La cuenta de servicio es una identidad máquina-a-máquina
  (`...@....iam.gserviceaccount.com`), invisible para el público.
- **Modo dual con selección explícita (`GDRIVE_MODE`)** confirmado: `api` por
  defecto (producción) y `public` para dev/CI/rollback. **Sin degradación
  automática**: con `api` y sin credenciales o con token caído, el proxy responde
  error claro (503 JSON + `error_log`) en lugar de caer a la vía pública. Se
  descarta el fallback implícito porque enmascararía fallos de la cuenta de
  servicio y podría servir por la vía que se quiere eliminar.
- **Ubicación del secreto**: `GOOGLE_SERVICE_ACCOUNT_FILE` apuntando a
  `./.secrets/service-account.json` en local (ruta **gitignoreada**); en
  producción, `GOOGLE_SERVICE_ACCOUNT_JSON_B64` (base64) como variable de
  entorno. El JSON **no** se mete en `.env`.
- **Deuda técnica reconocida (aceptada)**: el bug del HTML de confirmación de
  archivos >100 MB en la rama *pública* (fallback) **no** se arregla en este
  plan. En modo API no se usa: la Drive API autenticada sirve el binario directo
  (sin la página de virus scan de Google). Queda anotado como deuda.
  - Reproducido el 2026-10-16 en la verificación de la Fase 2: la rama pública
    sirvió **200 con 2510 bytes** (el HTML de confirmación) para un fichero >100 MB.
- **`302` de la Drive API**: se sigue **a mano** (`CURLOPT_FOLLOWLOCATION` off),
  sin reenviar la cabecera `Authorization` al CDN de destino, y se valida el host
  contra `GDriveAllowlist` (anti-SSRF). Se descarta `FOLLOWLOCATION` porque
  filtraría el `Bearer` a otro host y esquivaría la allowlist.
- **Reintento único en 401**: ante un token caducado/revocado se descarta la
  caché (`GoogleServiceAccount::clearCache()`) y se reintenta una vez con uno
  fresco. Se implementa en la resolución del upstream (donde se envía el Bearer),
  no en el stream (que en modo API va al CDN sin credenciales).
- **Claves i18n nuevas** en los 12 idiomas: `proxy.gdrive_auth` (401 de Drive) y
  `proxy.modo_sin_credenciales` (503 de configuración en modo API).
- **Descarga local = siempre por el proxy (opción A, 2026-10-16)**: en vez de
  mantener dos rutas (proxy en `api` y redirect directo a Drive en `public`), se
  unifica: `HomeController::download()` redirige siempre al proxy firmado con
  `&dl=1`. Se descarta la rama "en modo público mantener el redirect legacy"
  porque duplicaba la ruta de acceso a Drive, exponía la URL directa en el HTML
  y el bug >100 MB afecta igual al redirect legacy. Con `api` mal configurado el
  usuario ve un 503 claro (fail-loud), no un fallback silencioso.
- **`dl` fuera de la firma**: `?dl=1` no entra en el HMAC (`file_id|t`), así que
  no rompe enlaces ya emitidos; solo cambia `Content-Disposition`. Añadirlo a la
  firma obligaría a invalidar todas las URLs vivas sin ganancia de seguridad
  (una URL de streaming válida ya permite descargar el binario).
- **Preflight `HEAD` sin GET a Drive (2026-10-16)**: `rom_proxy.php` usa
  `CURLOPT_NOBODY` en el upstream cuando el cliente pide `HEAD`
  (`HomeController::checkProxyAccess()` en cada carga de `/home/play/{id}`). Antes
  abría un GET completo a Drive que había que cortar al desconectar el cliente.

---

## 14. Cómo obtener las credenciales (paso a paso)

> Se hace **una sola vez**. Requiere una cuenta de Google con acceso a la carpeta
> donde están las ROMs (la misma con la que se subieron).

1. **Crear/elegir proyecto** en https://console.cloud.google.com/ →
   selector de proyecto (barra superior) → **Nuevo proyecto** → nombre
   (p. ej. `roms-vault-drive`) → **Crear**. Esperar y seleccionarlo.
2. **Habilitar la Google Drive API**: menú ☰ → **APIs y servicios** →
   **Biblioteca** → buscar **Google Drive API** (la de "Google Drive", **no** la
   de *Activity*) → **Habilitar**. Sin esto, las llamadas devuelven 403
   `accessNotConfigured`.
3. **Crear la cuenta de servicio**: **APIs y servicios** → **Credenciales** →
   **Crear credenciales** → **Cuenta de servicio**.
   - Nombre: `roms-vault-proxy` (el ID se autogenera).
   - Roles de GCP: **ninguno** (no hace falta; el acceso es por Drive, no por IAM).
   - **Listo**.
4. **Crear la clave JSON**: en **Credenciales** → sección **Cuentas de servicio**
   → clic en la cuenta creada → pestaña **Claves** → **Agregar clave** →
   **Crear clave nueva** → **JSON** → **Crear**. Se descarga un `.json`.
5. **Guardar el JSON** (nunca al repo): colocarlo en
   `.secrets/service-account.json`. En producción, subir su contenido en base64:
   `base64 -w0 service-account.json` → variable `GOOGLE_SERVICE_ACCOUNT_JSON_B64`.
6. **Copiar el email** de la cuenta: `client_email` dentro del JSON, tipo
   `roms-vault-proxy@PROYECTO.iam.gserviceaccount.com`.
7. **Compartir la carpeta de ROMs** con ese email: en Google Drive → carpeta de
   ROMs → **Compartir** → pegar el email → permiso **Lector** → **Enviar**
   (se puede desmarcar "notificar"). Los archivos heredan el permiso.
8. **Probar sin privatizar** (archivos aún públicos): definir
   `GOOGLE_SERVICE_ACCOUNT_FILE` y verificar 200/206 por el proxy.
9. **Quitar el acceso público**: cuando el paso 8 funcione, seleccionar los
   archivos/carpeta → **Compartir** → **Acceso general** → **Restringido**.

Notas:
- Si la cuenta es de empresa (Workspace) y bloquea compartir con externos, hay
  que permitirlo a nivel de organización o usar una cuenta personal.
- En un proyecto nuevo la **Google Drive API hay que habilitarla** (paso 2) o las
  llamadas fallan con 403 `accessNotConfigured`.

---

## 15. Fases de implementación (código)

> **Base de partida:** `ead9d4c` (== `origin/main`) en la rama
> `fix/descarga-drirecta-drive`. El intento previo de servir la descarga por el
> proxy con `dl=1` (commits `51db8fb` + `2654b59`) se **revirtió y eliminó sin
> rastro** (rama vieja `fix/descarga-directa-proxy` borrada, reflog expirado y
> objetos purgados). Ese comportamiento **se re-implementa aquí dentro** (Fase 3),
> no se recupera del historial.

- **Fase 1 — Auth/API (offline) — HECHA (commit `9b2d3c9`):**
  `src/config/GoogleServiceAccount.php` (aserción RS256 + canje en `oauth2` +
  caché de token en `sys_get_temp_dir()` con refresco anticipado),
  `src/config/GDriveClient.php` (`enabled()`, `mediaUrl()`, `authHeaders()`),
  `www.googleapis.com` añadido a `GDriveAllowlist::HOSTS_DEFAULT`, `require_once`
  de ambas clases en `tests/bootstrap.php` y `tests/Unit/GoogleServiceAccountTest.php`.
  - **Nota de entorno:** el PHP de XAMPP/Windows **no** puede ejecutar
    `openssl_pkey_new()` (falta `openssl.cnf`; `OPENSSL_CONF` se lee al arrancar,
    así que `putenv()` no sirve). El test usa un par RSA 2048 fijo *de usar y
    tirar*, guardado en base64 en `tests/Unit/Support/rsa_test_{private,public}.pem.b64`
    (sin literales `-----BEGIN ... KEY-----` en el repo). `openssl_sign()` y
    `openssl_verify()` sí funcionan sin `openssl.cnf`.
  - **Nota php-jwt v7:** `JWT::decode()` no rellena el `$headers` de salida; el
    `alg` se lee del primer segmento (base64url) del propio JWT.
- **Fase 2 — Proxy modo dual con `GDRIVE_MODE` — HECHA (commit `2702048`):**
  - `src/config/GDriveClient.php`: `mode(): string` (`GDRIVE_MODE`, `api` por
    defecto; solo `public` activa el flujo público) y `enabled()` redefinido como
    `mode() === 'api' && credenciales`.
  - `endpoints/rom_proxy.php`: bifurcación explícita API/público. **Sin
    degradación automática**: con `GDRIVE_MODE=api` sin credenciales (o sin
    token) → 503 JSON `error_type=config` (`proxy.modo_sin_credenciales`).
    Se mantienen Range (206), reenvío de `Content-Length/Type/Range`, rate limit
    con exención de Range y `?dl=1` → `Content-Disposition: attachment`.
  - `resolveDriveApiMediaUrl()` (nueva): pide el binario a la Drive API y sigue
    el `302` **a mano** (`FOLLOWLOCATION` off) para no reenviar el `Bearer` al
    CDN y validar el host destino contra `GDriveAllowlist` (anti-SSRF). Solo lee
    cabeceras (aborta al primer byte de cuerpo). **Reintento único en 401**:
    `GoogleServiceAccount::clearCache()` + token fresco (el reintento se hace en
    la resolución del upstream, no en el stream, porque en modo API el stream va
    al CDN sin Bearer).
  - Mapeo de errores: 401 → `proxy.gdrive_auth` (`error_type=auth`), 403 →
    `proxy.gdrive_bloqueado`, 404 → `proxy.gdrive_no_existe`, 429 →
    `proxy.gdrive_cuota`; nunca se sirve el HTML de Google.
  - Limpieza: `guessContentType()` (código muerto) **eliminado**; `URL_CACHE_TTL`
    movido al bloque de configuración (la caché de URL solo aplica al modo público).
  - i18n: claves nuevas `proxy.gdrive_auth` y `proxy.modo_sin_credenciales` en los
    12 diccionarios (paridad mantenida).
- **Fase 3 — Descarga por el proxy — HECHA (commit `afe7baa`):** `UrlSigner::proxyUrl($fileId, $download)`
  (añade `&dl=1` cuando corresponde, sin cambiar el formato de firma `sig`) y
  `HomeController::download()` → 302 al proxy firmado con `dl=1` (antes hacía 302
  directo a Drive, que con archivos privados deja de funcionar). El contador de
  descargas y el rate limit se mantienen antes del redirect. Incluye el preflight
  `HEAD` con `CURLOPT_NOBODY` en `rom_proxy.php`.
- **Fase 4 — Config/secretos — HECHA:** `.env.example` (documentadas las claves de
  Drive y, tras esta tarea, el canal por el que llegan al contenedor), `.gitignore`
  (ignora `.secrets/`) y **`docker-entrypoint.sh`**: el contenedor genera su propio
  `.env` con un heredoc, así que **toda variable que no esté ahí es invisible para
  PHP en producción** (Apache no expone el entorno y no hay `PassEnv`/`SetEnv`).
  Añadidas al heredoc (con los mismos valores por defecto que el código):
  `GDRIVE_MODE` (`api`), `GOOGLE_SERVICE_ACCOUNT_FILE` (vacío),
  `GOOGLE_SERVICE_ACCOUNT_JSON_B64` (vacío), `GDRIVE_ALLOWED_HOSTS` (vacío = defaults)
  y `SIGNED_URL_TTL` (**900 s**, igual que `UrlSigner::ttl()`; se usa 900 y no 2 h
  para que un despliegue con la variable sin definir no estire la firma).
  Aviso informativo en el arranque (no aborta y **nunca imprime el secreto**) si
  `GDRIVE_MODE=api` y no hay credenciales: evita que el 503 quede oculto.
  El resto de variables que el código lee y el heredoc no escribe
  (`ENABLE_EMULATOR_THREADS`, `DOWNLOAD_RATE_LIMIT_*`, `AUTH_*`, `TFA_*`,
  `DB_SSLMODE`, `I18N_LANG`, `PRELOAD_MAX_BYTES`) queda **fuera de alcance**, en
  una tarea aparte.
  - **Arreglo PSR-12 (necesario para el CI):** el job `static` ejecuta `phpcs`
    (PSR-12) sobre los PHP **añadidos** en la rama
    (`git diff --diff-filter=A`), y `GDriveClient.php` /
    `GoogleServiceAccount.php` incumplían dos sniffs (`blank line` tras `<?php`,
    `OpeningBraceSpace` tras `class ... {`) más una línea de 132 caracteres.
    Corregidos los tres. Los archivos antiguos del repo también incumplen esos
    sniffs, pero quedan fuera del gate (no son "añadidos").
- **Fase 5 — Verificación y docs:** smoke manual (con y sin carpeta compartida),
  emulador (Range/seek), doc fechado de implementación + registro en el roadmap.

Sin dependencias nuevas (`firebase/php-jwt` ya está). Sin cambios de esquema de
BD, CSP ni URLs públicas.

---

## 16. Estado

**Fase 1 — completa (commit `9b2d3c9`):**
- [x] `src/config/GoogleServiceAccount.php`.
- [x] `src/config/GDriveClient.php`.
- [x] `www.googleapis.com` en `src/config/GDriveAllowlist.php`.
- [x] `require_once` en `tests/bootstrap.php`.
- [x] `tests/Unit/GoogleServiceAccountTest.php` (8 tests) + fixtures base64 en
      `tests/Unit/Support/`.
- **Verificación (evidencia):** `phpunit --testsuite Unit` → OK (168 tests,
  14587 assertions); `php -l` OK en los archivos tocados.

**Fase 2 — completa (commit `2702048`):**
- [x] `GDriveClient::mode()` + `enabled()` mode-aware.
- [x] `rom_proxy.php`: bifurcación API/público, 503 de configuración, `?dl=1`,
      302 manual sin Bearer, reintento único en 401, mapeo 401.
- [x] `guessContentType()` eliminado; `URL_CACHE_TTL` reubicado.
- [x] `tests/Unit/GDriveClientTest.php` (9 tests, offline).
- [x] `.env.example` → `GDRIVE_MODE` (comentario del modo corregido).
- [x] i18n: `proxy.gdrive_auth` + `proxy.modo_sin_credenciales` (12 idiomas).
- **Verificación (evidencia):**
  - `php -l` OK en `rom_proxy.php`, `GDriveClient.php` y `GDriveClientTest.php`.
  - `phpunit --testsuite Unit` → OK (177 tests, 14653 assertions).
  - `phpunit --testsuite Security` → OK (17 tests): el endpoint arranca y sigue
    rechazando firma ausente/tamper/vencida e id inexistente.
  - Smoke con el arnés de integración (temporal, borrado después):
    - `GDRIVE_MODE` ausente → `503` `{"error_type":"config"}` para un file_id activo.
    - `GDRIVE_MODE=public` → 502 `not_found` (rama pública operativa, sin 503).
    - ROM real pública con `Range: bytes=0-1023`: `dl=1` → `Content-Disposition:
      attachment`, sin `dl=1` → `inline` (ambas 200 con la página de confirmación
      (2510 B) del bug conocido de la rama pública, deuda aceptada).

**Fase 3 — completa:**
- [x] `UrlSigner::proxyUrl($fileId, bool $download = false)` → `&dl=1` (firma intacta).
- [x] `HomeController::download()` → 302 al proxy firmado con `dl=1` (contador y
      rate limit antes del redirect); ya no se expone `drive.google.com`.
- [x] Preflight `HEAD` en `rom_proxy.php` con `CURLOPT_NOBODY` en el upstream.
- [x] `tests/Unit/UrlSignerTest.php`: 1 test nuevo (dl sin cambiar la firma).
- [x] `tests/Integration/DownloadRedirectTest.php`: 3 tests (302 + `dl=1` + contador,
      403 sin firma/tamper, 404 file_id desconocido).
- **Verificación (evidencia):**
  - `php -l` OK en los 5 archivos tocados.
  - `phpunit --testsuite Unit` → OK (178 tests, 14658 assertions).
  - `phpunit --testsuite Integration` → OK (102 tests, 346 assertions).
  - `phpunit --testsuite Security` → OK (17 tests, 49 assertions).
  - Smoke con socket crudo contra `php -S` (temporal, borrado después):
    misma URL → `HEAD` 200 con **0 bytes** de cuerpo y `Content-Disposition:
    inline`; `GET` 200 con **2510 bytes** de cuerpo. Prueba que el preflight no
    abre el stream (modo público; el contenido de 2510 B es la deuda conocida).

**Fase 4 — completa (en el commit que añade esta nota):**
- [x] `.gitignore` → `.secrets/` (commit `9b2d3c9`).
- [x] `.env.example` → `GOOGLE_SERVICE_ACCOUNT_FILE` / `GOOGLE_SERVICE_ACCOUNT_JSON_B64`.
- [x] `.env.example` → `GDRIVE_MODE` (hecho en Fase 2).
- [x] `docker-entrypoint.sh` → `GDRIVE_MODE` (+default `api`),
      `GOOGLE_SERVICE_ACCOUNT_FILE`, `GOOGLE_SERVICE_ACCOUNT_JSON_B64`,
      `GDRIVE_ALLOWED_HOSTS` y `SIGNED_URL_TTL` (default 900 s) en el `.env` que
      genera el contenedor.
- [x] Aviso de arranque (no aborta, sin filtrar el secreto) si `GDRIVE_MODE=api`
      y no hay credenciales.
- [x] `.env.example` → nota de que el contenedor recibe esas claves vía
      `docker-entrypoint.sh`.
- [x] PSR-12 en los dos PHP añadidos por la rama (gate `phpcs` del CI).
- **Verificación (evidencia):**
  - `bash -n docker-entrypoint.sh` → OK.
  - Dry-run con el heredoc **extraído del propio archivo** (target a un `.env`
    temporal) y `GDRIVE_MODE`/`SIGNED_URL_TTL` sin definir: se generan
    `GDRIVE_MODE="api"`, `SIGNED_URL_TTL="900"`, `GDRIVE_ALLOWED_HOSTS="cdn.example.com"`,
    `GOOGLE_SERVICE_ACCOUNT_FILE=""` y el base64 **byte a byte idéntico** (68 B).
  - Bloque de aviso con los 4 casos: `api` sin credenciales → avisa; `api` con
    `..._JSON_B64`, `api` con `GOOGLE_SERVICE_ACCOUNT_FILE` y `public` sin
    credenciales → silencio. Las líneas `echo` del aviso son texto literal (no
    interpolan variables), así que no pueden filtrar el secreto.
  - Contraste de nombres: las 5 variables están exactamente 1 vez en el heredoc y
    son leídas por el código (`$_ENV['...']`) → sin erratas de nombre.
  - `phpcs` sobre los dos archivos añadidos → **0 errores, 0 avisos**;
    `phpstan analyse` (nivel 5 + baseline) → `[OK] No errors`; `php -l` OK en los
    5 archivos PHP tocados.
  - `phpunit` (Unit + Integration + Security) → **OK (297 tests, 15053 assertions)**.

**Pendiente:**
- [ ] **Fase 5** — Alta de la cuenta de servicio + compartir carpeta (usuario).
- [ ] **Fase 5** — Quitar acceso público y verificar (usuario).
- [ ] **Tarea aparte** — pasar al contenedor el resto de variables que el código ya
      lee (`ENABLE_EMULATOR_THREADS`, `DOWNLOAD_RATE_LIMIT_MAX/WINDOW`, `AUTH_*`,
  `TFA_*`, `DB_SSLMODE`, `I18N_LANG`, `PRELOAD_MAX_BYTES`).

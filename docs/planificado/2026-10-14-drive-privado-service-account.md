# Drive privado con service account (acceso solo desde la app)

**Fecha:** 2026-10-14
**Estado:** planificado (pendiente de aprobación)
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

## 3. Diseño propuesto (modo dual)

El proxy tendrá **dos modos**:

- **Modo API** (archivos privados): pide el archivo a la Drive API
  `GET https://www.googleapis.com/drive/v3/files/{id}?alt=media&supportsAllDrives=true`
  con `Authorization: Bearer <token>`; soporta `Range` (206).
- **Modo público** (fallback): si no hay cuenta de servicio, se mantiene el
  comportamiento actual (`GDRIVE_BASE`). Útil para desarrollo y como red de
  seguridad.

Ventaja: se puede **probar en producción sin privatizar todavía** y revertir sin
migrar datos (el `file_id` no cambia).

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
  - enabled(): bool                   (¿hay cuenta de servicio?)
  - mediaUrl(string $fileId): string  (.../drive/v3/files/{id}?alt=media&supportsAllDrives=true)
  - authHeaders(): array              ['Authorization: Bearer <token>']
  - centraliza "API vs público" para no dispersar la lógica en el proxy

[CREAR] tests/Unit/GoogleServiceAccountTest.php
  - construye la aserción RS256 con un par de claves de prueba (sin red)
    y verifica iss/scope/aud/exp

[MODIFICAR] endpoints/rom_proxy.php
  - require de las dos clases nuevas
  - elegir upstream: GDriveClient::enabled()
      -> Drive API (host www.googleapis.com) + Authorization: Bearer
      si no -> rama pública actual (GDRIVE_BASE + confirm token)
  - en modo API no se usa el resolvedor de redirects/confirm token; se sigue el
    302 con cURL sin reenviar el Bearer al host final (googleusercontent)
  - soporte de ?dl=1 -> Content-Disposition: attachment
  - mapear errores 401/403 de Google a los tipos actuales (private/quota/...)

[MODIFICAR] src/config/GDriveAllowlist.php
  - añadir 'www.googleapis.com' a HOSTS_DEFAULT
  - si el 302 final cae en otro *.googleusercontent.com, se añade vía
    GDRIVE_ALLOWED_HOSTS (env), sin tocar código

[MODIFICAR] src/controllers/HomeController.php
  - download(): dejar de redirigir a Drive; con cuenta de servicio, redirigir al
    proxy firmado con ?dl=1; sin ella, mantener el redirect público (modo dual)
  - el contador de descargas y el rate limit se mantienen ANTES del redirect

[MODIFICAR] src/config/UrlSigner.php
  - proxyUrl() con parámetro opcional para construir la URL con ?dl=1
    (la firma NO cambia)

[MODIFICAR] docker-entrypoint.sh
  - añadir GOOGLE_SERVICE_ACCOUNT_JSON_B64 (y GOOGLE_SERVICE_ACCOUNT_FILE) al
    .env del contenedor

[MODIFICAR] .env.example
  - GOOGLE_SERVICE_ACCOUNT_FILE= / GOOGLE_SERVICE_ACCOUNT_JSON_B64=

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
| `GOOGLE_SERVICE_ACCOUNT_FILE` | ruta al JSON (dev local) | `.env` local |
| `GOOGLE_SERVICE_ACCOUNT_JSON_B64` | JSON en base64 (prod) | Vercel + `docker-entrypoint.sh` |

- **Base64** en producción: el JSON tiene saltos de línea y comillas que rompen
  el formato `.env`/heredoc; base64 es seguro y se decodifica en PHP.
- El JSON es un **secreto crítico** (lectura de todo lo compartido con la
  cuenta): nunca al repo, nunca en logs.
- **Sin dependencias nuevas**: `firebase/php-jwt` ya está.

---

## 8. Pasos del usuario (una sola vez, en Google)

1. Crear/elegir un proyecto en Google Cloud Console.
2. Habilitar la **Google Drive API**.
3. Crear una **cuenta de servicio**.
4. Crear una **clave JSON** para esa cuenta y descargarla.
5. Compartir la **carpeta de ROMs** con el email de la cuenta de servicio
   (`...@....iam.gserviceaccount.com`), permiso **Lector**.
6. (Tras verificar) quitar "cualquiera con el enlace" de los archivos.

---

## 9. Orden de ejecución

1. `GoogleServiceAccount` + `GDriveClient` + test unitario.
2. `rom_proxy.php` (modo dual) + `GDriveAllowlist`.
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

- **Rollback:** si algo falla tras privatizar, volver a compartir como público
  temporalmente, o vaciar la configuración y volver al modo público.
- **Token expirado** sin refresco: mitigado con caché y refresco anticipado.
- **Host final distinto:** si el 302 cae en otro `*.googleusercontent.com`, se
  añade vía `GDRIVE_ALLOWED_HOSTS` (env), sin recompilar.
- **Nota sobre la sesión JWT:** hoy la sesión es solo del admin; los visitantes
  juegan sin login. Por eso la restricción correcta es HMAC (URL firmada) +
  archivos privados, **sin exigir sesión**. No habrá login de usuarios, así que
  esta fase queda descartada.

---

## 13. Estado

- [ ] `GoogleServiceAccount`.
- [ ] `GDriveClient`.
- [ ] `rom_proxy.php` (modo dual) + `www.googleapis.com` en la allowlist.
- [ ] `HomeController::download()` + `UrlSigner`.
- [ ] `.env.example` + `docker-entrypoint.sh` + `.gitignore`.
- [ ] Alta de la cuenta de servicio + carpeta compartida.
- [ ] Quitar acceso público y verificar.

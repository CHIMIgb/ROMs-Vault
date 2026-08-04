# Lote C2 Seguridad — IP real, descargas firmadas, CSP y defensa en profundidad (2026-08-03)

## Contexto

Tercer lote de la auditoría de backend (`docs/2026-08-02-auditoria-backend.md`),
centrado en los 5 hallazgos críticos/altos pendientes:

1. **`X-Forwarded-For` sin validar** en `RateLimiter::clientIp()` y
   `rom_proxy.php` → un atacante podía falsear esa cabecera y eludir el rate
   limiting (además de permitir llenar el disco con archivos de contadores).
2. **Sin cabeceras de seguridad** en las páginas HTML → clickjacking, MIME
   sniffing y fuga de `Referer` a terceros.
3. **`download()` sin firma ni rate limit** → descarga arbitraria y contador
   de descargas inflable sin pasar por el sitio.
4. **Sin `.htaccess`** en `public/uploads/` y `public/bios/` → si un archivo
   no-`.webp` se subiera por error, podría ejecutarse como script.
5. **`Model::create/update`** interpola los nombres de columna sin validar →
   superficie de inyección SQL latente si algún `$data` llega contaminado.

---

## 1. IP real del cliente (rate limiting confiable)

### `config/RateLimiter.php`

`clientIp()` se reescribe:

- La fuente primaria es **`REMOTE_ADDR`** (la IP del peer TCP que Apache/nginx ve).
- `X-Forwarded-For` / `X-Real-IP` **solo** se consideran cuando `REMOTE_ADDR`
  es una IP de red local (proxy/reverse-proxy típico: `127.0.0.0/8`, `::1`,
  `10.0.0.0/8`, `172.16.0.0/12`, `192.168.0.0/16`), comprobado por el nuevo
  método privado `esIpProxyConfiable()`.
- Toda IP se valida con `filter_var(FILTER_VALIDATE_IP)`; si la cabecera
  forwardeada no es una IP válida se ignora; si `REMOTE_ADDR` es inválido se
  usa el fallback compartido `0.0.0.0`.
- De una lista `X-Forwarded-For: ip1, ip2` se toma la primera IP.

**Motivo:** confiar en `X-Forwarded-For` de forma incondicional permitía que
cualquier cliente (no solo un proxy real) eligiera su IP de rate limit
(p.ej. `RateLimiter::check('1.2.3.4', ...)`), eludiendo el límite y creando un
archivo en disco por cada IP falsa.

### `rom_proxy.php`

- Se elimina la función local `checkRateLimit()` (duplicada de la clase) y el
  bloque de detección manual de `X-Forwarded-For`.
- Se usa directamente `RateLimiter::check(RateLimiter::clientIp(), ..., 'proxy')`
  con namespace `proxy` (misma clase que protege el login, contadores separados).

---

## 2. Cabeceras de seguridad globales + CSP

### `index.php` (todas las páginas HTML)

Se emiten antes de cualquier salida:

```
X-Content-Type-Options: nosniff
X-Frame-Options: SAMEORIGIN
Referrer-Policy: strict-origin-when-cross-origin
Permissions-Policy: camera=(), microphone=(), geolocation=()
Content-Security-Policy: ...
```

La **CSP** (versión final, tras las correcciones de integración con el emulador,
ver sección 6) está equilibrada con los orígenes reales que usa el sitio:

```
default-src 'self';
script-src 'self' 'unsafe-inline' 'unsafe-eval' blob: https://cdn.emulatorjs.org;
style-src 'self' 'unsafe-inline' https://fonts.googleapis.com https://cdn.emulatorjs.org;
font-src 'self' https://fonts.gstatic.com data: https://cdn.emulatorjs.org;
img-src 'self' data: blob: https://cdn.emulatorjs.org;
media-src 'self' blob: https://cdn.emulatorjs.org;
connect-src 'self' blob: https://cdn.emulatorjs.org;
worker-src 'self' blob: https://cdn.emulatorjs.org;
frame-ancestors 'self'; base-uri 'self'; form-action 'self'; object-src 'none'
```

Decisiones de diseño:

- `cdn.emulatorjs.org` permitido en `script/style/font/img/media/connect/worker-src`
  porque el emulador carga loader, estilos, fuentes, imágenes y cores desde el CDN.
- `blob:` permitido en `script/connect/img/media/worker-src` porque EmulatorJS
  descomprime el core y lo inyecta como Object URL (`blob:`) y porque `play.php`
  convierte la ROM precargada en `URL.createObjectURL(...)`.
- `'unsafe-inline'` en `script/style-src` porque el sitio usa scripts y estilos
  inline (IIFEs de vistas, atributos `style`).
- `'unsafe-eval'` en `script-src` porque el runtime de Emscripten (cores de
  EmulatorJS) usa `eval()`/`new Function()` internamente (`cwrap`,
  `createNamedFunction`). Sin él los cores no arrancan (ver sección 6).
- `frame-ancestors 'self'` + `X-Frame-Options: SAMEORIGIN` (redundante para
  navegadores antiguos) cierran el clickjacking.

Verificado: no hay OAuth externo, iframes ni `<form action="http...">` en las
vistas; todos los forms son `self` o sin `action` (POST a la URL actual), por
lo que `form-action 'self'` no rompe nada.

---

## 3. Descargas firmadas con HMAC + rate limit

### `config/UrlSigner.php` (nuevo)

Helper estático que centraliza el firmado HMAC con el mismo formato que ya
usaba `rom_proxy.php` (compatible de ida y vuelta):

```php
hash_hmac('sha256', $fileId . '|' . $timestamp, JWT_SECRET)
```

- `sign(string $fileId): array` → `['t' => timestamp, 'sig' => hash]`.
- `verify(string $fileId, int $timestamp, string $signature, int $ttl = 7200): bool`
  → `hash_equals()` + vigencia (2 horas, igual que el proxy; margen de reloj
  de ±5 min).
- `downloadUrl(string $fileId): string` → URL completa de `action=download`
  con `t` y `sig`.
- `proxyUrl(string $fileId): string` → URL completa de `rom_proxy.php` con
  `t` y `sig`.
- Secreto: `JWT_SECRET` (ya existente en `.env`); no se añade ninguna variable
  nueva.

### `controllers/HomeController.php`

- **`download()`**:
  1. Exige `file_id` + firma válida → si no, **403** con
     `views/errors/generic.php` (ya no usa `die()`).
  2. Rate limit por IP con namespace `download` (por defecto 30/60 s,
     configurable con `DOWNLOAD_RATE_LIMIT_MAX` / `DOWNLOAD_RATE_LIMIT_WINDOW`).
  3. Busca el juego → **404** con vista genérica si no existe.
  4. Solo entonces incrementa el contador y redirige a Google Drive.
- **`play()`**: genera `$downloadUrl = UrlSigner::downloadUrl($fileId)` y la
  pasa a la vista.
- **`signProxyUrl()`**: delega en `UrlSigner::proxyUrl()` (mismo formato de
  salida → `rom_proxy.php` y `checkProxyAccess()` siguen funcionando).

### Vistas

- `views/components/game_actions.php`: el botón Descargar usa
  `UrlSigner::downloadUrl(...)` (con `require_once` del helper para los 3
  contextos de inclusión: grid, ficha y AJAX).
- `views/home/play.php`: los dos enlaces HTML de descarga (error page y panel
  del emulador) usan `$downloadUrl`; el enlace del **error dinámico en JS**
  (modo precarga) usa una URL firmada inyectada desde PHP
  (`const signedDownloadUrl = '...'`) en vez de construir la URL a mano.
- `ajax_autocomplete.php`: `download_url` sale firmada del endpoint.
- `views/home/index.php`: el template del autocomplete escapa `play_url` y
  `download_url` con la función `escHtml()` existente (las URLs firmadas
  contienen `&`, que en un atributo HTML debe ir como `&amp;`).
- `views/home/play.php`: `EJS_language` pasa de `'es-419'` a `'es-ES'` porque
  la rama `stable` del CDN de EmulatorJS solo publica
  `localization/es-ES.json`; `es-419.json` daba 404 y la interfaz del emulador
  caía a inglés (ver sección 6).

**Efecto colateral buscado:** los enlaces de descarga copiados de antes de C2
dejan de funcionar (403) porque no llevan firma. Es intencional.

El flujo `controller=export&action=download` (dashboard admin, XLSX) es otro
controller y **no se toca**.

---

## 4. `.htaccess` de defensa en profundidad

### `public/uploads/.htaccess` (nuevo)

- `<FilesMatch "\.(php|phtml|php3|php4|php5|php7|php8|phar|cgi|pl|py|sh|asp|aspx|jsp)$">`
  → `Require all denied` (sintaxis Apache 2.4).
- Archivos sensibles/ocultos (`.htaccess`, `.htpasswd`, `.env`, `.log`,
  `.sql`, `.git*`) → denegados.
- `Options -Indexes`.

### `public/bios/.htaccess` (nuevo)

- Mismo patrón de bloqueo de scripts y archivos sensibles + `Options -Indexes`.
- **No** se bloquea la lectura de los `.bin`: `play.php` asigna
  `window.EJS_biosUrl` y EmulatorJS hace fetch directo desde el navegador a
  `public/bios/ps1/...` (misma conclusión que el borrador descartado en P0).

---

## 5. Validación de columnas en `Model`

### `models/Model.php`

- Nuevo método privado `sanitizeColumns(array $data): array`:
  - Solo acepta claves `is_string` que cumplan `^[a-zA-Z0-9_]+$`.
  - Descarta cualquier otra clave (imposible inyectar SQL vía nombre de columna).
  - Lanza `InvalidArgumentException` si no queda ninguna columna válida.
- `create()` y `update()` filtran `$data` con `sanitizeColumns()` **antes** de
  construir el SQL.

Defensa en profundidad: las columnas actuales del esquema cumplen el patrón
(titulo, consola_id, region, google_drive_file_id, …), por lo que no rompe
ningún flujo existente.

---

## 6. Corrección posterior: la CSP vs EmulatorJS (bloqueos en cadena)

La CSP inicial rompió la emulación online. EmulatorJS está compilado con
Emscripten, cuyo runtime exige una CSP muy permisiva, y **antes de C2 no
existía CSP** (por eso todo funcionaba). El arranque de un juego chocaba con
la política en 4 puntos, en orden, y cada fix despejaba el siguiente:

| # | Bloqueado por CSP | Error en consola | Fix (commit) |
| --- | --- | --- | --- |
| 1 | `emulator.min.css` en `style-src` | el loader no cargaba las hojas de estilo → el emulador no arrancaba visualmente | añadir `https://cdn.emulatorjs.org` a `style-src` (`bbf6503`) |
| 2 | `eval()`/`new Function()` en `script-src` | `EvalError: 'unsafe-eval' is not allowed` al iniciar el core | `'wasm-unsafe-eval'` → `'unsafe-eval'` (`f979bac`) |
| 3 | core descomprimido como `blob:` en `script-src` (`script-src-elem`) | se detiene al **99%** de la descompresión | añadir `blob:` a `script-src` (`1bd9bc7`) |
| 4 | fetch del WASM desde `blob:` en `connect-src` | `Aborted(both async and sync fetching of the wasm failed)` | añadir `blob:` a `connect-src` (`56602f8`) |

Cambios concretos sobre `index.php`:

- `script-src` → `'self' 'unsafe-inline' 'unsafe-eval' blob: https://cdn.emulatorjs.org`
  (se sustituye `'wasm-unsafe-eval'` por `'unsafe-eval'`, que además de cubrir
  la compilación WASM permite el `eval()` de JS que Emscripten usa en
  `cwrap`/`createNamedFunction`; y se añade `blob:` porque el core
  descomprimido se inyecta como Object URL).
- `style-src` → añade `https://cdn.emulatorjs.org` (loader + CSS del emulador).
- `font-src` → añade `https://cdn.emulatorjs.org` (iconos/fuentes del emulador).
- `img-src` → añade `https://cdn.emulatorjs.org` (sprites del emulador).
- `connect-src` → añade `blob:` (el WASM se instancia vía fetch desde el blob).
- `Permissions-Policy` → se elimina `interest-cohort=()`: Chrome no la
  reconoce como feature y emitía un warning (no rompía nada).
- `views/home/play.php` → `EJS_language = 'es-ES'` (el CDN `stable` no publica
  `es-419.json`).

**Consideración de seguridad:** con `'unsafe-inline'` + `'unsafe-eval'` la CSP
pierde casi todo su valor anti-XSS (requisito inherente de Emscripten). Lo que
sí se mantiene: `object-src 'none'`, `frame-ancestors 'self'`, `base-uri`,
`form-action` y el resto de cabeceras (`nosniff`, `X-Frame-Options`,
`Referrer-Policy`, `Permissions-Policy`). Las demás medidas de C2 (firmas de
descarga, rate limit, `.htaccess`, sanitización de columnas) no se ven
afectadas. Alternativa documentada si se quisiera una CSP más estricta en el
futuro: auto-hospedar cores compilados con `DYNAMIC_EXECUTION=0`, que los
builds oficiales de EmulatorJS no ofrecen.

---

## Verificación

Sin PHP CLI en el entorno: verificación **estática** (grep + lectura de diffs
+ balance de llaves/paréntesis).

- `grep checkRateLimit` → 0 usos (función duplicada eliminada).
- `grep action=download&file_id=` → 0 en flujos públicos (solo el export del
  admin, que se mantiene intacto).
- `grep HTTP_X_FORWARDED_FOR` → solo dentro de `RateLimiter::clientIp()`
  (protegido por `esIpProxyConfiable()`).
- Balance de `{`/`(` en los 10 archivos PHP tocados → OK.
- `ls public/uploads/.htaccess public/bios/.htaccess` → ambos creados.
- `grep escHtml views/home/index.php` → definida y usada para las URLs del
  autocomplete.
- `grep "form action" views/` → todos los forms son `self` o sin action.
- CSP de la sección 6: `curl -I` sobre los recursos del CDN confirmó que
  `cdn.emulatorjs.org/stable/data/localization/es-ES.json` existe (200) y que
  `es-419.json` no (404) → se corrigió `EJS_language`.
- Verificación manual posterior en navegador: la emulación online arranca y
  ejecuta juegos con la CSP final.

---

## Archivos del lote

| Acción | Archivo |
| --- | --- |
| CREAR | `config/UrlSigner.php` (firmado HMAC de descargas y proxy) |
| CREAR | `public/uploads/.htaccess` (bloqueo de scripts, Apache 2.4) |
| CREAR | `public/bios/.htaccess` (bloqueo de scripts, Apache 2.4) |
| MODIFICAR | `config/RateLimiter.php` (clientIp con REMOTE_ADDR + esIpProxyConfiable) |
| MODIFICAR | `rom_proxy.php` (usa RateLimiter; se elimina checkRateLimit duplicada) |
| MODIFICAR | `index.php` (nosniff, X-Frame-Options, Referrer-Policy, Permissions-Policy, CSP + correcciones de la sección 6) |
| MODIFICAR | `controllers/HomeController.php` (download firmado + rate limit; play con $downloadUrl; signProxyUrl delega) |
| MODIFICAR | `views/components/game_actions.php` (URL de descarga firmada) |
| MODIFICAR | `views/home/play.php` (enlaces firmados + signedDownloadUrl en JS + EJS_language es-ES) |
| MODIFICAR | `ajax_autocomplete.php` (download_url firmada) |
| MODIFICAR | `views/home/index.php` (escHtml en URLs del autocomplete) |
| MODIFICAR | `models/Model.php` (sanitizeColumns en create/update) |

Commits: `aa61093` (lote base) + `bbf6503`, `f979bac`, `1bd9bc7`, `56602f8`
(correcciones de integración con el emulador).

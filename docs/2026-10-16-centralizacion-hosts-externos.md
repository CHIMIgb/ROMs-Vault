# Centralización de hosts externos y de la CSP

**Fecha:** 2026-10-16
**Rama:** `fix/descarga-drirecta-drive`
**Estado:** implementado y verificado (pendiente de commit)
**Relacionados:** [`2026-10-16-endurecimiento-exposicion-http.md`](./2026-10-16-endurecimiento-exposicion-http.md) ·
[`2026-08-03-c2-seguridad-ip-descargas-csp.md`](./2026-08-03-c2-seguridad-ip-descargas-csp.md) ·
[`2026-10-07-migrar-imagenes-a-cloudflare-r2.md`](./2026-10-07-migrar-imagenes-a-cloudflare-r2.md) ·
[`2026-10-07-reorganizacion-estructura-archivos.md`](./2026-10-07-reorganizacion-estructura-archivos.md)

## 1. Contexto

El endurecimiento de la exposición HTTP (doc del mismo día) dejó claro que los
ficheros internos ya no se sirven, pero la auditoría derivada mostró otro frente:
**hosts externos escritos a mano en varios puntos distintos**. El síntoma clásico
de esta duplicación ya se sufrió antes: `emulator.min.css` no cargaba porque
`style-src` no incluía el CDN de EmulatorJS (ver `2026-08-03-c2-...`, tabla del
apartado 4). Cada vez que se toca un host externo hay que acordarse de N sitios
(JS del emulador, preconnect del layout, directivas de la CSP, `@import` del CSS)
y, si se olvida uno, la página sigue sirviendo pero el emulador o las fuentes se
rompen en silencio.

Objetivo: **una sola fuente de verdad por host** para los tres hosts que repite
el proyecto, sin cambiar el comportamiento HTTP observable (misma CSP, mismas
URLs) y con un test que falle si algo se desincroniza.

## 2. Auditoría: estado previo

Barrido de `src/`, `endpoints/`, `public/`, `index.php`, `router.php` en busca de
hosts literales (`https://...`) con `grep`.

### 2.1 Hosts duplicados (los que se centralizan)

| Host | Puntos encontrados | Riesgo |
|---|---|---|
| `https://cdn.emulatorjs.org` | `src/views/home/play.php:262` (`EJS_pathtodata`), `play.php:295` (`loader.js`) y **7 directivas** de la CSP en `index.php` | El JS y la CSP deben coincidir; cambiar el CDN exigía tocar 3 ficheros y 9 puntos |
| `https://fonts.googleapis.com` / `https://fonts.gstatic.com` | `public/css/modules/variables.css:9` (`@import`), `src/views/layout/header.php:38-39` (preconnect), `style-src`/`font-src` de la CSP en `index.php` | 4 puntos en 3 ficheros; el preconnect y la CSP deben referirse al mismo origen |
| `https://drive.google.com/uc?export=download...` | `endpoints/rom_proxy.php:94` (`define('GDRIVE_BASE', ...)`) | Duplicaba el host que ya declara `GDriveAllowlist::HOSTS_DEFAULT` (allowlist anti-SSRF): dos fuentes para el mismo host |

### 2.2 Ya estaba bien (no se toca)

- Hosts de terceros **como constantes de clase**: `GoogleServiceAccount.php:32,35`,
  `GDriveClient.php:31` (`FILES_ENDPOINT`), `DeepLTranslateProvider.php:24,27`,
  `GoogleTranslateProvider.php:23`.
- **Dominio propio**: no está hardcodeado en ningún sitio; se deriva de
  `$_SERVER` (`play.php:174`, `HomeController.php:494`, `DrenadoTrigger.php:91,124`)
  y el layout usa `<base href="/">` (`header.php:9`). Por eso las URLs limpias y
  legacy conviven sin tocar config.
- `xmlns="http://www.w3.org/2000/svg"` en los SVG inline: es un **namespace** XML,
  no una URL que se solicite. No se centraliza.

## 3. Solución

### 3.1 Archivos nuevos

| Archivo | Contenido |
|---|---|
| `src/config/EmulatorJS.php` | `CDN` (`https://cdn.emulatorjs.org`), `DATA_PATH` (`/stable/data/`), `dataUrl()`, `loaderUrl()` |
| `src/config/GoogleFonts.php` | `CSS_ORIGIN`, `FILES_ORIGIN`, `CSS_URL` (`CSS_ORIGIN . '/css2?family=Press+Start+2P&...'`) |
| `src/config/SecurityHeaders.php` | `csp()` (política completa), `emitir()` (las 5 cabeceras) y `r2ImgSrc()` privada (host público de R2 desde `R2_PUBLIC_HOST`, con `R2_PUBLIC_BASE` de reserva) |

`SecurityHeaders` carga las dos anteriores con `require_once` (el proyecto no usa
PSR-4), igual que hace `index.php` con el resto de `src/config`.

### 3.2 Archivos modificados

| Archivo | Cambio |
|---|---|
| `index.php` | `require` de las tres clases nuevas y sustitución del bloque de cabeceras (~38 líneas de CSP inline) por `SecurityHeaders::emitir();`. El comentario que explica **por qué** hacen falta `'unsafe-eval'` y `blob:` (Emscripten, Object URLs) se movió al docblock de la clase, donde vive la política |
| `src/views/home/play.php` | `EJS_pathtodata` y `loader.js` desde `EmulatorJS::dataUrl()` / `EmulatorJS::loaderUrl()` |
| `src/views/layout/header.php` | Los dos `preconnect` desde `GoogleFonts::CSS_ORIGIN` / `GoogleFonts::FILES_ORIGIN` |
| `src/config/GDriveClient.php` | Nueva constante `UC_BASE` (enlace público de Drive, solo modo `public`), documentando que su host debe seguir en la allowlist |
| `endpoints/rom_proxy.php` | Se elimina el `define('GDRIVE_BASE', ...)` y el flujo público usa `GDriveClient::UC_BASE`; el comentario anti-SSRF apunta ahora a esa constante |
| `tests/bootstrap.php` | `require_once` de las 3 clases nuevas |

### 3.3 Por qué constantes de clase y no variables de entorno

Los tres valores son **de la aplicación, no del despliegue**: cambiarlos obliga a
revisar la allowlist anti-SSRF, los tests y la CSP. Exponerlos en `.env` permitiría
apuntar el reproductor (y `script-src`) a un tercero sin pasar por revisión. Se
quedan como constantes documentadas y con test. No hay variables nuevas: ni
`.env`, ni `.env.example`, ni Vercel se tocan.

### 3.4 El caso del CSS (sincronía inevitable + test)

`public/css/modules/variables.css` no puede leer constantes PHP, así que conserva
la URL literal del `@import`. La garantía de que no divergen es
`tests/Unit/EmulatorJSGoogleFontsTest.php::testElImportDelCssCoincideConLaConstante`,
que compara el `@import` del fichero real con `GoogleFonts::CSS_URL` y falla si
alguien cambia solo uno de los dos.

## 4. Verificación

- `php -l` de los 8 ficheros tocados → sin errores de sintaxis.
- `phpcs --standard=phpcs.xml.dist` (gate PSR-12): `EmulatorJS.php`,
  `GoogleFonts.php`, `SecurityHeaders.php` y `GDriveClient.php` → **0 errores**.
- `endpoints/rom_proxy.php`: el conteo de avisos **baja** (33 en `HEAD` → 32)
  porque desaparece la violación de la línea del `define` eliminado; el resto de
  avisos son los heredados del fichero, con las líneas desplazadas 1 posición
  (comparado contra `HEAD` con `phpcs --report=csv`).
- `php vendor/bin/phpunit` → **`OK (315 tests, 15129 assertions)`** (antes: 306;
  +9 del test nuevo).
- **CSP idéntica**: `scratch/csp-antes-despues.php` generó la política con el
  código anterior y con `SecurityHeaders::csp()` usando el `.env` real
  (`R2_PUBLIC_HOST` de producción local) → `longitud antes: 609`,
  `longitud despues: 609`, `IDENTICAS: SI`.
- **Smoke con servidor real** (`php -S localhost:8033 router.php`,
  `scratch/smoke-hosts.ps1`):
  - `GET /` → HTTP 200, `Content-Security-Policy` de **609 caracteres idéntica** a
    la esperada, con `https://pub-<...>.r2.dev` inyectado desde el entorno.
  - `GET /` → preconnect a `https://fonts.googleapis.com` presente.
  - `GET /jugar/banjo-kazooie` → HTTP 200 con
    `EJS_pathtodata = 'https://cdn.emulatorjs.org/stable/data/'` y
    `loader.js` servido desde el mismo origen del CDN.
- `grep` final: en el código vivo (`src/`, `endpoints/`, `public/css`, `public/js`,
  `index.php`, `router.php`) no queda ningún literal del CDN de EmulatorJS ni de
  Google Fonts salvo las definiciones de las constantes y el `@import` del CSS.

## 5. Impacto y compatibilidad

- **CSP efectiva sin cambios** (609 caracteres idénticos, verificados en el
  servidor): `'unsafe-inline'`, `'unsafe-eval'`, `blob:` y el host de R2 siguen
  exactamente igual. El emulador no se ve afectado.
- **URLs públicas intactas** (`/rom_proxy.php`, `/ajax_*.php`, `/public/*`, rutas
  limpias y legacy); `vercel.json` sin tocar (`"rewrites": []`).
- **Sin variables de entorno nuevas**: no hay que tocar Vercel ni el `.env` local.
- `endpoints/rom_proxy.php` es camino crítico: el cambio de `GDRIVE_BASE` a
  `GDriveClient::UC_BASE` produce **la misma URL** (mismo literal) y el host sigue
  validándose contra `GDriveAllowlist`; lo cubre la suite completa (Integration).
- Los documentos históricos que copian la CSP o citan su ubicación
  (`2026-08-03-c2-...:71-77`, `2026-10-07-migrar-imagenes-a-cloudflare-r2.md:52`,
  que apunta a `index.php:24`) quedan como **registro de la época**. La fuente viva
  es ahora `src/config/SecurityHeaders.php`; no se editan sin permiso.

## 6. Pendientes

- `docs/2026-10-07-migrar-imagenes-a-cloudflare-r2.md:52` cita `index.php:24` como
  ubicación de la CSP → referencia caducada (actualizarla requiere permiso).
- No es un ítem del roadmap, así que no se ha tocado
  `docs/2026-08-05-plan-mejoras-nivel-senior.md`; si se quiere, se le añade fila
  en el "Registro de progreso".
- Cambiar de CDN (mirror de EmulatorJS) o de proveedor de fuentes es ahora un
  cambio de una constante + el `@import` del CSS, guiado por el test.

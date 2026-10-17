# Endurecimiento de la exposición HTTP: credenciales fuera del alcance del navegador

**Fecha:** 2026-10-16
**Rama:** `fix/descarga-drirecta-drive`
**Estado:** implementado y verificado (pendiente de commit)
**Relacionados:** [`2026-10-10-dockerignore.md`](./2026-10-10-dockerignore.md) ·
[`planificado/2026-10-14-drive-privado-service-account.md`](planificado/2026-10-14-drive-privado-service-account.md) ·
`docs/2026-10-07-reorganizacion-estructura-archivos.md`

## 1. Contexto

Tras privatizar en Drive todas las carpetas de `EMULADORES` (por consola), la
pregunta a responder era: **¿los archivos de Drive solo se alcanzan desde la
aplicación, en solo lectura, y está bien implementada la cuenta de servicio?**

La respuesta es sí en los tres puntos, verificado con evidencia (apartados 2.1
y 2.2). Pero la auditoría de "solo desde la aplicación" destapó que el
**servidor** sí servía por HTTP ficheros internos: `.env`, la clave privada de
la cuenta de servicio, `.git/`, el SQL de datos, etc. Este documento cubre esa
corrección y deja la evidencia completa de la verificación.

## 2. Auditoría

### 2.1 Drive: los archivos ya no son accesibles desde fuera

Barrido **anónimo** (sin cookies ni `Authorization`) de los 124 `file_id`
registrados en `juegos.google_drive_file_id`
(`scratch/audit-anonimo-todos.php`, desechable, no versionado):

```
file_ids registrados: 124
comprobados (un registro por id unico): 124
PUBLICOS sin credenciales  : 0
respuestas atipicas        : 0
RESULTADO: ningun archivo registrado responde sin credenciales
           (todos exigen login/permiso: se accede solo desde la app).
```

Antes de privatizar, el mismo test devolvía `HTTP 206` con contenido; ahora
`HTTP 302 -> accounts.google.com/ServiceLogin`. Jerarquía comprobada
levantando los `parents`: `EMULADORES` (raíz) → `PC` -> archivos.

### 2.2 Cuenta de servicio: implementada y de solo lectura

`scratch/audit-cuenta-servicio.php` (desechable):

```
[1] tokeninfo   -> scope: https://www.googleapis.com/auth/drive.readonly  (unico scope)
[2] files.get   -> HTTP 200 (WipEout.Pulse.EUR.PSP-NextLevel.iso)
    canEdit: no   canShare: no   canDelete: no   canRename: no   canAddChildren: no
[3] permissions.create -> HTTP 403  "Request had insufficient authentication scopes."
```

- **Solo lectura por dos vías independientes**: el scope OAuth del token
  (`drive.readonly`, hardcodeado en `GoogleServiceAccount::SCOPE`) y el rol
  efectivo (`capabilities` de lector). El intento de escritura de [3] se hace
  solo si el token es de lectura (guarda), así que no puede crear nada.
- `permissions.list` responde **403** a la cuenta de servicio: no puede ni leer
  la ACL (mínimo privilegio real).
- La caché del token vive en `sys_get_temp_dir()/rv_gdrive_token` (`0700`),
  fuera del webroot, y en producción la clave **no se escribe en disco**
  (`GOOGLE_SERVICE_ACCOUNT_JSON_B64` se decodifica en memoria).
- La app sigue funcionando con los archivos privados: smoke completo
  (`scratch/smoke_fase5.php`) → `=== TODAS LAS COMPROBACIONES OK ===`
  (ficha, `/jugar`, proxy 403 sin firma / manipulada / caducada, `HEAD` 200 con
  `Content-Length: 4946293860`, `Range` → `206`, `dl=1` → `attachment`,
  `/home/download` 403 sin firma y 302 firmado, ISO de PSP con su MIME).

### 2.3 Hallazgos de exposición HTTP (antes del cambio)

`php -S localhost:8020 router.php` + peticiones directas
(`scratch/audit-exposicion.php`):

```
/.env                            HTTP 200  4607B   <- BD, JWT, CSRF en claro
/.secrets/service-account.json   HTTP 200  2356B   <- CLAVE PRIVADA
/.git/config                     HTTP 200  3430B
/data/import_data.sql            HTTP 200  98297B
/docker-entrypoint.sh            HTTP 200  2801B
/src/config/database.php         HTTP 403          <- este ya estaba bien
rutas servidas con HTTP 200: 12
```

| # | Hallazgo | Severidad | Detalle |
|---|---|---|---|
| F1 | `.dockerignore` no excluía `.secrets/` | Alta (latente) | El `Dockerfile` hace `COPY . .`; un `docker build` desde una máquina con `.secrets/service-account.json` metía la clave en la imagen, y `.htaccess` no bloqueaba `.json` (`FilesMatch "\.(env\|log\|sql\|sh\|bak)$"`). El deploy desde git no se veía afectado (el fichero está ignorado) |
| F2 | `router.php` solo bloqueaba `/src/` | Media (dev/tests) | El servidor embebido sirve cualquier fichero real: `.env`, `.secrets/`, `.git/`, `data/*.sql`. Escucha en 127.0.0.1, pero cualquier proceso local lo lee |
| F3 | `/endpoints/rom_proxy.php` responde por su ruta interna | Info | Sin agujero: el proxy exige firma igual (400/403). **No se bloquea** porque el worker interno (`/endpoints/drain_traducciones.php`) se invoca por esa ruta |
| F4 | `AGENTS.md`, `Dockerfile`, `composer.json`, `phpunit.xml` servidos 200 | Baja | `data/*.sql` van en la imagen, pero Apache los bloquea por extensión |

## 3. Solución

1. **`.dockerignore`** — se añade `.secrets/` (cierra F1).
2. **`.htaccess`** — mismas reglas que en `router.php`, para Apache/Vercel:
   - `RewriteRule "(^|/)\.(?!well-known/)" - [F,L]` → bloquea dotfiles
     (`.env`, `.secrets/`, `.git/`, `.gitignore`…) sin romper `/.well-known/` (ACME).
   - `RewriteRule ^(data|tests|scratch|docs|scripts|vendor)/ - [F,L]` → directorios internos.
   - `<FilesMatch>` nuevos: `Dockerfile`, `docker-compose.yml|yaml`,
     `composer.json|lock`, `phpunit.xml(.dist)`, `AGENTS.md`, `README.md`,
     `router.php`, `docker-entrypoint.sh`.
   - **No** se bloquea `*.json` en general: EmulatorJS sirve JSON desde `/public/`.
3. **`router.php`** — espejo de esas denegaciones para dev y tests de
   integración, **antes** de servir ficheros existentes. Se conserva el 403 de
   `/src/`, el mapeo de los entrypoints públicos y el enrutado a `index.php`.
4. **`tests/Integration/ExposicionTest.php`** — 9 tests que fijan el contrato:
   403 y sin filtrar contenido en las rutas internas, y 200 en lo público
   (`/`, `/public/css/style.css`, `/public/img/icon.png`) y en la URL pública
   del proxy (403 JSON propio, no bloqueada por el endurecimiento).

## 4. Verificación

- `php -l router.php` y `php -l tests/Integration/ExposicionTest.php` → sin errores.
- `php vendor/bin/phpunit` → **`OK (306 tests, 15083 assertions)`** (antes: 297;
  +9 del test nuevo).
- `php vendor/bin/phpunit --testsuite Integration --filter ExposicionTest --testdox`
  → **9/9 OK**.
- `scratch/audit-exposicion.php` tras el cambio → **0 rutas servidas con HTTP 200**
  (todas 403; `/rom_proxy.php` 400/403 es el rechazo propio del proxy).
- `grep` de referencias a `vendor/`, `docs/`, `data/`, `tests/` en vistas y
  assets: ninguna (no se rompe nada al bloquearlas).

## 5. Impacto y compatibilidad

- **URLs públicas intactas**: `/rom_proxy.php`, `/ajax_*.php`, `/public/*`,
  rutas limpias y legacy. `vercel.json` sin tocar (`"rewrites": []`) y CSP sin
  cambios.
- `/endpoints/` sigue alcanzable: el worker interno de traducciones depende de
  esa ruta (`DrenadoTrigger::RUTA_ENDPOINT`), verificado por `DrainEndpointTest`.
- **Sin variables de entorno nuevas**: no hay que tocar Vercel ni el `.env`.
  F1 solo aplica a builds de Docker hechos en local.

## 6. Pendientes

- Fichero `.secrets/service-account.json` local con permisos amplios en
  Windows: `icacls` para restringirlo al usuario (higiene local, no afecta a
  producción).
- El `docker build` real se valida en Docker Desktop o en CI (FASE 2): aquí el
  daemon no está accesible.
- F3 (URL interna del proxy): sin cambios; si algún día se quiere una única
  URL pública, habría que mover el worker de drenado a otra ruta.

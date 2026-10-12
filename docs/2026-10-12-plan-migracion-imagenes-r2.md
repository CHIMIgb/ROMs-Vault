# Plan de implementación — Migrar las imágenes de juegos a Cloudflare R2

- **Fecha:** 2026-10-12
- **Estado:** propuesto (pendiente de aprobación e implementación)
- **Ámbito:** almacenamiento y entrega de portadas/capturas de juegos
- **Diseño previo:** `docs/2026-10-07-migrar-imagenes-a-cloudflare-r2.md` (este documento lo concreta y corrige rutas tras la reorg a `src/`)

## 0. Aclaración: Cloudflare R2, no Amazon S3

**El proveedor es Cloudflare R2**, no Amazon S3. Lo que confunde es el nombre:
R2 expone una **API compatible con S3** (protocolo SigV4) para que se pueda usar
cualquier cliente/SDK de S3 cambiando solo el endpoint. No hay cuenta de AWS ni
bucket de S3 en juego.

Por eso, en este plan se evita el prefijo `S3_` y se usan variables y clases
llamadas **`R2_*` / `R2Client`**; el endpoint real es
`https://<ACCOUNT_ID>.r2.cloudflarestorage.com` (infra de Cloudflare). El
término "S3-compatible" describe **cómo se habla** con R2, no **dónde** se
almacena.

## 1. Resumen del cambio

Mover el almacenamiento de **portadas y capturas** de `public/uploads/{consola}/{juego}/`
(disco del contenedor, efímero entre redeploys) a **Cloudflare R2**, conservando la
**misma estructura y lógica**:

- **Misma estructura de claves:** `{consola-slug}/{juego-slug}/portada.webp` +
  `captura-N.webp`. No cambia cómo se nombran ni cómo se agrupan.
- **Misma lógica de admin:** subir / mover al renombrar / borrar / reemplazar se
  mantienen; solo cambia el destino (objeto en R2 en vez de archivo en disco).
- **Único cambio real:** dónde se almacena. En BD, `juegos.imagen` y
  `juegos.capturas` pasan de ruta relativa (`public/uploads/...`) a **URL pública
  completa** (`https://pub-<hash>.r2.dev/...`). Las vistas ya pintan
  `src="..."` directo, así que el navegador carga **directo desde R2** sin pasar
  por PHP.
- **Fallback local:** sin credenciales R2 → modo disco actual (dev y tests
  intactos, la suite nunca toca R2).

## 2. Archivos afectados

```
[CREAR]      src/config/R2Client.php
  - Cliente S3-compatible (SigV4) mínimo: putObject, deleteObject, copyObject,
    headObject, listObjects.
[CREAR]      src/config/StorageService.php
  - Capa de almacenamiento con métodos espejo de las operaciones FS actuales:
    guardar(), borrar(), mover(), existe(), borrarPrefijo(), urlPublica(), keyDesdeUrl().
  - Si no hay credenciales R2 -> modo LOCAL (passthrough al filesystem actual).

[CREAR]      scripts/migrar-imagenes-r2.php
  - Recorre public/uploads/**, sube cada archivo con la clave {consola}/{juego}/...
    y genera los UPDATE juegos SET imagen=..., capturas=... (idempotente).

[MODIFICAR]  src/controllers/AdminController.php
  - uploadImage(): convertir a WebP en memoria y subir por StorageService.
  - carpetaDestinoJuego()/resolverCarpetaJuego(): devolver "prefijo" en vez de crear carpetas.
  - carpetaJuegoAbsoluta()/rutaUploadsAbsoluta(): resolver prefijo/URL, sin is_dir.
  - moverContenido()/rmdirRecursive()/eliminarCapturasViejas()/delete(): delegar en
    copiarPrefijo/borrarPrefijo.

[MODIFICAR]  src/models/Juego.php
  - Nuevo helper Juego::existeImagen(?string $ruta): bool (distingue http(s):// y local).

[MODIFICAR]  src/controllers/HomeController.php   (linea ~124, SEO/JSON-LD)
  - file_exists(ltrim($imagen,'/')) -> Juego::existeImagen($imagen).

[MODIFICAR]  src/views/home/show.php             (linea ~67, portada destacada)
  - file_exists(...) -> Juego::existeImagen($juego['imagen']).

[MODIFICAR]  index.php                            (CSP, linea ~41)
  - img-src: anadir el host publico de R2 desde env (R2_PUBLIC_HOST).

[MODIFICAR]  docker-entrypoint.sh
  - Anadir las variables R2 al heredoc del .env generado en runtime.

[MODIFICAR]  .env.example
  - Documentar R2_ACCOUNT_ID, R2_ACCESS_KEY_ID, R2_ACCESS_KEY_SECRET,
    R2_ENDPOINT, R2_BUCKET, R2_PUBLIC_BASE, R2_PUBLIC_HOST.

[CREAR]      docs/2026-10-12-migracion-imagenes-r2-implementacion.md   (doc fechado de la tarea)
[MODIFICAR]  docs/2026-08-05-plan-mejoras-nivel-senior.md             (marcar item + hash)
```

**Sin cambios:** vistas de grid/catálogo (`views/home/index.php`,
`components/related_games.php`, `admin/dashboard.php`, `endpoints/ajax_catalog.php`,
`endpoints/ajax_autocomplete.php`), `rom_proxy.php`, EmulatorJS, `vercel.json`.

## 3. Variables de entorno (`.env`, gitignored)

```dotenv
R2_ACCOUNT_ID=
R2_ACCESS_KEY_ID=
R2_ACCESS_KEY_SECRET=
R2_ENDPOINT=https://<ACCOUNT_ID>.r2.cloudflarestorage.com
R2_BUCKET=roms-vault-imagenes
R2_PUBLIC_BASE=https://pub-<hash>.r2.dev
R2_PUBLIC_HOST=pub-<hash>.r2.dev
```

- Sin estas variables → **modo local** (fallback para dev y tests).
- `R2_ENDPOINT` se usa solo para firmar (SigV4); `R2_PUBLIC_BASE` es la URL que
  se guarda en BD y ve el navegador.
- `docker-entrypoint.sh` regenera `.env` en runtime → hay que añadir estas
  variables al heredoc y definirlas en Vercel (Environment Variables).
- `R2_ACCESS_KEY_SECRET` es secreto: **nunca** se commitea (`.env` ya gitignored).

**Dependencias:** ninguna nueva. Cliente SigV4 mínimo (~150 líneas) en vez de
`aws/aws-sdk-php` (30+ paquetes) para solo 5 operaciones. *(Decisión a confirmar.)*

## 4. Guía: cómo preparar R2 en el panel de Cloudflare

> Documentación oficial vigente usada como referencia:
> **Create bucket** e **API tokens** (`developers.cloudflare.com/r2/api/tokens`)
> y **Public buckets** (`developers.cloudflare.com/r2/buckets/public-buckets`).

### 4.1. Account ID
1. Entra en el dashboard de Cloudflare → **Storage & databases → R2 → Overview**.
2. El **Account ID** aparece en la zona lateral derecha de esa página (y también
   al final de la pantalla de creación del token). Es un identificador de 32
   caracteres; lo necesitas para construir el endpoint y la URL pública.

### 4.2. Bucket
Si todavía no existe:
1. **R2 → Overview → Create bucket**.
2. Nombre: p. ej. `roms-vault-imagenes`.
3. Ubicación: la más cercana (p. ej. *Eastern Europe* o *Western Europe*) y
   storage class **Standard** (el free tier solo cubre Standard).
4. **Create bucket**.

### 4.3. Token de API (Access Key + Secret)
1. **R2 → Overview → Manage** (en *API Tokens*) → **Create API Token**.
2. Elige **Create Account API token** (recomendado; sobrevive a cambios de usuario)
   o *User API token*.
3. **Permissions: `Object Read & Write`**.
4. **Apply to specific buckets only** → selecciona `roms-vault-imagenes`
   (limita el token a este bucket).
5. **Create API Token**.
6. Copia **Access Key ID** y **Secret Access Key** **ahora**: el secreto **no se
   vuelve a mostrar**. Serán `R2_ACCESS_KEY_ID` y `R2_ACCESS_KEY_SECRET`.

### 4.4. Endpoint (`R2_ENDPOINT`)
En esa misma pantalla de confirmación (o en R2 → Overview) aparece:
```
https://<ACCOUNT_ID>.r2.cloudflarestorage.com
```

### 4.5. Acceso público (`R2_PUBLIC_BASE` / `R2_PUBLIC_HOST`)
Dos opciones (se pueden usar por separado):

- **Opción A — subdominio `r2.dev`** (rápida, para no-producción):
  1. Selecciona el bucket → **Settings → Public access**.
  2. **Enable** el `r2.dev` managed domain.
  3. La URL resultante es `https://pub-<hash>.r2.dev`.
  4. **Host** para el CSP: `pub-<hash>.r2.dev`.

- **Opción B — dominio propio en Cloudflare** (recomendada para producción:
  caching, HTTP/2, dominio propio):
  1. Selecciona el bucket → **Settings → Public access → Custom Domains** →
     **Connect domain** → p. ej. `img.mi-dominio.com`.
  2. **Host** para el CSP: `img.mi-dominio.com`.

> Nota: para servir `<img>` desde el navegador **no hace falta CORS** (las
> imágenes no se leen por JS). CORS solo sería necesario si algún día se sube
> directo desde el navegador con URLs prefirmadas.

## 5. Orden de ejecución (paso a paso, con verificación en cada paso)

1. **Rama** nueva desde `main` (`feature/migrar-imagenes-r2`).
2. **`R2Client` + `StorageService` en modo LOCAL** (sin tocar AdminController) →
   `php -l` + `composer test` verdes.
3. **Adaptar `AdminController`** para usar el servicio (upload/move/delete) →
   tests verdes (siguen usando disco).
4. **Helper `Juego::existeImagen()`** + ajustes en `HomeController` y `show.php`.
5. **CSP + `.env.example` + `docker-entrypoint.sh`**.
6. **Activar R2** con las credenciales del punto 4 y probar subir/mover/borrar un
   juego desde el admin → comprobar que la imagen carga desde R2 (consola sin
   errores de CSP).
7. **Script de migración**: subir los archivos y ejecutar los `UPDATE`
   (primero en BD local, luego en Neon tras desplegar).
8. **Verificación end-to-end** + doc fechado.

## 6. Impacto y verificación

- **Compatibilidad:** URLs públicas, `vercel.json` (`"rewrites": []`) y CSP
  (EmulatorJS) intactos; solo se **añade** un host a `img-src`. Paginación 20 sin
  cambios.
- **Riesgo principal:** la firma SigV4 propia (si se opta por el cliente mínimo);
  se mitiga con una subida real de prueba contra R2 en el paso 6.
- **Verificación:** `php -l`, `composer test` (verde, sin tocar R2), `phpstan`, y
  smoke real contra R2 (subir/mostrar/borrar + `curl` a la URL pública + consola
  del navegador sin errores de CSP).

## 7. Decisiones pendientes

1. **Acceso público:** `r2.dev` o dominio propio (define `R2_PUBLIC_BASE`/`R2_PUBLIC_HOST`).
2. **Cliente:** SigV4 mínimo (sin dependencias, como decidió el diseño previo) vs
   `aws/aws-sdk-php` (más probado, añade paquetes).
3. **Momento de la migración:** migrar los archivos existentes en este mismo
   trabajo, o dejar primero el código funcionando y migrar en un paso siguiente.

## 8. Referencias

- R2 S3-compatible / buckets: https://developers.cloudflare.com/r2/get-started/s3/
- API tokens (Access Key/Secret/endpoint): https://developers.cloudflare.com/r2/api/tokens/
- Public buckets (r2.dev / dominio propio): https://developers.cloudflare.com/r2/buckets/public-buckets
- `AGENTS.md` — convenciones del proyecto.
- `docs/2026-10-07-migrar-imagenes-a-cloudflare-r2.md` — diseño previo.

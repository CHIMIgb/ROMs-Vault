# Migración de imágenes de juegos a Cloudflare R2 — implementación

- **Fecha:** 2026-10-12
- **Estado:** implementado y verificado en local; migración de Neon pendiente tras el deploy
- **Ámbito:** almacenamiento y entrega de portadas/capturas (`juegos.imagen`, `juegos.capturas`)
- **Plan de referencia:** `docs/2026-10-12-plan-migracion-imagenes-r2.md`
- **Diseño previo:** `docs/2026-10-07-migrar-imagenes-a-cloudflare-r2.md`
- **Rama:** `feature/migrar-imagenes-r2`

## 1. Qué se hizo

Se movió el almacenamiento de las imágenes de juegos del disco efímero del
contenedor (`public/uploads/`) a **Cloudflare R2**, conservando la misma
estructura de claves y la misma lógica de admin. En base de datos, `imagen` y
`capturas` pasan de ruta relativa (`public/uploads/...`) a **URL pública
completa**; las vistas ya pintan `src="..."` directo, así que el navegador carga
los archivos **desde el CDN sin pasar por PHP**.

Estructura de claves en el bucket (idéntica a la local):

```
{consola-slug}/{juego-slug}/portada.webp
{consola-slug}/{juego-slug}/captura-N.webp
```

**Sin cambios:** URLs públicas, `vercel.json` (`"rewrites": []`), `rom_proxy.php`,
EmulatorJS, paginación y las vistas de grid/catálogo. La CSP solo **añade** el
host del CDN a `img-src`.

## 2. Archivos

```
[CREAR]      src/config/R2Client.php      Cliente S3-compatible (SigV4) mínimo: putObject,
                                          deleteObject, copyObject, headObject, listObjects.
[CREAR]      src/config/StorageService.php Capa de almacenamiento (R2 o local). API: guardar,
                                          borrar, existe, prefijoOcupado, borrarPrefijo,
                                          copiarPrefijo, urlPublica, referencia, keyDeReferencia.
[CREAR]      scripts/migrar-imagenes-r2.php Migración idempotente de public/uploads/ a R2 + UPDATEs.
[CREAR]      docs/2026-10-12-migracion-imagenes-r2-implementacion.md (este documento)
[MODIFICAR]  src/controllers/AdminController.php  upload/move/delete vía StorageService (por prefijos).
[MODIFICAR]  src/models/Juego.php                 Nuevo helper Juego::existeImagen().
[MODIFICAR]  src/controllers/HomeController.php   file_exists(...) -> Juego::existeImagen(...) (SEO/JSON-LD).
[MODIFICAR]  src/views/home/show.php              file_exists(...) -> Juego::existeImagen(...) (portada destacada).
[MODIFICAR]  index.php                            img-src: host de R2 desde R2_PUBLIC_HOST/R2_PUBLIC_BASE.
[MODIFICAR]  .env.example                         Documentadas las variables R2.
[MODIFICAR]  docker-entrypoint.sh                 Variables R2 añadidas al .env generado en runtime.
[MODIFICAR]  phpstan-baseline.neon                Ajuste de baseline (métodos retirados y usos nuevos).
[MODIFICAR]  docs/2026-08-05-plan-mejoras-nivel-senior.md (Registro de progreso).
```

## 3. Configuración (variables de entorno)

Se usan variables `R2_*` (no `S3_*`): el proveedor es **Cloudflare R2**; solo se
reutiliza el protocolo S3/SigV4 para hablarlo. Ver `.env.example`.

```dotenv
R2_ACCOUNT_ID=
R2_ACCESS_KEY_ID=
R2_ACCESS_KEY_SECRET=
R2_ENDPOINT=https://<ACCOUNT_ID>.r2.cloudflarestorage.com
R2_BUCKET=roms-vault-imagenes
R2_PUBLIC_BASE=https://pub-<hash>.r2.dev
R2_PUBLIC_HOST=pub-<hash>.r2.dev
```

- Sin credenciales completas -> **modo local** (fallback para dev y tests; la
  suite nunca toca R2).
- `R2_ENDPOINT` se usa solo para firmar (SigV4); `R2_PUBLIC_BASE` es la URL que
  se guarda en BD y ve el navegador.
- El host público (`r2.dev`) tiene el formato `pub-<hash>.r2.dev`, **no**
  `pub.<ACCOUNT_ID>.r2.dev`.
- El secreto **nunca** se commitea: solo en `.env` local y en las Environment
  Variables de Vercel. `docker-entrypoint.sh` regenera el `.env` en runtime.

## 4. Decisiones de diseño

1. **Cliente SigV4 mínimo** (sin `aws/aws-sdk-php`): solo 5 operaciones; evita
   30+ paquetes y mantiene el proyecto sin dependencias de framework.
2. **Account API Token** con permiso `Object Read & Write` limitado al bucket
   `roms-vault-imagenes`; *IP Address Filtering* vacío.
3. **Acceso público** vía *Public Development URL* (`r2.dev`) como opción rápida
   no-productiva; migrar a dominio propio en Cloudflare no requiere cambios de
   código (solo `R2_PUBLIC_BASE`/`R2_PUBLIC_HOST`).
4. **Sin CORS**: las imágenes se sirven a `<img>` (no se leen por JS), así que no
   hace falta.
5. **Código primero, migración después** (este orden): primero el código con
   fallback local, luego se activó R2 y se migraron los archivos.

## 5. Verificación (evidencia)

- `php -l` en todos los archivos PHP tocados: sin errores.
- `vendor/bin/phpstan analyse`: `[OK] No errors` (nivel 5 + baseline).
- `vendor/bin/phpunit` (Unit + Integration + Security): **184 tests, 11178
  assertions, OK** (modo local; la suite no usa R2).
- Prueba de humo local (`php -S localhost:8000 router.php`): `/`,
  `/home/show/1`, `/auth/login`, `/ajax_catalog.php` -> HTTP 200.
- **Cliente R2 real** (contra el bucket): `putObject`, `headObject`, `copyObject`,
  `listObjects`, `deleteObject` OK; GET a la URL pública -> **HTTP 200**.
- **E2E de `AdminController` contra R2** (subir -> URL pública -> GET `image/webp`
  -> copiar prefijo -> reubicar referencia -> borrar captura -> limpiar): sin
  restos.
- **Migración real (BD local + bucket):** **157 archivos** subidos, **0 errores**;
  **123 juegos** actualizados a URL remota; **0** referencias locales restantes;
  **157 objetos** en el bucket. Re-ejecución en `--dry-run`: **0 archivos**,
  **0 juegos** (idempotente).
- Sitio local con R2 activo: `/` y `/juego/wipeout-pulse` -> HTTP 200 con las
  portadas cargando desde `pub-...r2.dev` y la CSP incluyendo el host (sin
  errores de CSP).

## 6. Rollback

- Respaldo de las referencias previas en `scratch/juegos_refs_backup.json`
  (gitignored): 123 filas con `id`, `imagen`, `capturas`.
- Los archivos originales siguen en `public/uploads/` (no se borraron).
- Restaurar = ejecutar los `UPDATE` con el JSON del respaldo.

## 7. Pendiente

1. **Migrar Neon**: ejecutar `scripts/migrar-imagenes-r2.php` contra la BD de
   producción **después** de desplegar (con las credenciales R2 en Vercel).
2. **Rotar el secret** de R2 antes de producción (se compartió por chat).
3. **Comandos usados** (Windows): `C:\xampp\php\php.exe scripts\migrar-imagenes-r2.php --dry-run`
   y `... --yes`.

## 8. Referencias

- R2 S3-compatible: https://developers.cloudflare.com/r2/get-started/s3/
- API tokens: https://developers.cloudflare.com/r2/api/tokens/
- Public buckets: https://developers.cloudflare.com/r2/buckets/public-buckets

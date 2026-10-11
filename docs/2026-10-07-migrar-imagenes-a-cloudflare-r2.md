# Migrar las imágenes de juegos a Cloudflare R2

- **Fecha:** 2026-10-07
- **Estado:** propuesto (pendiente de implementar)
- **Commit base:** `b9bcf50`
- **Ámbito:** almacenamiento y entrega de portadas/capturas de juegos

## 1. Objetivo

Sacar las imágenes de juegos de `public/uploads/{consola}/{juego}/` y
almacenarlas en **Cloudflare R2**, de forma que:

1. El navegador las cargue **directamente desde R2** (o su CDN), sin pasar
   por el servidor PHP.
2. Sobrevivan a los redeploys (el filesystem del contenedor es efímero).
3. Los recursos del sitio dejen de concentrarse todos en el mismo servidor.

## 2. Motivación

1. **Filesystem efímero:** el deploy es Docker sobre Vercel (`Dockerfile`,
   `vercel.json`). Cada redeploy destruye `public/uploads/`. Migrar a object
   storage no es solo optimización: es lo que hace **persistir** las portadas.
2. **Descarga del servidor:** hoy PHP sirve cada imagen. Con un bucket público
   el navegador carga directo desde la red de Cloudflare.
3. **Orígenes distribuidos:** las ROMs ya vienen de Google Drive
   (`rom_proxy.php`) y el emulador de EmulatorJS; con R2 las imágenes dejan de
   depender del mismo host.
4. **Coste cero para este uso** (ver §5).

## 3. Estado actual verificado

- Estructura **nueva**: `public/uploads/{consola-slug}/{juego-slug}/` con
  `portada.webp` + `captura-1..7.webp` (6 juegos).
- Estructura **legada**: portadas planas `public/uploads/{hash}_{ts}.jpg`
  en la raíz (~116 juegos).
- **Total:** 152 archivos, ~7,6 MB.
- En BD: `juegos.imagen` = ruta relativa `public/uploads/...`;
  `juegos.capturas` = JSON con un array de rutas.
- La escritura/borrado/movido vive en `controllers/AdminController.php`:
  `uploadImage()`, `carpetaJuegoAbsoluta()`, `moverContenido()`,
  `rmdirRecursive()`, `eliminarCapturasViejas()`, `delete()`, usando
  `mkdir/rename/is_dir/unlink/file_exists` + `imagewebp()` a disco.
- Las vistas de grid/catálogo (`views/home/index.php`, `play.php`,
  `views/components/related_games.php`, `views/admin/dashboard.php` y el
  autocomplete) hacen `src="..."` directo → **no requieren cambios** si la BD
  guarda la URL completa.

## 4. Qué se rompe al usar URLs remotas

| Pieza | Ubicación | Motivo |
|---|---|---|
| CSP | `index.php:24` | `img-src 'self' data: blob: https://cdn.emulatorjs.org` bloquea el host de R2 → hay que añadirlo |
| SEO | `HomeController.php:95` | `file_exists(ltrim($imagen,'/'))` falla con `https://…` |
| Portada destacada | `views/home/show.php:67` | mismo `file_exists()` |
| Admin (edit/delete/move) | `controllers/AdminController.php` | todas las operaciones de FS sobre rutas relativas |

## 5. Por qué Cloudflare R2

R2 es almacenamiento de objetos **compatible con S3** con **egress gratuito**.

**Free tier (permanente, no es prueba de 12 meses):**

| Concepto | Gratis / mes |
|---|---|
| Almacenamiento Standard | 10 GB-mes |
| Operaciones clase A (escrituras) | 1.000.000 |
| Operaciones clase B (lecturas) | 10.000.000 |
| Egress (tráfico de salida) | Ilimitado / $0 |

Nuestro uso actual (152 archivos, 7,6 MB) cae **holgadamente dentro del free
tier → $0**. Tarifas por encima del free tier: $0,015/GB-mes, clase A
$4,50/millón, clase B $0,36/millón; el egress sigue siendo gratis.

**Ventaja frente a Amazon S3:** S3 cobra egress y su free tier (5 GB) dura solo
12 meses; R2 no cobra egress nunca y su free tier no caduca. El código es el
mismo (API S3-compatible), solo cambia el endpoint.

**Contrapartidas a tener en cuenta:**

- Activar R2 **exige tarjeta de crédito** aunque se use el plan gratuito (no se
  cobra dentro del free tier, pero piden método de pago).
- El free tier aplica solo a almacenamiento **Standard** (no Infrequent Access).
- El acceso público requiere activar *Public bucket access* (host `r2.dev`) o
  usar un dominio propio en Cloudflare.
- Amazon S3 queda como **alternativa equivalente**: el mismo cliente sirve
  cambiando endpoint/credenciales, con la salvedad del coste de egress.

## 6. Arquitectura propuesta

### 6.1. Entrega
- Bucket con **acceso público de lectura**: URLs estables
  `https://pub.<ACCOUNT_ID>.r2.dev/<bucket>/{consola}/{juego}/portada.webp`.
- Alternativa: **dominio propio en Cloudflare** apuntando al bucket (caching,
  HTTP/2, dominio propio; también gratis). El host resultante va al CSP.
- **Descartado:** proxy PHP para servir las imágenes (duplicaría la carga que
  se quiere eliminar), igual que hace `rom_proxy.php` con Drive.

### 6.2. Cliente R2/S3

Se implementa un cliente **SigV4 mínimo** en `config/S3Client.php`
(~150 líneas), coherente con el repo (sin framework, solo 3 dependencias
Composer). Operaciones necesarias:

- `putObject(key, bytes, contentType)` — subir portada/captura.
- `deleteObject(key)` — borrar un archivo.
- `copyObject(origen, destino)` — mover al renombrar/cambiar de consola.
- `headObject(key)` — comprobar existencia (sustituye `is_dir`/`file_exists`).
- `listObjects(prefix)` / borrado por prefijo — borrar la carpeta del juego.

R2 requiere endpoint propio y región `auto`; el cliente los toma de config.
Se descarta `aws/aws-sdk-php` (30+ paquetes) por innecesario para 5 llamadas.

## 7. Configuración (`.env`, ya gitignored)

```dotenv
R2_ACCOUNT_ID=...
R2_ACCESS_KEY_ID=...
R2_ACCESS_KEY_SECRET=...
S3_ENDPOINT=https://<ACCOUNT_ID>.r2.cloudflarestorage.com
S3_BUCKET=roms-vault-imagenes
S3_PUBLIC_BASE=https://pub.<ACCOUNT_ID>.r2.dev
S3_PUBLIC_HOST=pub.<ACCOUNT_ID>.r2.dev
```

- Sin estas variables → **modo local actual** (fallback para dev y tests; la
  suite nunca toca R2 ni AWS).
- `docker-entrypoint.sh` regenera `.env` en runtime → hay que **añadir las
  variables R2** a ese heredoc y definirlas en Vercel.
- `ABSOLUTE`/`S3_ENDPOINT` se usa solo para firmar; `S3_PUBLIC_BASE` es la URL
  que se guarda en BD y la que ve el navegador.

## 8. Cambios de código propuestos

1. **`config/S3Client.php` (nuevo)** — cliente SigV4 + helpers:
   - `guardarImagen(string $key, string $bytes, string $contentType): string`
   - `borrarObjeto(string $key): bool`
   - `copiarObjeto(string $origen, string $destino): bool`
   - `existeObjeto(string $key): bool`
   - `borrarPrefijo(string $prefix): void`
   - `urlPublica(string $key): string` y `keyDesdeUrl(string $url): ?string`
2. **`controllers/AdminController.php`**
   - `uploadImage()`: procesar con GD a `php://memory` (`imagewebp`) y subir
     por `putObject`; devolver la URL pública. Mantener fallback a disco.
   - `edit()`: mover carpeta = `copiarObjeto`+`borrarPrefijo`.
   - `delete()`: borrar por prefijo del juego.
   - `carpetaJuegoAbsoluta()` deja de usar `is_dir` sobre S3.
3. **`models/Juego.php`** — helper `existeImagen(?string $ruta): bool` que
   distingue `http(s)://` de ruta local; se usa en SEO y en la vista.
4. **`HomeController.php:95`** y **`views/home/show.php:67`** — sustituir
   `file_exists()` por `Juego::existeImagen()`.
5. **`index.php`** — añadir `S3_PUBLIC_HOST` a `img-src` (y a `connect-src`
   si en el futuro se sube directo con presigned URLs).
6. **`docker-entrypoint.sh`** — añadir las variables R2 al heredoc del `.env`.

## 9. Migración de datos

Script de una pasada (`scratch/` está gitignored):

1. Recorrer `public/uploads/**`.
2. Subir cada archivo a R2 con clave `{consola}/{juego}/…` (los legados planos
   se reubican bajo la carpeta de su consola/juego).
3. Emitir los `UPDATE juegos SET imagen=…, capturas=…` con las URLs públicas.
4. Ejecutar contra la BD en uso (Neon) **después** de desplegar el código nuevo.
5. Verificar: contar objetos en el bucket vs. filas con imagen; `curl` a una
   URL pública; que el CSP no bloquee (consola del navegador sin errores).

## 10. Checklist de implementación

- [ ] Escribir este documento (hecho).
- [ ] `config/S3Client.php`.
- [ ] Adaptar `AdminController.php` (upload/move/delete).
- [ ] Helper `Juego::existeImagen()` + ajustes en `HomeController` y `show.php`.
- [ ] `index.php`: host R2 en el CSP.
- [ ] `docker-entrypoint.sh` + variables en Vercel.
- [ ] Bucket R2 creado, acceso público activado y credenciales emitidas.
- [ ] Script de migración y ejecución.
- [ ] Verificación end-to-end (subir un juego nuevo desde el admin).

## 11. Impacto y riesgos

- Las vistas no cambian salvo SEO y portada destacada (vía helper).
- No se toca `rom_proxy.php` ni el flujo de ROMs ni EmulatorJS.
- Con credenciales ausentes todo queda en modo local → tests y dev sin AWS.
- Coste estimado: **$0** dentro del free tier (7,6 MB y tráfico bajo).
- Startup de R2: puede tardar unas horas en activarse; requiere tarjeta.

## 12. Decisiones tomadas

- **Proveedor:** Cloudflare R2 (free tier permanente, egress $0).
- **Entrega:** bucket público vía `pub.<account>.r2.dev` o dominio propio.
- **Cliente:** SigV4 mínimo con endpoint configurable (sin AWS SDK).
- **Fallback:** modo local cuando no hay credenciales (dev y tests).
- **Portadas legadas:** migradas a `{consola}/{juego}/portada.webp`.

## 13. Referencias

- Producto: https://www.cloudflare.com/es-es/developer-platform/products/r2/
- Precios: https://developers.cloudflare.com/r2/pricing/
- `AGENTS.md` — convenciones del proyecto.
- `docs/2026-08-05-plan-mejoras-nivel-senior.md` — roadmap (marcar el ítem y
  el commit cuando se implemente).
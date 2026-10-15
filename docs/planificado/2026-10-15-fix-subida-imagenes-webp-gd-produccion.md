# Subida de imágenes en producción: falta soporte WebP en GD (Dockerfile)

**Fecha:** 2026-10-15
**Estado:** implementado (2026-10-15; pendiente de deploy en Vercel)
**Ámbito:** subida y conversión de imágenes del admin en producción (Vercel)
**Síntoma:** HTTP 500 al editar un juego con portada nueva
**Relacionados:** `Dockerfile`, `src/controllers/AdminController.php`
(`uploadImage`), `.github/workflows/ci.yml` (jobs `tests` y `build`)

---

## 1. Resumen del problema

Al editar un juego en producción (`roms-vault.vercel.app`) subiendo una portada,
la petición `POST /admin/edit/156` devuelve **HTTP 500**. En local (XAMPP) la
misma operación funciona.

El bug **no tiene relación** con la cola de traducciones ni con el worker de
GitHub Actions (PR #19): es un defecto preexistente de la ruta de subida de
imágenes, que hasta ahora no se había ejercitado en el contenedor de Vercel.

---

## 2. Causa raíz (evidencia)

Log de Vercel:

```
PHP Fatal error:  Uncaught Error: Call to undefined function imagecreatefromwebp()
in /var/www/html/src/controllers/AdminController.php:544
Stack trace:
#0 AdminController.php(236): AdminController->uploadImage(Array, 'pc/american-mcg...', 'portada')
#1 index.php(157): AdminController->edit('156')
```

Cadena de causas:

1. La portada subida es un **WebP**; el `switch` de `uploadImage()` entra al
   `case 'image/webp'` (L543) y llama a `imagecreatefromwebp()` (L544).
2. Esa función **no existe** en el contenedor porque el `Dockerfile` (L6)
   configura GD **sin** `--with-webp`:
   ```dockerfile
   docker-php-ext-configure gd --with-freetype --with-jpeg
   ```
   Por tanto tampoco existe `imagewebp()`.
3. El `@` de la L544 **no suprime** el error de una función inexistente: es un
   `Error` (fatal), no un `Warning`.
4. La guarda de la L522 comprueba `imagecreatefromjpeg`, **no** `imagewebp`.
5. La L554 usa `imagewebp()` **siempre** para convertir. Conclusión: en
   producción **cualquier** subida (JPEG/PNG/GIF incluidos) falla con el mismo
   500, no solo las WebP.

En local no se reproduce porque XAMPP trae GD compilado con WebP.

---

## 3. Impacto

- Toda subida de portada o capturas desde el admin en producción devuelve 500.
- Bloquea el alta/edición con imagen (y, de paso, impide verificar visualmente
  el drenado de la cola de traducciones al editar un juego).
- No afecta al sitio público ni a la lectura de contenido.

---

## 4. Por qué el CI no lo detectó

- El job `tests` instala `gd` con `setup-php` (que **sí** trae WebP); no refleja
  el contenedor de producción.
- El job `build` solo construye la imagen; no ejecuta ninguna subida.
- No hay ninguna comprobación de que el runtime de producción tenga
  `function_exists('imagewebp')`.

---

## 5. Fix propuesto

### a) `Dockerfile` — añadir WebP a GD

```dockerfile
RUN apt-get update && apt-get install -y \
    libpng-dev libjpeg-dev libfreetype6-dev libwebp-dev zip unzip git \
    libpq-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp \
    && docker-php-ext-install gd pdo pdo_mysql pdo_pgsql \
    && apt-get clean && rm -rf /var/lib/apt/lists/*
```

Cambios: añadir `libwebp-dev` a los paquetes y `--with-webp` al configure de GD.

### b) `AdminController::uploadImage()` — fallar con mensaje, no con 500

Comprobar la función que realmente se usa (`imagewebp`) y la del formato de
entrada, devolviendo el error controlado en lugar de provocar un fatal.

```php
// L522: la guarda debe mirar imagewebp (es la que se usa siempre en L554)
if (!extension_loaded('gd') || !function_exists('imagewebp')) {
    return ['success' => false, 'error' => t('msg.gd_no_activada')];
}
...
// case 'image/webp':
case 'image/webp':
    if (!function_exists('imagecreatefromwebp')) {
        return ['success' => false, 'error' => t('msg.gd_no_activada')];
    }
    $image = @imagecreatefromwebp($file['tmp_name']);
    break;
```

### c) (Opcional) Red de seguridad en CI

En el job `build`, tras `docker build`, comprobar que la imagen tiene WebP:

```yaml
- name: Verificar GD + WebP en la imagen
  run: docker run --rm roms-vault:ci php -r "exit(function_exists('imagewebp') ? 0 : 1);"
```

---

## 6. Plan de implementación

1. Rama nueva desde `main` (una vez mergeado el PR #19):
   `fix/upload-webp-gd-produccion`.
2. Aplicar (a) `Dockerfile` y (b) `AdminController::uploadImage`.
3. Aplicar (c) si se quiere blindar el CI (recomendado).
4. Commit + PR; esperar CI verde (los 5 checks).
5. **Rebuild + redeploy en Vercel** (cambia la imagen Docker).
6. Probar en producción.

---

## 7. Validación

- En producción: subir una portada **`.webp`** y otra **`.jpg`** → 200 y la
  imagen queda guardada y visible.
- En el contenedor:
  `php -r "var_dump(function_exists('imagecreatefromwebp'), function_exists('imagewebp'));"`
  → ambos `true`.
- Comprobar que, si algún día falta GD, el admin responde con el mensaje
  `msg.gd_no_activada` y **no** con un 500.

---

## 8. Notas

- **Workaround mientras tanto:** editar el juego **sin subir imagen nueva**
  (solo cambiar la descripción) no pasa por `uploadImage()` y por tanto **sí**
  encola el trabajo de traducción.
- **Mejora opcional (fuera de alcance):** `uploadImage()` decide el formato con
  `$file['type']`, que es el MIME que envía el cliente. Valorar validar el tipo
  real con `finfo_file()` antes de elegir la función de carga.

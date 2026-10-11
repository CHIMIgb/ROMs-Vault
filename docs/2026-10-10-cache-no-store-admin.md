# Cache-Control: no-store en páginas de admin (ítem 9.4)

- **Fecha:** 2026-10-10
- **Rama:** `pendientes-seguridad-rendimiento`
- **Commit de código:** `420c2f2`
- **Plan origen:** `docs/_archivo/2026-10-07-plan-mejoras-seguridad-y-rendimiento.md` (§9.4)

---

## 1. Problema

El ítem 9.4 pedía añadir `Cache-Control: no-store` a las páginas HTML de admin
si no existía. Verificado con `grep`:

- **Sí lo tenían** los entrypoints de `endpoints/`: `ajax_admin.php:18`,
  `ajax_emulador.php:16`, `ajax_consola.php:16`, `ajax_categoria.php:16`,
  `ajax_catalog.php:17`, `ajax_lang.php:18` y `rom_proxy.php:616-617`.
- **No lo tenía** ninguna página HTML servida por `index.php`, incluidas todas
  las del panel (`AdminController`), que muestran datos de gestión sensibles.

---

## 2. Solución

En `index.php`, justo después del bloque de sanitización de rutas (antes de
cargar el controlador), se emite la cabecera para cualquier ruta cuyo
`controller === 'admin'`:

```php
if ($controller === 'admin') {
    header('Cache-Control: no-store');
}
```

Ventajas de la ubicación:

- Cubre **todas** las respuestas del panel, incluidos los `302` al login y los
  `403` de `AuthMiddleware`, porque se emite antes de que el controlador decida.
- **No** afecta al resto de páginas públicas (home, catálogo, ficha, play),
  cuyas respuestas sí pueden cachearse.
- No interfiere con los JSON de `endpoints/` (que no pasan por `index.php`).

Se usó el valor mínimo `Cache-Control: no-store`, igual que los endpoints
existentes, para mantener la convención del repo.

---

## 3. Archivos modificados

| Acción | Archivo | Cambio |
|--------|---------|--------|
| MODIFICAR | `index.php` | `header('Cache-Control: no-store')` para rutas del controlador `admin` |

---

## 4. Verificación (evidencia)

- `php -l` de `index.php` → sin errores.
- Smoke `php -S localhost:8141 router.php` (`curl.exe -D -`):
  - `GET /admin/dashboard` **sin sesión** → `HTTP/1.1 302 Found` +
    `Cache-Control: no-store`.
  - `GET /` → `HTTP/1.1 200 OK` **sin** `Cache-Control: no-store`.
- `composer test` → **OK (166 tests, 7489 assertions)**.
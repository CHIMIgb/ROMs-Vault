# Lote P0 Seguridad + Rendimiento del Catálogo (2026-08-02)

## Contexto

Se implementó el lote P0 de la auditoría de backend (`docs/2026-08-02-auditoria-backend.md`)
más las mejoras de carga inicial/perf aprobadas:

1. **CSRF** en todos los POST y mutadores GET del panel admin y login.
2. **`.htaccess`** en sintaxis Apache 2.4 (Require all denied).
3. **Ocultar detalle PDO** al fallar la conexión a BD.
4. **Rate limit** en el login (anti fuerza bruta).
5. **`loading="lazy"`** en el grid del catálogo.
6. **Orden por defecto `recientes`** (en vez de `RANDOM()`).
7. **Índices SQL** de rendimiento.

---

## 1. Protección CSRF

### Servicio: `config/CsrfService.php` (nuevo)

Patrón **Double Submit Cookie**:

- Token aleatorio de 64 hex por navegador, guardado en cookie `rv_csrf`
  (`httpOnly` + `SameSite=Strict`, 12 h de vida, renovable).
- El mismo token se inyecta en cada formulario (`CsrfService::field()`) y en
  `<meta name="csrf-token">` (`CsrfService::metaTag()`) para peticiones AJAX.
- `verify()` compara el token recibido (POST `csrf_token`, header
  `X-CSRF-Token` o query `csrf_token`) contra la cookie usando `hash_equals()`.
- `verifyAjax()` exige el header `X-CSRF-Token` (para los toggles AJAX).
- `deny()` responde 403: JSON para AJAX, HTML (`views/errors/generic.php`)
  para navegación normal. No existe `views/errors/403.php` en el proyecto.

### Integración

- `index.php`:
  - `require_once` de `CsrfService.php`.
  - `CsrfService::ensureToken()` al inicio, antes de cualquier salida HTML.
  - Verificación global: todo `REQUEST_METHOD === 'POST'` debe pasar `verify()`
    o se responde 403 antes de ejecutar el controlador.
- `views/layout/header.php`: `<meta name="csrf-token">` condicionado a
  `class_exists('CsrfService')` (por si una vista se incluye fuera del router).
- Campos ocultos en los 8 formularios POST:
  - `views/auth/login.php`
  - `views/admin/add.php`, `views/admin/edit.php`
  - `views/admin/categorias/add.php`, `views/admin/categorias/edit.php`
  - `views/admin/consolas/add.php`, `views/admin/consolas/edit.php`
  - `views/admin/emuladores/edit.php`
- Los 4 fetch AJAX admin envían `X-CSRF-Token` (token leído del meta tag
  dentro de la IIFE):
  - `views/admin/dashboard.php`
  - `views/admin/categorias/index.php`, `views/admin/consolas/index.php`,
    `views/admin/emuladores/index.php`

### Mutadores protegidos (además del POST global)

- `AdminController`: `toggleActive`, `toggleActiveAjax`, `delete`
- `CategoriaController`: `delete`, `toggleActiveAjax`
- `ConsolaController`: `delete`, `toggleActiveAjax`
- `EmuladorController`: `toggleActiveAjax`

### Bug preexistente corregido en el camino

El JS de `views/admin/categorias/edit.php` y `views/admin/consolas/edit.php`
referenciaba `btn-eliminar-*` que no existía en el HTML (null → error JS).
Se agregó el botón Eliminar (con `data-id`/`data-nombre`) y el delete se
convirtió de **GET** (`window.location`) a **POST** con token CSRF mediante un
formulario oculto `form-eliminar-*` que incluye `input[name=id]`. El `onOk`
de `RVAlerts.confirm` ahora hace `submit()` del form.

Como el router solo entrega `$id` de GET, `CategoriaController::delete($id = null)`
y `ConsolaController::delete($id = null)` leen `$_POST['id']` como fallback
cuando el id no viene del router.

### Archivo descartado: `bios_htaccess.htaccess`

Se creó un borrador para bloquear `public/bios/`, pero se **descartó**:
`views/home/play.php:265-268` asigna `window.EJS_biosUrl`, que EmulatorJS
consume **desde el navegador del cliente** (hace fetch directo a
`public/bios/ps1/`). Bloquear el directorio rompería la emulación de PS1.
El archivo fue eliminado.

---

## 2. `.htaccess` en Apache 2.4

`.htaccess` raíz:

- `<FilesMatch "\.(env|log|sql|sh|bak)$">` → `Require all denied`
  (antes `Order Allow,Deny` + `Deny from all`, sintaxis Apache 2.2 que no
  aplica en Apache 2.4 sin `mod_access_compat`).
- Se eliminó la directiva `<Directory>` (no es válida dentro de `.htaccess`).

---

## 3. Ocultar detalle PDO

`config/database.php`: el catch de `PDOException` ya no expone
`$e->getMessage()` al cliente.

- Antes: `die("Error de conexión a la base de datos. Detalle técnico: ...")`
- Ahora: `error_log()` con el detalle + `http_response_code(503)` +
  mensaje genérico sin fuga de información.

---

## 4. Rate limit en login

### Servicio: `config/RateLimiter.php` (nuevo)

- Mismo patrón que `rom_proxy.php` (archivos JSON en
  `sys_get_temp_dir()/rv_rate_limit/<namespace>/<md5(ip)>.json`).
- Ventana deslizante con limpieza probabilística de archivos viejos.
- `clientIp()` respeta `X-Forwarded-For` / `X-Real-IP` (proxies).
- `respond429()`: JSON para AJAX, HTML para navegación normal, con header
  `Retry-After`.
- `reset()`: borra el contador (usado tras login exitoso).

### Integración

`controllers/AuthController::login()`:

- Límite por defecto: **5 intentos / 900 s** (15 min), override con env
  `AUTH_LOGIN_MAX` / `AUTH_LOGIN_WINDOW`.
- Se ejecuta antes de validar credenciales.
- `RateLimiter::reset()` tras login exitoso (solo cuentan intentos fallidos).

---

## 5. `loading="lazy"` en el grid

- `views/home/index.php:88` y `ajax_catalog.php:57`: las `<img>` del grid del
  catálogo ahora llevan `loading="lazy" decoding="async"` (antes eager).
- También se aplicó a las imágenes renderizadas por el JS del catálogo
  (`views/home/index.php:302`).
- `views/components/related_games.php` ya usaba `loading="lazy"` (sin cambios).

---

## 6. Orden por defecto `recientes`

Se eliminó `RANDOM()` como fallback del orden del catálogo, que forzaba un
sort completo de la tabla en cada request sin filtros.

- `controllers/HomeController.php:31-33`: fallback `'random'` → `'recientes'`.
- `ajax_catalog.php`: si no llega `orden`, se fuerza `'recientes'`.
- `models/Juego.php` `resolveOrder`: fallback `'RANDOM()'` →
  `'j.created_at DESC'` (indexable). La opción explícita `'random' => 'RANDOM()'`
  se conserva en el mapa.
- `views/home/index.php`: el select de orden marca `recientes` como seleccionado
  cuando no hay `?orden` en la URL; el botón Limpiar resetea a `recientes`
  (antes `titulo`).

Nota: `RANDOM()` sigue usándose intencionalmente en secciones de variedad
(`relacionadosPorConsola`, `queryGenero`, popularidad) — fuera del alcance.

---

## 7. Índices SQL de rendimiento

### `data/migrations/2026-08-02-indices-rendimiento.sql` (nuevo)

Todos idempotentes (`IF NOT EXISTS`), PostgreSQL:

```sql
-- 1) findByFileId() — usado en cada play/download vía rom_proxy
CREATE INDEX IF NOT EXISTS idx_juegos_drive_file_id ON public.juegos(google_drive_file_id);

-- 2) Catálogo público: activo + filtros comunes
CREATE INDEX IF NOT EXISTS idx_juegos_activo_consola   ON public.juegos(activo, consola_id);
CREATE INDEX IF NOT EXISTS idx_juegos_activo_categoria ON public.juegos(activo, categoria_id);
CREATE INDEX IF NOT EXISTS idx_juegos_activo_region    ON public.juegos(activo, region);

-- 3) Orden por defecto "recientes"
CREATE INDEX IF NOT EXISTS idx_juegos_activo_creado    ON public.juegos(activo, created_at DESC);

-- 4) Rankings (Top 5 descargas / jugadas)
CREATE INDEX IF NOT EXISTS idx_juegos_activo_descargas ON public.juegos(activo, downloads_count DESC);
CREATE INDEX IF NOT EXISTS idx_juegos_activo_jugadas   ON public.juegos(activo, plays_count DESC);
```

### `data/roms-vaultDB-postgreSQL.sql`

Se añadieron los mismos 6 índices al bloque de índices del schema (para
instalaciones nuevas). El resto del schema no se tocó.

Nota: `idx_juegos_activo_jugadas` duplica conceptualmente `idx_juegos_jugadas`
del schema original (existente); se mantiene porque el orden por popularidad
usa `plays_count DESC` y no requiere cambios de queries.

---

## Verificación

Sin PHP CLI en el entorno: la verificación fue **estática** (lectura de diffs,
greps de `CsrfService::field()`, `loading="lazy"`, `RANDOM()`, `Order Allow,Deny`).

Chequeos realizados:

- 10 usos de `CsrfService::field()` (8 forms + 2 forms ocultos de delete).
- Sin restos de `Order Allow,Deny` / `Deny from all` (el único `.htaccess`
  era `bios_htaccess.htaccess`, eliminado).
- `RANDOM()` solo queda donde es intencional (mapa `'random'` y variedad).
- Nombres de columnas de índices coinciden con el schema
  (`google_drive_file_id`, `region`, `downloads_count`, `plays_count`).

---

## Archivos del lote

| Acción | Archivo |
| --- | --- |
| CREAR | `config/CsrfService.php` |
| CREAR | `config/RateLimiter.php` |
| CREAR | `data/migrations/2026-08-02-indices-rendimiento.sql` |
| MODIFICAR | `index.php` (ensureToken + verificación POST global) |
| MODIFICAR | `.htaccess` (Apache 2.4) |
| MODIFICAR | `config/database.php` (sin fuga PDO) |
| MODIFICAR | `controllers/AdminController.php`, `CategoriaController.php`, `ConsolaController.php`, `EmuladorController.php` (CSRF en mutadores) |
| MODIFICAR | `controllers/AuthController.php` (rate limit login) |
| MODIFICAR | `controllers/HomeController.php`, `ajax_catalog.php`, `models/Juego.php` (default recientes) |
| MODIFICAR | `views/layout/header.php` (meta csrf-token) |
| MODIFICAR | 8 vistas admin/auth (campo CSRF) + 4 vistas admin (header AJAX) |
| MODIFICAR | `views/admin/categorias/edit.php`, `views/admin/consolas/edit.php` (botón eliminar + POST) |
| MODIFICAR | `views/home/index.php` (lazy loading + default recientes) |
| MODIFICAR | `data/roms-vaultDB-postgreSQL.sql` (6 índices) |
| ELIMINAR | `bios_htaccess.htaccess` (idea descartada) |

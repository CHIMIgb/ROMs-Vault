# Auditoría de Backend — ROMs Vault

**Fecha:** 2026-08-02
**Alcance:** Revisión completa del backend PHP (MVC), punto por punto.
**Versión PHP objetivo:** 8.2 (según `Dockerfile`) — se pueden usar enums, `match`, `readonly`, nullsafe, etc.
**Base de datos:** PostgreSQL (Neon, SSL) vía PDO.

---

## Resumen ejecutivo

El backend es un MVC PHP hecho a medida, correcto en lo básico: usa **prepared statements**, **whitelist de ordenamiento**, **JWT en cookie httpOnly**, **firma HMAC** para el proxy de ROMs y **rate limiting** en el proxy. El catálogo público **ya está paginado** (20 juegos/página, server + AJAX), por lo que el problema de "cargar todo de golpe" no está en la query principal sino en los detalles que se explican en la sección 1.

Los problemas más graves encontrados, en orden:

| # | Prioridad | Problema | Riesgo |
|---|-----------|----------|--------|
| 1 | **P0** | Sin protección CSRF en ningún POST admin ni login; cambios de estado y borrados vía GET | Secuestro de sesión admin |
| 2 | **P0** | `.htaccess` protege `.env`/`.log`/`.sql` con sintaxis Apache 2.2 (`Order Allow,Deny`) que no aplica en Apache 2.4 | Exposición de credenciales |
| 3 | **P0** | `database.php` muestra el detalle de la excepción PDO en pantalla (`die(... $e->getMessage())`) | Fuga de datos de conexión |
| 4 | **P1** | `google_drive_file_id` **no tiene índice** y se busca por cada play/download/proxy | Full scan por request |
| 5 | **P1** | El grid del catálogo carga las 20 portadas **sin `loading="lazy"`**; orden por defecto `RANDOM()` | Percepción de carga total + costo de sort |
| 6 | **P1** | Export de `usuarios` expone `password_hash` y PII en un XLSX | Fuga de credenciales |
| 7 | **P1** | `bios_debug.log` se escribe en disco en cada `play()` (código de debug) | I/O inútil + rutas internas |
| 8 | **P1** | Login sin rate limiting / bloqueo por intentos | Brute force |
| 9 | **P2** | `getRelated()` y `getCatalogStats()` hacen 5-8 queries por ficha de detalle | Rendimiento |
| 10 | **P2** | `X-Forwarded-For` se acepta sin validar (spoofeable) en el rate limit del proxy | Bypass del rate limit |

---

## 1. Carga inicial del catálogo y paginación (punto solicitado)

### Estado actual (lo que YA funciona)

- `HomeController::index()` (controllers/HomeController.php:35-47) configura paginación de **20 por página** y usa `getWithRelationsPaginated()` + `countWithFilters()`.
- `ajax_catalog.php` repite la misma paginación para el filtrado en tiempo real (máx. 20 por request).
- `views/home/index.php` renderiza el grid con `$juegos` (ya paginado) y la paginación con `Pagination::render()`.
- El JS de `views/home/index.php` usa `fetch('ajax_catalog.php?...&page=N')` → **la query ya está paginada**.

### Lo que el usuario percibe como "cargar todo de golpe"

1. **`<img>` sin `loading="lazy"` en el grid** (views/home/index.php:88 y ajax_catalog.php:55).
   Al entrar, el navegador descarga las 20 portadas **simultáneamente** (eager). Con 20 o más, la página "pesa" todo de golpe. Las imágenes de `related_games.php` y del autocompletado **sí** usan `loading="lazy"`, pero las del grid principal no.
   → **Fix:** `loading="lazy" decoding="async"` en las `<img>` del grid (ambas copias). No cambia la query, solo el comportamiento del navegador.

2. **Orden por defecto `RANDOM()`** (controllers/HomeController.php:31-33 → models/Juego.php:136).
   La primera carga sin filtros usa `ORDER BY RANDOM()`, que fuerza un **sort completo de la tabla** en cada request (y en cada cambio de filtro). Con pocos cientos de juegos es tolerable; con miles, notable.
   → **Fix:** default `recientes` (`j.created_at DESC` ya indexable) o `titulo`. Si se quiere aleatorio, hacerlo por una semilla aleatoria (`ORDER BY (j.id * 2654435761) % 4294967296` con un seed) o precalcular un orden en lote, nunca `RANDOM()` por request.

3. **`COUNT(*)` por cada request** (models/Juego.php:189-223).
   `countWithFilters()` recorre la tabla con los mismos filtros cada vez (una vez en el render inicial y otra en cada fetch AJAX). Con `ILIKE '%...%'` no puede usar índices.
   → **Fix:** cachear el total con TTL corto (30-60 s) en Redis/APCu o por sesión; o usar keyset pagination (ver abajo) que no requiere COUNT.

4. **Faltan índices de apoyo** (data/roms-vaultDB-postgreSQL.sql:117-125).
   Existen `idx_juegos_consola`, `idx_juegos_categoria`, `idx_juegos_activo`. Faltan:
   - `google_drive_file_id` — **crítico**: `findByFileId()` se llama en `play()`, `download()` y cada request del proxy (rom_proxy.php:172). Sin índice = full scan por request.
   - Índices compuestos para el catálogo: `(activo, consola_id)`, `(activo, categoria_id)`, `(activo, region)`.
   - Índices para los ordenamientos: `downloads_count DESC`, `plays_count DESC`, `created_at DESC`, `fecha_lanzamiento DESC`.
   - Para `titulo ILIKE '%x%'`: solo un índice **GIN con pg_trgm** ayuda (`CREATE EXTENSION pg_trgm; CREATE INDEX ... USING gin (titulo gin_trgm_ops)`).

5. **Paginación OFFSET** (models/Juego.php:164).
   Con OFFSET, la página 5000 recorre 100.000 filas. Para colecciones grandes → **keyset pagination** (`WHERE j.id < :cursor ORDER BY j.id DESC LIMIT 20`), más rápido y estable con ordenamientos por `created_at`/`id`.

6. **Código muerto:** `getWithRelations()` sin paginar (models/Juego.php:49-74) no se usa en ninguna parte → eliminarlo o documentarlo para evitar que alguien lo use y cargue todo.

7. **`Consola::all()` y `Categoria::all()`** se ejecutan en cada carga del catálogo (controllers/HomeController.php:50-51) para poblar los selects. Con decenas de filas es barato, pero es un patrón a cachear si crecen.

### Plan de mejora recomendado (carga inicial)

1. `loading="lazy" decoding="async"` en `<img>` del grid (index.php + ajax_catalog.php).
2. Default de orden → `recientes` (evita `RANDOM()` en la primera carga).
3. Índice único/regular en `google_drive_file_id` y compuestos para el catálogo.
4. (Opcional) Keyset pagination y cache del COUNT.

---

## 2. Enrutamiento y front controller

**Archivo:** index.php, .htaccess

- ✅ `controller`/`action` validados con regex (index.php:13).
- ⚠️ **Rewriting inconsistente:** el `.htaccess` reescribe todo a `index.php?_url=/$1` (línea 8), pero `index.php` **nunca lee `$_GET['_url']`**: el routing usa `?controller=&action=` por query string. El `_url` es código muerto y confuso.
- ⚠️ **Sin autoloading PSR-4:** cada controller/model hace `require_once` manual (incluso duplicado: `config/AuthMiddleware.php` se incluye en index.php y de nuevo en controllers). Composer ya existe en el proyecto pero no está configurado para autoload propio.
- ⚠️ **Sin manejo centralizado de errores:** una excepción no capturada produce white screen; los 404/403 se repiten inline 3 veces en index.php.
- ⚠️ **Sin router de método HTTP:** no hay validación a nivel de ruta de GET/POST (se hace a mano en cada controller).

---

## 3. Configuración y entorno

**Archivos:** config/database.php, config/JWTService.php, .env.example, docker-entrypoint.sh, Dockerfile

- ✅ dotenv `safeLoad()`, PDO con `ERRMODE_EXCEPTION` y `EMULATE_PREPARES=false` (correcto).
- ⚠️ **`die()` expone el detalle PDO al cliente** (config/database.php:48). En producción debe loguearse y mostrar un mensaje genérico.
- ⚠️ **Dotenv se carga dos veces** (database.php y JWTService.php) sin un bootstrap único.
- ⚠️ **`.env` escrito en disco con credenciales** (docker-entrypoint.sh:14-29): necesario para Apache+PHP en contenedor, pero las credenciales quedan en texto plano en el filesystem del contenedor. Restringir permisos del archivo (chmod 600).
- ⚠️ **`.htaccess` con sintaxis Apache 2.2** (`Order Allow,Deny`, líneas 18-20). En Apache 2.4 (php:8.2-apache) solo funciona si `mod_access_compat` está activo; por defecto **no protege `.env`, `*.log`, `*.sql`**. Usar `Require all denied` + `<FilesMatch>`.
- ⚠️ **`public/bios/` y `public/uploads/`** son servidos tal cual. Los BIOS de PS1 (`scph1001.bin`, etc.) son binarios con copyright y quedan descargables por URL. Bloquear `public/bios/` y proteger `public/uploads/` de ejecución PHP.
- ⚠️ **Dockerfile:** instala `pdo_mysql` que no se usa (la app es solo PostgreSQL).
- ⚠️ **`bios_debug.log`** se genera en runtime (controllers/HomeController.php:219) y contiene rutas internas del desarrollador (`C:\Users\chimi\...`). Eliminar el `file_put_contents` (es código de debug).

---

## 4. Seguridad

**Archivos:** config/AuthMiddleware.php, config/JWTService.php, controllers/*, ajax_*.php, rom_proxy.php, .htaccess

### 4.1 Autenticación (JWT)

- ✅ Cookie `httpOnly` + `SameSite=Strict` + `Secure` cuando HTTPS (config/JWTService.php:135-146).
- ✅ Auto-refresh de token dentro del umbral.
- ⚠️ **Falta verificación de rol:** `AuthMiddleware::requireAuth()` solo comprueba que exista token (config/AuthMiddleware.php:19-26). El payload trae `rol_id`, pero **no se valida** que sea admin. Cualquier usuario con token válido entra al panel admin.
- ⚠️ **Login sin protección:** sin rate limiting, sin CAPTCHA, sin bloqueo temporal tras N intentos (controllers/AuthController.php:7-26). Expuesto a brute force.
- ⚠️ **Sin prefijo `__Secure-`** en el nombre de la cookie.

### 4.2 CSRF (CRÍTICO)

- ❌ **No existe protección CSRF en ningún formulario**: login, alta/edición de juegos, consolas, categorías, emuladores.
- ❌ **Cambios de estado y borrados por GET**: `toggleActive`, `toggleActiveAjax`, `delete` (admin, categorías, consolas) se disparan con `index.php?controller=admin&action=delete&id=5` (controllers/AdminController.php:158-205, 268-277; CategoriaController.php:110-131; ConsolaController.php:117-138).
  → Un atacante puede hacer que un admin autenticado borre/desactive contenido con una simple imagen `<img src=".../action=delete&id=5">`.
  → **Fix:** token CSRF por sesión + verificar en cada POST; convertir los mutadores a POST/PATCH; al menos exigir `Origin/Referer` consistente.

### 4.3 SQL Injection / XSS

- ✅ Prepared statements en el código activo; whitelist de `ORDER BY` (models/Juego.php:128-139).
- ✅ Escapado con `htmlspecialchars()` en las vistas revisadas.
- ⚠️ **`Model::create()/update()`** interpolan nombres de columnas (models/Model.php:23-41). Seguro solo si `$data` siempre proviene de claves controladas (hoy sí: los controllers fijan las claves). Mantener esa invariante o whitelist de columnas.
- ⚠️ En `index.php:88` del catálogo el `src` de la imagen se escapa, correcto.

### 4.4 Uploads

- ⚠️ **MIME del cliente no es fiable** (controllers/AdminController.php:213-216 usa `$file['type']`). El flujo GD (`imagecreatefrom*`) valida de facto que sea imagen, pero:
  - `imagecreatefromwebp/png/jpeg` puede consumir mucha memoria con imágenes de dimensiones enormes → validar dimensiones (`getimagesize`) y limitar (p. ej. máx. 4000×4000).
  - Se usa `@` silenciado; si falla, el mensaje no distingue causa.
  - No hay límite de dimensiones ni recompresión a un ancho máximo.
- ✅ Nombre de archivo seguro (`uniqid()_time().webp`) y conversión a WebP.

### 4.5 Headers y proxy

- ✅ Firma HMAC de URLs del proxy con TTL 2h y `hash_equals` (rom_proxy.php:92-119).
- ✅ Rate limiting por IP en el proxy con ventana deslizante.
- ⚠️ **`X-Forwarded-For` sin validación** (rom_proxy.php:151-156): si el deploy no confía en los proxies intermedios, el atacante manda el header que quiera y **bypassa el rate limit**. En Vercel los headers de proxy se pueden confiar, pero validar contra la lista de proxies o al menos limitar longitud/duplicados.
- ⚠️ **CORS `*` por defecto** (rom_proxy.php:38-40) si `ALLOWED_ORIGINS` está vacío. En producción debe estar configurado.
- ⚠️ **`CURLOPT_SSL_VERIFYPEER => false`** en el preflight interno de play (controllers/HomeController.php:319). Quitar si el entorno lo permite.
- ⚠️ **Faltan headers de seguridad en páginas HTML**: CSP, HSTS, `X-Frame-Options`, `Referrer-Policy`, `Permissions-Policy` (solo están en el proxy).
- ⚠️ **Preflight cURL interno al propio servidor** en cada play (controllers/HomeController.php:306-346): doble request (interno + externo) por cada jugada; si el proxy cae, encadena latencia. Considerar validar localmente (con el rate limit y la BD) sin cURL.

### 4.6 Miscelánea

- ⚠️ `Export` de `usuarios` incluye `password_hash` (models/Export.php:143) y emails (PII) en un XLSX descargable. No exportar hashes; enmascarar PII.
- ⚠️ La tabla `descargas` registra `ip_address`, `user_agent`, `cookie_id` (PII). No hay código que la use (feature muerta) — o implementarla con finalidad clara o no recolectar esos datos.
- ⚠️ `index.php` no fija `display_errors` según entorno (en producción debe estar off).

---

## 5. Base de datos y consultas

**Archivos:** models/*, data/roms-vaultDB-postgreSQL.sql

- ✅ FK con `ON DELETE SET NULL/CASCADE` razonables; trigger `updated_at`.
- ⚠️ **Índices incompletos** (ver sección 1.4). En especial `google_drive_file_id`.
- ⚠️ **`getRelated()`** (models/Juego.php:392-566) ejecuta hasta 5-6 queries por ficha: `relacionadosPorConsola` + hasta 4 niveles de `queryGenero` + `categoriasRelacionadas()` (que carga TODAS las categorías con `allActivas()` cada vez) + re-`findWithDetails()` en nivel 3. Simplificable a 2-3 queries (una por sección con `CASE`, o cargar las categorías una sola vez por request).
- ⚠️ **`getCatalogStats()`** (models/Juego.php:707-728): 4 subqueries `COUNT` correlacionadas sobre toda la tabla por ficha de detalle. Con miles de juegos, 4 full scans por visita. Cachear los totales por consola/categoría o calcularlos con una sola query con `COUNT() FILTER`.
- ⚠️ **`RANDOM()`** en orden default y en `relacionadosPorConsola`/`queryGenero` (models/Juego.php:420,557).
- ⚠️ `ILIKE '%x%'` sin índice pg_trgm → full scan en cada tecleo del buscador (debounce 380 ms).
- ⚠️ **Sin connection pooling** para Postgres en la nube (pgbouncer recomendable para Neon bajo muchos requests).
- ⚠️ `Export.php` consulta **todas** las filas de la tabla (sin límite) — para catálogos grandes puede agotar memoria. Stream del XLSX o límite.

---

## 6. Arquitectura / MVC / calidad de código

- ⚠️ **Sin PSR-4 autoload** y `require_once` manual repetido.
- ⚠️ **Sin `declare(strict_types=1)`**; tipado inconsistente entre modelos (Categoria/Consola/Emulador sí tipan; Juego/Model/controllers no).
- ⚠️ Uso de **`die()`** para errores de flujo (play, download, database) en lugar de excepciones + página de error.
- ⚠️ **Lógica de vista dentro de la vista**: el JS de `views/home/index.php` (300+ líneas) y `views/admin/dashboard.php` (200+ líneas) está inline; `public/js/script.js` está vacío pero se incluye en el footer de todas las páginas (request innecesario).
- ⚠️ **Duplicación del template del grid** (~50 líneas idénticas) entre `views/home/index.php` y `ajax_catalog.php`. Extraer a `views/components/game_grid.php`.
- ⚠️ `getWithRelations()` sin paginar es **código muerto**.
- ⚠️ En `play()` el `incrementPlays()` se ejecuta incluso cuando el core no existe (comentado como intencional) — revisar si contamina métricas.
- ⚠️ Controllers de admin no validan longitudes máximas (`titulo` hasta 200, `idiomas` 200, etc.) → errores de truncamiento/consistencia.
- ✅ Buenos patrones: whitelist de orden, builders de WHERE reutilizables (`buildAdminWhere`), transacciones en `Emulador::replaceForConsola`.

---

## 7. Observabilidad y operaciones

- ⚠️ Solo `error_log()` plano; sin request ID, sin niveles, sin contexto (usuario/acción).
- ⚠️ **Sin health checks** (`/healthz`) para el contenedor.
- ⚠️ Sin métricas (latencia, consultas lentas, tasa de errores del proxy).
- ⚠️ **`set_time_limit(0)`** en el proxy (rom_proxy.php:65) puede chocar con límites de plataforma (Vercel). Monitorear streams largos.
- ⚠️ Sin alertas sobre errores del proxy (429/502 de Google Drive).
- ⚠️ Sin tareas programadas: no hay limpieza de imágenes huérfanas en `public/uploads` cuando se borra un juego (solo se borra la imagen en `AdminController::delete`), ni limpieza de cache del proxy (`sys_get_temp_dir()/romproxy_cache` se limpia solo por TTL).

---

## 8. Testing y CI/CD

- ❌ Sin tests unitarios ni de integración.
- ❌ Sin linter/static analyzer (PHPStan/Psalm).
- ⚠️ No hay pipeline CI visible (el deploy es vía Vercel por `.vercel`).
- ⚠️ No hay base de datos de test ni fixtures.

---

## 9. Priorización y plan de trabajo sugerido

### P0 — Seguridad (hacer ya)
1. Protección CSRF: token en sesión + verificación en todos los POST; mutadores a POST (toggle/delete); al menos validar Origin/Referer.
2. Arreglar `.htaccess` a sintaxis Apache 2.4 (`Require all denied` para `.env`, `*.log`, `*.sql`, `bios_debug.log`) + bloquear `public/bios/`.
3. `database.php`: no exponer `$e->getMessage()` al cliente en producción.
4. Login: rate limit simple (mismo patrón que rom_proxy) + bloqueo temporal por usuario/IP.

### P1 — Performance y exposición de datos
5. `loading="lazy" decoding="async"` en el grid del catálogo (index.php + ajax_catalog.php).
6. Orden por defecto `recientes` en lugar de `random`.
7. Índice en `google_drive_file_id` + índices compuestos del catálogo + opcional pg_trgm para títulos.
8. Eliminar `bios_debug.log` y el `file_put_contents`.
9. Export: excluir `password_hash` y enmascarar PII; validar rol en `requireAuth` (verificar `rol_id`).
10. Configurar `ALLOWED_ORIGINS` en producción.

### P2 — Optimización y mantenibilidad
11. Reducir queries de `getRelated()`/`getCatalogStats()` (1-2 queries por sección; cache de totales).
12. Extraer grid a componente; mover JS inline a `public/js/`.
13. Autoload PSR-4 + bootstrap único (dotenv una vez).
14. Keyset pagination + cache del COUNT.
15. Health checks, logging estructurado con request ID.
16. Tests unitarios de los modelos de consulta (Juego paginación/relacionados) y del proxy (firma, rate limit).
17. Validación de longitudes/formatos en controllers admin (consistencia con el esquema).

---

## Archivos revisados

- `index.php`, `.htaccess`, `Dockerfile`, `docker-entrypoint.sh`, `composer.json`, `.env.example`, `.gitignore`
- `config/`: `database.php`, `AuthMiddleware.php`, `JWTService.php`
- `controllers/`: `HomeController.php`, `AdminController.php`, `AuthController.php`, `CategoriaController.php`, `ConsolaController.php`, `EmuladorController.php`, `ErrorsController.php`, `ExportController.php`
- `models/`: `Model.php`, `Juego.php`, `Consola.php`, `Categoria.php`, `Emulador.php`, `Export.php`, `Usuario.php`
- `ajax_*.php`: `ajax_catalog.php`, `ajax_admin.php`, `ajax_categoria.php`, `ajax_consola.php`, `ajax_emulador.php`, `ajax_autocomplete.php`
- `rom_proxy.php`
- `views/`: `layout/header.php`, `layout/footer.php`, `home/index.php`, `home/show.php`, `home/play.php` (parcial), `components/*`, `admin/dashboard.php`, `admin/add.php`, `admin/categorias/*`, `admin/consolas/*`, `admin/emuladores/*`, `auth/login.php`
- `data/roms-vaultDB-postgreSQL.sql`

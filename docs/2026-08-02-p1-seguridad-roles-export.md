# Lote P1 Seguridad — Roles, Export y Debug (2026-08-02)

## Contexto

Segundo lote de la auditoría de backend (`docs/2026-08-02-auditoria-backend.md`),
centrado en los 4 hallazgos P1 de seguridad pendientes:

1. **Export de `usuarios`** exponía `password_hash` y emails (PII) en el XLSX.
2. **`bios_debug.log`** se escribía en disco en cada `play()` con rutas internas.
3. **`AuthMiddleware`** solo comprobaba que existiera token; no validaba `rol_id`
   → cualquier usuario con token válido entraba al panel admin.
4. **`CURLOPT_SSL_VERIFYPEER => false`** en el preflight de `play()`.

---

## 1. Validación de rol admin

### `config/AuthMiddleware.php`

- Nueva constante `ADMIN_ROLE_ID = 1` (rol "Administrador" según la semilla de
  `data/import_data.sql`).
- Nuevo método `requireAdmin()`:
  - Sin sesión válida → redirige al login (igual que `requireAuth()`).
  - Autenticado pero `rol_id !== 1` → redirige al catálogo público (`index.php`).
- Nuevo método `requireAdminAjax()`:
  - Sin sesión o sin rol admin → `http_response_code(403)` + `Alert::render(...)`.
- `requireAuth()` / `requireAuthAjax()` se conservan como guardas genéricas de
  "autenticado" (sin restricción de rol) por si hacen falta en el futuro.

### Llamadas actualizadas (panel admin completo)

| Archivo | Cambio |
| --- | --- |
| `controllers/AdminController.php` | constructor → `requireAdmin()` |
| `controllers/CategoriaController.php` | constructor → `requireAdmin()` |
| `controllers/ConsolaController.php` | constructor → `requireAdmin()` |
| `controllers/EmuladorController.php` | constructor → `requireAdmin()` |
| `controllers/ExportController.php` | constructor → `requireAdmin()` |
| `ajax_admin.php` | `requireAuthAjax()` → `requireAdminAjax()` |
| `ajax_categoria.php` | `requireAuthAjax()` → `requireAdminAjax()` |
| `ajax_consola.php` | `requireAuthAjax()` → `requireAdminAjax()` |
| `ajax_emulador.php` | `requireAuthAjax()` → `requireAdminAjax()` |

**Nota de comportamiento:** el payload del JWT ya incluye `rol_id`
(`JWTService::generate()`), así que la validación funciona sin tocar el token.
Un usuario autenticado con rol distinto de admin que visite el panel es
redirigido a la home; las peticiones AJAX del panel reciben 403.

---

## 2. Export sin hashes ni PII completa

### `models/Export.php`

- `getUsuariosData()`:
  - Se eliminó `u.password_hash` del SELECT y `'Password Hash'` de los headers.
  - El email de la persona (`persona_email`) se enmascara antes de exportar.
- `getPersonasData()`:
  - El email se enmascara (`row['email'] = self::maskEmail(...)`).
- Nuevo método privado `maskEmail(?string $email): string`:
  - Convierte `juan@example.com` → `j***@example.com` (primera letra +
    asteriscos + dominio).
  - Devuelve vacío si no hay email y el original si no parece email.

El export de juegos/consolas/categorías/roles/descargas no cambia (no exponen
hashes). `descargas` conserva `ip_address`/`user_agent`/`cookie_id`: es una
feature muerta sin consumidor; queda pendiente la decisión de implementarla o
dejar de recolectar (ver auditoría §4.6).

---

## 3. Eliminar debug en disco

### `controllers/HomeController.php`

- `getBiosUrl()`: se eliminó el `file_put_contents(__DIR__ . '/../bios_debug.log',
  ...)` que corría en cada `play()` y volcaba core/región/rutas/CWD.
- Se borró el archivo `bios_debug.log` del disco (ya estaba cubierto por
  `*.log` en `.gitignore`).

---

## 4. Verificación TLS en el preflight de play

### `controllers/HomeController.php` (`checkProxyAccess()`)

- `CURLOPT_SSL_VERIFYPEER => false` → `true`, consistente con `rom_proxy.php`
  (que ya usaba `true`). Se elimina el riesgo de Man-in-the-middle en el HEAD
  interno contra el proxy.

---

## Verificación

Sin PHP CLI en el entorno: verificación estática.

- `grep requireAuthAjax` → solo queda la definición en `AuthMiddleware`.
- `grep ::requireAuth()` → 0 usos (todos migrados a `requireAdmin`).
- `grep password_hash models/Export.php` → 0 (fuera de `Usuario.php`).
- `grep CURLOPT_SSL_VERIFYPEER` → todos `true` (HomeController + rom_proxy).
- `grep bios_debug` → 0 en código; archivo eliminado del disco.

---

## Archivos del lote

| Acción | Archivo |
| --- | --- |
| MODIFICAR | `config/AuthMiddleware.php` (ADMIN_ROLE_ID + requireAdmin/requireAdminAjax) |
| MODIFICAR | `controllers/AdminController.php`, `CategoriaController.php`, `ConsolaController.php`, `EmuladorController.php`, `ExportController.php` (constructor → requireAdmin) |
| MODIFICAR | `ajax_admin.php`, `ajax_categoria.php`, `ajax_consola.php`, `ajax_emulador.php` (→ requireAdminAjax) |
| MODIFICAR | `models/Export.php` (sin password_hash, maskEmail) |
| MODIFICAR | `controllers/HomeController.php` (sin bios_debug.log, SSL verify true) |
| ELIMINAR | `bios_debug.log` (disco; ya ignorado por git) |

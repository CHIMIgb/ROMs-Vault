# Auditoría IDOR / objeto directo (A01) — ítem 2.3

**Fecha:** 2026-10-08
**Rama:** `optimizacion-arquitectura`
**Ítem del roadmap:** [2.3 Revisión IDOR / objeto directo (A01)](_archivo/2026-10-07-plan-mejoras-seguridad-y-rendimiento.md)

## Alcance

Revisadas todas las acciones de controllers que reciben `id` (o `file_id`) de la URL/query y
todos los entrypoints AJAX del panel. Criterio: ninguna acción debe servir datos ni ejecutar
cambios validando solo el `id` sin verificar sesión y rol (`AuthMiddleware::requireAdmin()` /
`requireAdminAjax()`).

## Resultado: SIN IDOR en el panel de administración

| Ruta | Protección | Veredicto |
|---|---|---|
| `AdminController::*` (dashboard, add, **edit($id)**, **toggleActive($id)**, **toggleActiveAjax($id)**, **delete($id)**, tfa) | `requireAdmin()` en constructor | ✅ |
| `ConsolaController::*` (**edit($id)**, **delete($id)**, **toggleActiveAjax($id)**, **toggleEmulacionAjax($id)**) | `requireAdmin()` en constructor | ✅ |
| `CategoriaController::*` (**edit($id)**, **delete($id)**, **toggleActiveAjax($id)**) | `requireAdmin()` en constructor | ✅ |
| `EmuladorController::*` (**edit($id)**, **toggleActiveAjax($id)**) | `requireAdmin()` en constructor | ✅ |
| `ExportController::download()` | `requireAdmin()` en constructor | ✅ |
| `ajax_admin.php` / `ajax_consola.php` / `ajax_categoria.php` / `ajax_emulador.php` | `requireAdminAjax()` | ✅ |

Los mutadores vía GET (`toggleActive`, `delete`, `toggleActiveAjax`) exigen además token
CSRF (`CsrfService::verify()` / `verifyAjax()`), por lo que no basta conocer el `id`.

## Rutas públicas con `id`/`file_id` (por diseño)

| Ruta | Recurso | Control de publicación |
|---|---|---|
| `home/show($id)` | ficha del juego | ✅ `findWithDetails()` filtra `j.activo = true` |
| `home/play?file_id=...` | reproductor online del juego | ⚠️ ver hallazgo 1 |
| `home/download?file_id=&t=&sig=` | descarga local | ✅ firma HMAC + rate limit (ver observación) |
| `rom_proxy.php?file_id=&t=&sig=` | streaming/descarga proxy | ✅ firma HMAC + TTL + rate limit + allowlist de hosts |

`show()` no enumera juegos inactivos (404 si `activo = false`). No hay datos por objeto
(la colección pública es la misma para todos), por lo que no existe IDOR clásico.

## Hallazgo 1 (menor — publicación): `play()` no filtra `activo`

`HomeController::play()` y `HomeController::download()` usan `Juego::findByFileId()`, que
NO filtra `j.activo = true`:

```sql
-- src/models/Juego.php:20-30
WHERE j.google_drive_file_id = ?
```

Consecuencia: un juego desactivado **después** de publicarse sigue siendo jugable (y no
descartable) con una URL directa conocida `/home/play?file_id=…` (play no exige firma), y
`incrementPlays()` se ejecuta igualmente (infla métricas de contenido no publicado).

**Corrección propuesta (1 línea, sin impacto en el panel — el admin usa `find()` por id):**
añadir `AND j.activo = true` a `findByFileId()`. *Requiere aprobación / test de
regresión.* *(Pendiente de aplicar — ver plan §9.)*

## Observación (no defecto — decisión documentada): descarga local a Drive directo

`download()` valida firma + rate limit y **redirige (302) a
`https://drive.google.com/uc?export=download&id=…&confirm=t`**. Es la decisión tomada en
`docs/2026-08-03-c2-seguridad-ip-descargas-csp.md` (la descarga local redirige a Drive tras
validar; el proxy firmado se usa para streaming del emulador). La URL directa **solo** se
emite como `Location` tras verificaciones, nunca se incrusta en HTML. Si se quisiera
centralizar toda descarga en `rom_proxy.php`, sería un cambio de comportamiento aparte.

## Conclusión

El panel está libre de IDOR (rol + CSRF en todos los mutadores). Único pendiente
recomendado: filtro `activo` en `findByFileId()` (Hallazgo 1), fuera del alcance de esta
auditoría.
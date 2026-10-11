# Cierre del residuo i18n: mensajes de play() al idioma activo (ítem 6.3)

- **Fecha:** 2026-10-10
- **Rama:** `pendientes-seguridad-rendimiento`
- **Commit de código:** `2b687b1`
- **Plan origen:** `docs/_archivo/2026-10-07-plan-mejoras-seguridad-y-rendimiento.md` (§6.3)

---

## 1. Problema

`HomeController::play()` todavía **hardcodeaba en español** tres mensajes de
error, aunque los diccionarios **ya contenían** las claves equivalentes en los 6
idiomas (`msg.consola_no_soportada`, `msg.emulacion_desactivada`,
`msg.bios_requerida`). Un visitante con `rv_lang=en` recibía el mismo texto en
español.

Hallazgos adicionales verificados:

- `src/i18n/es.json` tenía una **clave duplicada** (`play.error_juego_no_existe`
  en las líneas 588-589) y una **coma suelta** antes de `play.error_sin_juego`
  (JSON válido por coincidencia, pero sucio).
- `src/i18n/en.json` tenía `play.error_sin_juego` **aún en español**.

---

## 2. Solución

- `HomeController::play()` → los tres mensajes usan `t()` con el placeholder
  `{consola}` ya existente:
  - `msg.consola_no_soportada` (core ausente)
  - `msg.emulacion_desactivada` (emulación off por consola)
  - `msg.bios_requerida` (PS1 sin BIOS; la vista no escapa `$errorMsg` para
    permitir el `<code>` de la ruta del BIOS)
- `es.json` → eliminada la clave duplicada y normalizada la coma.
- `en.json` → `play.error_sin_juego` traducido al inglés.

Sin claves nuevas: se reutilizan las ya existentes en los 6 diccionarios.

---

## 3. Archivos modificados

| Acción | Archivo | Cambio |
|--------|---------|--------|
| MODIFICAR | `src/controllers/HomeController.php` | 3 literales ES → `t()` |
| MODIFICAR | `src/i18n/es.json` | Quitar duplicado y coma suelta |
| MODIFICAR | `src/i18n/en.json` | `play.error_sin_juego` → inglés |

---

## 4. Verificación (evidencia)

- `php -l` de `HomeController.php` → sin errores.
- Validación i18n (`scratch/validate_i18n.php`): los 6 JSON válidos, **586
  claves** por idioma, paridad completa.
- Smoke `php -S localhost:8140 router.php` con `/home/play?file_id=1fVWgAr...`
  (WipEout Pulse, consola PSP con `emulacion_online=false` → rama
  `msg.emulacion_desactivada`):
  - `?lang=es` → `<html lang="es"` y mensaje **ES**.
  - `?lang=en` → `<html lang="en"` y mensaje **EN** (`Online emulation is
    disabled for "PSP" ...`).
  - (El intento con `Cookie: rv_lang` vía `-Headers` de PowerShell no se
    envía correctamente; hay que fijar la cookie con `?lang=` + sesión.)
- `composer test` → **OK (166 tests, 7489 assertions)**.
# i18n — Detección de idioma y diccionarios completos (ítem 6.2 del plan)

**Fecha:** 2026-10-09 · **Plan:** `docs/_archivo/2026-10-07-plan-mejoras-seguridad-y-rendimiento.md` (ítem 6.2) · **Rama:** `optimizacion-arquitectura`

## 1. Objetivo

Cerrar el ítem 6.2 del plan activo (detección de idioma) y **poblar los diccionarios
`src/i18n/{es,en}.json` con TODO el texto hardcodeado de la aplicación** según
petición del usuario, dejando las claves listas para la migración mecánica de
vistas/controladores del ítem 6.3.

## 2. Decisiones de diseño

- **Precedencia de idioma** (roadmap FASE 5.1): cookie `rv_lang` → `Accept-Language`
  (primer idioma soportado `es|en`) → `I18N_LANG` (.env) → default `es`.
  Sin subdirectorio `/en/`: no se duplican URLs (SEO/enlaces intactos).
- **Selector `?lang=es|en`** como query string global en cualquier URL: valida la
  whitelist, escribe la cookie y redirige **302 a la misma URL sin el parámetro**
  (p. ej. `/?lang=en` → `/`). Con otros parámetros: `/?lang=en&page=2` → `/?page=2`.
- **Cookie `rv_lang`**: `SameSite=Lax`, `Path=/`, 1 año, `httpOnly=false`, `Secure`
  solo sobre HTTPS. NO lleva `__Host-`: el prefijo exige `Secure` y rompería el dev
  en `http://localhost:8000` (mismo motivo que `__Host-rv_token`). No es dato
  sensible y el selector funciona sin JavaScript.
- **`<html lang>` dinámico**: `header.php` emite `I18n::getLang()` saneado.
- **No se toca la BD/perfil de usuario**: la columna `lang` en `usuarios` queda como
  extensión futura opcional (documentada, no implementada).
- **Diccionarios completos, sin migrar vistas**: las ~568 claves quedan disponibles
  y el ítem 6.3 las consumirá. Las vistas siguen mostrando las mismas cadenas; nada
  visible cambia salvo el atributo `lang` del `<html>`.

## 3. Cambios

- `src/config/i18n.php` — `detectLangFromRequest()`, `setLangCookie()`, `reset()`
  (tests); `init()` ahora detecta por petición en lugar de fijar `I18N_LANG`.
- `index.php` — bloque `?lang=` antes del routing: cookie + redirect 302 sin `lang`.
- `src/views/layout/header.php` — `<html lang="<?= ...getLang() ?>">`.
- `src/i18n/es.json`, `src/i18n/en.json` — **568 claves paritarias** (568×2).
  Generadas por grupos desde el inventario exhaustivo (~565 cadenas) verificado
  en las 10 secciones: layout/components/errors/auth (25), home/show/play (133),
  admin juegos+dashboard (125), admin consolas/categorías/emuladores/tfa (181),
  backend: controllers+endpoints+config+JS+router (84). Prefijos: `nav.*`, `header.*`,
  `footer.*`, `component.*`, `error.*`, `auth.*`, `two_factor.*`, `home.*`, `show.*`,
  `play.*`, `admin.{juego,dashboard,consola,categoria,emulador,tfa}.*`, `msg.*`,
  `proxy.*`, `ajax.*`, `rate.*`, `csrf.*`, `js.*`, `router.*`, más la semilla 6.1
  (`app.*`, `nav.*`, `auth.*`, `errors.*`, `catalog.*`, `common.*`).
- Tests: `tests/Unit/I18nTest.php` (+7 detección/reset) y
  `tests/Integration/I18nFlowTest.php` (8 tests HTTP reales: 302 + cookie,
  páginas con `lang` correcto, inválidos ignorados, Accept-Language).

## 4. Verificación

- `php -l` OK en `src/config/i18n.php`, `index.php`, `header.php`, `I18nTest.php`,
  `I18nFlowTest.php`.
- Suite completa con `TEST_DB_PASSWORD=admin123`: **161 tests / 2679 assertions OK**
  (Unit 95/2466, Integration 50/167, Security incluida).
- Smoke con `php -S 0.0.0.0:8017 router.php`:
  - `/?lang=en` → `302` → `/` (sin parámetro).
  - `/` con cookie `rv_lang=en` → `<html lang="en">`.
  - `/?lang=xx` → `200` (inválido ignorado, sin cookie).
  - `/` sin cookie → `<html lang="es">`.
- Paridad es/en forzada por test (`testDiccionariosJsonValidosYParitarios`).

## 5. Estado

- [x] ítem 6.2 completo. Pendiente: 6.3 (migración de vistas a `t()`), 6.4 (selector UI).
- Registro de progreso actualizado en el plan con el hash de este trabajo.

## 6. Commits

- `a3e120f` — `feat(i18n): ...` (código + diccionarios).
- `docs(i18n): ...` — este doc + tick 6.2 + registro (siguiente commit).
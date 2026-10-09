# i18n — Cargador de diccionarios (ítem 6.1 del plan)

Fecha: 2026-10-09

## Objetivo

Crear la infraestructura base de traducción de la aplicación: diccionarios
JSON (`es`, `en`), clase cargadora `I18n` y la función global `t('clave',
params)` con placeholders. Es infraestructura pura — **no se migra ninguna
vista en este ítem** (eso es el ítem 6.3); solo queda el mecanismo disponible,
verificado y listo para que 6.2 (detección de idioma) lo gobierne.

## Decisiones de diseño

1. **Diccionarios en JSON** (`src/i18n/{es,en}.json`), no en PHP. Motivo: los
   JSON no ejecutan código (más seguros si algún día se editan desde
   administración) y son editables sin tocar el código. Al cargar se valida
   que sean `array<string,string>`; un JSON roto degrada a diccionario vacío
   con `error_log`, nunca a fatal.
2. **Función global `t('clave', params)`** con placeholders `{nombre}`
   (sustitución con `strtr`). Fallback: clave ausente en el idioma activo →
   se busca en `es` → si tampoco, se devuelve la propia clave (visible en
   desarrollo).
3. **Idioma base `es`** configurable con `I18N_LANG` (añadido a `.env.example`
   como opcional). La detección por cookie/`Accept-Language` es el ítem 6.2 y
   usará `I18n::setLang()` sin tocar este cargador.
4. **Datos de la BD no se traducen** (títulos de juegos, consolas, categorías,
   regiones, descripciones): son contenido, no interfaz. La decisión queda
   documentada aquí para no reinterpretarla al migrar la UI. Si algún día se
   quiere, se hará con tablas de traducción por entidad (extensión futura) sin
   afectar a `t()`.

## Archivos

- `src/config/i18n.php` — clase `I18n` (init/setLang/getLang/translate/
  dictionary/load con cache estática y whitelist `['es','en']`) + función
  global `t()`.
- `src/i18n/es.json`, `src/i18n/en.json` — semilla de claves (nav.*, auth.*,
  errors.*, catalog.*, common.saludo con placeholder) con paridad 1:1.
- `index.php` — `require_once` + `I18n::init()` (t() disponible en todas las
  páginas HTML).
- `tests/bootstrap.php` — `require_once` del cargador (suite Unit).
- `tests/Unit/I18nTest.php` — 7 tests del contrato de t() y de los JSON.
- `.env.example` — `I18N_LANG=es` documentado.

## Verificación

- `php -l` en `src/config/i18n.php`, `index.php`, `tests/Unit/I18nTest.php`: OK.
- Suite Unit: `OK (88 tests, 266 assertions)` (+7 I18nTest; un fallo propio del
  sanity check inicial corregido al instante).
- Suite completa: `OK (146 tests, 462 assertions)` (antes 139/366).
- Smoke `php -S` con `roms-vault-test`: `/` y `/auth/login` → **200** con HTML
  idéntico al anterior (la infraestructura no altera ninguna salida), 0 errores
  en el log del server.

## Commits

- `feat(i18n): cargador de diccionarios y función t() (ítem 6.1)` — código.
- `docs(i18n): cargador de diccionarios (doc + registro)` — docs/plan.
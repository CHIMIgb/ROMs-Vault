# i18n — Nuevos idiomas Tier 1: it, ru y tr

**Fecha:** 2026-10-11 · **Plan:** `docs/2026-08-05-plan-mejoras-nivel-senior.md` (FASE 5) · **Rama:** `pendientes-7.2-slugs-y-4.4`

## 1. Objetivo

Ampliar el sistema i18n de **6 idiomas (es, en, fr, pt, de, ja)** a **9**: añadir
**italiano (`it`)**, **ruso (`ru`)** y **turco (`tr`)**, con paridad total de
claves respecto a `es.json` (592 claves), bandera SVG en el selector y formateo
de fechas/números por locale en el fallback sin `intl`.

Es el **Tier 1** de un análisis de ampliación de idiomas (2026-10-11). El **Tier 2**
(candidatos descartados por ahora) se documenta aparte en
`docs/_archivo/2026-10-11-idiomas-candidatos-tier2.md`.

## 2. Decisiones de diseño

- **Criterio de elección (Tier 1)**: idioma con **código publicado en el CDN de
  EmulatorJS** (`cdn.emulatorjs.org/stable/data/localization/<código>.json`,
  verificado: `it-IT`, `ru-RU`, `tr-TR` existen), comunidad de jugadores retro
  amplia y traducción de la UI del emulador. Con esto la app mantiene la
  coherencia "todo el sitio, incluido el emulador, en el idioma activo".
- **Códigos base (language tags cortos)**: `it`, `ru`, `tr`; el locale Intl se
  mapea aparte (`it_IT`, `ru_RU`, `tr_TR`) y el código de EmulatorJS a
  `it-IT`, `ru-RU`, `tr-TR`.
- **Diccionarios paritarios**: `it.json`, `ru.json`, `tr.json` con **592 claves**,
  mismo conjunto y orden que `es.json`, UTF-8. Validado con verificación de
  faltantes/sobrantes, valores no vacíos y tipos string.
- **`intl` sigue siendo opcional y NO está en producción**: el **fallback de
  `IntlHelper` es el comportamiento canónico**; se extendió con meses y
  separadores por locale para it/ru/tr (ver §4).
- **Fuentes (nota de riesgo menor)**: `Press Start 2P` (títulos) **sí** tiene
  subset cirílico en Google Fonts → ruso correcto en encabezados. `Courier Prime`
  (cuerpo) **no** publica cirílico → el texto ruso cae a `'Courier New', monospace`
  (que sí incluye cirílico). Degradación grácil, sin cambios de CSS ni de fuentes.
- **RTL fuera de alcance**: `ar`/`he` no se añaden (ligado al pendiente 5.2).
- **Sin cambios de infraestructura**: la detección por `Accept-Language`, el
  selector `?lang=` y `endpoints/ajax_lang.php` usan `I18n::LANGUAGES` como única
  fuente de verdad; `public/js/rv-lang.js` deriva los idiomas del DOM del selector,
  por lo que **no requiere cambios**.

## 3. Cambios

- **`src/i18n/it.json`, `ru.json`, `tr.json`** (nuevos) — 592 claves cada uno,
  mismo orden que `es.json`, UTF-8 sin BOM.
- **`src/config/i18n.php`** — `LANGUAGES` pasa de
  `['es','en','fr','pt','de','ja']` a `['es','en','fr','pt','de','ja','it','ru','tr']`;
  docblock de cabecera actualizado.
- **`src/config/IntlHelper.php`** — `LOCALES` (→ `it_IT`/`ru_RU`/`tr_TR`),
  `EMULATOR_LOCALES` (→ `it-IT`/`ru-RU`/`tr-TR`), `fallbackSeparators()`
  (`ru_RU` usa espacio irrompible como separador de miles; it/tr usan `,`/`.`)
  y `fallbackMonths()` (meses abreviados de it/ru/tr). El orden de fecha por
  defecto (d mes y) ya es el correcto para los tres.
- **`src/views/layout/header.php`** — `$langFlags` añade banderas SVG de `it`
  (tricolor vertical verde/blanco/rojo), `ru` (tricolor horizontal
  blanco/azul/rojo) y `tr` (rojo con media luna y estrella blancas). El menú
  muestra ya las 9 opciones.
- **Tests**:
  - `tests/Unit/I18nTest.php` — `testSwitchIdiomasNuevos` añade
    `it→Catalogo`, `ru→Каталог`, `tr→Katalog`;
    `testEmulatorLanguageMapeaCadaIdiomaAlCodigoDelCdn` y
    `testEmulatorLanguageSiempreDevuelveUnCodigoPublicado` (`$publicados`) añaden
    `it-IT`/`ru-RU`/`tr-TR`; docblock actualizado.
  - `tests/Integration/I18nFlowTest.php` — `testQueryLangNuevosIdiomasEscribeCookie`
    itera ahora `['fr','pt','de','ja','it','ru','tr']`;
    `testAcceptLanguageSoloSoportadosIgnoraResto` cambia sus idiomas "no
    soportados" de `ru-RU,it` a **`pl-PL,nl`** (ru/it ya están en la whitelist).

## 4. Verificación

- `php -l` OK en `src/config/i18n.php`, `src/config/IntlHelper.php`,
  `src/views/layout/header.php`, `tests/Unit/I18nTest.php` y
  `tests/Integration/I18nFlowTest.php`.
- Paridad de diccionarios: it/ru/tr con **592 claves, 0 faltantes, 0 sobrantes,
  0 valores vacíos/no-string**, mismas claves que `es.json`.
- Fallback de `IntlHelper` (sin `intl`): `it=7 ott 2026 / 1.234.567,89`,
  `ru=7 окт. 2026 / 1 234 567,89`, `tr=7 Eki 2026 / 1.234.567,89`.
- Suite completa (`composer test`): **OK** (ver commit). `phpstan analyse`: **OK**.
- Smoke HTTP (`php -S localhost:8000 router.php`): `GET /?lang=it|ru|tr` → **302**
  con cookie `rv_lang` y `<html lang="...">` correcto; sin claves crudas.

## 5. Estado

- [x] FASE 5 — 3 idiomas Tier 1 integrados y verificados (9 idiomas en total).
- [ ] RTL (5.2) y Tier 2 (zh-CN/ko-KO/ar-AR): pendientes (ver doc archivado).

## 6. Commits

- `feat(i18n): añadir idiomas italiano, ruso y turco (Tier 1)`.
- `docs(i18n): ...` — este doc + tick FASE 5.1 + registro de progreso.

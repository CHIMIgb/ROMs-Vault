# i18n — Idiomas Tier 2: zh, ko y ar + soporte RTL

**Fecha:** 2026-10-12 · **Plan:** `docs/2026-08-05-plan-mejoras-nivel-senior.md` (FASE 5.1 y 5.2) · **Rama:** `feature/idiomas-tier2`

## 1. Objetivo

Ampliar el sistema i18n de **9 idiomas (es, en, fr, pt, de, ja, it, ru, tr)** a **12**:
añadir **chino simplificado (`zh`)**, **coreano (`ko`)** y **árabe (`ar`)** con paridad
total de claves respecto a `es.json` (592 claves), bandera SVG en el selector y
formateo de fechas/números por locale.

`ar` introduce, además, el **soporte RTL** que quedaba pendiente en la FASE 5.2:
la app pasa a declarar `dir="ltr|rtl"` en `<html>` y el CSS se migra a
**logical properties** para que el layout se refleje correctamente en árabe sin
alterar el render LTR del resto de idiomas.

Es el **Tier 2** del análisis de ampliación de idiomas: los tres candidatos que se
descartaron provisionalmente en `docs/_archivo/2026-10-11-idiomas-candidatos-tier2.md`
y cuyo alcance/diseño se fijó en `docs/_archivo/2026-10-12-plan-idiomas-db-uiux-scripts.md`.

## 2. Decisiones de diseño

- **Criterio de elección (Tier 2)**: idioma con **código publicado en el CDN de
  EmulatorJS** (`cdn.emulatorjs.org/stable/data/localization/<código>.json`;
  verificado: `zh-CN`, `ko-KO`, `ar-AR` existen), y con traducción de la UI del
  emulador. Con esto la app mantiene la coherencia "todo el sitio, incluido el
  emulador, en el idioma activo".
- **Códigos base (language tags cortos)**: `zh`, `ko`, `ar`. El **locale Intl**
  se mapea aparte (`zh_CN`, `ko_KR`, `ar_AR`) y el **código de EmulatorJS** a
  `zh-CN`, `ko-KO`, `ar-AR`.
  - Nota: el código del CDN para coreano es `ko-KO` (no `ko-KR`); es un
    particularismo de EmulatorJS y se respeta tal cual en `EMULATOR_LOCALES`.
- **Bandera de `ar`**: Arabia Saudí simplificada (verde con la shahada estilizada),
  coherente con el resto de banderas SVG del selector.
- **Diccionarios paritarios**: `zh.json`, `ko.json`, `ar.json` con **592 claves**,
  mismo conjunto que `es.json`, UTF-8 sin BOM. Traducción **desde cero** (no
  derivada de otros romances): CJK y árabe requieren contenido propio.
- **RTL como capacidad de primera clase**:
  - `I18n::RTL_LANGS = ['ar']` y `I18n::isRtl(?string $lang = null): bool` son la
    **única fuente de verdad** (null = idioma activo).
  - `src/views/layout/header.php` emite `dir="<?= I18n::isRtl() ? 'rtl' : 'ltr' ?>"`
    en `<html>`.
  - `public/js/rv-lang.js` sincroniza `lang` **y `dir`** del `<html>` al cambiar de
    idioma sin recarga, leyendo ambos atributos del HTML devuelto por SSR.
- **CSS: logical properties en lugar de reglas por `dir`**. Se convirtieron las
  propiedades direccionales a su equivalente lógico (`*-inline-start/end`,
  `text-align: start|end`, `inset-inline-*`), lo que **garantiza equivalencia
  visual exacta en LTR** y reflejo correcto en RTL. Se mantuvieron como físicos:
  - el *stretch* neutro (`left:0;right:0`, `left:50%`) y animaciones decorativas
    (scan/glitch) que no dependen de la dirección de lectura;
  - `image-preview-remove` (posición física sobre la miniatura, por convención).
  - Se usó una regla `[dir="rtl"]` **solo** donde no existe propiedad lógica:
    `background-position` de los chevrons SVG en los `<select>` y en
    `.lang-trigger`, y la inversión `transform: scaleX(-1)` de las flechas
    prev/next del carrusel de capturas (`.screenshot-nav svg.pxi`).
- **CSP y `vercel.json` intactos**: no se toca la política de seguridad ni los
  rewrites. `ar`/`zh`/`ko` son solo contenido y atributos del DOM.

## 3. Cambios

- **`src/i18n/zh.json`, `ko.json`, `ar.json`** (nuevos) — 592 claves cada uno,
  mismo conjunto que `es.json`, UTF-8 sin BOM.
- **`src/config/i18n.php`** — `LANGUAGES` pasa a
  `['es','en','fr','pt','de','ja','it','ru','tr','zh','ko','ar']`; nueva constante
  `RTL_LANGS = ['ar']`; nuevo método `isRtl(?string $lang = null): bool`.
- **`src/config/IntlHelper.php`** — `LOCALES` añade `zh_CN`/`ko_KR`/`ar_AR`;
  `EMULATOR_LOCALES` añade `zh-CN`/`ko-KO`/`ar-AR`; `fallbackSeparators()` y
  `fallbackMonths()` ampliados para los tres locales; docblocks actualizados.
- **`src/views/layout/header.php`** — `<html lang dir>`; `$langFlags` añade banderas
  de `zh`, `ko` y `ar` (12 opciones en el menú).
- **`public/js/rv-lang.js`** — `applyDocument()` sincroniza el atributo `dir` del
  `<html>` al cambiar de idioma sin recarga.
- **CSS (logical properties)** en `public/css/modules/`:
  - `layout.css`: `.subtitle`/`nav a svg`, `.lang-switch`, `.lang-trigger`
    (padding + `[dir="rtl"]` position), `.lang-menu`, `.lang-option`.
  - `components.css`: `.search-input`, `.search-button`, `.filters select`
    (+`[dir="rtl"]`), `.btn-edit`, `.alert`, `.rv-modal-header`, `.rv-toast*`,
    `.rv-inline-alert`, márgenes de `svg.pxi`.
  - `admin.css`: `.ranking-pos`, `.ranking-bar-fill`, `.admin-filter-select`
    (+`[dir="rtl"]`), `.admin-table th`.
  - `forms.css`: `.file-input-btn`, `.form-group select` (+`[dir="rtl"]`),
    `.password-wrapper input`, `.password-toggle`.
  - `game-details.css`: `.proxy-error-*`, `.error-404-*`, `#proxy-load-pct`,
    `.size-control-value`, `.screenshot-nav--prev/--next` (+`[dir="rtl"] scaleX(-1)`),
    `.screenshot-counter`, `.screenshot-open .pxi`, `.screenshot-lightbox-close`,
    `.detail-region-seal`, `.detail-hack-ribbon`, `.spec-value`,
    `.detail-synopsis-content::first-letter`, `.synopsis-hack-badge`,
    `.detail-new-badge`, `.howto-dl-link` y equivalentes en media queries.
  - `game-grid.css`: `.game-info-value`, `.game-hack-info`, `.game-download span`.
  - `responsive.css`: `.search-input`/`.filters select` padding, `.game-download span`.
- **Tests**:
  - `tests/Unit/I18nTest.php` — `testSwitchIdiomasNuevos` añade
    `zh→目录`, `ko→카탈로그`, `ar→الكتالوج`;
    `testEmulatorLanguageMapeaCadaIdiomaAlCodigoDelCdn` y
    `testEmulatorLanguageSiempreDevuelveUnCodigoPublicado` (`$publicados`) añaden
    `zh-CN`/`ko-KO`/`ar-AR`; nuevos tests `testIsRtlSoloParaIdiomasRtl` y
    `testIsRtlSinArgumentoUsaIdiomaActivo`.
  - `tests/Integration/I18nFlowTest.php` — las aserciones de `<html lang="...">`
    pasan a prefijo (toleran el atributo `dir`); `testQueryLangNuevosIdiomasEscribeCookie`
    itera ahora los 10 idiomas no-base; nuevos tests `testIdiomaLtrLlevaDirLtr` y
    `testIdiomaArabeLlevaDirRtl`.

## 4. Verificación

- `php -l` OK en los 67 archivos de `src/`, `endpoints/`, `index.php` y `router.php`
  (0 errores).
- Paridad de diccionarios: `zh`/`ko`/`ar` con **592 claves, 0 faltantes, 0 sobrantes**,
  mismas claves que `es.json`.
- Suite completa (`composer test`): **OK — 189 tests, 14780 aserciones, 0 fallos**
  (Unit 105 + Integration + Security).
- Smoke HTTP (`php -S localhost:8899 router.php`):
  - `GET /?lang=ar` → **302** con `Location: /`; el `<html>` resultante es
    `<html lang="ar" dir="rtl">`.
  - `GET /` con cookie `rv_lang=zh` → `<html lang="zh" dir="ltr">`;
    con `rv_lang=ko` → `<html lang="ko" dir="ltr">`.
  - Selector con **12 opciones** (`.lang-option`), cada una con bandera SVG con
    contenido real (incluidas `zh`, `ko`, `ar`).
  - `/src/config/i18n.php` → **403** (la app sigue sin servirse por HTTP).

## 5. Estado

- [x] FASE 5.1 — 3 idiomas Tier 2 integrados y verificados (12 idiomas en total).
- [x] FASE 5.2 — Soporte RTL en CSS con logical properties (`dir` en `<html>`,
  `isRtl()` como fuente de verdad, `rv-lang.js` sincroniza `dir`).
- [ ] Quedan pruebas visuales manuales con navegador real para el reflejo del
  layout en `ar` (validación de escritorio y móvil).

## 6. Commits

- `feat(i18n): añadir idiomas chino, coreano y árabe y soporte RTL (Tier 2)` — `e288816`.
- `docs(i18n): documentar Tier 2 (zh/ko/ar) y soporte RTL y marcar FASE 5.1/5.2` — este doc + tick FASE 5.1/5.2 + registro de progreso.

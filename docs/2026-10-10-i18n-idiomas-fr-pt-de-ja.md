# i18n — Idiomas fr, pt (pt-BR), de y ja

**Fecha:** 2026-10-10 · **Plan:** `docs/2026-08-05-plan-mejoras-nivel-senior.md` (FASE 5) · **Rama:** `optimizacion-arquitectura`

## 1. Objetivo

Ampliar el sistema i18n de **2 idiomas (es, en)** a **6**: añadir **francés (`fr`)**,
**portugués de Brasil (`pt`)**, **alemán (`de`)** y **japonés (`ja`)**, con
paridad total de claves respecto a `es.json`, banderas en el selector, y
formateo de fechas/números por locale en el fallback sin `intl`.

## 2. Decisiones de diseño

- **Portugués de Brasil (`pt`)**: el código base `pt` permite que `pt-BR` se
  detecte automáticamente por `Accept-Language` (`explode('-', $code)[0]`). Se
  traduce con variante brasileña (arquivo, salvar, tela, usuário).
- **Códigos base (_language tags_ cortos)**: `fr`, `pt`, `de`, `ja`. El locale
  Intl se mapea aparte (`fr_FR`, `pt_BR`, `de_DE`, `ja_JP`).
- **Diccionarios generados por builder reproducible**: `scratch/build_lang.php`
  toma `es.json` (585 claves) y un mapa `es-valor => traducción`, y emite
  `src/i18n/<lang>.json` con **EXACTAMENTE** las mismas claves y orden que
  `es.json`. Valida faltantes, sobrantes y placeholders `{x}`. Los mapas y el
  builder son *throwaway* (`scratch/` está gitignored); el artefacto que se
  commitea son los 4 JSON.
- **`intl` es opcional y NO está en producción**: XAMPP no carga `intl` y el
  `Dockerfile` no la instala. Por eso el **fallback de `IntlHelper` es el
  comportamiento canónico** y se extendió con meses y separadores por locale.
- **Japonés y fuentes**: las fuentes del design system (`Press Start 2P`,
  `Courier Prime`) no incluyen glifos CJK; el japonés cae a la fuente monoespaciada
  del sistema. Aceptado (no se añaden fuentes nuevas).
- **RTL sigue pendiente**: `ar`/`he` no se añaden en esta tarea (login logical
  properties sigue en FASE 5.2 como ítem abierto).
- **`EJS_language` no se parametriza aquí**: queda como ítem 5.3 (candidato:
  `str_replace('_','-', IntlHelper::getLocale())`).

## 3. Cambios

- **`src/i18n/fr.json`, `pt.json`, `de.json`, `ja.json`** (nuevos) — 585 claves
  cada uno, mismo orden que `es.json`, UTF-8 sin BOM.
- **`src/config/i18n.php`** — `LANGUAGES` pasa de `['es','en']` a
  `['es','en','fr','pt','de','ja']`; docblocks actualizados.
- **`src/views/layout/header.php`** — `$langFlags` añade banderas SVG de `fr`
  (tricolor), `pt` (Brasil), `de` (tricolor horizontal) y `ja` (círculo rojo);
  el menú muestra ya las 6 opciones.
- **`src/config/IntlHelper.php`** — `LOCALES` (código i18n → locale Intl) y
  `getLocale()` extienden es/en a fr/pt/de/ja; `fallbackSeparators()` y
  `fallbackMonths()` nuevos; `fallbackDate()` respeta orden de fecha y meses por
  locale. `formatNumber()` usa los separadores por locale sin `intl`.
- **Tests** (aserciones que usaban `fr`/`de` como “no soportados", aprobado por el
  usuario):
  - `tests/Unit/I18nTest.php` — `setLang('fr')` → `setLang('xx')`; cookie
    `rv_lang='fr'` → `'xx'`; paridad y "no vacíos" ahora iteran `LANGUAGES`;
    **nuevo** `testSwitchIdiomasNuevos`.
  - `tests/Integration/I18nFlowTest.php` — `/?lang=fr` → `/?lang=xx`;
    Accept-Language de no soportados pasa a `ru-RU,it`; **nuevos**
    `testQueryLangNuevosIdiomas` y `testAcceptLanguageDetectaIdiomaRegional`.

## 4. Verificación

- `php -l` OK en `src/config/i18n.php`, `src/config/IntlHelper.php`,
  `src/views/layout/header.php`.
- Paridad de diccionarios (`scratch/verify_lang.php`): fr/pt/de/ja con **585
  claves, 0 faltantes, 0 sobrantes, mismo orden que `es.json`, 0 placeholders
  distintos**.
- Fallback de `IntlHelper` (sin `intl`) por locale:
  `es=7 oct 2026 / 1.234.567,89`, `en=Oct 7, 2026 / 1,234,567.89`,
  `fr=7 oct. 2026 / 1 234 567,89`, `pt=7 out. 2026 / 1.234.567,89`,
  `de=7 Okt. 2026 / 1.234.567,89`, `ja=2026/10/7 / 1,234,567.89`.
- Suite completa con `TEST_DB_PASSWORD=…`: **164 tests / 7459 assertions OK**.
- Smoke HTTP (`php -S localhost:8021 router.php`):
  - selector con **6 opciones / 7 banderas** (6 opciones + trigger);
  - `?lang=` para `es|en|fr|pt/de/ja` refleja `<html lang="...">` y el texto
    traducido (`Contraseña/Password/Mot de passe/Senha/Passwort/パスワード`);
  - `?lang=ru` → `200`, sin cookie `rv_lang` (whitelist intacta).

## 5. Estado

- [x] FASE 5 — 4 idiomas nuevos integrados y verificados.
- [ ] RTL (5.2) y parametrización de `EJS_language` (5.3): pendientes.

## 5.b Commits

- _(feature de idiomas)_ — `feat(i18n): añadir idiomas fr, pt (pt-BR), de y ja`.
- `docs(i18n): ...` — este doc + tick FASE 5 + registro de progreso.

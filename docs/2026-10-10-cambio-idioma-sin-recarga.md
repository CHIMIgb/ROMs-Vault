# Cambio de idioma sin recarga de página (FASE 5.3)

> Fecha: 2026-10-10 · Commits: `3561a05` (feature) · `a9746e5` (fix iconos/whitespace en syncNode) · `7eb9136` (KPI total_jugadas + orden en.json) · `9f3a344` (transición localizada) · `b24d585` (EJS_language por idioma) · `48d2c5a` (cabecera admin consistente) · `84ee20c` (detección automática del navegador)
> Relacionado: [`2026-08-05-plan-mejoras-nivel-senior.md`](./2026-08-05-plan-mejoras-nivel-senior.md) (FASE 5) · [`2026-10-10-i18n-idiomas-fr-pt-de-ja.md`](./2026-10-10-i18n-idiomas-fr-pt-de-ja.md)

## Resumen

El selector de idioma del header (`<form class="lang-switch">`) funcionaba con
submit GET nativo: cambiaba la cookie y recargaba la página. Esta tarea añade un
**enhancement AJAX** para cambiar el idioma **sin recargar**, con transición
suave, manteniendo SSR y sin romper los listeners/estado de los scripts de la
página (filtros, carrusel, EmulatorJS).

## Decisiones de diseño

1. **Progressive enhancement, no reemplazo.** Sin JavaScript el formulario
   sigue enviando GET y recargando (flujo original intacto). Con JS, se
   intercepta el `submit` por delegación en `document` (sobrevive a la
   regeneración del propio selector).

2. **Sincronización in-place del DOM, no body-swap.** Un `body.innerHTML`
   reemplazaría los nodos y mataría los listeners directos de las vistas
   (`home/index.php`, `home/show.php`, `home/play.php`) e inflaría otra vez el
   trabajo del `MutationObserver` de `pixelicons.js`. Como el DOM es
   **estructuralmente idéntico** entre idiomas (sólo cambia el texto), la
   función `syncNode()` recorre en paralelo el DOM actual y el HTML fetcheado
   (parseado con `DOMParser`) y actualiza únicamente:
   - nodos de texto (`TEXT_NODE`),
   - atributos whitelisteados: `placeholder`, `title`, `aria-label`, `alt`,
   - `<html lang>`, `<title>` y `content` de `meta[name|property]`,
   - el subárbol `.lang-switch` (bandera/código/`aria-selected`).

   La identidad de los elementos se conserva (visión por referencia), por lo
   que los listeners y el estado no se pierden. El emparejado es **tolerante**:
   si el DOM runtime tiene nodos extra (autocomplete, grids regenerados,
   modales), avanza sin romperse. `syncNode` trata como transparentes los nodos
   de icono (`svg.pxi` en vivo frente a `<i data-i>` en el HTML) y el
   whitespace, y usa un **lookahead** de 6 nodos para reanudar tras un nodo
   extra sin abortar la rama (fix `a9746e5`: el nav del header y el menú del
   dashboard admin ahora sí cambian de idioma).

3. **Endpoint dedicado** `endpoints/ajax_lang.php` (entrypoint standalone, no
   pasa por `index.php`; mapeado por `router.php` y `.htaccess`):
   - Solo `POST` (405 en GET) para evitar CSRF-vía-imagen/`<a>`.
   - Guard `Sec-Fetch-Site`: 403 si el origen de la petición no es
     `same-origin`/`same-site`/`none` (mitigación CSRF en capa extra, además
     del patrón doble cookie que ya es global en `index.php`).
   - Whitelist de idiomas desde `I18n::LANGUAGES` (400 si no está).
   - `setcookie('rv_lang', …)`: misma semántica que `I18n::setLangCookie()`
     (expira 1 año, `path=/`, `secure` condicional, `httponly=false` para que
     el emulador/JS de la página pueda leerla, `samesite=Lax`).
   - Respuesta `application/json` con `nosniff` y `Cache-Control: no-store`.

4. **Transición localizada, no global.** En lugar de atenuar todo el `<body>`
   (`html.lang-switching body`), `syncNode()` registra en un `Set` los
   elementos que realmente cambian (parent de cada text node actualizado y el
   elemento cuyo atributo traducible cambió) y `flashFadeTargets()` les aplica
   `@keyframes rv-lang-pop` (opacity `1 → .45 → 1`, 350 ms). `safeToFade()`
   excluye cualquier elemento cuyo subtree contenga `img/video/canvas/audio/
   iframe/.emulator`: las portadas, capturas y el canvas de EmulatorJS
   permanecen quietos. `prefers-reduced-motion: reduce` anula la animación.
   La clase `.lang-switch` también se anima (es la zona que cambia por
   excelencia) y el flag `html.lang-switching` queda solo como guard de
   reentrada, sin efecto visual.

5. **Fallback robusto**: si el POST o el segundo `fetch` fallan, se navega por
   GET a `location.href` con `?lang=…` (el servidor fija la cookie y redirige).

6. **UI del emulador por idioma (FASE 5.3, `b24d585`).** `window.EJS_language`
   estaba fijo en `'es-ES'`, así que el menú de EmulatorJS salía siempre en
   español. Ahora `IntlHelper::emulatorLanguage()` lo resuelve desde el idioma
   activo con una **whitelist** de los códigos que **realmente publica** el CDN
   (`cdn.emulatorjs.org/stable/data/localization/`): `es→es-ES`, `en→en-US`,
   `pt→pt-BR`, `de→de-GER`, `ja→ja-JA`. **`fr` cae a `en-US`** porque EmulatorJS
   no publica francés (`af-FR` es afrikáans, no `fr-FR`) — limitación externa.
   Se descartó el candidato `str_replace('_','-', getLocale())` porque EmulatorJS
   usa códigos propios (`de-GER`, `ja-JA`) que no coinciden con los locales Intl
   (`de_DE`, `ja_JP`).

7. **Cabecera admin consistente entre idiomas (`48d2c5a`).** `dashboard.php`
   tenía un `<style>` inline que reescribía `.admin-header` con `flex-wrap: wrap`:
   si título + 6 botones cabían → una línea; si las etiquetas eran largas (de/fr)
   → los botones bajaban. Resultado: disposición distinta según el idioma. Ahora
   `.admin-header` (módulo `admin.css`) es **siempre apilada** (título arriba,
   acciones debajo a la izquierda), y el `<style>` inline se eliminó (además
   cumple la convención "CSS solo en `public/css/modules/*`").

8. **Detección automática del idioma del navegador.** Además de la detección
   server-side por `Accept-Language` (que se mantiene como base del SSR y para
   navegadores sin JS), `rv-lang.js` detecta el idioma del navegador en cliente
   (`navigator.languages`, con respaldo en `navigator.language`/`userLanguage`):
   - Solo actúa en la **primera visita** (sin cookie `rv_lang` válida): si el
     usuario ya eligió idioma con el selector, se respeta y no se hace nada.
   - La lista de idiomas soportados se **deriva del DOM** (`.lang-switch
     button[name="lang"]`), única fuente = `I18n::LANGUAGES`.
   - Si el idioma detectado **≠** el renderizado → `switchLanguage()` (POST +
     sincronización in-place + transición + fallback GET).
   - Si **coincide** → `persistLang()`: POST silencioso (sin refetch) para fijar
     la cookie y que el SSR sea estable.
   - Guard de reentrada `autoDetectDone` (no repetir si `DOMContentLoaded` se
     reemite) y `browserLanguages()` prioriza `navigator.languages` (que ya viene
     ordenado) para no colar códigos no deseados.
   - Idiomas no soportados (p. ej. `ru-RU`) → no se hace nada.

## Archivos

| Archivo | Cambio |
|---------|--------|
| `endpoints/ajax_lang.php` | **Nuevo**: endpoint JSON para fijar la cookie (POST, guard, whitelist) |
| `public/js/rv-lang.js` | **Nuevo**: IIFE `'use strict'`, delegación de submit, doble `fetch`, `syncNode`/`syncMeta`/`swapLangSwitch`, transición, detección del idioma del navegador y fallback |
| `router.php` | `'ajax_lang.php'` añadido a la whitelist `$entrypoints` |
| `src/views/layout/header.php` | `<form class="lang-switch" … data-enhance="lang">` |
| `src/views/layout/footer.php` | `<script src="public/js/rv-lang.js" defer>` junto a pixelicons/rv-alerts |
| `public/css/modules/layout.css` | `@keyframes rv-lang-pop` + `.rv-lang-fade` + `prefers-reduced-motion` |
| `src/i18n/*.json` | Clave KPI `admin.dashboard.total_jugadas` en los 6 idiomas (ver `7eb9136`) |
| `src/config/IntlHelper.php` | `const EMULATOR_LOCALES` + `emulatorLanguage()` (whitelist CDN) (ver `b24d585`) |
| `src/views/home/play.php` | `EJS_language` desde `IntlHelper::emulatorLanguage()` (ver `b24d585`) |
| `tests/bootstrap.php` · `tests/Unit/I18nTest.php` | Carga de `IntlHelper` + tests del mapeo de idioma del emulador (ver `b24d585`) |
| `public/css/modules/admin.css` | `.admin-header` apilada (título arriba, acciones debajo) (ver `48d2c5a`) |
| `src/views/admin/dashboard.php` | Eliminado el `<style>` inline duplicado (ver `48d2c5a`) |

## Verificación

- `php -l` OK en `endpoints/ajax_lang.php`, `router.php`, header/footer.
- `node --check public/js/rv-lang.js` OK; test funcional con **jsdom** real
  (solo dev, `scratch/langjs/`): POST + segundo fetch emitidos, `<html lang>`,
  `<title>`, metas, textos, `placeholder`/`title`/`aria-label` actualizados,
  **identidad del nodo conservada**, listener del nodo sigue vivo, `.lang-switch`
  regenerado (JA seleccionado) y clase de transición limpiada. Con el fix de
  iconos: enlaces con `<svg.pxi>` traducen su texto, `svgs` de iconos y logo
  intactos, nodo runtime extra en medio no rompe el resto (lookahead). Con la
  transición localizada: `.rv-lang-fade` solo en elementos cambiados sin media
  (h1, nav, menú admin); un `<p>` cuyo subtree contiene `<img>` se traduce pero
  **no** recibe la clase.
- Smoke HTTP (`php -S localhost:8022 router.php`): POST válido → 200 + cookie
  `rv_lang=ja`; SSR siguiente en japonés; `lang=ru` → 400; GET → 405;
  `Sec-Fetch-Site: cross-site` → 403; home carga `rv-lang.js` y el form
  `data-enhance="lang"`.
- Suite completa: `composer test` **OK (166 tests, 7489 assertions)**.
- `IntlHelper::emulatorLanguage()` (ítem 5.3): `php -l` OK; `I18nTest` (17/17)
  cubre el mapeo `es→es-ES / en→en-US / fr→en-US / pt→pt-BR / de→de-GER /
  ja→ja-JA` y que el código devuelto siempre exista en el CDN.
- Detección automática del navegador (jsdom, `scratch/langjs/`, solo dev):
  **16/16** — sin cookie + `de-DE` con SSR `es` → 1 POST `lang=de` + refetch +
  `<html lang="de">` sincronizado; sin cookie + `en` con SSR `en` → 1 POST
  silencioso sin refetch; con cookie `rv_lang=fr` → sin POST; `ru-RU` → sin
  acción; `[ru-RU, pt-BR]` → detecta `pt`; `languages=[]` + `language=ja` →
  detecta `ja`.
- Cabecera admin (ítem `48d2c5a`): `dashboard.php` ya **no** emite `<style>`
  inline; `.admin-header` sirve `flex-direction: column` desde `admin.css`.
  Verificación visual final a cargo del usuario en `/admin/dashboard` cambiando
  de idioma (es/en/fr/de/ja).

## Pendiente

- RTL (`ar`/`he`) sigue descartado (sección 5.2).
- Deuda detectada: `en.json` tiene `play.error_sin_juego` con texto en español
  ("Error: No se especificó el juego a reproducir.") — traducirla en una
  pasada de revisión de traducciones.
- `fr` recibe la UI de EmulatorJS en inglés porque el CDN no publica francés;
  si algún día lo hace, añadirlo a `IntlHelper::EMULATOR_LOCALES`.
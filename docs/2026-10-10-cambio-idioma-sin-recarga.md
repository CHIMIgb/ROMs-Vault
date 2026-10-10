# Cambio de idioma sin recarga de página (FASE 5.3)

> Fecha: 2026-10-10 · Commits: `3561a05` (feature) · `a9746e5` (fix iconos/whitespace en syncNode) · `7eb9136` (KPI total_jugadas + orden en.json)
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

## Archivos

| Archivo | Cambio |
|---------|--------|
| `endpoints/ajax_lang.php` | **Nuevo**: endpoint JSON para fijar la cookie (POST, guard, whitelist) |
| `public/js/rv-lang.js` | **Nuevo**: IIFE `'use strict'`, delegación de submit, doble `fetch`, `syncNode`/`syncMeta`/`swapLangSwitch`, transición y fallback |
| `router.php` | `'ajax_lang.php'` añadido a la whitelist `$entrypoints` |
| `src/views/layout/header.php` | `<form class="lang-switch" … data-enhance="lang">` |
| `src/views/layout/footer.php` | `<script src="public/js/rv-lang.js" defer>` junto a pixelicons/rv-alerts |
| `public/css/modules/layout.css` | `@keyframes rv-lang-pop` + `.rv-lang-fade` + `prefers-reduced-motion` |
| `src/i18n/*.json` | Clave KPI `admin.dashboard.total_jugadas` en los 6 idiomas (ver `7eb9136`) |

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
- Suite completa: `composer test` **OK (164 tests, 7471 assertions)**.

## Pendiente

- `EJS_language` sigue hardcodeado `'es-ES'` en `src/views/home/play.php:266` —
  parametrizarlo según `IntlHelper::getLocale()` (candidato
  `str_replace('_','-', …)`).
- RTL (`ar`/`he`) sigue descartado (sección 5.2).
- Deuda detectada: `en.json` tiene `play.error_sin_juego` con texto en español
  ("Error: No se especificó el juego a reproducir.") — traducirla en una
  pasada de revisión de traducciones.
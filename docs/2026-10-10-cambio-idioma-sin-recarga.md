# Cambio de idioma sin recarga de página (FASE 5.3)

> Fecha: 2026-10-10 · Commit: `3561a05`
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
   modales), avanza sin romperse.

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

4. **Transición suave** por CSS: `html.lang-switching body { opacity: .7;
   pointer-events: none }` + `transition: opacity .15s ease-in-out` en `body`
   (se añade al módulo `layout.css`).

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
| `public/css/modules/layout.css` | Transición `html.lang-switching body` |

## Verificación

- `php -l` OK en `endpoints/ajax_lang.php`, `router.php`, header/footer.
- `node --check public/js/rv-lang.js` OK; test funcional con **jsdom** real
  (solo dev, `scratch/langjs/`): POST + segundo fetch emitidos, `<html lang>`,
  `<title>`, metas, textos, `placeholder`/`title`/`aria-label` actualizados,
  **identidad del nodo conservada**, listener del nodo sigue vivo, `.lang-switch`
  regenerado (JA seleccionado) y clase de transición limpiada.
- Smoke HTTP (`php -S localhost:8022 router.php`): POST válido → 200 + cookie
  `rv_lang=ja`; SSR siguiente en japonés; `lang=ru` → 400; GET → 405;
  `Sec-Fetch-Site: cross-site` → 403; home carga `rv-lang.js` y el form
  `data-enhance="lang"`.
- Suite completa: `composer test` **OK (164 tests, 7459 assertions)**.

## Pendiente

- `EJS_language` sigue hardcodeado `'es-ES'` en `src/views/home/play.php:266` —
  parametrizarlo según `IntlHelper::getLocale()` (candidato
  `str_replace('_','-', …)`).
- RTL (`ar`/`he`) sigue descartado (sección 5.2).
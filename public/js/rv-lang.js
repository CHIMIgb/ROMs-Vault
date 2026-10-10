/**
 * rv-lang.js — Cambio de idioma sin recargar la página (ROMs Vault, FASE 5.3)
 * ===========================================================================
 * Progressive enhancement del selector `.lang-switch`:
 *
 *  1. Intercepta el submit del formulario de idioma (delegación en document).
 *  2. Hace POST a /ajax_lang.php para fijar la cookie `rv_lang` en el servidor.
 *  3. Vuelve a pedir la página actual por `fetch()` (SSR) ya en el nuevo idioma.
 *  4. Aplica los cambios EN SITIO: solo reescribe nodos de texto y los
 *     atributos traducidos (placeholder/title/aria-label/alt) y actualiza
 *     <html lang>, <title> y metas. NO reemplaza el <body>, por lo que se
 *     preservan todos los listeners y el estado de los scripts de la página
 *     (filtros, carrusel, EmulatorJS…). El DOM es estructuralmente idéntico
 *     entre idiomas: solo cambia el texto.
 *
 * Sin JavaScript, el formulario sigue funcionando como GET normal (recarga).
 */
(function (global) {
    'use strict';

    var ENDPOINT = '/ajax_lang.php';

    // No tocar el propio selector (sus banderas SVG cambian de estructura), ni
    // scripts/estilos, ni nada marcado con data-lang-skip.
    var SKIP_TAGS = { SCRIPT: 1, STYLE: 1, TEMPLATE: 1, NOSCRIPT: 1 };
    var ATTRS = ['placeholder', 'title', 'aria-label', 'alt'];

    if (!global.fetch || !global.DOMParser || !global.URLSearchParams || !global.Promise) {
        return; // sin soporte: se queda el submit GET nativo
    }

    function sameKind(a, b) {
        if (a.nodeType !== b.nodeType) return false;
        if (a.nodeType === 1) return a.tagName === b.tagName;
        return true;
    }

    function shouldSkip(el) {
        if (el.nodeType !== 1) return false;
        if (SKIP_TAGS[el.tagName]) return true;
        if (el.classList && el.classList.contains('lang-switch')) return true;
        if (el.hasAttribute && el.hasAttribute('data-lang-skip')) return true;
        return false;
    }

    /**
     * Un nodo "icono" (pixelicons) nunca lleva texto traducible: en el DOM en
     * vivo es un <svg class="pxi"> (pixelicons reemplaza el marcador con
     * replaceWith), mientras que en el HTML fetcheado sigue siendo el marcador
     * <i data-i="..."> o <span class="pxi" data-i="...">. Si no se filtran,
     * disparan el emparejamiento de syncNode y "roban" la alineación al texto
     * que les sigue (nav del header, menú del dashboard admin, botones…).
     */
    function isIconNode(n) {
        if (n.nodeType !== 1) return false;
        if (n.hasAttribute && n.hasAttribute('data-i')) return true;
        if (n.classList && n.classList.contains('pxi')) return true;
        return false;
    }

    // Ventana de lookahead: cuántos nodos del HTML nuevo se miran para
    // reanudar la alineación tras un nodo runtime extra (modales, grids, etc.)
    var LOOKAHEAD = 6;

    function alignIndex(node, list, start) {
        var end = Math.min(start + LOOKAHEAD, list.length);
        for (var k = start; k < end; k++) {
            if (sameKind(node, list[k])) return k;
        }
        return -1; // no hay equivalente cercano: nodo extra solo en el DOM actual
    }

    /** Texto solo whitespace: transparente para la alineación. */
    function wsOnly(n) {
        return n.nodeType === 3 && /^\s*$/.test(n.data);
    }

    /** Devuelve los hijos de un elemento ignorando iconos y whitespace. */
    function kindChildren(el) {
        var out = [];
        var kids = el.childNodes;
        for (var i = 0; i < kids.length; i++) {
            if (isIconNode(kids[i])) continue;
            if (wsOnly(kids[i])) continue;
            out.push(kids[i]);
        }
        return out;
    }

    /**
     * Sincroniza en sitio el nodo actual con su equivalente en el HTML nuevo.
     * Tolerante a nodos añadidos/eliminados en tiempo de ejecución: filtra los
     * iconos y usa lookahead para reanudar tras un nodo extra; nunca revienta.
     */
    function syncNode(cur, inc) {
        if (!cur || !inc) return;

        if (cur.nodeType === 3) { // TEXT_NODE
            if (inc.nodeType === 3 && cur.data !== inc.data) {
                cur.data = inc.data;
            }
            return;
        }
        if (cur.nodeType !== 1) return; // comentarios y otros: ignorar
        if (shouldSkip(cur)) return;

        for (var k = 0; k < ATTRS.length; k++) {
            var attr = ATTRS[k];
            var nv = inc.getAttribute(attr);
            if (nv !== null && cur.getAttribute(attr) !== nv) {
                cur.setAttribute(attr, nv);
            }
        }

        var a = kindChildren(cur);
        var b = kindChildren(inc);
        var j = 0;
        for (var i = 0; i < a.length; i++) {
            var m = alignIndex(a[i], b, j);
            if (m < 0) continue; // nodo extra solo en el DOM actual: saltarlo
            syncNode(a[i], b[m]);
            j = m + 1;
        }
    }

    function syncMeta(parsed) {
        var incoming = {};
        parsed.querySelectorAll('meta[name], meta[property]').forEach(function (m) {
            var key = m.getAttribute('name') || m.getAttribute('property');
            if (key) incoming[key] = m.getAttribute('content');
        });
        document.querySelectorAll('meta[name], meta[property]').forEach(function (m) {
            var key = m.getAttribute('name') || m.getAttribute('property');
            if (key && Object.prototype.hasOwnProperty.call(incoming, key)) {
                var nv = incoming[key];
                if (nv !== null && m.getAttribute('content') !== nv) {
                    m.setAttribute('content', nv);
                }
            }
        });
    }

    function swapLangSwitch(parsed) {
        var cur = document.querySelector('.lang-switch');
        var inc = parsed.querySelector('.lang-switch');
        if (cur && inc) {
            cur.innerHTML = inc.innerHTML;
        }
    }

    function applyDocument(parsed, lang) {
        var newLang = parsed.documentElement.getAttribute('lang') || lang;
        document.documentElement.setAttribute('lang', newLang);
        if (parsed.title) document.title = parsed.title;
        syncMeta(parsed);
        if (parsed.body) syncNode(document.body, parsed.body);
        swapLangSwitch(parsed);
    }

    function fallbackNavigation(lang) {
        var url = new URL(global.location.href);
        url.searchParams.set('lang', lang);
        global.location.assign(url.href);
    }

    function switchLanguage(lang) {
        var root = document.documentElement;
        if (root.classList.contains('lang-switching')) return;
        root.classList.add('lang-switching');

        Promise.resolve()
            .then(function () {
                return fetch(ENDPOINT, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
                    body: new URLSearchParams({ lang: lang }),
                    credentials: 'same-origin',
                    cache: 'no-store'
                });
            })
            .then(function (res) {
                if (!res.ok) throw new Error('lang-endpoint-' + res.status);
                return fetch(global.location.href, {
                    headers: { 'X-Requested-With': 'XMLHttpRequest' },
                    credentials: 'same-origin',
                    cache: 'no-store'
                });
            })
            .then(function (res) {
                if (!res.ok) throw new Error('page-' + res.status);
                return res.text();
            })
            .then(function (html) {
                var parsed = new DOMParser().parseFromString(html, 'text/html');
                if (!parsed || !parsed.body) throw new Error('parse');
                applyDocument(parsed, lang);
                document.dispatchEvent(new CustomEvent('rv:languagechange', { detail: { lang: lang } }));
            })
            .catch(function () {
                fallbackNavigation(lang);
            })
            .finally(function () {
                global.requestAnimationFrame(function () {
                    root.classList.remove('lang-switching');
                });
            });
    }

    // Delegación: sigue funcionando aunque el propio formulario se regenere.
    document.addEventListener('submit', function (e) {
        var form = e.target;
        if (!form || !form.classList || !form.classList.contains('lang-switch')) return;

        var lang = '';
        if (e.submitter && e.submitter.name === 'lang') {
            lang = e.submitter.value;
        } else {
            var sel = form.querySelector('button[name="lang"][aria-selected="true"]');
            lang = sel ? sel.value : '';
        }
        if (!lang) return;

        e.preventDefault();
        if (lang === document.documentElement.getAttribute('lang')) return;
        switchLanguage(lang);
    });

})(window);

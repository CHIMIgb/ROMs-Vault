/**
 * pixelicons.js — ROMs Vault Pixel Art Icon System
 * ─────────────────────────────────────────────────
 * Usa pixelarticons@1.8.1 (https://pixelarticons.com)
 * MIT License — halfmage
 *
 * Los SVG paths son los originales del repositorio:
 * https://github.com/halfmage/pixelarticons/tree/master/svg
 *
 * Uso en HTML:
 *   <span class="pxi" data-i="gamepad"></span>
 *
 * El script inyecta el SVG inline automáticamente al cargar.
 * currentColor hereda el color CSS del elemento padre.
 */

(function () {
    'use strict';

    // ── SVG paths reales de pixelarticons@1.8.1 ──────────────────────────
    // Cada valor es el contenido <path d="..."> del SVG 24×24
    const PATHS = {
        // Navegación
        'home':        'M12 3L4 9v12h5v-7h6v7h5V9z',
        'logout':      'M5 3H3v18h2v-2H5V5h0v16H3V3h2zm4 4H7v2H5v2H3v2h2v2h2v2h2v-2h2v2h2v-2h-2V9h2V7h-2zm2 4v2h-2v-2z',
        'shield':      'M12 2L4 6v6c0 4.4 3.4 8.5 8 9.5 4.6-1 8-5.1 8-9.5V6zm0 2.2l6 2.7V12c0 3.2-2.5 6.3-6 7.3-3.5-1-6-4.1-6-7.3V6.9z',
        'shield-2':    'M12 2L4 6v6c0 4.4 3.4 8.5 8 9.5 4.6-1 8-5.1 8-9.5V6zm0 2.2l6 2.7V12c0 3.2-2.5 6.3-6 7.3-3.5-1-6-4.1-6-7.3V6.9zM10 15l-3-3 1.4-1.4 1.6 1.6 4.6-4.6L16 9z',

        // Gaming
        'gamepad':     'M17 6H7c-2.8 0-5 2.2-5 5s2.2 5 5 5h10c2.8 0 5-2.2 5-5s-2.2-5-5-5zm-9 7H6v-2h2v-2h2v2h2v2h-2v2H8v-2zm7 1h-2v-2h2v2zm2-3h-2v-2h2v2z',
        'gamepad-2':   'M2 8h20v9H2zm5 2H5v2h2v2h2v-2h2v-2H9v-2H7zm7 2h2v2h2v-2h2v-2h-2v-2h-2v2h-2z',
        'joystick':    'M11 3h2v9h-2zM7 5h10v2H7zm1 13h8v3H8zM6 16h12v2H6zM9 12h6v2H9z',
        'play':        'M8 5v14l11-7z',
        'disc':        'M12 2C6.5 2 2 6.5 2 12s4.5 10 10 10 10-4.5 10-10S17.5 2 12 2zm0 18c-4.4 0-8-3.6-8-8s3.6-8 8-8 8 3.6 8 8-3.6 8-8 8zm0-12c-2.2 0-4 1.8-4 4s1.8 4 4 4 4-1.8 4-4-1.8-4-4-4zm0 6c-1.1 0-2-.9-2-2s.9-2 2-2 2 .9 2 2-.9 2-2 2z',
        'trophy':      'M7 2H5v6H3v4h2c.5 2.3 2 4.2 4 5.4V20h6v-2.6c2-1.2 3.5-3.1 4-5.4h2V8h-2V2h-2v6H7V2zm9 8c0 2.2-1.8 4-4 4s-4-1.8-4-4V8h8v2zm-3 8H11v-1.1c.3.1.7.1 1 .1s.7 0 1-.1V18z',
        'sword':       'M3 21l4-4 1 1-4 4-1-1zm3-5l9-9-1-1L5 15l1 1zm4-10L8 4l12 1-1 12-2-2 1-9z',
        'castle':      'M3 2h2v4H3zm4 0h2v4H7zm4 0h2v4h-2zm4 0h2v4h-2zm4 0h2v4h-2zM2 6h20v14H2zm7 4v8h6v-8h-6z',

        // Acciones
        'download':    'M5 20h14v-2H5v2zm7-18v14l-5-5-1.4 1.4L12 19l6.4-6.6L17 11l-4 4V2h-2z',
        'upload':      'M5 20h14v-2H5v2zM12 2L5.4 8.6 7 10l4-4v12h2V6l4 4 1.6-1.4z',
        'search':      'M15.5 14h-.79l-.28-.27A6.47 6.47 0 0 0 16 9.5 6.5 6.5 0 1 0 9.5 16c1.61 0 3.09-.59 4.23-1.57l.27.28v.79l5 4.99L20.49 19zm-6 0C7.01 14 5 11.99 5 9.5S7.01 5 9.5 5 14 7.01 14 9.5 11.99 14 9.5 14z',
        'link':        'M3.9 12c0-1.7 1.4-3.1 3.1-3.1h4V7H7C4.2 7 2 9.2 2 12s2.2 5 5 5h4v-1.9H7c-1.7 0-3.1-1.4-3.1-3.1zM8 13h8v-2H8v2zm9-6h-4v1.9h4c1.7 0 3.1 1.4 3.1 3.1s-1.4 3.1-3.1 3.1h-4V17h4c2.8 0 5-2.2 5-5s-2.2-5-5-5z',
        'edit':        'M3 17.25V21h3.75L17.81 9.94l-3.75-3.75L3 17.25zM20.71 7.04a1 1 0 0 0 0-1.41l-2.34-2.34a1 1 0 0 0-1.41 0l-1.83 1.83 3.75 3.75 1.83-1.83z',
        'reload':      'M17.65 6.35A7.96 7.96 0 0 0 12 4C7.58 4 4 7.58 4 12s3.58 8 8 8c3.73 0 6.84-2.55 7.73-6h-2.08A5.99 5.99 0 0 1 12 18c-3.31 0-6-2.69-6-6s2.69-6 6-6c1.66 0 3.14.69 4.22 1.78L13 11h7V4l-2.35 2.35z',
        'close':       'M19 6.41L17.59 5 12 10.59 6.41 5 5 6.41 10.59 12 5 17.59 6.41 19 12 13.41 17.59 19 19 17.59 13.41 12z',
        'check':       'M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z',
        'expand':      'M7 14H5v5h5v-2H7v-3zm-2-4h2V7h3V5H5v5zm12 7h-3v2h5v-5h-2v3zM14 5v2h3v3h2V5h-5z',
        'share':       'M18 16.08c-.76 0-1.44.3-1.96.77L8.91 12.7c.05-.23.09-.46.09-.7s-.04-.47-.09-.7l7.05-4.11A2.996 2.996 0 0 0 21 5c0-1.66-1.34-3-3-3s-3 1.34-3 3c0 .24.04.47.09.7L8.04 9.81A2.996 2.996 0 0 0 6 9c-1.66 0-3 1.34-3 3s1.34 3 3 3c.79 0 1.5-.31 2.04-.81l7.12 4.16c-.05.21-.08.43-.08.65a2.99 2.99 0 0 0 2.98 3A2.99 2.99 0 0 0 21 19a2.99 2.99 0 0 0-3-2.92z',

        // Estado / alertas
        'warning':     'M1 21h22L12 2 1 21zm12-3h-2v-2h2v2zm0-4h-2v-4h2v4z',
        'info':        'M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-6h2v6zm0-8h-2V7h2v2z',
        'lock':        'M18 8h-1V6c0-2.76-2.24-5-5-5S7 3.24 7 6v2H6c-1.1 0-2 .9-2 2v10c0 1.1.9 2 2 2h12c1.1 0 2-.9 2-2V10c0-1.1-.9-2-2-2zm-6 9c-1.1 0-2-.9-2-2s.9-2 2-2 2 .9 2 2-.9 2-2 2zm3.1-9H8.9V6c0-1.71 1.39-3.1 3.1-3.1 1.71 0 3.1 1.39 3.1 3.1v2z',
        'trash':       'M6 19c0 1.1.9 2 2 2h8c1.1 0 2-.9 2-2V7H6v12zM19 4h-3.5l-1-1h-5l-1 1H5v2h14V4z',
        'clock':       'M11.99 2C6.47 2 2 6.48 2 12s4.47 10 9.99 10C17.52 22 22 17.52 22 12S17.52 2 11.99 2zM12 20c-4.42 0-8-3.58-8-8s3.58-8 8-8 8 3.58 8 8-3.58 8-8 8zm.5-13H11v6l5.25 3.15.75-1.23-4.5-2.67V7z',
        'wifi':        'M1 9l2 2c4.97-4.97 13.03-4.97 18 0l2-2C16.93 2.93 7.08 2.93 1 9zm8 8l3 3 3-3a4.237 4.237 0 0 0-6 0zm-4-4 2 2a7.074 7.074 0 0 1 10 0l2-2C15.14 9.14 8.87 9.14 5 13z',
        'globe':       'M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-1 17.93c-3.95-.49-7-3.85-7-7.93 0-.62.08-1.21.21-1.79L9 15v1c0 1.1.9 2 2 2v1.93zm6.9-2.54c-.26-.81-1-1.39-1.9-1.39h-1v-3c0-.55-.45-1-1-1H8v-2h2c.55 0 1-.45 1-1V7h2c1.1 0 2-.9 2-2v-.41c2.93 1.19 5 4.06 5 7.41 0 2.08-.8 3.97-2.1 5.39z',
        'save':        'M17 3H5a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14c1.1 0 2-.9 2-2V7l-4-4zm-5 16c-1.66 0-3-1.34-3-3s1.34-3 3-3 3 1.34 3 3-1.34 3-3 3zm3-10H5V5h10v4z',
        'lightbulb':   'M9 21c0 .55.45 1 1 1h4c.55 0 1-.45 1-1v-1H9v1zm3-19C8.14 2 5 5.14 5 9c0 2.38 1.19 4.47 3 5.74V17c0 .55.45 1 1 1h6c.55 0 1-.45 1-1v-2.26c1.81-1.27 3-3.36 3-5.74 0-3.86-3.14-7-7-7z',
        'image':       'M21 19V5c0-1.1-.9-2-2-2H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2zM8.5 13.5l2.5 3.01L14.5 12l4.5 6H5l3.5-4.5z',

        // Multimedia / interfaz
        'chevron-left':  'M15.41 7.41L14 6l-6 6 6 6 1.41-1.41L10.83 12z',
        'chevron-right': 'M10 6L8.59 7.41 13.17 12l-4.58 4.59L10 18l6-6z',
        'arrow-left':    'M20 11H7.83l5.59-5.59L12 4l-8 8 8 8 1.41-1.41L7.83 13H20v-2z',
        'arrow-right':   'M12 4l-1.41 1.41L16.17 11H4v2h12.17l-5.58 5.59L12 20l8-8z',
        'menu':          'M3 18h18v-2H3v2zm0-5h18v-2H3v2zm0-7v2h18V6H3z',
        'plus':          'M19 13h-6v6h-2v-6H5v-2h6V5h2v6h6v2z',
        'minus':         'M19 13H5v-2h14v2z',
        'user':          'M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z',
        'dashboard':     'M3 13h8V3H3v10zm0 8h8v-6H3v6zm10 0h8V11h-8v10zm0-18v6h8V3h-8z',
        'hard-drive':    'M6 2h12l4 8-4 12H6L2 10zm12 14H6l-1-3h14zm1-5H5L4 8l2-4h12l2 4z',

        // Error page
        'file-x':        'M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8zm4 18H6V4h7v5h5v11zm-9-4.59L10.41 14 9 12.59 10.59 11 9 9.41 10.41 8 12 9.59 13.59 8 15 9.41 13.41 11 15 12.59 13.59 14z',
        'slash':         'M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm0 18c-4.42 0-8-3.58-8-8 0-1.85.63-3.55 1.69-4.9L16.9 18.31A7.902 7.902 0 0 1 12 20zm6.31-3.1L7.1 5.69A7.902 7.902 0 0 1 12 4c4.42 0 8 3.58 8 8 0 1.85-.63 3.55-1.69 4.9z',
        'zap':           'M7 2v11h3v9l7-12h-4l4-8z',
        'settings-cog':  'M19.14 12.94c.04-.3.06-.61.06-.94 0-.32-.02-.64-.07-.94l2.03-1.58c.18-.14.23-.41.12-.61l-1.92-3.32c-.12-.22-.37-.29-.59-.22l-2.39.96c-.5-.38-1.03-.7-1.62-.94l-.36-2.54c-.04-.24-.24-.41-.48-.41h-3.84c-.24 0-.43.17-.47.41l-.36 2.54c-.59.24-1.13.57-1.62.94l-2.39-.96c-.22-.08-.47 0-.59.22L2.74 8.87c-.12.21-.08.47.12.61l2.03 1.58c-.05.3-.09.63-.09.94s.02.64.07.94l-2.03 1.58c-.18.14-.23.41-.12.61l1.92 3.32c.12.22.37.29.59.22l2.39-.96c.5.38 1.03.7 1.62.94l.36 2.54c.05.24.24.41.48.41h3.84c.24 0 .44-.17.47-.41l.36-2.54c.59-.24 1.13-.56 1.62-.94l2.39.96c.22.08.47 0 .59-.22l1.92-3.32c.12-.22.07-.47-.12-.61l-2.01-1.58zM12 15.6c-1.98 0-3.6-1.62-3.6-3.6s1.62-3.6 3.6-3.6 3.6 1.62 3.6 3.6-1.62 3.6-3.6 3.6z',

        // Upload file
        'upload-2':      'M9 16h6v-6h4l-7-7-7 7h4v6zm-4 2h14v2H5v-2z',

        // Password eye
        'eye':           'M12 4.5C7 4.5 2.73 7.61 1 12c1.73 4.39 6 7.5 11 7.5s9.27-3.11 11-7.5c-1.73-4.39-6-7.5-11-7.5zM12 17c-2.76 0-5-2.24-5-5s2.24-5 5-5 5 2.24 5 5-2.24 5-5 5zm0-8c-1.66 0-3 1.34-3 3s1.34 3 3 3 3-1.34 3-3-1.34-3-3-3z',
        'eye-off':       'M12 7c2.76 0 5 2.24 5 5 0 .65-.13 1.26-.36 1.83l2.92 2.92c1.51-1.26 2.7-2.89 3.43-4.75-1.73-4.39-6-7.5-11-7.5-1.4 0-2.74.25-3.98.7l2.16 2.16C10.74 7.13 11.35 7 12 7zM2 4.27l2.28 2.28.46.46A11.804 11.804 0 0 0 1 12c1.73 4.39 6 7.5 11 7.5 1.55 0 3.03-.3 4.38-.84l.42.42L19.73 22 21 20.73 3.27 3 2 4.27zM7.53 9.8l1.55 1.55c-.05.21-.08.43-.08.65 0 1.66 1.34 3 3 3 .22 0 .44-.03.65-.08l1.55 1.55c-.67.33-1.41.53-2.2.53-2.76 0-5-2.24-5-5 0-.79.2-1.53.53-2.2zm4.31-.78 3.15 3.15.02-.16c0-1.66-1.34-3-3-3l-.17.01z',
    };

    // ── Función: crear SVG element desde un path ──────────────────────────
    function makeSVG(iconName, extraClass, size) {
        const d = PATHS[iconName];
        if (!d) return null;
        const svg = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
        svg.setAttribute('viewBox', '0 0 24 24');
        svg.setAttribute('fill', 'currentColor');
        svg.setAttribute('aria-hidden', 'true');
        svg.setAttribute('width',  size || '1em');
        svg.setAttribute('height', size || '1em');
        svg.className.baseVal = 'pxi' + (extraClass ? ' ' + extraClass : '');
        const path = document.createElementNS('http://www.w3.org/2000/svg', 'path');
        path.setAttribute('d', d);
        svg.appendChild(path);
        return svg;
    }

    // ── Inyectar iconos en todos los [data-i] del DOM ─────────────────────
    function injectAll(root) {
        root = root || document;
        root.querySelectorAll('[data-i]').forEach(el => {
            const name       = el.dataset.i;
            const extraClass = el.dataset.cls || '';
            const size       = el.dataset.sz  || '';
            const svg = makeSVG(name, extraClass, size);
            if (!svg) return;
            // Copiar atributos de accesibilidad
            const label = el.getAttribute('aria-label');
            if (label) {
                svg.removeAttribute('aria-hidden');
                svg.setAttribute('aria-label', label);
                svg.setAttribute('role', 'img');
            }
            el.replaceWith(svg);
        });
    }

    // ── API pública ───────────────────────────────────────────────────────
    window.PXI = {
        // Obtiene SVG element
        get: makeSVG,
        // Obtiene string SVG para insertar con innerHTML / PHP
        svg: function(name, cls, size) {
            const s = makeSVG(name, cls, size);
            return s ? s.outerHTML : '';
        },
        // Inyecta todos los iconos en el documento o un nodo
        inject: injectAll,
        // Genera string de path para PHP helper
        path: function(name) { return PATHS[name] || ''; }
    };

    // Auto-inyectar al cargar DOM
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', () => injectAll());
    } else {
        injectAll();
    }

    // Observer para inyectar en contenido dinámico (AJAX)
    const obs = new MutationObserver(mutations => {
        mutations.forEach(m => {
            m.addedNodes.forEach(node => {
                if (node.nodeType !== 1) return;
                if (node.hasAttribute && node.hasAttribute('data-i')) injectAll(node.parentElement);
                else if (node.querySelector) injectAll(node);
            });
        });
    });
    obs.observe(document.body, { childList: true, subtree: true });

})();

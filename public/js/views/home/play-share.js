// views/home/play-share.js - copiar el enlace de la ficha (pantalla de juego)
(function () {
    'use strict';

    const data = JSON.parse(
        (document.getElementById('rv-data-play-share') || {}).textContent || '{}'
    );

    // ── Botón Compartir ───────────────────────────────────────────────────
    const btnShare = document.getElementById('btn-share-game');
    const toast = document.getElementById('share-toast');

    if (btnShare) {
        btnShare.addEventListener('click', () => {
            const url = btnShare.dataset.url;
            copyToClipboard(url);
        });
    }

    function copyToClipboard(text) {
        // Método 1: API moderna (requiere HTTPS o localhost)
        if (navigator.clipboard && window.isSecureContext) {
            navigator.clipboard.writeText(text)
                .then(() => showToast('<i data-i="check"></i> ' + data.t['play.enlace_copiado']))
                .catch(() => legacyCopy(text));
            return;
        }
        // Método 2: execCommand — funciona en HTTP e IPs locales
        legacyCopy(text);
    }

    function legacyCopy(text) {
        const ta = document.createElement('textarea');
        ta.value = text;
        ta.style.cssText = 'position:fixed;top:0;left:0;opacity:0;pointer-events:none;';
        document.body.appendChild(ta);
        ta.focus();
        ta.select();
        try {
            const ok = document.execCommand('copy');
            showToast(ok
                ? '<i data-i="check"></i> ' + data.t['play.enlace_copiado']
                : '<i data-i="close"></i> ' + data.t['play.no_se_pudo_copiar']);
        } catch (_) {
            showToast('<i data-i="close"></i> ' + data.t['play.no_se_pudo_copiar_enlace']);
        }
        document.body.removeChild(ta);
    }

    function showToast(msg) {
        if (!toast) return;
        toast.innerHTML = msg;
        toast.classList.add('share-toast--visible');
        setTimeout(() => toast.classList.remove('share-toast--visible'), 2800);
    }

})();

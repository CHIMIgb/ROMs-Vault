// views/home/show-share.js - copiar el enlace de la ficha
(function () {
    'use strict';
    var btn   = document.getElementById('btn-share-game');
    var toast = document.getElementById('share-toast');
    if (!btn || !toast) return;

    function showToast() {
        toast.classList.add('share-toast--visible');
        clearTimeout(showToast._t);
        showToast._t = setTimeout(function () {
            toast.classList.remove('share-toast--visible');
        }, 2200);
    }

    function fallbackCopy(url) {
        var ta = document.createElement('textarea');
        ta.value = url;
        ta.setAttribute('readonly', '');
        ta.style.position = 'fixed';
        ta.style.opacity = '0';
        document.body.appendChild(ta);
        ta.select();
        try { document.execCommand('copy'); } catch (e) {}
        document.body.removeChild(ta);
    }

    btn.addEventListener('click', function () {
        var url = window.location.href;
        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(url).then(showToast, function () {
                fallbackCopy(url);
                showToast();
            });
        } else {
            fallbackCopy(url);
            showToast();
        }
    });
})();

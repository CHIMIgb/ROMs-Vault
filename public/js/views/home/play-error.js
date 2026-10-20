// views/home/play-error.js
// Botón "Reintentar ahora" de la página de error de quota (B3 — auditoría
// UI/UX 2026-10-17): sustituye a href="javascript:location.reload()".
// Mejora progresiva: sin JS, el href del enlace (la URL actual) recarga la página.
(function () {
    const retry = document.querySelector('.btn-retry[data-reload]');
    if (!retry) return;
    retry.addEventListener('click', e => {
        e.preventDefault();
        window.location.reload();
    });
})();

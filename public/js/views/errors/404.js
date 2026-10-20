// views/errors/404.js
// Cargado por errors/404.php y errors/generic.php (pantallas de error hermanas).
//
// Conteo de créditos ascendente decorativo (solo el 404 lo renderiza;
// en generic.php este bloque sale y no hace nada).
(function () {
    let n = 0;
    const el = document.getElementById('credits-num');
    if (!el) return;
    const timer = setInterval(() => {
        n = Math.min(n + 1, 99);
        el.textContent = String(n).padStart(2, '0');
        if (n >= 99) clearInterval(timer);
    }, 40);
})();

// Botón "Volver atrás" (B3 — auditoría UI/UX 2026-10-17): sustituye al
// href="javascript:history.back()" (URI de JS obsoleta y frágil ante CSP).
// Mejora progresiva: sin JS, o sin historial previo, el href="/" del enlace
// lleva al catálogo.
(function () {
    const back = document.querySelector('.btn-404-back[data-back]');
    if (!back) return;
    back.addEventListener('click', e => {
        e.preventDefault();
        if (window.history.length > 1) {
            window.history.back();
        } else {
            window.location.href = '/';
        }
    });
})();

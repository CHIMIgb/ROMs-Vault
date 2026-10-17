// views/errors/404.js
// Conteo de créditos ascendente decorativo
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

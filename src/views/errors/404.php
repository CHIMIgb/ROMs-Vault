<?php
// views/errors/404.php
// Variable opcional: $errorContext — detalle técnico para el admin
?>
<div class="error-404-page">

    <!-- Pantalla de Game Over estilo arcade -->
    <div class="error-404-screen">
        <div class="error-404-scanlines"></div>

        <div class="error-404-code">404</div>
        <div class="error-404-gameover">GAME OVER</div>
        <div class="error-404-insert">INSERT COIN TO CONTINUE</div>

        <div class="error-404-blink">▼</div>
    </div>

    <h1 class="error-404-title">Página no encontrada</h1>

    <p class="error-404-msg">
        La URL que escribiste no existe en este servidor.
        Puede que el enlace esté roto, que la página haya sido movida,
        o que hayas escrito mal la dirección.
    </p>

    <!-- Acciones -->
    <div class="error-404-actions">
        <a href="/" class="btn-404-home">
            Ir al catálogo
        </a>
        <a href="javascript:history.back()" class="btn-404-back">
            ← Volver atrás
        </a>
    </div>

    <?php if (!empty($errorContext) && AuthMiddleware::getUser()): ?>
    <!-- Solo visible para admins logueados -->
    <details class="error-404-details">
        <summary>Detalles técnicos (admin)</summary>
        <div class="error-technical-content">
            <div><span>URL:</span> <code><?= htmlspecialchars($_SERVER['REQUEST_URI'] ?? '') ?></code></div>
            <div><span>Detalle:</span> <code><?= htmlspecialchars($errorContext) ?></code></div>
        </div>
    </details>
    <?php endif; ?>

    <!-- Contador de créditos animado -->
    <div class="error-404-credits" id="credits-counter">
        <span id="credits-label">CREDIT(S)</span>
        <span id="credits-num">00</span>
    </div>

</div>

<script>
// Conteo de créditos ascendente decorativo
(function () {
    let n = 0;
    const el = document.getElementById('credits-num');
    if (!el) return;
    const t = setInterval(() => {
        n = Math.min(n + 1, 99);
        el.textContent = String(n).padStart(2, '0');
        if (n >= 99) clearInterval(t);
    }, 40);
})();
</script>

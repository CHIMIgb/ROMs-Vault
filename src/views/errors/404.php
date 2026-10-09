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

    <h1 class="error-404-title"><?= t('errors.not_found') ?></h1>

    <p class="error-404-msg"><?= t('errors.not_found_detalle') ?></p>

    <!-- Acciones -->
    <div class="error-404-actions">
        <a href="/" class="btn-404-home">
            <?= t('errors.ir_catalogo') ?>
        </a>
        <a href="javascript:history.back()" class="btn-404-back">
            <?= t('errors.volver_atras') ?>
        </a>
    </div>

    <?php if (!empty($errorContext) && AuthMiddleware::getUser()): ?>
    <!-- Solo visible para admins logueados -->
    <details class="error-404-details">
        <summary><?= t('errors.detalles_admin') ?></summary>
        <div class="error-technical-content">
            <div><span><?= t('errors.url') ?></span> <code><?= htmlspecialchars($_SERVER['REQUEST_URI'] ?? '') ?></code></div>
            <div><span><?= t('errors.detalle') ?></span> <code><?= htmlspecialchars($errorContext) ?></code></div>
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
    const timer = setInterval(() => {
        n = Math.min(n + 1, 99);
        el.textContent = String(n).padStart(2, '0');
        if (n >= 99) clearInterval(t);
    }, 40);
})();
</script>

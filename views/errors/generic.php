<?php
// views/errors/generic.php
// Variables: $errorCode, $errorTitle, $errorMsg
?>
<div class="error-404-page">
    <div class="error-404-screen">
        <div class="error-404-scanlines"></div>
        <div class="error-404-code"><?= (int)($errorCode ?? 500) ?></div>
        <div class="error-404-gameover">ERROR</div>
        <div class="error-404-insert">PRESS ANY KEY</div>
        <div class="error-404-blink">▼</div>
    </div>

    <h1 class="error-404-title"><?= htmlspecialchars($errorTitle ?? 'Error') ?></h1>
    <p class="error-404-msg"><?= htmlspecialchars($errorMsg ?? 'Ha ocurrido un error inesperado.') ?></p>

    <div class="error-404-actions">
        <a href="/" class="btn-404-home">🏠 Ir al catálogo</a>
        <a href="javascript:history.back()" class="btn-404-back">← Volver atrás</a>
    </div>
</div>

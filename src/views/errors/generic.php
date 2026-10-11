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

    <h1 class="error-404-title"><?= htmlspecialchars($errorTitle ?? t('errors.generico_titulo')) ?></h1>
    <p class="error-404-msg"><?= htmlspecialchars($errorMsg ?? t('errors.generico_msg')) ?></p>

    <div class="error-404-actions">
        <a href="/" class="btn-404-home"><?= t('errors.ir_catalogo_icono') ?></a>
        <a href="javascript:history.back()" class="btn-404-back"><?= t('errors.volver_atras') ?></a>
    </div>
</div>

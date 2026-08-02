<?php
// views/components/game_actions.php
// Uso en el grid del catálogo:  (sin contexto)       → botones compactos .game-play / .game-download
// Uso en la ficha de detalle:   $detailContext = true → botones grandes .detail-btn--play / .detail-btn--download
$isDetail     = !empty($detailContext);
$actionsClass = $isDetail ? 'detail-actions' : 'game-actions';
$playClass    = $isDetail ? 'detail-btn detail-btn--play'     : 'game-play';
$dlClass      = $isDetail ? 'detail-btn detail-btn--download' : 'game-download';
?>
<div class="<?= $actionsClass ?>">
    <?php 
    $consola = strtolower(trim($juego['consola_nombre'] ?? ''));
    if ($consola !== 'psp' && $consola !== 'playstation portable'): 
    ?>
        <a href="index.php?controller=home&action=play&file_id=<?= urlencode($juego['google_drive_file_id']) ?>" class="<?= $playClass ?>">
            <i data-i="play" aria-hidden="true"></i> Jugar Online
        </a>
    <?php endif; ?>
    <a href="index.php?controller=home&action=download&file_id=<?= urlencode($juego['google_drive_file_id']) ?>" class="<?= $dlClass ?>" target="_blank" rel="noopener noreferrer">
        <i data-i="download" aria-hidden="true"></i> Descargar
    </a>
</div>

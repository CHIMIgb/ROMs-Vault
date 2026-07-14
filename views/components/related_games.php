<?php
// views/components/related_games.php
// Requires: $juego (array)

$relacionados = [];
if (!empty($juego['id']) && !empty($juego['consola_id']) && !empty($juego['categoria_id'])) {
    require_once 'models/Juego.php';
    $relacionados = (new Juego())->getRelated(
        (int) $juego['id'],
        (int) $juego['consola_id'],
        (int) $juego['categoria_id'],
        8
    );
}
?>
<?php if (!empty($relacionados)): ?>
    <section class="related-section">
        <h2 class="related-title">
            <span class="related-title-icon"><i data-i="gamepad"></i></span>
            Juegos relacionados
            <span class="related-title-sub">— misma consola o género</span>
        </h2>
        <div class="related-grid">
            <?php foreach ($relacionados as $rel): ?>
                <a href="index.php?controller=home&action=show&id=<?= $rel['id'] ?>"
                    class="related-card" title="<?= htmlspecialchars($rel['titulo']) ?>">
                    <div class="related-cover <?= empty($rel['imagen']) ? 'no-image' : '' ?>">
                        <?php if (!empty($rel['imagen'])): ?>
                            <img src="<?= htmlspecialchars($rel['imagen']) ?>" alt="<?= htmlspecialchars($rel['titulo']) ?>"
                                loading="lazy">
                        <?php else: ?>
                            <i data-i="disc" data-cls="pxi-cover-placeholder"></i>
                        <?php endif; ?>
                    </div>
                    <div class="related-info">
                        <span class="related-game-title"><?= htmlspecialchars($rel['titulo']) ?></span>
                        <span class="related-game-meta">
                            <span class="game-tag platform" style="font-size:.55rem;padding:.1rem .4rem;">
                                <?= htmlspecialchars($rel['consola_nombre'] ?? '') ?>
                            </span>
                            <?php if (!empty($rel['region'])): ?>
                                <span class="game-tag language" style="font-size:.55rem;padding:.1rem .4rem;">
                                    <?= htmlspecialchars($rel['region']) ?>
                                </span>
                            <?php endif; ?>
                        </span>
                    </div>
                </a>
            <?php endforeach; ?>
        </div>
    </section>
<?php endif; ?>

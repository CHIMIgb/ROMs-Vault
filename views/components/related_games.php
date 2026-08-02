<?php
// views/components/related_games.php
// Requires: $juego (array)
//
// getRelated() devuelve cada juego con una columna 'relevancia':
//   2 = misma consola, 1 = misma categoría (género)
// Separamos en dos secciones curadas para una navegación más útil.

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

$mismaConsola = [];
$mismoGenero  = [];
foreach ($relacionados as $rel) {
    if ((int) ($rel['relevancia'] ?? 0) === 2) {
        $mismaConsola[] = $rel;
    } else {
        $mismoGenero[] = $rel;
    }
}

$renderRel = function (array $items, string $titulo, string $subtitulo, string $icon, string $variant) {
    if (empty($items)) {
        return;
    }
    ?>
    <section class="related-section related-subsection related-section--<?= $variant ?>">
        <h2 class="related-title">
            <span class="related-title-icon"><i data-i="<?= $icon ?>" aria-hidden="true"></i></span>
            <span class="related-title-text"><?= $titulo ?></span>
            <?php if ($subtitulo !== ''): ?>
                <span class="related-title-sub"><?= htmlspecialchars($subtitulo) ?></span>
            <?php endif; ?>
        </h2>
        <div class="related-grid">
            <?php foreach ($items as $rel): ?>
                <a href="index.php?controller=home&action=show&id=<?= (int) $rel['id'] ?>"
                    class="related-card" title="<?= htmlspecialchars($rel['titulo']) ?>">
                    <div class="related-cover <?= empty($rel['imagen']) ? 'no-image' : '' ?>">
                        <?php if (!empty($rel['imagen'])): ?>
                            <img src="<?= htmlspecialchars($rel['imagen']) ?>" alt="<?= htmlspecialchars($rel['titulo']) ?>"
                                loading="lazy">
                        <?php else: ?>
                            <i data-i="disc" data-cls="pxi-cover-placeholder" aria-hidden="true"></i>
                        <?php endif; ?>
                    </div>
                    <div class="related-info">
                        <span class="related-game-title"><?= htmlspecialchars($rel['titulo']) ?></span>
                        <span class="related-game-meta">
                            <?php if (!empty($rel['consola_nombre'])): ?>
                            <span class="related-tag related-tag--platform">
                                <?= htmlspecialchars($rel['consola_nombre']) ?>
                            </span>
                            <?php endif; ?>
                            <?php if (!empty($rel['region'])): ?>
                                <span class="related-tag related-tag--language">
                                    <?= htmlspecialchars($rel['region']) ?>
                                </span>
                            <?php endif; ?>
                        </span>
                    </div>
                </a>
            <?php endforeach; ?>
        </div>
    </section>
    <?php
};

$renderRel(
    $mismaConsola,
    'Más de ' . ($juego['consola_nombre'] ?? 'la consola'),
    $juego['consola_nombre'] ?? '',
    'gamepad',
    'consola'
);
$renderRel(
    $mismoGenero,
    'Géneros similares',
    $juego['categoria_nombre'] ?? '',
    'disc',
    'genero'
);

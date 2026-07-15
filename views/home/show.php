<div class="game-detail-page">

    <!-- ── Back navigation ── -->
    <a href="index.php?controller=home&action=index" class="detail-back-link">
        <i data-i="arrow-left" aria-hidden="true"></i>
        Volver al catálogo
    </a>

    <!-- ── Hero section: cover + info ── -->
    <div class="game-detail-hero">

        <!-- Cover art with CRT-style frame -->
        <div class="detail-cover-frame">
            <div class="detail-cover-scanlines"></div>
            <?php if (!empty($juego['imagen']) && file_exists(ltrim($juego['imagen'], '/'))): ?>
                <img src="<?= htmlspecialchars($juego['imagen']) ?>" alt="<?= htmlspecialchars($juego['titulo']) ?>" class="detail-cover-img">
            <?php else: ?>
                <div class="detail-cover-placeholder">
                    <i data-i="image" data-cls="pxi-cover-placeholder" aria-hidden="true"></i>
                </div>
            <?php endif; ?>
        </div>

        <!-- Info panel -->
        <div class="detail-info-panel">

            <!-- Title block -->
            <div class="detail-title-block">
                <h1 class="detail-title"><?= htmlspecialchars($juego['titulo']) ?></h1>
                <div class="detail-tags">
                    <span class="tag tag-consola"><?= htmlspecialchars($juego['consola_nombre'] ?? 'Desconocida') ?></span>
                    <span class="tag tag-categoria"><?= htmlspecialchars($juego['categoria_nombre'] ?? 'Sin Categoría') ?></span>
                    <span class="tag tag-region"><?= htmlspecialchars($juego['region'] ?? 'ALL') ?></span>
                    <?php if ($juego['formato_imagen'] === 'Hack'): ?>
                        <span class="tag tag-hack">Hack ROM</span>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Spec sheet -->
            <div class="detail-spec-sheet">
                <div class="spec-row">
                    <span class="spec-label">Lanzamiento</span>
                    <span class="spec-dots"></span>
                    <span class="spec-value"><?= !empty($juego['fecha_lanzamiento']) ? htmlspecialchars(date('d/m/Y', strtotime($juego['fecha_lanzamiento']))) : 'Desconocida' ?></span>
                </div>
                <div class="spec-row">
                    <span class="spec-label">Idiomas</span>
                    <span class="spec-dots"></span>
                    <span class="spec-value"><?= htmlspecialchars($juego['idiomas'] ?? 'No especificado') ?></span>
                </div>
                <div class="spec-row">
                    <span class="spec-label">Tamaño</span>
                    <span class="spec-dots"></span>
                    <span class="spec-value"><?= number_format($juego['size_bytes'] / 1048576, 2) ?> MB</span>
                </div>
                <?php if (!empty($juego['game_id_code'])): ?>
                <div class="spec-row">
                    <span class="spec-label">Serial / ID</span>
                    <span class="spec-dots"></span>
                    <span class="spec-value spec-value--code"><?= htmlspecialchars($juego['game_id_code']) ?></span>
                </div>
                <?php endif; ?>
            </div>

            <!-- Stats bar -->
            <div class="detail-stats-bar">
                <span class="stat-chip" title="Descargas">
                    <i data-i="download" aria-hidden="true"></i>
                    <?= number_format($juego['downloads_count']) ?> descargas
                </span>
                <span class="stat-chip" title="Partidas Online">
                    <i data-i="play" aria-hidden="true"></i>
                    <?= number_format($juego['plays_count']) ?> jugadas
                </span>
            </div>

            <!-- Action buttons -->
            <div class="detail-actions">
                <?php if (strtolower(trim($juego['consola_nombre'] ?? '')) !== 'psp' && strtolower(trim($juego['consola_nombre'] ?? '')) !== 'playstation portable'): ?>
                    <a href="index.php?controller=home&action=play&file_id=<?= urlencode($juego['google_drive_file_id']) ?>" class="detail-btn detail-btn--play">
                        <i data-i="play" aria-hidden="true"></i>
                        Jugar Online
                    </a>
                <?php endif; ?>

                <a href="index.php?controller=home&action=download&file_id=<?= urlencode($juego['google_drive_file_id']) ?>" class="detail-btn detail-btn--download" target="_blank" rel="noopener noreferrer">
                    <i data-i="download" aria-hidden="true"></i>
                    Descargar ROM
                </a>
            </div>
        </div>
    </div>

    <!-- ── Synopsis ── -->
    <?php if (!empty($juego['descripcion'])): ?>
    <section class="detail-synopsis">
        <h2 class="detail-section-title">
            <i data-i="file-text" aria-hidden="true"></i>
            Sinopsis
        </h2>
        <div class="detail-synopsis-content">
            <?= nl2br(htmlspecialchars($juego['descripcion'])) ?>
        </div>
    </section>
    <?php endif; ?>

    <!-- ── Related games ── -->
    <?php require_once 'views/components/related_games.php'; ?>
</div>

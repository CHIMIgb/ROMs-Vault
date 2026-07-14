<div class="game-detail-page">
    <div class="detail-actions-top">
        <a href="index.php?controller=home&action=index" class="btn btn-secondary">
            <i class="pixel-icon arrow-left"></i> Volver al Catálogo
        </a>
    </div>

    <div class="game-detail-container">
        <!-- Columna de Portada -->
        <div class="game-detail-cover">
            <?php if (!empty($juego['imagen']) && file_exists(ltrim($juego['imagen'], '/'))): ?>
                <img src="<?= htmlspecialchars($juego['imagen']) ?>" alt="<?= htmlspecialchars($juego['titulo']) ?>">
            <?php else: ?>
                <div class="no-image-detail">
                    <i class="pixel-icon image-placeholder"></i>
                </div>
            <?php endif; ?>
        </div>

        <!-- Columna de Información -->
        <div class="game-detail-info">
            <h1 class="detail-title"><?= htmlspecialchars($juego['titulo']) ?></h1>
            
            <div class="detail-tags">
                <span class="tag tag-consola"><?= htmlspecialchars($juego['consola_nombre'] ?? 'Desconocida') ?></span>
                <span class="tag tag-categoria"><?= htmlspecialchars($juego['categoria_nombre'] ?? 'Sin Categoría') ?></span>
                <span class="tag tag-region"><?= htmlspecialchars($juego['region'] ?? 'ALL') ?></span>
                <?php if ($juego['formato_imagen'] === 'Hack'): ?>
                    <span class="tag tag-hack">Hack ROM</span>
                <?php endif; ?>
            </div>

            <div class="detail-metadata">
                <div class="meta-item">
                    <span class="meta-label">Lanzamiento:</span>
                    <span class="meta-value"><?= !empty($juego['fecha_lanzamiento']) ? htmlspecialchars(date('d/m/Y', strtotime($juego['fecha_lanzamiento']))) : 'Desconocida' ?></span>
                </div>
                <div class="meta-item">
                    <span class="meta-label">Idiomas:</span>
                    <span class="meta-value"><?= htmlspecialchars($juego['idiomas'] ?? 'No especificado') ?></span>
                </div>
                <div class="meta-item">
                    <span class="meta-label">Tamaño:</span>
                    <span class="meta-value"><?= number_format($juego['size_bytes'] / 1048576, 2) ?> MB</span>
                </div>
                <?php if (!empty($juego['game_id_code'])): ?>
                <div class="meta-item">
                    <span class="meta-label">Serial/ID:</span>
                    <span class="meta-value"><?= htmlspecialchars($juego['game_id_code']) ?></span>
                </div>
                <?php endif; ?>
            </div>

            <div class="detail-stats">
                <span title="Descargas">📥 <?= number_format($juego['downloads_count']) ?></span>
                <span title="Partidas Online">🕹️ <?= number_format($juego['plays_count']) ?></span>
            </div>

            <div class="detail-actions">
                <!-- El botón de Jugar Online se oculta para PSP u otras consolas no soportadas online si lo deseas, o dejas que el controlador lance el aviso -->
                <?php if (strtolower(trim($juego['consola_nombre'] ?? '')) !== 'psp' && strtolower(trim($juego['consola_nombre'] ?? '')) !== 'playstation portable'): ?>
                    <a href="index.php?controller=home&action=play&file_id=<?= urlencode($juego['google_drive_file_id']) ?>" class="btn btn-primary btn-play-large">
                        ▶ Jugar Online
                    </a>
                <?php endif; ?>
                
                <a href="index.php?controller=home&action=download&file_id=<?= urlencode($juego['google_drive_file_id']) ?>" class="btn btn-success btn-download-large" target="_blank" rel="noopener noreferrer">
                    ⬇ Descargar ROM
                </a>
            </div>
        </div>
    </div>

    <!-- Sinopsis -->
    <?php if (!empty($juego['descripcion'])): ?>
    <div class="game-detail-synopsis">
        <h2>Sinopsis</h2>
        <div class="synopsis-content">
            <?= nl2br(htmlspecialchars($juego['descripcion'])) ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- Juegos Relacionados -->
    <?php if (!empty($relatedGames)): ?>
    <div class="related-games-section">
        <h2>Juegos Relacionados</h2>
        <div class="games-grid">
            <?php foreach ($relatedGames as $relacionado): ?>
                <div class="game-card">
                    <a href="index.php?controller=home&action=show&id=<?= $relacionado['id'] ?>" class="game-card-link">
                        <div class="game-cover">
                            <?php if (!empty($relacionado['imagen']) && file_exists(ltrim($relacionado['imagen'], '/'))): ?>
                                <img src="<?= htmlspecialchars($relacionado['imagen']) ?>" alt="<?= htmlspecialchars($relacionado['titulo']) ?>" loading="lazy">
                            <?php else: ?>
                                <div class="no-image"><i class="pixel-icon image-placeholder"></i></div>
                            <?php endif; ?>
                            
                            <?php if ($relacionado['formato_imagen'] === 'Hack'): ?>
                                <div class="hack-badge">Hack</div>
                            <?php endif; ?>
                        </div>
                        
                        <div class="game-info">
                            <h3 class="game-title" title="<?= htmlspecialchars($relacionado['titulo']) ?>">
                                <?= htmlspecialchars($relacionado['titulo']) ?>
                            </h3>
                            <div class="game-tags">
                                <span class="tag-categoria"><?= htmlspecialchars($relacionado['categoria_nombre'] ?? '') ?></span>
                                <span class="tag-consola"><?= htmlspecialchars($relacionado['consola_nombre'] ?? '') ?></span>
                                <?php if (!empty($relacionado['region'])): ?>
                                    <span class="tag-region"><?= htmlspecialchars($relacionado['region']) ?></span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </a>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>
</div>

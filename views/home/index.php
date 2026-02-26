<!-- views/home/index.php -->
<div class="filters">
    <form method="GET" action="index.php">
        <input type="hidden" name="controller" value="home">
        <input type="hidden" name="action" value="index">
        
        <select name="consola">
            <option value="">Todas las plataformas</option>
            <?php foreach ($consolas as $consola): ?>
                <option value="<?= $consola['id'] ?>" <?= (isset($_GET['consola']) && $_GET['consola'] == $consola['id']) ? 'selected' : '' ?>>
                    <?= htmlspecialchars($consola['nombre']) ?>
                </option>
            <?php endforeach; ?>
        </select>

        <select name="categoria">
            <option value="">Todos los géneros</option>
            <?php foreach ($categorias as $categoria): ?>
                <option value="<?= $categoria['id'] ?>" <?= (isset($_GET['categoria']) && $_GET['categoria'] == $categoria['id']) ? 'selected' : '' ?>>
                    <?= htmlspecialchars($categoria['nombre']) ?>
                </option>
            <?php endforeach; ?>
        </select>

        <select name="region">
            <option value="">Todas las regiones</option>
            <option value="PAL" <?= (isset($_GET['region']) && $_GET['region'] == 'PAL') ? 'selected' : '' ?>>PAL</option>
            <option value="NTSC" <?= (isset($_GET['region']) && $_GET['region'] == 'NTSC') ? 'selected' : '' ?>>NTSC</option>
            <option value="NTSC-J" <?= (isset($_GET['region']) && $_GET['region'] == 'NTSC-J') ? 'selected' : '' ?>>NTSC-J</option>
            <option value="NTSC-U" <?= (isset($_GET['region']) && $_GET['region'] == 'NTSC-U') ? 'selected' : '' ?>>NTSC-U</option>
        </select>

        <button type="submit">Filtrar</button>
    </form>
</div>

<div class="games-grid">
    <?php if (empty($juegos)): ?>
        <div class="alert alert-info" style="grid-column: 1/-1; text-align: center; padding: 3rem;">
            No hay juegos disponibles que coincidan con los filtros.
        </div>
    <?php else: ?>
        <?php foreach ($juegos as $juego): ?>
            <div class="game-card">
                <div class="game-card-inner">
                    <!-- Portada -->
                    <div class="game-cover <?= empty($juego['imagen']) ? 'no-image' : '' ?>">
                        <?php if (!empty($juego['imagen'])): ?>
                            <img src="<?= htmlspecialchars($juego['imagen']) ?>" alt="<?= htmlspecialchars($juego['titulo']) ?>">
                        <?php else: ?>
                            <span>📀</span>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Título -->
                    <h3 class="game-title"><?= htmlspecialchars($juego['titulo']) ?></h3>
                    
                    <!-- Tags (género, idioma, plataforma) -->
                    <div class="game-metadata">
                        <span class="game-tag genre"><?= htmlspecialchars($juego['categoria_nombre'] ?? 'Sin género') ?></span>
                        <span class="game-tag platform"><?= htmlspecialchars($juego['consola_nombre'] ?? 'Multi') ?></span>
                        <span class="game-tag language"><?= htmlspecialchars($juego['region'] ?? 'All') ?></span>
                    </div>
                    
                    <!-- Información de hack/mod (si aplica) -->
                    <?php if (!empty($juego['formato_imagen']) && $juego['formato_imagen'] === 'Hack'): ?>
                        <div class="game-hack-info">
                            🔧 <?= htmlspecialchars($juego['descripcion'] ?? 'ROM Hack') ?>
                        </div>
                    <?php endif; ?>
                    
                    <!-- Información adicional (como en la imagen) -->
                    <div class="game-info">
                        <div class="game-info-item">
                            <span class="game-info-label">Idiomas</span>
                            <span class="game-info-value"><?= htmlspecialchars($juego['idiomas'] ?? 'English') ?></span>
                        </div>
                        <div class="game-info-item">
                            <span class="game-info-label">Tamaño</span>
                            <span class="game-info-value"><?= number_format($juego['size_bytes'] / 1048576, 2) ?> MB</span>
                        </div>
                        <?php if (!empty($juego['downloads_count'])): ?>
                            <div class="game-info-item">
                                <span class="game-info-label">Descargas</span>
                                <span class="game-info-value"><?= number_format($juego['downloads_count']) ?></span>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Botón descarga -->
                    <a href="index.php?controller=home&action=download&file_id=<?= urlencode($juego['google_drive_file_id']) ?>" 
                       class="game-download" 
                       target="_blank">
                        <span>⬇️</span> Descargar ROM
                    </a>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<!-- Paginación (opcional) -->
<?php if (isset($totalPages) && $totalPages > 1): ?>
<div class="pagination">
    <?php for ($i = 1; $i <= $totalPages; $i++): ?>
        <?php if ($i == $currentPage): ?>
            <span class="current"><?= $i ?></span>
        <?php else: ?>
            <a href="?controller=home&action=index&page=<?= $i ?><?= isset($_GET['consola']) ? '&consola='.$_GET['consola'] : '' ?><?= isset($_GET['categoria']) ? '&categoria='.$_GET['categoria'] : '' ?><?= isset($_GET['region']) ? '&region='.$_GET['region'] : '' ?>">
                <?= $i ?>
            </a>
        <?php endif; ?>
    <?php endfor; ?>
</div>
<?php endif; ?>
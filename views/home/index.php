<div class="filters">
    <form method="GET" action="index.php">
        <input type="hidden" name="controller" value="home">
        <input type="hidden" name="action" value="index">
        <select name="consola">
            <option value="">Todas las consolas</option>
            <?php foreach ($consolas as $consola): ?>
                <option value="<?= $consola['id'] ?>" <?= (isset($_GET['consola']) && $_GET['consola'] == $consola['id']) ? 'selected' : '' ?>>
                    <?= htmlspecialchars($consola['nombre']) ?>
                </option>
            <?php endforeach; ?>
        </select>

        <select name="categoria">
            <option value="">Todas las categorías</option>
            <?php foreach ($categorias as $categoria): ?>
                <option value="<?= $categoria['id'] ?>" <?= (isset($_GET['categoria']) && $_GET['categoria'] == $categoria['id']) ? 'selected' : '' ?>>
                    <?= htmlspecialchars($categoria['nombre']) ?>
                </option>
            <?php endforeach; ?>
        </select>

        <select name="region">
            <option value="">Todas las regiones</option>
            <?php foreach ($regiones as $region): ?>
                <option value="<?= $region ?>" <?= (isset($_GET['region']) && $_GET['region'] == $region) ? 'selected' : '' ?>>
                    <?= $region ?>
                </option>
            <?php endforeach; ?>
        </select>

        <button type="submit">Filtrar</button>
    </form>
</div>

<div class="file-list">
    <?php if (empty($juegos)): ?>
        <p>No hay juegos disponibles.</p>
    <?php else: ?>
        <?php foreach ($juegos as $juego): ?>
            <div class="file-item">
                <div class="file-info">
                    <?php if (!empty($juego['imagen'])): ?>
                        <img src="<?= htmlspecialchars($juego['imagen']) ?>" 
                            alt="Portada de <?= htmlspecialchars($juego['titulo']) ?>" 
                            class="file-icon" 
                            style="width: 60px; height: 60px; object-fit: cover; border-radius: 8px;">
                    <?php else: ?>
                        <span class="file-icon">🎮</span>
                    <?php endif; ?>
                    <div class="file-details">
                        <div class="file-name">
                            <?= htmlspecialchars($juego['titulo']) ?>
                            <span class="badge"><?= htmlspecialchars($juego['consola_nombre']) ?></span>
                            <span class="file-format">.iso</span>
                        </div>
                        <div class="file-size"><?= number_format($juego['size_bytes'] / 1048576, 2) ?> MB</div>
                        <div class="file-region">Región: <?= htmlspecialchars($juego['region']) ?></div>
                    </div>
                </div>
                <a href="index.php?controller=home&action=download&file_id=<?= urlencode($juego['google_drive_file_id']) ?>" 
                class="download-btn" target="_blank">
                    <span>⬇️</span> Descargar
                </a>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>
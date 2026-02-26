<!-- views/home/index.php -->
<!-- FILTROS -->
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

<!-- DIAGNÓSTICO (visible solo para depuración) -->
<div style="background: #333; color: #fff; padding: 15px; margin-bottom: 20px; border-radius: 5px; font-family: monospace;">
    <strong>🔍 DIAGNÓSTICO DE PAGINACIÓN:</strong><br>
    Total juegos: <strong><?= $totalJuegos ?? 'NO DEFINIDO' ?></strong><br>
    Páginas totales: <strong><?= $totalPages ?? 'NO DEFINIDO' ?></strong><br>
    Página actual: <strong><?= $currentPage ?? 'NO DEFINIDO' ?></strong><br>
    Juegos mostrados: <strong><?= count($juegos) ?></strong><br>
    Items por página: <strong>20</strong>
</div>

<!-- GRID DE JUEGOS -->
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
                    
                    <!-- Tags -->
                    <div class="game-metadata">
                        <span class="game-tag genre"><?= htmlspecialchars($juego['categoria_nombre'] ?? 'Sin género') ?></span>
                        <span class="game-tag platform"><?= htmlspecialchars($juego['consola_nombre'] ?? 'Multi') ?></span>
                        <span class="game-tag language"><?= htmlspecialchars($juego['region'] ?? 'All') ?></span>
                    </div>
                    
                    <!-- Información de hack/mod -->
                    <?php if (!empty($juego['formato_imagen']) && $juego['formato_imagen'] === 'Hack'): ?>
                        <div class="game-hack-info">
                            🔧 <?= htmlspecialchars($juego['descripcion'] ?? 'ROM Hack') ?>
                        </div>
                    <?php endif; ?>
                    
                    <!-- Información adicional -->
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

<!-- PAGINACIÓN - VISIBLE SIEMPRE QUE HAYA JUEGOS -->
<?php if (!empty($juegos) && isset($totalPages) && $totalPages > 0): ?>
<div class="pagination" style="margin-top: 2rem; text-align: center; display: flex; justify-content: center; gap: 5px; flex-wrap: wrap;">
    <!-- Botón anterior -->
    <?php if ($currentPage > 1): ?>
        <a href="?controller=home&action=index&page=<?= $currentPage - 1 ?><?= isset($_GET['consola']) ? '&consola='.$_GET['consola'] : '' ?><?= isset($_GET['categoria']) ? '&categoria='.$_GET['categoria'] : '' ?><?= isset($_GET['region']) ? '&region='.$_GET['region'] : '' ?>" 
           style="background: var(--card-bg); padding: 8px 12px; border-radius: 4px; text-decoration: none; color: var(--text); border: 1px solid var(--border);">
            ← Anterior
        </a>
    <?php endif; ?>
    
    <!-- Números de página -->
    <?php for ($i = 1; $i <= $totalPages; $i++): ?>
        <?php if ($i == $currentPage): ?>
            <span style="background: var(--accent); color: white; padding: 8px 12px; border-radius: 4px; font-weight: bold; border: 1px solid var(--accent);">
                <?= $i ?>
            </span>
        <?php else: ?>
            <a href="?controller=home&action=index&page=<?= $i ?><?= isset($_GET['consola']) ? '&consola='.$_GET['consola'] : '' ?><?= isset($_GET['categoria']) ? '&categoria='.$_GET['categoria'] : '' ?><?= isset($_GET['region']) ? '&region='.$_GET['region'] : '' ?>" 
               style="background: var(--card-bg); padding: 8px 12px; border-radius: 4px; text-decoration: none; color: var(--text); border: 1px solid var(--border);">
                <?= $i ?>
            </a>
        <?php endif; ?>
    <?php endfor; ?>
    
    <!-- Botón siguiente -->
    <?php if ($currentPage < $totalPages): ?>
        <a href="?controller=home&action=index&page=<?= $currentPage + 1 ?><?= isset($_GET['consola']) ? '&consola='.$_GET['consola'] : '' ?><?= isset($_GET['categoria']) ? '&categoria='.$_GET['categoria'] : '' ?><?= isset($_GET['region']) ? '&region='.$_GET['region'] : '' ?>" 
           style="background: var(--card-bg); padding: 8px 12px; border-radius: 4px; text-decoration: none; color: var(--text); border: 1px solid var(--border);">
            Siguiente →
        </a>
    <?php endif; ?>
</div>

<!-- Información de páginas -->
<div style="text-align: center; margin-top: 1rem; color: var(--text-light); font-size: 0.9rem;">
    Mostrando <?= count($juegos) ?> de <?= $totalJuegos ?> juegos • Página <?= $currentPage ?> de <?= $totalPages ?>
</div>
<?php endif; ?>
<!-- views/admin/dashboard.php -->

<!-- ═══════════════════════════════════════════════════════════
     ESTADÍSTICAS GLOBALES REALES
     ═══════════════════════════════════════════════════════════ -->
<div class="admin-stats-grid">
    <div class="stat-card">
        <div class="stat-label">Total juegos</div>
        <div class="stat-value" style="color: var(--primary);"><?= number_format($stats['total']) ?></div>
    </div>
    <div class="stat-card">
        <div class="stat-label">Activos</div>
        <div class="stat-value" style="color: var(--success);"><?= number_format($stats['activos']) ?></div>
    </div>
    <div class="stat-card">
        <div class="stat-label">Inactivos</div>
        <div class="stat-value" style="color: var(--text-light);"><?= number_format($stats['inactivos']) ?></div>
    </div>
    <div class="stat-card">
        <div class="stat-label">Total descargas</div>
        <div class="stat-value" style="color: var(--primary);"><?= number_format($stats['total_descargas']) ?></div>
    </div>
    <div class="stat-card">
        <div class="stat-label">Total jugadas online</div>
        <div class="stat-value" style="color: var(--primary);"><?= number_format($stats['total_jugadas']) ?></div>
    </div>
</div>

<!-- ═══════════════════════════════════════════════════════════
     RANKINGS
     ═══════════════════════════════════════════════════════════ -->
<?php if (!empty($topDescargas) || !empty($topJugados)): ?>
<div class="admin-rankings">

    <?php if (!empty($topDescargas)): ?>
    <div class="ranking-card">
        <h3 class="ranking-title">⬇ Top 5 más descargados</h3>
        <ol class="ranking-list">
            <?php
            $maxD = $topDescargas[0]['downloads_count'] ?: 1;
            foreach ($topDescargas as $i => $j):
                $pct = round(($j['downloads_count'] / $maxD) * 100);
            ?>
            <li class="ranking-item">
                <span class="ranking-pos"><?= $i + 1 ?></span>
                <div class="ranking-info">
                    <span class="ranking-game"><?= htmlspecialchars($j['titulo']) ?></span>
                    <span class="ranking-platform"><?= htmlspecialchars($j['consola_nombre'] ?? '—') ?></span>
                    <div class="ranking-bar-track">
                        <div class="ranking-bar-fill" style="width:<?= $pct ?>%"></div>
                    </div>
                </div>
                <span class="ranking-count"><?= number_format($j['downloads_count']) ?></span>
            </li>
            <?php endforeach; ?>
        </ol>
    </div>
    <?php endif; ?>

    <?php if (!empty($topJugados)): ?>
    <div class="ranking-card">
        <h3 class="ranking-title">▶ Top 5 más jugados online</h3>
        <ol class="ranking-list">
            <?php
            $maxP = $topJugados[0]['plays_count'] ?: 1;
            foreach ($topJugados as $i => $j):
                $pct = round(($j['plays_count'] / $maxP) * 100);
            ?>
            <li class="ranking-item">
                <span class="ranking-pos"><?= $i + 1 ?></span>
                <div class="ranking-info">
                    <span class="ranking-game"><?= htmlspecialchars($j['titulo']) ?></span>
                    <span class="ranking-platform"><?= htmlspecialchars($j['consola_nombre'] ?? '—') ?></span>
                    <div class="ranking-bar-track">
                        <div class="ranking-bar-fill" style="width:<?= $pct ?>%; background: var(--success);"></div>
                    </div>
                </div>
                <span class="ranking-count"><?= number_format($j['plays_count']) ?></span>
            </li>
            <?php endforeach; ?>
        </ol>
    </div>
    <?php endif; ?>

</div>
<?php endif; ?>

<!-- ═══════════════════════════════════════════════════════════
     CABECERA + BOTÓN AÑADIR
     ═══════════════════════════════════════════════════════════ -->
<div class="admin-header">
    <h2>Panel de Administración</h2>
    <a href="index.php?controller=admin&action=add" class="btn-primary">+ Añadir nuevo juego</a>
</div>

<!-- ═══════════════════════════════════════════════════════════
     FILTROS EXTENDIDOS
     ═══════════════════════════════════════════════════════════ -->
<div class="search-section">
    <form method="GET" action="index.php" class="admin-filter-form">
        <input type="hidden" name="controller" value="admin">
        <input type="hidden" name="action"     value="dashboard">

        <!-- Búsqueda por título -->
        <div class="search-wrapper" style="flex:2; min-width:180px;">
            <input type="text"
                   name="busqueda"
                   placeholder="Buscar por título…"
                   value="<?= htmlspecialchars($_GET['busqueda'] ?? '') ?>"
                   class="search-input">
            <button type="submit" class="search-button"><span>⌕</span></button>
        </div>

        <!-- Consola -->
        <select name="consola" class="admin-filter-select">
            <option value="">Todas las consolas</option>
            <?php foreach ($consolas as $c): ?>
                <option value="<?= $c['id'] ?>" <?= (($_GET['consola'] ?? '') == $c['id']) ? 'selected' : '' ?>>
                    <?= htmlspecialchars($c['nombre']) ?>
                </option>
            <?php endforeach; ?>
        </select>

        <!-- Categoría -->
        <select name="categoria" class="admin-filter-select">
            <option value="">Todas las categorías</option>
            <?php foreach ($categorias as $cat): ?>
                <option value="<?= $cat['id'] ?>" <?= (($_GET['categoria'] ?? '') == $cat['id']) ? 'selected' : '' ?>>
                    <?= htmlspecialchars($cat['nombre']) ?>
                </option>
            <?php endforeach; ?>
        </select>

        <!-- Región -->
        <select name="region" class="admin-filter-select">
            <option value="">Todas las regiones</option>
            <option value="PAL"    <?= (($_GET['region'] ?? '') === 'PAL')    ? 'selected' : '' ?>>PAL</option>
            <option value="NTSC"   <?= (($_GET['region'] ?? '') === 'NTSC')   ? 'selected' : '' ?>>NTSC</option>
            <option value="NTSC-J" <?= (($_GET['region'] ?? '') === 'NTSC-J') ? 'selected' : '' ?>>NTSC-J</option>
            <option value="NTSC-U" <?= (($_GET['region'] ?? '') === 'NTSC-U') ? 'selected' : '' ?>>NTSC-U</option>
        </select>

        <!-- Estado -->
        <select name="activo" class="admin-filter-select">
            <option value=""  <?= (!isset($_GET['activo']) || $_GET['activo'] === '') ? 'selected' : '' ?>>Todos los estados</option>
            <option value="1" <?= (($_GET['activo'] ?? '') === '1') ? 'selected' : '' ?>>Solo activos</option>
            <option value="0" <?= (($_GET['activo'] ?? '') === '0') ? 'selected' : '' ?>>Solo inactivos</option>
        </select>

        <button type="submit" class="btn-primary" style="white-space:nowrap;">Filtrar</button>

        <?php
        $hayFiltros = !empty($_GET['busqueda'])  || !empty($_GET['consola'])
                   || !empty($_GET['categoria']) || !empty($_GET['region'])
                   || (isset($_GET['activo']) && $_GET['activo'] !== '');
        ?>
        <?php if ($hayFiltros): ?>
            <a href="?controller=admin&action=dashboard" class="clear-filters" style="white-space:nowrap;">✕ Limpiar</a>
        <?php endif; ?>
    </form>
</div>

<!-- ═══════════════════════════════════════════════════════════
     TABLA DE JUEGOS
     ═══════════════════════════════════════════════════════════ -->
<?php if (empty($juegos)): ?>
    <div class="alert alert-info" style="text-align:center; padding:3rem;">
        No hay juegos que coincidan con los filtros aplicados.
    </div>
<?php else: ?>
    <div style="overflow-x:auto;">
        <table class="admin-table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Portada</th>
                    <th>Título</th>
                    <th>ID Juego</th>
                    <th>Consola</th>
                    <th>Categoría</th>
                    <th>Región</th>
                    <th style="text-align:center;" title="Descargas">⬇</th>
                    <th style="text-align:center;" title="Jugadas online">▶</th>
                    <th>Estado</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($juegos as $juego): ?>
                <?php
                // Construir query string con filtros actuales para preservarlos en toggleActive
                $filterQS = '';
                foreach (['busqueda','consola','categoria','region','activo'] as $p) {
                    if (isset($_GET[$p]) && $_GET[$p] !== '') {
                        $filterQS .= '&' . $p . '=' . urlencode($_GET[$p]);
                    }
                }
                ?>
                <tr>
                    <td>#<?= $juego['id'] ?></td>
                    <td>
                        <?php if (!empty($juego['imagen'])): ?>
                            <img src="<?= htmlspecialchars($juego['imagen']) ?>"
                                 alt="Portada"
                                 style="width:40px;height:40px;object-fit:cover;border-radius:4px;">
                        <?php else: ?>
                            <span style="color:var(--text-light);">📁</span>
                        <?php endif; ?>
                    </td>
                    <td><strong><?= htmlspecialchars($juego['titulo']) ?></strong></td>
                    <td><code style="font-size:0.78rem;"><?= htmlspecialchars($juego['game_id_code'] ?? '—') ?></code></td>
                    <td><?= htmlspecialchars($juego['consola_nombre']  ?? '—') ?></td>
                    <td><?= htmlspecialchars($juego['categoria_nombre'] ?? '—') ?></td>
                    <td>
                        <span class="game-tag" style="background:var(--background);color:var(--text);">
                            <?= htmlspecialchars($juego['region'] ?? '—') ?>
                        </span>
                    </td>
                    <td style="text-align:center;font-variant-numeric:tabular-nums;font-size:0.85rem;">
                        <?= number_format($juego['downloads_count'] ?? 0) ?>
                    </td>
                    <td style="text-align:center;font-variant-numeric:tabular-nums;font-size:0.85rem;">
                        <?= number_format($juego['plays_count'] ?? 0) ?>
                    </td>
                    <td>
                        <a href="index.php?controller=admin&action=toggleActive&id=<?= $juego['id'] ?>&page=<?= $currentPage ?><?= $filterQS ?>"
                           style="font-size:0.75rem;text-decoration:none;padding:0.2rem 0.5rem;border-radius:4px;display:inline-block;margin-top:0.3rem;
                                  background:<?= $juego['activo'] ? 'var(--danger,#e74c3c)' : 'var(--success,#27ae60)' ?>;color:#fff;"
                           onclick="return confirm('¿Confirmas <?= $juego['activo'] ? 'desactivar' : 'activar' ?> este juego?')">
                            <?= $juego['activo'] ? 'Desactivar' : 'Activar' ?>
                        </a>
                    </td>
                    <td>
                        <a href="index.php?controller=admin&action=edit&id=<?= $juego['id'] ?>" class="btn-edit">Editar</a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <!-- PAGINACIÓN -->
    <?php
    $paramStr = '';
    foreach (['busqueda','consola','categoria','region','activo'] as $p) {
        if (isset($_GET[$p]) && $_GET[$p] !== '') {
            $paramStr .= '&' . $p . '=' . urlencode($_GET[$p]);
        }
    }
    ?>
    <?php if (isset($totalPages) && $totalPages > 1): ?>
    <div class="pagination" style="margin-top:1.5rem;">
        <?php if ($currentPage > 1): ?>
            <a href="?controller=admin&action=dashboard&page=<?= $currentPage - 1 ?><?= $paramStr ?>" class="pagination-link">← Anterior</a>
        <?php endif; ?>

        <?php for ($i = 1; $i <= $totalPages; $i++): ?>
            <?php if ($i == $currentPage): ?>
                <span class="pagination-current"><?= $i ?></span>
            <?php else: ?>
                <a href="?controller=admin&action=dashboard&page=<?= $i ?><?= $paramStr ?>" class="pagination-link"><?= $i ?></a>
            <?php endif; ?>
        <?php endfor; ?>

        <?php if ($currentPage < $totalPages): ?>
            <a href="?controller=admin&action=dashboard&page=<?= $currentPage + 1 ?><?= $paramStr ?>" class="pagination-link">Siguiente →</a>
        <?php endif; ?>
    </div>

    <div class="pagination-info" style="margin-top:0.75rem;">
        Mostrando <?= count($juegos) ?> de <?= number_format($totalJuegos) ?> juegos • Página <?= $currentPage ?> de <?= $totalPages ?>
        <?php if ($hayFiltros): ?>
            <span style="color:var(--text-light);"> • Filtros activos</span>
        <?php endif; ?>
    </div>
    <?php endif; ?>

<?php endif; ?>

    <!-- Estadísticas rápidas -->
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 1rem; margin-top: 2rem;">
        <div style="background: var(--card-bg); padding: 1rem; border-radius: 8px; border: 1px solid var(--border);">
            <div style="color: var(--text-light); font-size: 0.8rem;">Total juegos</div>
            <div style="font-size: 1.8rem; font-weight: 600; color: var(--primary);"><?= $totalJuegos ?></div>
        </div>
        <div style="background: var(--card-bg); padding: 1rem; border-radius: 8px; border: 1px solid var(--border);">
            <div style="color: var(--text-light); font-size: 0.8rem;">Activos</div>
            <div style="font-size: 1.8rem; font-weight: 600; color: var(--success);">
                <?= count(array_filter($juegos, fn($j) => $j['activo'])) ?>
            </div>
        </div>
        <div style="background: var(--card-bg); padding: 1rem; border-radius: 8px; border: 1px solid var(--border);">
            <div style="color: var(--text-light); font-size: 0.8rem;">Inactivos</div>
            <div style="font-size: 1.8rem; font-weight: 600; color: var(--text-light);">
                <?= count(array_filter($juegos, fn($j) => !$j['activo'])) ?>
            </div>
        </div>
    </div>

    <br>

<!-- views/admin/dashboard.php -->
<div class="admin-header">
    <h2>Panel de Administración</h2>
    <a href="index.php?controller=admin&action=add" class="btn-primary">+ Añadir nuevo juego</a>
</div>

<!-- BARRA DE BÚSQUEDA -->
<div class="search-section">
    <form method="GET" action="index.php" class="search-form">
        <input type="hidden" name="controller" value="admin">
        <input type="hidden" name="action" value="dashboard">

        <div class="search-wrapper">
            <input type="text"
                   name="busqueda"
                   placeholder="Buscar juego por título..."
                   value="<?= htmlspecialchars($_GET['busqueda'] ?? '') ?>"
                   class="search-input">
            <button type="submit" class="search-button">
                <span>⌕</span>
            </button>
        </div>
    </form>
    <?php if (!empty($_GET['busqueda'])): ?>
        <a href="?controller=admin&action=dashboard" class="clear-filters" style="margin-left: 0.75rem;">Limpiar</a>
    <?php endif; ?>
</div>

<?php if (empty($juegos)): ?>
    <div class="alert alert-info" style="text-align: center; padding: 3rem;">
        No hay juegos registrados. ¡Añade el primero!
    </div>
<?php else: ?>
    <div style="overflow-x: auto;">
        <table class="admin-table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Portada</th>
                    <th>Título</th>
                    <th>Consola</th>
                    <th>Categoría</th>
                    <th>Región</th>
                    <th>Estado</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($juegos as $juego): ?>
                <tr>
                    <td>#<?= $juego['id'] ?></td>
                    <td>
                        <?php if (!empty($juego['imagen'])): ?>
                            <img src="<?= htmlspecialchars($juego['imagen']) ?>" 
                                 alt="Portada de <?= htmlspecialchars($juego['titulo']) ?>"
                                 style="width: 40px; height: 40px; object-fit: cover; border-radius: 4px;">
                        <?php else: ?>
                            <span style="color: var(--text-light); font-size: 0.8rem;">📁 Sin imagen</span>
                        <?php endif; ?>
                    </td>
                    <td><strong><?= htmlspecialchars($juego['titulo']) ?></strong></td>
                    <td><?= htmlspecialchars($juego['consola_nombre'] ?? '—') ?></td>
                    <td><?= htmlspecialchars($juego['categoria_nombre'] ?? '—') ?></td>
                    <td>
                        <span class="game-tag" style="background: var(--background); color: var(--text);">
                            <?= htmlspecialchars($juego['region'] ?? '—') ?>
                        </span>
                    </td>
                    <td>
                        <a href="index.php?controller=admin&action=toggleActive&id=<?= $juego['id'] ?>&page=<?= $currentPage ?>"
                           style="font-size: 0.75rem; text-decoration: none; padding: 0.2rem 0.5rem; border-radius: 4px; display: inline-block; margin-top: 0.3rem;
                                  background: <?= $juego['activo'] ? 'var(--danger, #e74c3c)' : 'var(--success, #27ae60)' ?>;
                                  color: #fff;"
                           onclick="return confirm('¿Confirmas <?= $juego['activo'] ? 'desactivar' : 'activar' ?> este juego?')">
                            <?= $juego['activo'] ? 'Desactivar' : 'Activar' ?>
                        </a>
                    </td>
                    <td>
                        <a href="index.php?controller=admin&action=edit&id=<?= $juego['id'] ?>" 
                           class="btn-edit">Editar</a>
                        <!--
                        <a href="index.php?controller=admin&action=delete&id=<?= $juego['id'] ?>" 
                           class="btn-delete" 
                           onclick="return confirm('¿Estás seguro de eliminar este juego? Esta acción no se puede deshacer.')">Eliminar</a>
                        -->
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <!-- PAGINACIÓN -->
    <?php
        $busquedaParam = !empty($_GET['busqueda']) ? '&busqueda=' . urlencode($_GET['busqueda']) : '';
    ?>
    <?php if (isset($totalPages) && $totalPages > 1): ?>
    <div class="pagination" style="margin-top: 1.5rem;">
        <?php if ($currentPage > 1): ?>
            <a href="?controller=admin&action=dashboard&page=<?= $currentPage - 1 ?><?= $busquedaParam ?>" class="pagination-link">← Anterior</a>
        <?php endif; ?>

        <?php for ($i = 1; $i <= $totalPages; $i++): ?>
            <?php if ($i == $currentPage): ?>
                <span class="pagination-current"><?= $i ?></span>
            <?php else: ?>
                <a href="?controller=admin&action=dashboard&page=<?= $i ?><?= $busquedaParam ?>" class="pagination-link"><?= $i ?></a>
            <?php endif; ?>
        <?php endfor; ?>

        <?php if ($currentPage < $totalPages): ?>
            <a href="?controller=admin&action=dashboard&page=<?= $currentPage + 1 ?><?= $busquedaParam ?>" class="pagination-link">Siguiente →</a>
        <?php endif; ?>
    </div>

    <div class="pagination-info" style="margin-top: 0.75rem;">
        Mostrando <?= count($juegos) ?> de <?= $totalJuegos ?> juegos • Página <?= $currentPage ?> de <?= $totalPages ?>
        <?php if (!empty($_GET['busqueda'])): ?>
            • Búsqueda: "<?= htmlspecialchars($_GET['busqueda']) ?>"
        <?php endif; ?>
    </div>
    <?php endif; ?>

<?php endif; ?>
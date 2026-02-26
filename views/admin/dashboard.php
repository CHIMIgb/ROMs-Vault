<!-- views/admin/dashboard.php -->
<div class="admin-header">
    <h2>Panel de Administración</h2>
    <a href="index.php?controller=admin&action=add" class="btn-primary">+ Añadir nuevo juego</a>
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
                        <?php if ($juego['activo']): ?>
                            <span style="color: var(--success); font-weight: 500;">✓ Activo</span>
                        <?php else: ?>
                            <span style="color: var(--text-light);">✗ Inactivo</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <a href="index.php?controller=admin&action=edit&id=<?= $juego['id'] ?>" 
                           class="btn-edit">Editar</a>
                        <a href="index.php?controller=admin&action=delete&id=<?= $juego['id'] ?>" 
                           class="btn-delete" 
                           onclick="return confirm('¿Estás seguro de eliminar este juego? Esta acción no se puede deshacer.')">Eliminar</a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    
    <!-- Estadísticas rápidas -->
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 1rem; margin-top: 2rem;">
        <div style="background: var(--card-bg); padding: 1rem; border-radius: 8px; border: 1px solid var(--border);">
            <div style="color: var(--text-light); font-size: 0.8rem;">Total juegos</div>
            <div style="font-size: 1.8rem; font-weight: 600; color: var(--primary);"><?= count($juegos) ?></div>
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
<?php endif; ?>
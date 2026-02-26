<h2>Panel de Administración</h2>
<a href="index.php?controller=admin&action=add" class="btn">Añadir nuevo juego</a>

<table class="admin-table">
    <thead>
        <tr>
            <th>ID</th>
            <th>Portada</th>
            <th>Título</th>
            <th>Consola</th>
            <th>Categoría</th>
            <th>Región</th>
            <th>Activo</th>
            <th>Acciones</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($juegos as $juego): ?>
        <tr>
            <td><?= $juego['id'] ?></td>
            <td>
                <?php if ($juego['imagen']): ?>
                    <img src="<?= $juego['imagen'] ?>" width="50" height="50" style="object-fit: cover;" alt="Portada">
                <?php else: ?>
                    <span style="color: #999;">Sin imagen</span>
                <?php endif; ?>
            </td>
            <td><?= htmlspecialchars($juego['titulo']) ?></td>
            <td><?= htmlspecialchars($juego['consola_nombre']) ?></td>
            <td><?= htmlspecialchars($juego['categoria_nombre']) ?></td>
            <td><?= htmlspecialchars($juego['region']) ?></td>
            <td><?= $juego['activo'] ? 'Sí' : 'No' ?></td>
            <td>
                <a href="index.php?controller=admin&action=edit&id=<?= $juego['id'] ?>" class="btn-edit">Editar</a>
                <a href="index.php?controller=admin&action=delete&id=<?= $juego['id'] ?>" 
                class="btn-delete" 
                onclick="return confirm('¿Estás seguro de eliminar este juego?')">Eliminar</a>
            </td>
        </tr>
        <?php endforeach; ?>
    </tbody>
</table>
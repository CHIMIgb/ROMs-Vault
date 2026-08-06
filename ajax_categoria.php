<?php
/**
 * ajax_categoria.php
 * Endpoint AJAX — devuelve el HTML de la tabla de categorías + paginación.
 * Llamado por el JS de búsqueda en tiempo real en views/admin/categorias/index.php
 */

require_once __DIR__ . '/config/AuthMiddleware.php';
AuthMiddleware::requireAdminAjax();

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/models/Model.php';
require_once __DIR__ . '/models/Categoria.php';

header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-store');

$categoriaModel = new Categoria();

$busqueda = isset($_GET['busqueda']) && $_GET['busqueda'] !== '' ? trim($_GET['busqueda']) : null;
$activo   = isset($_GET['activo'])   && $_GET['activo']   !== '' ? $_GET['activo']         : null;

$itemsPerPage = 20;
$currentPage  = max(1, (int)($_GET['page'] ?? 1));
$offset       = ($currentPage - 1) * $itemsPerPage;

$categorias = $categoriaModel->getAllPaginated($busqueda, $offset, $itemsPerPage, $activo);
$total      = $categoriaModel->countAll($busqueda, $activo);
$totalPages = (int)ceil($total / $itemsPerPage);

// Query string base (sin page) para los links de paginación
$qParts = [];
if ($busqueda) $qParts[] = 'busqueda=' . urlencode($busqueda);
if ($activo !== null) $qParts[] = 'activo=' . urlencode($activo);
$qBase = $qParts ? '&' . implode('&', $qParts) : '';
?>
<?php if (empty($categorias)): ?>
    <?php 
    require_once __DIR__ . '/views/components/Alert.php';
    Alert::render('info', 'No hay categorías que coincidan con los filtros.', 'info', 'text-align:center;padding:3rem;justify-content:center;');
    ?>
<?php else: ?>
<div style="overflow-x:auto;">
    <table class="admin-table">
        <thead>
            <tr>
                <th>ID</th>
                <th>Nombre</th>
                <th>Descripción</th>
                <th>Estado</th>
                <th>Creada</th>
                <th>Acciones</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($categorias as $cat): ?>
            <tr>
                <td>#<?= $cat['id'] ?></td>
                <td><strong><?= htmlspecialchars($cat['nombre']) ?></strong></td>
                <td style="max-width:260px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;color:var(--slate-mid);font-size:.88rem;">
                    <?= htmlspecialchars($cat['descripcion'] ?? '—') ?>
                </td>
                <td>
                    <button class="btn-toggle-active <?= $cat['activo'] ? 'btn-toggle--on' : 'btn-toggle--off' ?>"
                            data-id="<?= $cat['id'] ?>"
                            data-titulo="<?= htmlspecialchars($cat['nombre'], ENT_QUOTES) ?>"
                            data-accion="<?= $cat['activo'] ? 'desactivar' : 'activar' ?>">
                        <?= $cat['activo'] ? 'Desactivar' : 'Activar' ?>
                    </button>
                </td>
                <td><?= date('d/m/Y', strtotime($cat['created_at'])) ?></td>
                <td>
                    <a href="/categoria/edit/<?= $cat['id'] ?>" class="btn-edit">Editar</a>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<?php 
require_once __DIR__ . '/views/components/Pagination.php';
Pagination::render(
    $currentPage, 
    $totalPages ?? 1, 
    $qBase, 
    'categoria', 
    'index', 
    count($categorias), 
    $total, 
    'categorías', 
    'cat'
);
?>
<?php endif; ?>

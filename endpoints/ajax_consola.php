<?php
/**
 * ajax_consola.php
 * Endpoint AJAX — devuelve el HTML de la tabla de consolas + paginación.
 * Llamado por el JS de búsqueda en tiempo real en views/admin/consolas/index.php
 */

require_once __DIR__ . '/../src/config/AuthMiddleware.php';
AuthMiddleware::requireAdminAjax();

require_once __DIR__ . '/../src/config/database.php';
require_once __DIR__ . '/../src/models/Model.php';
require_once __DIR__ . '/../src/models/Consola.php';

header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-store');

$consolaModel = new Consola();

$busqueda = isset($_GET['busqueda']) && $_GET['busqueda'] !== '' ? trim($_GET['busqueda']) : null;
$activo   = isset($_GET['activo'])   && $_GET['activo']   !== '' ? $_GET['activo']         : null;

$itemsPerPage = 20;
$currentPage  = max(1, (int)($_GET['page'] ?? 1));
$offset       = ($currentPage - 1) * $itemsPerPage;

$consolas   = $consolaModel->getAllPaginated($busqueda, $offset, $itemsPerPage, $activo);
$total      = $consolaModel->countAll($busqueda, $activo);
$totalPages = (int)ceil($total / $itemsPerPage);

// Query string base (sin page) para los links de paginación
$qParts = [];
if ($busqueda) $qParts[] = 'busqueda=' . urlencode($busqueda);
if ($activo !== null) $qParts[] = 'activo=' . urlencode($activo);
$qBase = $qParts ? '&' . implode('&', $qParts) : '';
?>
<?php if (empty($consolas)): ?>
    <?php 
    require_once __DIR__ . '/../src/views/components/Alert.php';
    Alert::render('info', t('admin.consola.sin_resultados'), 'info', 'text-align:center;padding:3rem;justify-content:center;');
    ?>
<?php else: ?>
<div style="overflow-x:auto;">
    <table class="admin-table">
        <thead>
            <tr>
                <th><?= t('admin.consola.id') ?></th>
                <th><?= t('admin.consola.nombre') ?></th>
                <th><?= t('admin.consola.fabricante') ?></th>
                <th><?= t('admin.consola.descripcion') ?></th>
                <th><?= t('admin.consola.estado') ?></th>
                <th><?= t('admin.consola.emulacion') ?></th>
                <th><?= t('admin.consola.creada_col') ?></th>
                <th><?= t('admin.consola.acciones') ?></th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($consolas as $c): ?>
            <tr>
                <td>#<?= $c['id'] ?></td>
                <td><strong><?= htmlspecialchars($c['nombre']) ?></strong></td>
                <td><?= htmlspecialchars($c['fabricante'] ?? '—') ?></td>
                <td style="max-width:220px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;color:var(--slate-mid);font-size:.88rem;">
                    <?= htmlspecialchars($c['descripcion'] ?? '—') ?>
                </td>
                <td>
                    <button class="btn-toggle-active <?= $c['activo'] ? 'btn-toggle--on' : 'btn-toggle--off' ?>"
                            data-id="<?= $c['id'] ?>"
                            data-titulo="<?= htmlspecialchars($c['nombre'], ENT_QUOTES) ?>"
                            data-accion="<?= $c['activo'] ? 'desactivar' : 'activar' ?>">
                        <?= $c['activo'] ? t('admin.consola.desactivar') : t('admin.consola.activar') ?>
                    </button>
                </td>
                <td>
                    <button class="btn-toggle-active btn-toggle-emulacion <?= $c['emulacion_online'] ? 'btn-toggle--on' : 'btn-toggle--off' ?>"
                            data-id="<?= $c['id'] ?>"
                            data-titulo="<?= htmlspecialchars($c['nombre'], ENT_QUOTES) ?>"
                            data-accion="<?= $c['emulacion_online'] ? 'desactivar' : 'activar' ?>">
                        <?= $c['emulacion_online'] ? t('admin.consola.desactivar') : t('admin.consola.activar') ?>
                    </button>
                </td>
                <td><?= date('d/m/Y', strtotime($c['created_at'])) ?></td>
                <td>
                    <a href="/consola/edit/<?= $c['id'] ?>" class="btn-edit"><?= t('admin.consola.editar') ?></a>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<?php 
require_once __DIR__ . '/../src/views/components/Pagination.php';
Pagination::render(
    $currentPage, 
    $totalPages ?? 1, 
    $qBase, 
    'consola', 
    'index', 
    count($consolas), 
    $total, 
    t('component.consolas'), 
    'cs'
);
?>
<?php endif; ?>

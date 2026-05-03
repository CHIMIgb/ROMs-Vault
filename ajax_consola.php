<?php
/**
 * ajax_consola.php
 * Endpoint AJAX — devuelve el HTML de la tabla de consolas + paginación.
 * Llamado por el JS de búsqueda en tiempo real en views/admin/consolas/index.php
 */

require_once __DIR__ . '/config/AuthMiddleware.php';
AuthMiddleware::requireAuthAjax();

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/models/Model.php';
require_once __DIR__ . '/models/Consola.php';

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
    <div class="rv-inline-alert rv-inline--info rv-inline--visible" style="text-align:center;padding:3rem;justify-content:center;">
        <span class="rv-inline-icon"><i data-i="info"></i></span>
        <span class="rv-inline-msg">No hay consolas que coincidan con los filtros.</span>
    </div>
<?php else: ?>
<div style="overflow-x:auto;">
    <table class="admin-table">
        <thead>
            <tr>
                <th>ID</th>
                <th>Nombre</th>
                <th>Fabricante</th>
                <th>Descripción</th>
                <th>Estado</th>
                <th>Creada</th>
                <th>Acciones</th>
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
                        <?= $c['activo'] ? 'Desactivar' : 'Activar' ?>
                    </button>
                </td>
                <td><?= date('d/m/Y', strtotime($c['created_at'])) ?></td>
                <td>
                    <a href="index.php?controller=consola&action=edit&id=<?= $c['id'] ?>" class="btn-edit">Editar</a>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<?php if ($totalPages > 1): ?>
<div class="pagination" style="margin-top:1.5rem;" id="cs-pagination">
    <?php if ($currentPage > 1): ?>
        <a href="?controller=consola&action=index&page=<?= $currentPage - 1 ?><?= $qBase ?>"
           class="pagination-link" data-page="<?= $currentPage - 1 ?>">
            <i data-i="chevron-left"></i> Anterior
        </a>
    <?php endif; ?>
    <?php
    $range = 2; $pages = [];
    for ($i = 1; $i <= $totalPages; $i++) {
        if ($i === 1 || $i === $totalPages || abs($i - $currentPage) <= $range) $pages[] = $i;
    }
    $prev = null;
    foreach ($pages as $i):
        if ($prev !== null && $i - $prev > 1): ?>
            <span style="padding:0 0.3rem;color:var(--slate-light);">...</span>
        <?php endif;
        if ($i === $currentPage): ?>
            <span class="pagination-current"><?= $i ?></span>
        <?php else: ?>
            <a href="?controller=consola&action=index&page=<?= $i ?><?= $qBase ?>"
               class="pagination-link" data-page="<?= $i ?>"><?= $i ?></a>
        <?php endif;
        $prev = $i;
    endforeach; ?>
    <?php if ($currentPage < $totalPages): ?>
        <a href="?controller=consola&action=index&page=<?= $currentPage + 1 ?><?= $qBase ?>"
           class="pagination-link" data-page="<?= $currentPage + 1 ?>">
            Siguiente <i data-i="chevron-right"></i>
        </a>
    <?php endif; ?>
</div>
<?php endif; ?>

<div class="pagination-info" style="margin-top:0.75rem;">
    Mostrando <?= count($consolas) ?> de <?= number_format($total) ?> consola<?= $total !== 1 ? 's' : '' ?>
    <?php if ($totalPages > 1): ?>- Página <?= $currentPage ?> de <?= $totalPages ?><?php endif; ?>
</div>
<?php endif; ?>

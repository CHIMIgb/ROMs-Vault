<?php
/**
 * ajax_emulador.php
 * Endpoint AJAX — devuelve el HTML de la tabla de emuladores + paginación.
 * Llamado por el JS de búsqueda en tiempo real en views/admin/emuladores/index.php
 */

require_once __DIR__ . '/../src/config/AuthMiddleware.php';
AuthMiddleware::requireAdminAjax();

require_once __DIR__ . '/../src/config/database.php';
require_once __DIR__ . '/../src/models/Model.php';
require_once __DIR__ . '/../src/models/Emulador.php';

header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-store');

$emuladorModel = new Emulador();

$busqueda = isset($_GET['busqueda']) && $_GET['busqueda'] !== '' ? trim($_GET['busqueda']) : null;
$activo   = isset($_GET['activo'])   && $_GET['activo']   !== '' ? $_GET['activo']         : null;

$itemsPerPage = 20;
$currentPage  = max(1, (int)($_GET['page'] ?? 1));
$offset       = ($currentPage - 1) * $itemsPerPage;

$consolas = $emuladorModel->getConsolasPaginated($busqueda, $offset, $itemsPerPage, $activo);
$total        = $emuladorModel->countConsolas($busqueda);
$totalPages   = (int)ceil($emuladorModel->countConsolas($busqueda, $activo) / $itemsPerPage);

// Emuladores de las consolas visibles en esta página, agrupados por consola
$emuladoresPorConsola = [];
$emuladores = $emuladorModel->getByConsolaIds(array_map('intval', array_column($consolas, 'id')));
foreach ($emuladores as $e) {
    $cid = (int) $e['consola_id'];
    if (!isset($emuladoresPorConsola[$cid])) {
        $emuladoresPorConsola[$cid] = ['principal' => null, 'alterno' => null];
    }
    $dato = [
        'nombre'      => $e['nombre'],
        'plataformas' => array_values(array_filter(array_map('trim', explode(',', (string) $e['plataformas'])))),
        'url'         => $e['url'],
    ];
    if (!empty($e['es_alterno'])) {
        $emuladoresPorConsola[$cid]['alterno'] = $dato;
    } else {
        $emuladoresPorConsola[$cid]['principal'] = $dato;
    }
}

$filas = [];
foreach ($consolas as $c) {
    $cid = (int) $c['id'];
    $filas[] = [
        'id'             => $cid,
        'consola_nombre' => $c['consola_nombre'],
        'activo'         => (bool) $c['activo'],
        'principal'      => $emuladoresPorConsola[$cid]['principal'] ?? null,
        'alterno'        => $emuladoresPorConsola[$cid]['alterno'] ?? null,
    ];
}

// Query string base (sin page) para los links de paginación
$qParts = [];
if ($busqueda) $qParts[] = 'busqueda=' . urlencode($busqueda);
if ($activo !== null) $qParts[] = 'activo=' . urlencode($activo);
$qBase = $qParts ? '&' . implode('&', $qParts) : '';
?>
<?php if (empty($filas)): ?>
    <?php 
    require_once __DIR__ . '/../src/views/components/Alert.php';
    Alert::render('info', t('admin.emulador.sin_resultados'), 'info', 'text-align:center;padding:3rem;justify-content:center;');
    ?>
<?php else: ?>
<div style="overflow-x:auto;">
    <table class="admin-table">
        <thead>
            <tr>
                <th><?= t('admin.emulador.id') ?></th>
                <th><?= t('admin.emulador.consola') ?></th>
                <th><?= t('admin.emulador.principal') ?></th>
                <th><?= t('admin.emulador.alternativo') ?></th>
                <th><?= t('admin.emulador.estado') ?></th>
                <th><?= t('admin.emulador.acciones') ?></th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($filas as $f): ?>
            <tr>
                <td>#<?= $f['id'] ?></td>
                <td><strong><?= htmlspecialchars($f['consola_nombre']) ?></strong></td>
                <td>
                    <?php if ($f['principal']): ?>
                        <strong><?= htmlspecialchars($f['principal']['nombre']) ?></strong>
                        <div style="color:var(--slate-mid);font-size:.88rem;margin-top:.25rem;">
                            <?= htmlspecialchars(implode(' / ', $f['principal']['plataformas'])) ?>
                            ·
                            <a href="<?= htmlspecialchars($f['principal']['url']) ?>" target="_blank" rel="noopener noreferrer" style="text-decoration:underline;"><?= t('admin.emulador.sitio_oficial') ?></a>
                        </div>
                    <?php else: ?>
                        <span style="color:var(--text-light);">—</span>
                    <?php endif; ?>
                </td>
                <td>
                    <?php if ($f['alterno']): ?>
                        <strong><?= htmlspecialchars($f['alterno']['nombre']) ?></strong>
                        <div style="color:var(--slate-mid);font-size:.88rem;margin-top:.25rem;">
                            <?= htmlspecialchars(implode(' / ', $f['alterno']['plataformas'])) ?>
                            ·
                            <a href="<?= htmlspecialchars($f['alterno']['url']) ?>" target="_blank" rel="noopener noreferrer" style="text-decoration:underline;"><?= t('admin.emulador.sitio_oficial') ?></a>
                        </div>
                    <?php else: ?>
                        <span style="color:var(--text-light);">—</span>
                    <?php endif; ?>
                </td>
                <td>
                    <button class="btn-toggle-active <?= $f['activo'] ? 'btn-toggle--on' : 'btn-toggle--off' ?>"
                            data-id="<?= $f['id'] ?>"
                            data-titulo="<?= htmlspecialchars($f['consola_nombre'], ENT_QUOTES) ?>"
                            data-accion="<?= $f['activo'] ? 'desactivar' : 'activar' ?>">
                        <?= $f['activo'] ? t('admin.emulador.desactivar') : t('admin.emulador.activar') ?>
                    </button>
                </td>
                <td>
                    <a href="/emulador/edit/<?= $f['id'] ?>" class="btn-edit"><?= t('admin.emulador.editar') ?></a>
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
    'emulador', 
    'index', 
    count($filas), 
    $total, 
    t('component.consolas_con_emulador'), 
    'em'
);
?>
<?php endif; ?>

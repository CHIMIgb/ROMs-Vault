<?php
/**
 * ajax_admin.php
 * Endpoint AJAX — devuelve únicamente el HTML de la tabla de juegos + paginación.
 * Llamado por el JS de tiempo real en views/admin/dashboard.php
 */

session_start();
if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    echo '<div class="alert alert-error">Acceso denegado.</div>';
    exit;
}

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/models/Model.php';
require_once __DIR__ . '/models/Juego.php';
require_once __DIR__ . '/models/Consola.php';
require_once __DIR__ . '/models/Categoria.php';

header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-store');

$juegoModel = new Juego();

$filters = [];
if (isset($_GET['busqueda'])  && $_GET['busqueda']  !== '') $filters['busqueda']  = $_GET['busqueda'];
if (isset($_GET['consola'])   && $_GET['consola']   !== '') $filters['consola']   = $_GET['consola'];
if (isset($_GET['categoria']) && $_GET['categoria'] !== '') $filters['categoria'] = $_GET['categoria'];
if (isset($_GET['region'])    && $_GET['region']    !== '') $filters['region']    = $_GET['region'];
if (isset($_GET['activo'])    && $_GET['activo']    !== '') $filters['activo']    = $_GET['activo'];

$itemsPerPage = 20;
$currentPage  = max(1, (int)($_GET['page'] ?? 1));
$offset       = ($currentPage - 1) * $itemsPerPage;

$juegos      = $juegoModel->getAllPaginatedFiltered($filters, $offset, $itemsPerPage);
$totalJuegos = $juegoModel->countAllFiltered($filters);
$totalPages  = (int)ceil($totalJuegos / $itemsPerPage);

$hayFiltros = !empty($filters);

// Query string base sin page
$qParts = [];
foreach (['busqueda','consola','categoria','region','activo'] as $p) {
    if (isset($filters[$p])) $qParts[] = $p . '=' . urlencode($filters[$p]);
}
$qBase = $qParts ? '&' . implode('&', $qParts) : '';
?>
<?php if (empty($juegos)): ?>
    <div class="alert alert-info" style="text-align:center;padding:3rem;">
        No hay juegos que coincidan con los filtros aplicados.
    </div>
<?php else: ?>
<div style="overflow-x:auto;">
    <table class="admin-table" id="admin-table">
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
            $filterQS = $qBase;
            ?>
            <tr>
                <td>#<?= $juego['id'] ?></td>
                <td>
                    <?php if (!empty($juego['imagen'])): ?>
                        <img src="<?= htmlspecialchars($juego['imagen']) ?>" alt="Portada"
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
<?php if ($totalPages > 1): ?>
<div class="pagination" style="margin-top:1.5rem;" id="admin-pagination">
    <?php if ($currentPage > 1): ?>
        <a href="?controller=admin&action=dashboard&page=<?= $currentPage - 1 ?><?= $qBase ?>"
           class="pagination-link" data-page="<?= $currentPage - 1 ?>">← Anterior</a>
    <?php endif; ?>
    <?php
    $range = 2;
    $pages = [];
    for ($i = 1; $i <= $totalPages; $i++) {
        if ($i === 1 || $i === $totalPages || abs($i - $currentPage) <= $range) $pages[] = $i;
    }
    $prev = null;
    foreach ($pages as $i):
        if ($prev !== null && $i - $prev > 1): ?>
            <span style="padding:0 0.3rem;color:var(--slate-light);">…</span>
        <?php endif;
        if ($i === $currentPage): ?>
            <span class="pagination-current"><?= $i ?></span>
        <?php else: ?>
            <a href="?controller=admin&action=dashboard&page=<?= $i ?><?= $qBase ?>"
               class="pagination-link" data-page="<?= $i ?>"><?= $i ?></a>
        <?php endif;
        $prev = $i;
    endforeach; ?>
    <?php if ($currentPage < $totalPages): ?>
        <a href="?controller=admin&action=dashboard&page=<?= $currentPage + 1 ?><?= $qBase ?>"
           class="pagination-link" data-page="<?= $currentPage + 1 ?>">Siguiente →</a>
    <?php endif; ?>
</div>
<?php endif; ?>

<div class="pagination-info" style="margin-top:0.75rem;" id="admin-info">
    Mostrando <?= count($juegos) ?> de <?= number_format($totalJuegos) ?> juegos • Página <?= $currentPage ?> de <?= $totalPages ?>
    <?php if ($hayFiltros): ?>
        <span style="color:var(--text-light);"> • Filtros activos</span>
    <?php endif; ?>
</div>
<?php endif; ?>

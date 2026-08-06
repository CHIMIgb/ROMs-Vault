<?php
/**
 * ajax_admin.php
 * Endpoint AJAX — devuelve únicamente el HTML de la tabla de juegos + paginación.
 * Llamado por el JS de tiempo real en views/admin/dashboard.php
 */

require_once __DIR__ . '/config/AuthMiddleware.php';
AuthMiddleware::requireAdminAjax();

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
    <?php 
    require_once __DIR__ . '/views/components/Alert.php';
    Alert::render('info', 'No hay juegos que coincidan con los filtros aplicados.', 'info', 'text-align:center;padding:3rem;justify-content:center;');
    ?>
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
                    <button class="btn-toggle-active <?= $juego['activo'] ? 'btn-toggle--on' : 'btn-toggle--off' ?>"
                            data-id="<?= $juego['id'] ?>"
                            data-titulo="<?= htmlspecialchars($juego['titulo'], ENT_QUOTES) ?>"
                            data-accion="<?= $juego['activo'] ? 'desactivar' : 'activar' ?>">
                        <?= $juego['activo'] ? 'Desactivar' : 'Activar' ?>
                    </button>
                </td>
                <td>
                    <a href="/admin/edit/<?= $juego['id'] ?>" class="btn-edit">Editar</a>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<!-- PAGINACIÓN -->
<?php 
require_once __DIR__ . '/views/components/Pagination.php';
$extra = $hayFiltros ? '<span style="color:var(--text-light);"> • Filtros activos</span>' : '';
Pagination::render(
    $currentPage, 
    $totalPages ?? 1, 
    $qBase, 
    'admin', 
    'dashboard', 
    count($juegos), 
    $totalJuegos, 
    'juegos', 
    'admin',
    $extra
);
?>
<?php endif; ?>

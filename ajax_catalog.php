<?php
/**
 * ajax_catalog.php
 * Endpoint AJAX — devuelve únicamente el HTML del grid de juegos + paginación.
 * Llamado por el JS de tiempo real en views/home/index.php
 */

session_start();
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
if (isset($_GET['orden'])     && $_GET['orden']     !== '') $filters['orden']     = $_GET['orden'];

$itemsPerPage = 20;
$currentPage  = max(1, (int)($_GET['page'] ?? 1));
$offset       = ($currentPage - 1) * $itemsPerPage;

$juegos      = $juegoModel->getWithRelationsPaginated($filters, $offset, $itemsPerPage);
$totalJuegos = $juegoModel->countWithFilters($filters);
$totalPages  = (int)ceil($totalJuegos / $itemsPerPage);

$qParts = [];
foreach (['busqueda','consola','categoria','region','orden'] as $p) {
    if (!empty($filters[$p])) $qParts[] = $p . '=' . urlencode($filters[$p]);
}
$qBase = $qParts ? '&' . implode('&', $qParts) : '';
?>
<!-- GRID -->
<div class="games-grid" id="games-grid">
    <?php if (empty($juegos)): ?>
        <div class="rv-inline-alert rv-inline--info rv-inline--visible" style="grid-column:1/-1;text-align:center;padding:3rem;justify-content:center;">
            <span class="rv-inline-icon">ℹ</span>
            <span class="rv-inline-msg">No hay juegos disponibles que coincidan con los filtros.</span>
        </div>
    <?php else: ?>
        <?php foreach ($juegos as $juego): ?>
            <div class="game-card">
                <div class="game-card-inner">
                    <div class="game-cover <?= empty($juego['imagen']) ? 'no-image' : '' ?>">
                        <?php if (!empty($juego['imagen'])): ?>
                            <img src="<?= htmlspecialchars($juego['imagen']) ?>" alt="<?= htmlspecialchars($juego['titulo']) ?>">
                        <?php else: ?>
                            <i data-i="disc" data-cls="pxi-cover-placeholder" aria-hidden="true"></i>
                        <?php endif; ?>
                    </div>
                    <h3 class="game-title"><?= htmlspecialchars($juego['titulo']) ?></h3>
                    <div class="game-metadata">
                        <span class="game-tag genre"><?= htmlspecialchars($juego['categoria_nombre'] ?? 'Sin género') ?></span>
                        <span class="game-tag platform"><?= htmlspecialchars($juego['consola_nombre'] ?? 'Multi') ?></span>
                        <span class="game-tag language"><?= htmlspecialchars($juego['region'] ?? 'All') ?></span>
                    </div>
                    <?php if (!empty($juego['formato_imagen']) && $juego['formato_imagen'] === 'Hack'): ?>
                        <div class="game-hack-info"><?= htmlspecialchars($juego['descripcion'] ?? 'ROM Hack') ?></div>
                    <?php endif; ?>
                    <div class="game-info">
                        <div class="game-info-item">
                            <span class="game-info-label">Idiomas</span>
                            <span class="game-info-value"><?= htmlspecialchars($juego['idiomas'] ?? 'English') ?></span>
                        </div>
                        <div class="game-info-item">
                            <span class="game-info-label">Tamaño</span>
                            <span class="game-info-value"><?= number_format($juego['size_bytes'] / 1048576, 2) ?> MB</span>
                        </div>
                        <?php if (!empty($juego['downloads_count'])): ?>
                        <div class="game-info-item">
                            <span class="game-info-label">Descargas</span>
                            <span class="game-info-value"><?= number_format($juego['downloads_count']) ?></span>
                        </div>
                        <?php endif; ?>
                        <?php if (!empty($juego['plays_count'])): ?>
                        <div class="game-info-item">
                            <span class="game-info-label">Jugadas</span>
                            <span class="game-info-value"><?= number_format($juego['plays_count']) ?></span>
                        </div>
                        <?php endif; ?>
                    </div>
                    <div class="game-actions">
                        <a href="index.php?controller=home&action=play&file_id=<?= urlencode($juego['google_drive_file_id']) ?>" class="game-play"><i data-i="play" aria-hidden="true"></i> Jugar Online</a>
                        <a href="index.php?controller=home&action=download&file_id=<?= urlencode($juego['google_drive_file_id']) ?>" class="game-download" target="_blank"><i data-i="download" aria-hidden="true"></i> Descargar</a>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<!-- PAGINACIÓN -->
<?php if ($totalPages > 0): ?>
<div class="pagination" id="catalog-pagination">
    <?php if ($currentPage > 1): ?>
        <a href="?controller=home&action=index&page=<?= $currentPage - 1 ?><?= $qBase ?>" class="pagination-link" data-page="<?= $currentPage - 1 ?>"><i data-i="chevron-left" aria-hidden="true"></i> Anterior</a>
    <?php endif; ?>
    <?php
    $range = 2;
    $pages = [];
    for ($i = 1; $i <= $totalPages; $i++) {
        if ($i === 1 || $i === $totalPages || abs($i - $currentPage) <= $range) {
            $pages[] = $i;
        }
    }
    $prev = null;
    foreach ($pages as $i):
        if ($prev !== null && $i - $prev > 1): ?>
            <span style="padding:0 0.3rem;color:var(--slate-light);">...</span>
        <?php endif;
        if ($i === $currentPage): ?>
            <span class="pagination-current"><?= $i ?></span>
        <?php else: ?>
            <a href="?controller=home&action=index&page=<?= $i ?><?= $qBase ?>" class="pagination-link" data-page="<?= $i ?>"><?= $i ?></a>
        <?php endif;
        $prev = $i;
    endforeach; ?>
    <?php if ($currentPage < $totalPages): ?>
        <a href="?controller=home&action=index&page=<?= $currentPage + 1 ?><?= $qBase ?>" class="pagination-link" data-page="<?= $currentPage + 1 ?>">Siguiente <i data-i="chevron-right" aria-hidden="true"></i></a>
    <?php endif; ?>
</div>
<div class="pagination-info" id="catalog-info">
    Mostrando <?= count($juegos) ?> de <?= number_format($totalJuegos) ?> juegos • Página <?= $currentPage ?> de <?= $totalPages ?>
    <?php if (!empty($filters['busqueda'])): ?>
        • Búsqueda: "<?= htmlspecialchars($filters['busqueda']) ?>"
    <?php endif; ?>
</div>
<?php endif; ?>

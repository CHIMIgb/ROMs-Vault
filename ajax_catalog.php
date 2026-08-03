<?php
/**
 * ajax_catalog.php
 * Endpoint AJAX — devuelve únicamente el HTML del grid de juegos + paginación.
 * Llamado por el JS de tiempo real en views/home/index.php
 */

// Endpoint público — no requiere autenticación
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
// Orden por defecto: más recientes (evita RANDOM() que fuerza un sort completo)
if (empty($filters['orden'])) $filters['orden'] = 'recientes';

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
        <?php 
        require_once __DIR__ . '/views/components/Alert.php';
        Alert::render('info', 'No hay juegos disponibles que coincidan con los filtros.', 'info', 'grid-column:1/-1;text-align:center;padding:3rem;justify-content:center;');
        ?>
    <?php else: ?>
        <?php foreach ($juegos as $juego): ?>
            <div class="game-card">
                <div class="game-card-inner">
                    <a href="index.php?controller=home&action=show&id=<?= $juego['id'] ?>" class="game-detail-link" style="text-decoration:none; color:inherit; display:block;">
                        <div class="game-cover <?= empty($juego['imagen']) ? 'no-image' : '' ?>">
                            <?php if (!empty($juego['imagen'])): ?>
                                <img src="<?= htmlspecialchars($juego['imagen']) ?>" alt="<?= htmlspecialchars($juego['titulo']) ?>" loading="lazy" decoding="async">
                            <?php else: ?>
                                <i data-i="disc" data-cls="pxi-cover-placeholder" aria-hidden="true"></i>
                            <?php endif; ?>
                        </div>
                        <h3 class="game-title"><?= htmlspecialchars($juego['titulo']) ?></h3>
                    </a>
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
                    <?php require __DIR__ . '/views/components/game_actions.php'; ?>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<!-- PAGINACIÓN -->
<?php 
require_once __DIR__ . '/views/components/Pagination.php';
$extra = !empty($filters['busqueda']) 
    ? ' • Búsqueda: "' . htmlspecialchars($filters['busqueda']) . '"' 
    : '';

Pagination::render(
    $currentPage, 
    $totalPages ?? 1, 
    $qBase, 
    'home', 
    'index', 
    count($juegos), 
    $totalJuegos, 
    'juegos', 
    'catalog',
    $extra
);
?>

<?php
/**
 * ajax_catalog.php
 * Endpoint AJAX — devuelve únicamente el HTML del grid de juegos + paginación.
 * Llamado por el JS de tiempo real en views/home/index.php
 */

// Endpoint público — no requiere autenticación
require_once __DIR__ . '/../src/config/i18n.php';
require_once __DIR__ . '/../src/config/database.php';
require_once __DIR__ . '/../src/models/Model.php';
require_once __DIR__ . '/../src/models/Juego.php';
require_once __DIR__ . '/../src/models/Consola.php';
require_once __DIR__ . '/../src/models/Categoria.php';
require_once __DIR__ . '/../src/config/TraduccionService.php';

header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-store');

$juegoModel = new Juego();

$filters = [];
if (isset($_GET['busqueda'])  && $_GET['busqueda']  !== '') $filters['busqueda']  = $_GET['busqueda'];
// Validación estricta: consola y categoria solo admiten ids enteros positivos.
// Un valor no numérico (p. ej. ?consola=abc) no debe llegar al modelo: el cast
// de PDO/PostgreSQL podría producir un 500 en lugar de un listado sin resultados.
$consolaVal = $_GET['consola'] ?? '';
if ($consolaVal !== '' && filter_var($consolaVal, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) !== false) {
    $filters['consola'] = (int) $consolaVal;
}
$categoriaVal = $_GET['categoria'] ?? '';
if ($categoriaVal !== '' && filter_var($categoriaVal, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) !== false) {
    $filters['categoria'] = (int) $categoriaVal;
}
if (isset($_GET['region'])    && $_GET['region']    !== '') $filters['region']    = $_GET['region'];
if (isset($_GET['orden'])     && $_GET['orden']     !== '') $filters['orden']     = $_GET['orden'];
// Orden por defecto: aleatorio (como el usuario prefiere para la carga del catálogo)
if (empty($filters['orden'])) $filters['orden'] = 'random';

$itemsPerPage = 20;
$currentPage  = max(1, (int)($_GET['page'] ?? 1));
$offset       = ($currentPage - 1) * $itemsPerPage;

$juegos      = $juegoModel->getWithRelationsPaginated($filters, $offset, $itemsPerPage, Juego::getCatalogSeed());
$totalJuegos = $juegoModel->countWithFilters($filters);
$totalPages  = (int)ceil($totalJuegos / $itemsPerPage);

// Traducción del contenido editorial al idioma activo (fallback a 'es'): sin
// esto, las tarjetas que reemplaza el AJAX mostrarían categoría, descripción e
// idiomas en español aunque la UI esté en otro idioma.
TraduccionService::resolverCategoriasVinculadas($juegos);
TraduccionService::resolverJuegos($juegos);

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
        require_once __DIR__ . '/../src/views/components/EmptyState.php';
        EmptyState::render(t('catalog.vacio_titulo'), t('catalog.sin_resultados'), t('home.limpiar'));
        ?>
    <?php else: ?>
        <?php require_once __DIR__ . '/../src/views/components/game_card.php'; ?>
        <?php $i = 0; foreach ($juegos as $juego): $i++; ?>
            <?php GameCard::render($juego, $i); ?>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<!-- PAGINACIÓN -->
<?php 
require_once __DIR__ . '/../src/views/components/Pagination.php';
$extra = !empty($filters['busqueda']) 
    ? t('home.paginacion_busqueda', ['valor' => htmlspecialchars($filters['busqueda'])]) 
    : '';

Pagination::render(
    $currentPage, 
    $totalPages ?? 1, 
    $qBase, 
    'home', 
    'index', 
    count($juegos), 
    $totalJuegos, 
    t('component.juegos'), 
    'catalog',
    $extra
);
?>

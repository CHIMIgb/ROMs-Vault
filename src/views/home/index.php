<!-- views/home/index.php -->

<!-- BARRA DE BÚSQUEDA -->
<div class="search-section">
    <div class="search-wrapper">
        <input type="text"
               id="f-busqueda"
               placeholder="<?= t('home.buscar_placeholder') ?>"
               value="<?= htmlspecialchars($_GET['busqueda'] ?? '') ?>"
               class="search-input"
               autocomplete="off"
               aria-label="<?= t('home.buscar_aria_label') ?>"
               aria-autocomplete="list"
               aria-controls="autocomplete-list"
               aria-expanded="false">
        <button type="button" class="search-button" onclick="document.getElementById('f-busqueda').dispatchEvent(new Event('input'))" aria-label="<?= t('home.boton_buscar') ?>">
            <i data-i="search" aria-hidden="true"></i>
        </button>
        <!-- Dropdown autocompletado -->
        <ul id="autocomplete-list" class="autocomplete-list" role="listbox" aria-label="<?= t('home.sugerencias') ?>"></ul>
    </div>
</div>

<!-- FILTROS -->
<div class="filters">
    <div class="filters-row">
        <select id="f-consola">
            <option value=""><?= t('home.todas_plataformas') ?></option>
            <?php foreach ($consolas as $consola): ?>
                <option value="<?= $consola['id'] ?>" <?= (($_GET['consola'] ?? '') == $consola['id']) ? 'selected' : '' ?>>
                    <?= htmlspecialchars($consola['nombre']) ?>
                </option>
            <?php endforeach; ?>
        </select>

        <select id="f-categoria">
            <option value=""><?= t('home.todos_generos') ?></option>
            <?php foreach ($categorias as $categoria): ?>
                <option value="<?= $categoria['id'] ?>" <?= (($_GET['categoria'] ?? '') == $categoria['id']) ? 'selected' : '' ?>>
                    <?= htmlspecialchars($categoria['nombre']) ?>
                </option>
            <?php endforeach; ?>
        </select>

        <select id="f-region">
            <option value=""><?= t('home.todas_regiones') ?></option>
            <option value="PAL"    <?= (($_GET['region'] ?? '') === 'PAL')    ? 'selected' : '' ?>><?= t('home.region_pal') ?></option>
            <option value="NTSC"   <?= (($_GET['region'] ?? '') === 'NTSC')   ? 'selected' : '' ?>><?= t('home.region_ntsc') ?></option>
            <option value="NTSC-J" <?= (($_GET['region'] ?? '') === 'NTSC-J') ? 'selected' : '' ?>><?= t('home.region_ntsc_j') ?></option>
            <option value="NTSC-U" <?= (($_GET['region'] ?? '') === 'NTSC-U') ? 'selected' : '' ?>><?= t('home.region_ntsc_u') ?></option>
        </select>

        <select id="f-orden">
            <option value="random"    <?= (($_GET['orden'] ?? 'random') === 'random') ? 'selected' : '' ?>><?= t('home.orden_aleatorio') ?></option>
            <option value="titulo"    <?= (($_GET['orden'] ?? '') === 'titulo')    ? 'selected' : '' ?>><?= t('home.orden_a_z') ?></option>
            <option value="recientes" <?= (($_GET['orden'] ?? '') === 'recientes') ? 'selected' : '' ?>><?= t('home.orden_mas_recientes') ?></option>
            <option value="descargas" <?= (($_GET['orden'] ?? '') === 'descargas') ? 'selected' : '' ?>><?= t('home.orden_mas_descargados') ?></option>
            <option value="jugados"   <?= (($_GET['orden'] ?? '') === 'jugados')   ? 'selected' : '' ?>><?= t('home.orden_mas_jugados') ?></option>
            <option value="año_desc"  <?= (($_GET['orden'] ?? '') === 'año_desc')  ? 'selected' : '' ?>><?= t('home.orden_ano_desc') ?></option>
            <option value="año_asc"   <?= (($_GET['orden'] ?? '') === 'año_asc')   ? 'selected' : '' ?>><?= t('home.orden_ano_asc') ?></option>
        </select>

        <button type="button" id="btn-limpiar" class="clear-filters"><i data-i="close" aria-hidden="true"></i> <?= t('home.limpiar') ?></button>
    </div>

    <!-- Indicador de carga -->
    <div id="filter-loading" class="filter-loading">
        <span class="filter-loading-bar"></span>
        <span class="filter-loading-text"><?= t('home.buscando') ?></span>
    </div>
</div>

<!-- ZONA DE RESULTADOS (reemplazada por AJAX) -->
<div id="catalog-results">

    <!-- GRID DE JUEGOS -->
    <div class="games-grid" id="games-grid">
        <?php if (empty($juegos)): ?>
            <?php require_once __DIR__ . '/../components/EmptyState.php'; ?>
            <?php EmptyState::render(t('catalog.vacio_titulo'), t('catalog.sin_resultados'), t('home.limpiar')); ?>
        <?php else: ?>
            <?php require_once __DIR__ . '/../components/game_card.php'; ?>
            <?php $i = 0; foreach ($juegos as $juego): $i++; ?>
                <?php GameCard::render($juego, $i); ?>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <!-- PAGINACION -->
    <?php if (!empty($juegos) && isset($totalPages) && $totalPages > 0): ?>
    <?php
    $qParts = [];
    foreach (['busqueda','consola','categoria','region','orden'] as $p) {
        if (!empty($_GET[$p])) $qParts[] = $p . '=' . urlencode($_GET[$p]);
    }
    $qBase = $qParts ? '&' . implode('&', $qParts) : '';
    
    require_once __DIR__ . '/../components/Pagination.php';
    $extra = isset($_GET['busqueda']) && $_GET['busqueda'] !== '' 
        ? t('home.paginacion_busqueda', ['valor' => htmlspecialchars($_GET['busqueda'])]) 
        : '';
        
    Pagination::render(
        $currentPage, 
        $totalPages ?? 1, 
        $qBase, 
        'home', 
        'index', 
        count($juegos), 
        $totalJuegos ?? 0, 
        t('component.juegos'), 
        'catalog',
        $extra
    );
    ?>
    <?php endif; ?>

</div><!-- /#catalog-results -->

<script type="application/json" id="rv-data-home-index"><?= json_encode(
    [
        't' => [
            'home.error_carga'  => t('home.error_carga'),
            'home.jugar_online' => t('home.jugar_online'),
            'home.descargar'    => t('home.descargar'),
        ],
    ],
    JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
) ?></script>

<script src="public/js/views/home/index.js" defer></script>

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
            <?php $i = 0; foreach ($juegos as $juego): $i++; ?>
                <div class="game-card">
                    <div class="game-card-inner">
                        <a href="<?= !empty($juego['slug']) ? '/juego/' . rawurlencode($juego['slug']) : '/home/show/' . $juego['id'] ?>" class="game-detail-link">
                            <div class="game-cover <?= empty($juego['imagen']) ? 'no-image' : '' ?>">
                                <!-- Disco placeholder siempre presente: se ve cuando no hay
                                     imagen o cuando la imagen falla al cargar (onerror) -->
                                <i data-i="disc" data-cls="pxi-cover-placeholder" aria-hidden="true"></i>
                                <?php if (!empty($juego['imagen'])): ?>
                                    <img src="<?= htmlspecialchars($juego['imagen']) ?>" alt="<?= htmlspecialchars($juego['titulo']) ?>" loading="lazy" decoding="async"<?= $i === 1 ? ' fetchpriority="high"' : '' ?> onerror="this.style.display='none';">
<?php
require_once __DIR__ . '/../../config/IntlHelper.php';
?>
<?php endif; ?>
                            </div>
                            <h3 class="game-title"><?= htmlspecialchars($juego['titulo']) ?></h3>
                        </a>
                        <div class="game-metadata">
                            <span class="game-tag genre"><?= htmlspecialchars($juego['categoria_nombre'] ?? t('home.sin_genero')) ?></span>
                            <span class="game-tag platform"><?= htmlspecialchars($juego['consola_nombre'] ?? t('home.multi')) ?></span>
                            <span class="game-tag language"><?= htmlspecialchars($juego['region'] ?? t('home.region_all')) ?></span>
                        </div>
                        <?php if (!empty($juego['formato_imagen']) && $juego['formato_imagen'] === 'Hack'): ?>
                            <div class="game-hack-info"><?= htmlspecialchars($juego['descripcion'] ?? t('home.rom_hack')) ?></div>
                        <?php endif; ?>
                        <div class="game-info">
                            <div class="game-info-item">
                                <span class="game-info-label"><?= t('home.idiomas') ?></span>
                                <span class="game-info-value"><?= htmlspecialchars($juego['idiomas'] ?? 'English') ?></span>
                            </div>
                            <div class="game-info-item">
                                <span class="game-info-label"><?= t('home.tamano') ?></span>
                                <span class="game-info-value"><?= IntlHelper::formatBytes($juego['size_bytes'] ?? 0) ?></span>
                            </div>
                            <?php if (!empty($juego['downloads_count'])): ?>
                            <div class="game-info-item">
                                <span class="game-info-label"><?= t('home.descargas') ?></span>
                                <span class="game-info-value"><?= IntlHelper::formatNumber($juego['downloads_count']) ?></span>
                            </div>
                            <?php endif; ?>
                            <?php if (!empty($juego['plays_count'])): ?>
                            <div class="game-info-item">
                                <span class="game-info-label"><?= t('home.jugadas') ?></span>
                                <span class="game-info-value"><?= IntlHelper::formatNumber($juego['plays_count']) ?></span>
                            </div>
                            <?php endif; ?>
                        </div>
                        <?php require __DIR__ . '/../components/game_actions.php'; ?>
                    </div>
                </div>
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

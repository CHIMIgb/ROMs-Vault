<!-- views/admin/dashboard.php -->

<?php
require_once __DIR__ . '/../../config/IntlHelper.php';
?>

<!-- ESTADÍSTICAS GLOBALES REALES -->
<div class="admin-stats-grid">
    <div class="stat-card">
        <div class="stat-label"><?= t('admin.dashboard.total_juegos') ?></div>
        <div class="stat-value"><?= IntlHelper::formatNumber($stats['total']) ?></div>
    </div>
    <div class="stat-card">
        <div class="stat-label"><?= t('admin.dashboard.activos') ?></div>
        <div class="stat-value stat-value--ok"><?= IntlHelper::formatNumber($stats['activos']) ?></div>
    </div>
    <div class="stat-card">
        <div class="stat-label"><?= t('admin.dashboard.inactivos') ?></div>
        <div class="stat-value stat-value--muted"><?= IntlHelper::formatNumber($stats['inactivos']) ?></div>
    </div>
    <div class="stat-card">
        <div class="stat-label"><?= t('admin.dashboard.total_descargas') ?></div>
        <div class="stat-value"><?= IntlHelper::formatNumber($stats['total_descargas']) ?></div>
    </div>
    <div class="stat-card">
        <div class="stat-label"><?= t('admin.dashboard.total_jugadas') ?></div>
        <div class="stat-value"><?= IntlHelper::formatNumber($stats['total_jugadas']) ?></div>
    </div>
</div>

<!-- RANKINGS -->
<?php if (!empty($topDescargas) || !empty($topJugados)): ?>
<div class="admin-rankings">
    <?php if (!empty($topDescargas)): ?>
    <div class="ranking-card">
        <h3 class="ranking-title"><?= t('admin.dashboard.top_descargados') ?></h3>
        <ol class="ranking-list">
            <?php $maxD = $topDescargas[0]['downloads_count'] ?: 1;
            foreach ($topDescargas as $i => $j):
                $pct = round(($j['downloads_count'] / $maxD) * 100); ?>
            <li class="ranking-item">
                <span class="ranking-pos"><?= $i + 1 ?></span>
                <div class="ranking-info">
                    <span class="ranking-game"><?= htmlspecialchars($j['titulo']) ?></span>
                    <span class="ranking-platform"><?= htmlspecialchars($j['consola_nombre'] ?? '') ?></span>
                    <div class="ranking-bar-track"><div class="ranking-bar-fill" style="width:<?= $pct ?>%"></div></div>
                </div>
                <span class="ranking-count"><?= IntlHelper::formatNumber($j['downloads_count']) ?></span>
            </li>
            <?php endforeach; ?>
        </ol>
    </div>
    <?php endif; ?>
    <?php if (!empty($topJugados)): ?>
    <div class="ranking-card">
        <h3 class="ranking-title"><?= t('admin.dashboard.top_jugados') ?></h3>
        <ol class="ranking-list">
            <?php $maxP = $topJugados[0]['plays_count'] ?: 1;
            foreach ($topJugados as $i => $j):
                $pct = round(($j['plays_count'] / $maxP) * 100); ?>
            <li class="ranking-item">
                <span class="ranking-pos"><?= $i + 1 ?></span>
                <div class="ranking-info">
                    <span class="ranking-game"><?= htmlspecialchars($j['titulo']) ?></span>
                    <span class="ranking-platform"><?= htmlspecialchars($j['consola_nombre'] ?? '') ?></span>
                    <div class="ranking-bar-track"><div class="ranking-bar-fill ranking-bar-fill--plays" style="width:<?= $pct ?>%"></div></div>
                </div>
                <span class="ranking-count"><?= IntlHelper::formatNumber($j['plays_count']) ?></span>
            </li>
            <?php endforeach; ?>
        </ol>
    </div>
    <?php endif; ?>
</div>
<?php endif; ?>

<!-- CABECERA -->
<div class="admin-header">
    <h2><?= t('admin.dashboard.acciones') ?></h2>
    <div class="admin-header-actions">
        <button type="button" id="btn-export" class="btn-primary btn-primary--success">
            <i data-i="save"></i> <?= t('admin.dashboard.exportar') ?>
        </button>
        <a href="/admin/add" class="btn-primary">
            <i data-i="plus"></i> <?= t('admin.dashboard.anadir_juego') ?>
        </a>
    </div>
</div>

<!-- FILTROS EXTENDIDOS EN TIEMPO REAL -->
<div class="search-section">
    <div class="admin-filter-form">
        <div class="search-wrapper">
            <input type="text"
                   id="af-busqueda"
                   placeholder="<?= t('admin.dashboard.buscar_titulo') ?>"
                   value="<?= htmlspecialchars($_GET['busqueda'] ?? '') ?>"
                   class="search-input"
                   autocomplete="off">
            <button type="button" class="search-button"
                    onclick="document.getElementById('af-busqueda').dispatchEvent(new Event('input'))">
                <i data-i="search" aria-hidden="true"></i>
            </button>
        </div>

        <select id="af-consola" class="admin-filter-select">
            <option value=""><?= t('admin.dashboard.todas_consolas') ?></option>
            <?php foreach ($consolas as $c): ?>
                <option value="<?= $c['id'] ?>" <?= (($_GET['consola'] ?? '') == $c['id']) ? 'selected' : '' ?>>
                    <?= htmlspecialchars($c['nombre']) ?>
                </option>
            <?php endforeach; ?>
        </select>

        <select id="af-categoria" class="admin-filter-select">
            <option value=""><?= t('admin.dashboard.todas_categorias') ?></option>
            <?php foreach ($categorias as $cat): ?>
                <option value="<?= $cat['id'] ?>" <?= (($_GET['categoria'] ?? '') == $cat['id']) ? 'selected' : '' ?>>
                    <?= htmlspecialchars($cat['nombre']) ?>
                </option>
            <?php endforeach; ?>
        </select>

        <select id="af-region" class="admin-filter-select">
            <option value=""><?= t('admin.dashboard.todas_regiones') ?></option>
            <option value="PAL"    <?= (($_GET['region'] ?? '') === 'PAL')    ? 'selected' : '' ?>>PAL</option>
            <option value="NTSC"   <?= (($_GET['region'] ?? '') === 'NTSC')   ? 'selected' : '' ?>>NTSC</option>
            <option value="NTSC-J" <?= (($_GET['region'] ?? '') === 'NTSC-J') ? 'selected' : '' ?>>NTSC-J</option>
            <option value="NTSC-U" <?= (($_GET['region'] ?? '') === 'NTSC-U') ? 'selected' : '' ?>>NTSC-U</option>
        </select>

        <select id="af-activo" class="admin-filter-select">
            <option value=""  <?= (!isset($_GET['activo']) || $_GET['activo'] === '') ? 'selected' : '' ?>><?= t('admin.dashboard.todos_estados') ?></option>
            <option value="1" <?= (($_GET['activo'] ?? '') === '1') ? 'selected' : '' ?>><?= t('admin.dashboard.solo_activos') ?></option>
            <option value="0" <?= (($_GET['activo'] ?? '') === '0') ? 'selected' : '' ?>><?= t('admin.dashboard.solo_inactivos') ?></option>
        </select>

        <button type="button" id="af-limpiar" class="clear-filters clear-filters--nowrap"><i data-i="close" aria-hidden="true"></i> <?= t('admin.dashboard.limpiar') ?></button>
    </div>

    <!-- Indicador de carga -->
    <div id="admin-filter-loading" class="filter-loading">
        <span class="filter-loading-bar"></span>
        <span class="filter-loading-text"><?= t('admin.dashboard.buscando') ?></span>
    </div>
</div>

<!-- ZONA DE RESULTADOS ADMIN -->
<div id="admin-results">

    <?php if (empty($juegos)): ?>
        <?php 
        require_once __DIR__ . '/../components/Alert.php';
        Alert::render('info', t('admin.dashboard.sin_juegos'), 'info', 'text-align:center;padding:3rem;justify-content:center;');
        ?>
    <?php else: ?>
    <div class="admin-table-wrap">
        <table class="admin-table">
            <thead>
                <tr>
                    <th><?= t('admin.juego.tabla_id') ?></th><th><?= t('admin.juego.tabla_portada') ?></th><th><?= t('admin.juego.tabla_titulo') ?></th><th><?= t('admin.juego.tabla_id_juego') ?></th>
                    <th><?= t('admin.juego.tabla_consola') ?></th><th><?= t('admin.juego.tabla_categoria') ?></th><th><?= t('admin.juego.tabla_region') ?></th>
                    <th class="col-center" title="<?= t('admin.juego.tabla_descargas') ?>"><?= t('admin.juego.tabla_descargas_abrev') ?></th>
                    <th class="col-center" title="<?= t('admin.juego.tabla_jugadas_online') ?>"><?= t('admin.juego.tabla_jugadas_abrev') ?></th>
                    <th><?= t('admin.juego.tabla_estado') ?></th><th><?= t('admin.juego.tabla_acciones') ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($juegos as $juego): ?>
                <?php
                $filterQS = '';
                foreach (['busqueda','consola','categoria','region','activo'] as $p) {
                    if (isset($_GET[$p]) && $_GET[$p] !== '') {
                        $filterQS .= '&' . $p . '=' . urlencode($_GET[$p]);
                    }
                }
                ?>
                <tr>
                    <td>#<?= $juego['id'] ?></td>
                    <td>
                        <?php if (!empty($juego['imagen'])): ?>
                            <img src="<?= htmlspecialchars($juego['imagen']) ?>" alt="<?= t('admin.juego.alt_portada') ?>">
                        <?php else: ?>
                            <span class="text-muted"><i data-i="image"></i></span>
                        <?php endif; ?>
                    </td>
                    <td><strong><?= htmlspecialchars($juego['titulo']) ?></strong></td>
                    <td><code><?= htmlspecialchars($juego['game_id_code'] ?? '-') ?></code></td>
                    <td><?= htmlspecialchars($juego['consola_nombre']  ?? '-') ?></td>
                    <td><?= htmlspecialchars($juego['categoria_nombre'] ?? '-') ?></td>
                    <td>
                        <span class="game-tag">
                            <?= htmlspecialchars($juego['region'] ?? '-') ?>
                        </span>
                    </td>
                    <td class="col-num"><?= IntlHelper::formatNumber($juego['downloads_count'] ?? 0) ?></td>
                    <td class="col-num"><?= IntlHelper::formatNumber($juego['plays_count'] ?? 0) ?></td>
                    <td>
                        <button class="btn-toggle-active <?= $juego['activo'] ? 'btn-toggle--on' : 'btn-toggle--off' ?>"
                                data-id="<?= $juego['id'] ?>"
                                data-titulo="<?= htmlspecialchars($juego['titulo'], ENT_QUOTES) ?>"
                                data-accion="<?= $juego['activo'] ? 'desactivar' : 'activar' ?>">
                            <?= $juego['activo'] ? t('admin.juego.desactivar') : t('admin.juego.activar') ?>
                        </button>
                    </td>
                    <td>
                        <a href="/admin/edit/<?= $juego['id'] ?>" class="btn-edit"><?= t('admin.juego.editar') ?></a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <!-- PAGINACION -->
    <?php
    $paramStr = '';
    foreach (['busqueda','consola','categoria','region','activo'] as $p) {
        if (isset($_GET[$p]) && $_GET[$p] !== '') $paramStr .= '&' . $p . '=' . urlencode($_GET[$p]);
    }
    
    require_once __DIR__ . '/../components/Pagination.php';
    Pagination::render(
        $currentPage, 
        $totalPages ?? 1, 
        $paramStr, 
        'admin', 
        'dashboard', 
        count($juegos), 
        $totalJuegos, 
        t('component.juegos'), 
        'admin'
    );
    ?>
    <?php endif; ?>

</div><!-- /#admin-results -->

<!-- MODAL DE EXPORTACION -->
<div id="export-modal" class="rv-modal-overlay">
    <div class="rv-modal-box rv-modal--info rv-modal--open">
        <div class="rv-modal-header">
            <span class="rv-modal-icon"><i data-i="save"></i></span>
            <h3 class="rv-modal-title"><?= t('admin.dashboard.exportar_titulo') ?></h3>
        </div>
        <div class="rv-modal-body rv-modal-body--export">
            <p class="export-note"><?= t('admin.dashboard.exportar_instruccion') ?></p>
            <select id="export-table" class="admin-filter-select export-select">
                <option value="juegos"><?= t('admin.dashboard.exportar_tabla_juegos') ?></option>
                <option value="consolas"><?= t('admin.dashboard.exportar_tabla_consolas') ?></option>
                <option value="categorias"><?= t('admin.dashboard.exportar_tabla_categorias') ?></option>
                <option value="usuarios"><?= t('admin.dashboard.exportar_tabla_usuarios') ?></option>
                <option value="personas"><?= t('admin.dashboard.exportar_tabla_personas') ?></option>
                <option value="roles"><?= t('admin.dashboard.exportar_tabla_roles') ?></option>
                <option value="descargas"><?= t('admin.dashboard.exportar_tabla_descargas') ?></option>
            </select>
        </div>
        <div class="rv-modal-footer">
            <button class="rv-btn rv-btn-cancel" id="btn-export-cancel"><?= t('admin.dashboard.cancelar') ?></button>
            <button class="rv-btn rv-btn-ok rv-btn--success" id="btn-export-confirm"><?= t('admin.dashboard.descargar') ?></button>
        </div>
    </div>
</div>

<script type="application/json" id="rv-data-admin-dashboard"><?= json_encode(
    [
        't' => [
            'admin.juego.error_cargar_resultados' => t('admin.juego.error_cargar_resultados'),
            'admin.juego.confirmar_activar_titulo' => t('admin.juego.confirmar_activar_titulo'),
            'admin.juego.confirmar_desactivar_titulo' => t('admin.juego.confirmar_desactivar_titulo'),
            'admin.juego.confirmar_activar_mensaje' => t('admin.juego.confirmar_activar_mensaje', ['nombre' => '{n}']),
            'admin.juego.confirmar_desactivar_mensaje' => t('admin.juego.confirmar_desactivar_mensaje', ['nombre' => '{n}']),
            'admin.juego.confirmar_btn_activar' => t('admin.juego.confirmar_btn_activar'),
            'admin.juego.confirmar_btn_desactivar' => t('admin.juego.confirmar_btn_desactivar'),
            'admin.juego.cancelar' => t('admin.juego.cancelar'),
            'admin.juego.error_desconocido' => t('admin.juego.error_desconocido'),
            'admin.juego.error_cambiar_estado' => t('admin.juego.error_cambiar_estado'),
            'admin.juego.toast_activado' => t('admin.juego.toast_activado', ['nombre' => '{n}']),
            'admin.juego.toast_desactivado' => t('admin.juego.toast_desactivado', ['nombre' => '{n}']),
            'admin.juego.desactivar' => t('admin.juego.desactivar'),
            'admin.juego.activar' => t('admin.juego.activar'),
        ],
    ],
    JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
) ?></script>

<script src="public/js/views/admin/dashboard.js" defer></script>

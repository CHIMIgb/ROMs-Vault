<!-- views/admin/categorias/index.php -->

<?php
require_once __DIR__ . '/../../../config/IntlHelper.php';
?>

<!-- ESTADÍSTICAS -->
<div class="admin-stats-grid">
    <div class="stat-card">
        <div class="stat-label"><?= t('admin.categoria.total') ?></div>
        <div class="stat-value"><?= IntlHelper::formatNumber($total) ?></div>
    </div>
    <div class="stat-card">
        <div class="stat-label"><?= t('admin.categoria.activas') ?></div>
        <div class="stat-value stat-value--ok"><?= IntlHelper::formatNumber($totalActivas) ?></div>
    </div>
    <div class="stat-card">
        <div class="stat-label"><?= t('admin.categoria.inactivas') ?></div>
        <div class="stat-value stat-value--muted"><?= IntlHelper::formatNumber($total - $totalActivas) ?></div>
    </div>
</div>

<!-- CABECERA -->
<div class="admin-header">
    <h2><?= t('admin.categoria.titulo') ?></h2>
    <div class="admin-header-actions">
        <a href="/admin/dashboard" class="btn-primary">
            <i data-i="arrow-left"></i> <?= t('admin.categoria.dashboard') ?>
        </a>
        <a href="/categoria/add" class="btn-primary">
            <i data-i="plus"></i> <?= t('admin.categoria.nueva') ?>
        </a>
    </div>
</div>

<!-- Alertas de estado -->
<?php 
require_once __DIR__ . '/../../components/Alert.php';
if (isset($_GET['created'])): 
    Alert::render('success', t('admin.categoria.creada'), 'check', 'margin-bottom:1.25rem;');
elseif (isset($_GET['updated'])): 
    Alert::render('success', t('admin.categoria.actualizada'), 'check', 'margin-bottom:1.25rem;');
elseif (isset($_GET['deleted'])): 
    Alert::render('success', t('admin.categoria.eliminada'), 'check', 'margin-bottom:1.25rem;');
elseif (isset($_GET['error']) && $_GET['error'] === 'has_games'): 
    $count = (int)($_GET['count'] ?? 0);
    Alert::render('danger', t('admin.categoria.error_has_games', ['count' => $count]), 'alert-triangle', 'margin-bottom:1.25rem;');
endif; 
?>

<!-- FILTROS — mismo patrón exacto que el dashboard -->
<div class="search-section">
    <div class="admin-filter-form">
        <div class="search-wrapper">
            <input type="text"
                   id="cat-busqueda"
                   placeholder="<?= t('admin.categoria.buscar_placeholder') ?>"
                   value="<?= htmlspecialchars($_GET['busqueda'] ?? '') ?>"
                   class="search-input"
                   autocomplete="off">
            <button type="button" class="search-button"
                    onclick="document.getElementById('cat-busqueda').dispatchEvent(new Event('input'))">
                <i data-i="search" aria-hidden="true"></i>
            </button>
        </div>

        <select id="cat-activo" class="admin-filter-select">
            <option value=""  <?= (!isset($_GET['activo']) || $_GET['activo'] === '') ? 'selected' : '' ?>><?= t('admin.categoria.todos_estados') ?></option>
            <option value="1" <?= (($_GET['activo'] ?? '') === '1') ? 'selected' : '' ?>><?= t('admin.categoria.solo_activas') ?></option>
            <option value="0" <?= (($_GET['activo'] ?? '') === '0') ? 'selected' : '' ?>><?= t('admin.categoria.solo_inactivas') ?></option>
        </select>

        <button type="button" id="cat-limpiar" class="clear-filters clear-filters--nowrap">
            <i data-i="close" aria-hidden="true"></i> <?= t('admin.categoria.limpiar') ?>
        </button>
    </div>

    <div id="cat-loading" class="filter-loading">
        <span class="filter-loading-bar"></span>
        <span class="filter-loading-text"><?= t('admin.categoria.buscando') ?></span>
    </div>
</div>

<!-- ZONA DE RESULTADOS — mismo patrón que #admin-results -->
<div id="categorias-results">

    <?php if (empty($categorias)): ?>
        <?php 
        require_once __DIR__ . '/../../components/Alert.php';
        Alert::render('info', t('admin.categoria.sin_resultados'), 'info', 'text-align:center;padding:3rem;justify-content:center;');
        ?>
    <?php else: ?>
    <div class="admin-table-wrap">
        <table class="admin-table">
            <thead>
                <tr>
                    <th><?= t('admin.categoria.id') ?></th>
                    <th><?= t('admin.categoria.nombre') ?></th>
                    <th><?= t('admin.categoria.descripcion') ?></th>
                    <th><?= t('admin.categoria.creada_col') ?></th>
                    <th><?= t('admin.categoria.estado') ?></th>
                    <th><?= t('admin.categoria.acciones') ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($categorias as $cat): ?>
                <tr>
                    <td>#<?= $cat['id'] ?></td>
                    <td><strong><?= htmlspecialchars($cat['nombre']) ?></strong></td>
                    <td class="cell-ellipsis cell-ellipsis--wide">
                        <?= htmlspecialchars($cat['descripcion'] ?? '—') ?>
                    </td>
                    <td><?= htmlspecialchars(IntlHelper::formatDate($cat['created_at'], IntlHelper::MEDIUM)) ?></td>
                    <td>
                        <!-- btn-toggle-active con data-titulo, exactamente igual que el dashboard -->
                        <button class="btn-toggle-active <?= $cat['activo'] ? 'btn-toggle--on' : 'btn-toggle--off' ?>"
                                data-id="<?= $cat['id'] ?>"
                                data-titulo="<?= htmlspecialchars($cat['nombre'], ENT_QUOTES) ?>"
                                data-accion="<?= $cat['activo'] ? 'desactivar' : 'activar' ?>">
                            <?= $cat['activo'] ? t('admin.categoria.desactivar') : t('admin.categoria.activar') ?>
                        </button>
                    </td>
                    <td>
                        <!-- Solo btn-edit, exactamente igual que el dashboard -->
                        <a href="/categoria/edit/<?= $cat['id'] ?>" class="btn-edit"><?= t('admin.categoria.editar') ?></a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <!-- PAGINACIÓN -->
    <?php
    $paramStr = '';
    foreach (['busqueda', 'activo'] as $p) {
        if (isset($_GET[$p]) && $_GET[$p] !== '') $paramStr .= '&' . $p . '=' . urlencode($_GET[$p]);
    }
    
    require_once __DIR__ . '/../../components/Pagination.php';
    Pagination::render(
        $currentPage, 
        $totalPages ?? 1, 
        $paramStr, 
        'categoria', 
        'index', 
        count($categorias), 
        $total, 
        t('component.categorias'), 
        'cat'
    );
    ?>
<?php endif; ?>

</div><!-- /#categorias-results -->

<script type="application/json" id="rv-data-admin-categorias-index"><?= json_encode(
    [
        't' => [
            'admin.categoria.confirm_activar_titulo' => t('admin.categoria.confirm_activar_titulo'),
            'admin.categoria.confirm_desactivar_titulo' => t('admin.categoria.confirm_desactivar_titulo'),
            'admin.categoria.confirm_estado_activar' => t('admin.categoria.confirm_estado_activar', ['nombre' => '{n}']),
            'admin.categoria.confirm_estado_desactivar' => t('admin.categoria.confirm_estado_desactivar', ['nombre' => '{n}']),
            'admin.categoria.si_activar' => t('admin.categoria.si_activar'),
            'admin.categoria.si_desactivar' => t('admin.categoria.si_desactivar'),
            'admin.categoria.cancelar' => t('admin.categoria.cancelar'),
            'admin.categoria.error_desconocido' => t('admin.categoria.error_desconocido'),
            'admin.categoria.error_estado' => t('admin.categoria.error_estado'),
            'admin.categoria.toast_activada' => t('admin.categoria.toast_activada', ['nombre' => '{n}']),
            'admin.categoria.toast_desactivada' => t('admin.categoria.toast_desactivada', ['nombre' => '{n}']),
            'admin.categoria.desactivar' => t('admin.categoria.desactivar'),
            'admin.categoria.activar' => t('admin.categoria.activar'),
        ],
    ],
    JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
) ?></script>

<script src="public/js/views/admin/categorias-index.js" defer></script>

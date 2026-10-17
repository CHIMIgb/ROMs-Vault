<!-- views/admin/consolas/index.php -->

<?php
require_once __DIR__ . '/../../../config/IntlHelper.php';
?>

<!-- ESTADÍSTICAS -->
<div class="admin-stats-grid">
    <div class="stat-card">
        <div class="stat-label"><?= t('admin.consola.total') ?></div>
        <div class="stat-value" style="color:var(--slate);"><?= IntlHelper::formatNumber($total) ?></div>
    </div>
    <div class="stat-card">
        <div class="stat-label"><?= t('admin.consola.activas') ?></div>
        <div class="stat-value" style="color:var(--green-ok);"><?= IntlHelper::formatNumber($totalActivas) ?></div>
    </div>
    <div class="stat-card">
        <div class="stat-label"><?= t('admin.consola.inactivas') ?></div>
        <div class="stat-value" style="color:var(--slate-light);"><?= IntlHelper::formatNumber($total - $totalActivas) ?></div>
    </div>
</div>

<!-- CABECERA -->
<div class="admin-header">
    <h2><?= t('admin.consola.titulo') ?></h2>
    <div class="admin-header-actions">
        <a href="/admin/dashboard" class="btn-primary">
            <i data-i="arrow-left"></i> <?= t('admin.consola.dashboard') ?>
        </a>
        <a href="/consola/add" class="btn-primary">
            <i data-i="plus"></i> <?= t('admin.consola.nueva') ?>
        </a>
    </div>
</div>

<!-- Alertas de estado -->
<?php 
require_once __DIR__ . '/../../components/Alert.php';
if (isset($_GET['created'])): 
    Alert::render('success', t('admin.consola.creada'), 'check', 'margin-bottom:1.25rem;');
elseif (isset($_GET['updated'])): 
    Alert::render('success', t('admin.consola.actualizada'), 'check', 'margin-bottom:1.25rem;');
elseif (isset($_GET['deleted'])): 
    Alert::render('success', t('admin.consola.eliminada'), 'check', 'margin-bottom:1.25rem;');
elseif (isset($_GET['error']) && $_GET['error'] === 'has_games'): 
    $count = (int)($_GET['count'] ?? 0);
    Alert::render('danger', t('admin.consola.error_has_games', ['count' => $count]), 'alert-triangle', 'margin-bottom:1.25rem;');
endif; 
?>

<!-- FILTROS — mismo patrón exacto que el dashboard -->
<div class="search-section">
    <div class="admin-filter-form">
        <div class="search-wrapper" style="flex:2;min-width:180px;">
            <input type="text"
                   id="cs-busqueda"
                   placeholder="<?= t('admin.consola.buscar_placeholder') ?>"
                   value="<?= htmlspecialchars($_GET['busqueda'] ?? '') ?>"
                   class="search-input"
                   autocomplete="off">
            <button type="button" class="search-button"
                    onclick="document.getElementById('cs-busqueda').dispatchEvent(new Event('input'))">
                <i data-i="search" aria-hidden="true"></i>
            </button>
        </div>

        <select id="cs-activo" class="admin-filter-select">
            <option value=""  <?= (!isset($_GET['activo']) || $_GET['activo'] === '') ? 'selected' : '' ?>><?= t('admin.consola.todos_estados') ?></option>
            <option value="1" <?= (($_GET['activo'] ?? '') === '1') ? 'selected' : '' ?>><?= t('admin.consola.solo_activas') ?></option>
            <option value="0" <?= (($_GET['activo'] ?? '') === '0') ? 'selected' : '' ?>><?= t('admin.consola.solo_inactivas') ?></option>
        </select>

        <button type="button" id="cs-limpiar" class="clear-filters" style="display:none;white-space:nowrap;">
            <i data-i="close" aria-hidden="true"></i> <?= t('admin.consola.limpiar') ?>
        </button>
    </div>

    <div id="cs-loading" class="filter-loading" style="display:none;">
        <span class="filter-loading-bar"></span>
        <span class="filter-loading-text"><?= t('admin.consola.buscando') ?></span>
    </div>
</div>

<!-- ZONA DE RESULTADOS — mismo patrón que #admin-results -->
<div id="consolas-results">

    <?php if (empty($consolas)): ?>
        <?php 
        require_once __DIR__ . '/../../components/Alert.php';
        Alert::render('info', t('admin.consola.sin_resultados'), 'info', 'text-align:center;padding:3rem;justify-content:center;');
        ?>
    <?php else: ?>
    <div style="overflow-x:auto;">
        <table class="admin-table">
            <thead>
                <tr>
                    <th><?= t('admin.consola.id') ?></th>
                    <th><?= t('admin.consola.nombre') ?></th>
                    <th><?= t('admin.consola.fabricante') ?></th>
                    <th><?= t('admin.consola.descripcion') ?></th>
                    <th><?= t('admin.consola.creada_col') ?></th>
                    <th><?= t('admin.consola.estado') ?></th>
                    <th><?= t('admin.consola.emulacion') ?></th>
                    <th><?= t('admin.consola.acciones') ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($consolas as $c): ?>
                <tr>
                    <td>#<?= $c['id'] ?></td>
                    <td><strong><?= htmlspecialchars($c['nombre']) ?></strong></td>
                    <td><?= htmlspecialchars($c['fabricante'] ?? '—') ?></td>
                    <td style="max-width:220px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;color:var(--slate-mid);font-size:.88rem;">
                        <?= htmlspecialchars($c['descripcion'] ?? '—') ?>
                    </td>
                    <td><?= htmlspecialchars(IntlHelper::formatDate($c['created_at'], IntlHelper::MEDIUM)) ?></td>
                    <td>
                        <!-- btn-toggle-active con data-titulo, exactamente igual que el dashboard -->
                        <button class="btn-toggle-active <?= $c['activo'] ? 'btn-toggle--on' : 'btn-toggle--off' ?>"
                                data-id="<?= $c['id'] ?>"
                                data-titulo="<?= htmlspecialchars($c['nombre'], ENT_QUOTES) ?>"
                                data-accion="<?= $c['activo'] ? 'desactivar' : 'activar' ?>">
                            <?= $c['activo'] ? t('admin.consola.desactivar') : t('admin.consola.activar') ?>
                        </button>
                    </td>
                    <td>
                        <!-- Emulación online (EmulatorJS) — toggle independiente del estado activo -->
                        <button class="btn-toggle-active btn-toggle-emulacion <?= $c['emulacion_online'] ? 'btn-toggle--on' : 'btn-toggle--off' ?>"
                                data-id="<?= $c['id'] ?>"
                                data-titulo="<?= htmlspecialchars($c['nombre'], ENT_QUOTES) ?>"
                                data-accion="<?= $c['emulacion_online'] ? 'desactivar' : 'activar' ?>">
                            <?= $c['emulacion_online'] ? t('admin.consola.desactivar') : t('admin.consola.activar') ?>
                        </button>
                    </td>
                    <td>
                        <!-- Solo btn-edit, exactamente igual que el dashboard -->
                        <a href="/consola/edit/<?= $c['id'] ?>" class="btn-edit"><?= t('admin.consola.editar') ?></a>
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
        'consola', 
        'index', 
        count($consolas), 
        $total, 
        t('component.consolas'), 
        'cs'
    );
    ?>
<?php endif; ?>

</div><!-- /#consolas-results -->

<script type="application/json" id="rv-data-admin-consolas-index"><?= json_encode(
    [
        't' => [
            'admin.consola.confirm_activar_titulo' => t('admin.consola.confirm_activar_titulo'),
            'admin.consola.confirm_desactivar_titulo' => t('admin.consola.confirm_desactivar_titulo'),
            'admin.consola.confirm_estado_activar' => t('admin.consola.confirm_estado_activar', ['nombre' => '{n}']),
            'admin.consola.confirm_estado_desactivar' => t('admin.consola.confirm_estado_desactivar', ['nombre' => '{n}']),
            'admin.consola.confirm_emulacion_activar_titulo' => t('admin.consola.confirm_emulacion_activar_titulo'),
            'admin.consola.confirm_emulacion_desactivar_titulo' => t('admin.consola.confirm_emulacion_desactivar_titulo'),
            'admin.consola.confirm_emulacion_activar' => t('admin.consola.confirm_emulacion_activar', ['nombre' => '{n}']),
            'admin.consola.confirm_emulacion_desactivar' => t('admin.consola.confirm_emulacion_desactivar', ['nombre' => '{n}']),
            'admin.consola.si_activar' => t('admin.consola.si_activar'),
            'admin.consola.si_desactivar' => t('admin.consola.si_desactivar'),
            'admin.consola.cancelar' => t('admin.consola.cancelar'),
            'admin.consola.error_desconocido' => t('admin.consola.error_desconocido'),
            'admin.consola.error_estado' => t('admin.consola.error_estado'),
            'admin.consola.toast_activada' => t('admin.consola.toast_activada', ['nombre' => '{n}']),
            'admin.consola.toast_desactivada' => t('admin.consola.toast_desactivada', ['nombre' => '{n}']),
            'admin.consola.toast_emulacion_activada' => t('admin.consola.toast_emulacion_activada', ['nombre' => '{n}']),
            'admin.consola.toast_emulacion_desactivada' => t('admin.consola.toast_emulacion_desactivada', ['nombre' => '{n}']),
            'admin.consola.desactivar' => t('admin.consola.desactivar'),
            'admin.consola.activar' => t('admin.consola.activar'),
        ],
    ],
    JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
) ?></script>

<script src="public/js/views/admin/consolas-index.js" defer></script>

<!-- views/admin/emuladores/index.php -->

<?php
require_once __DIR__ . '/../../../config/IntlHelper.php';
?>

<!-- ESTADÍSTICAS — mismo patrón que consolas/categorías -->
<div class="admin-stats-grid">
    <div class="stat-card">
        <div class="stat-label"><?= t('admin.emulador.total') ?></div>
        <div class="stat-value" style="color:var(--primary);"><?= IntlHelper::formatNumber($total) ?></div>
    </div>
    <div class="stat-card">
        <div class="stat-label"><?= t('admin.emulador.activas') ?></div>
        <div class="stat-value" style="color:var(--success);"><?= IntlHelper::formatNumber($totalActivas) ?></div>
    </div>
    <div class="stat-card">
        <div class="stat-label"><?= t('admin.emulador.inactivas') ?></div>
        <div class="stat-value" style="color:var(--text-light);"><?= IntlHelper::formatNumber($total - $totalActivas) ?></div>
    </div>
</div>

<!-- CABECERA — mismo patrón que consolas/categorías -->
<div class="admin-header">
    <h2><?= t('admin.emulador.titulo') ?></h2>
    <div class="admin-header-actions">
        <a href="/admin/dashboard" class="btn-primary">
            <i data-i="arrow-left"></i> <?= t('admin.emulador.dashboard') ?>
        </a>
        <a href="/emulador/add" class="btn-primary">
            <i data-i="plus"></i> <?= t('admin.emulador.nuevo') ?>
        </a>
    </div>
</div>

<!-- Alertas de estado — mismo componente Alert que el resto del panel -->
<?php 
require_once __DIR__ . '/../../components/Alert.php';
if (isset($_GET['created'])): 
    Alert::render('success', t('admin.emulador.registrado'), 'check', 'margin-bottom:1.25rem;');
elseif (isset($_GET['updated'])): 
    Alert::render('success', t('admin.emulador.actualizados'), 'check', 'margin-bottom:1.25rem;');
endif; 
?>

<!-- FILTROS — mismo patrón exacto que consolas/categorías -->
<div class="search-section">
    <div class="admin-filter-form">
        <div class="search-wrapper" style="flex:2;min-width:180px;">
            <input type="text"
                   id="em-busqueda"
                   placeholder="<?= t('admin.emulador.buscar_placeholder') ?>"
                   value="<?= htmlspecialchars($_GET['busqueda'] ?? '') ?>"
                   class="search-input"
                   autocomplete="off">
            <button type="button" class="search-button"
                    onclick="document.getElementById('em-busqueda').dispatchEvent(new Event('input'))">
                <i data-i="search" aria-hidden="true"></i>
            </button>
        </div>

        <select id="em-activo" class="admin-filter-select">
            <option value=""  <?= (!isset($_GET['activo']) || $_GET['activo'] === '') ? 'selected' : '' ?>><?= t('admin.emulador.todos_estados') ?></option>
            <option value="1" <?= (($_GET['activo'] ?? '') === '1') ? 'selected' : '' ?>><?= t('admin.emulador.solo_activos') ?></option>
            <option value="0" <?= (($_GET['activo'] ?? '') === '0') ? 'selected' : '' ?>><?= t('admin.emulador.solo_inactivos') ?></option>
        </select>

        <button type="button" id="em-limpiar" class="clear-filters" style="display:none;white-space:nowrap;">
            <i data-i="close" aria-hidden="true"></i> <?= t('admin.emulador.limpiar') ?>
        </button>
    </div>

    <div id="em-loading" class="filter-loading" style="display:none;">
        <span class="filter-loading-bar"></span>
        <span class="filter-loading-text"><?= t('admin.emulador.buscando') ?></span>
    </div>
</div>

<!-- ZONA DE RESULTADOS — misma tabla admin-table que el resto del panel -->
<div id="emuladores-results">

    <?php if (empty($filas)): ?>
        <?php 
        require_once __DIR__ . '/../../components/Alert.php';
        Alert::render('info', t('admin.emulador.sin_resultados'), 'info', 'text-align:center;padding:3rem;justify-content:center;');
        ?>
    <?php else: ?>
    <div style="overflow-x:auto;">
        <table class="admin-table">
            <thead>
                <tr>
                    <th><?= t('admin.emulador.id') ?></th>
                    <th><?= t('admin.emulador.consola') ?></th>
                    <th><?= t('admin.emulador.principal') ?></th>
                    <th><?= t('admin.emulador.alternativo') ?></th>
                    <th><?= t('admin.emulador.estado') ?></th>
                    <th><?= t('admin.emulador.acciones') ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($filas as $f): ?>
                <tr>
                    <td>#<?= $f['id'] ?></td>
                    <td><strong><?= htmlspecialchars($f['consola_nombre']) ?></strong></td>
                    <td>
                        <?php if ($f['principal']): ?>
                            <strong><?= htmlspecialchars($f['principal']['nombre']) ?></strong>
                            <div style="color:var(--slate-mid);font-size:.88rem;margin-top:.25rem;">
                                <?= htmlspecialchars(implode(' / ', $f['principal']['plataformas'])) ?>
                                ·
                                <a href="<?= htmlspecialchars($f['principal']['url']) ?>" target="_blank" rel="noopener noreferrer" style="text-decoration:underline;"><?= t('admin.emulador.sitio_oficial') ?></a>
                            </div>
                        <?php else: ?>
                            <span style="color:var(--text-light);">—</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if ($f['alterno']): ?>
                            <strong><?= htmlspecialchars($f['alterno']['nombre']) ?></strong>
                            <div style="color:var(--slate-mid);font-size:.88rem;margin-top:.25rem;">
                                <?= htmlspecialchars(implode(' / ', $f['alterno']['plataformas'])) ?>
                                ·
                                <a href="<?= htmlspecialchars($f['alterno']['url']) ?>" target="_blank" rel="noopener noreferrer" style="text-decoration:underline;"><?= t('admin.emulador.sitio_oficial') ?></a>
                            </div>
                        <?php else: ?>
                            <span style="color:var(--text-light);">—</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <!-- btn-toggle-active con data-titulo, exactamente igual que el dashboard -->
                        <button class="btn-toggle-active <?= $f['activo'] ? 'btn-toggle--on' : 'btn-toggle--off' ?>"
                                data-id="<?= $f['id'] ?>"
                                data-titulo="<?= htmlspecialchars($f['consola_nombre'], ENT_QUOTES) ?>"
                                data-accion="<?= $f['activo'] ? 'desactivar' : 'activar' ?>">
                            <?= $f['activo'] ? t('admin.emulador.desactivar') : t('admin.emulador.activar') ?>
                        </button>
                    </td>
                    <td>
                        <!-- Solo btn-edit, exactamente igual que el dashboard -->
                        <a href="/emulador/edit/<?= $f['id'] ?>" class="btn-edit"><?= t('admin.emulador.editar') ?></a>
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
        'emulador', 
        'index', 
        count($filas), 
        $total, 
        t('component.consolas_con_emulador'), 
        'em'
    );
    ?>
    <?php endif; ?>

</div><!-- /#emuladores-results -->

<script type="application/json" id="rv-data-admin-emuladores-index"><?= json_encode(
    [
        't' => [
            'admin.emulador.confirm_activar_titulo' => t('admin.emulador.confirm_activar_titulo'),
            'admin.emulador.confirm_desactivar_titulo' => t('admin.emulador.confirm_desactivar_titulo'),
            'admin.emulador.confirm_estado_activar' => t('admin.emulador.confirm_estado_activar', ['nombre' => '{n}']),
            'admin.emulador.confirm_estado_desactivar' => t('admin.emulador.confirm_estado_desactivar', ['nombre' => '{n}']),
            'admin.emulador.si_activar' => t('admin.emulador.si_activar'),
            'admin.emulador.si_desactivar' => t('admin.emulador.si_desactivar'),
            'admin.emulador.cancelar' => t('admin.emulador.cancelar'),
            'admin.emulador.error_desconocido' => t('admin.emulador.error_desconocido'),
            'admin.emulador.error_estado' => t('admin.emulador.error_estado'),
            'admin.emulador.toast_activado' => t('admin.emulador.toast_activado', ['nombre' => '{n}']),
            'admin.emulador.toast_desactivado' => t('admin.emulador.toast_desactivado', ['nombre' => '{n}']),
            'admin.emulador.desactivar' => t('admin.emulador.desactivar'),
            'admin.emulador.activar' => t('admin.emulador.activar'),
        ],
    ],
    JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
) ?></script>

<script src="public/js/views/admin/emuladores-index.js" defer></script>

<!-- views/admin/categorias/index.php -->

<?php
require_once __DIR__ . '/../../../config/IntlHelper.php';
?>

<!-- ESTADÍSTICAS -->
<div class="admin-stats-grid">
    <div class="stat-card">
        <div class="stat-label"><?= t('admin.categoria.total') ?></div>
        <div class="stat-value" style="color:var(--primary);"><?= IntlHelper::formatNumber($total) ?></div>
    </div>
    <div class="stat-card">
        <div class="stat-label"><?= t('admin.categoria.activas') ?></div>
        <div class="stat-value" style="color:var(--success);"><?= IntlHelper::formatNumber($totalActivas) ?></div>
    </div>
    <div class="stat-card">
        <div class="stat-label"><?= t('admin.categoria.inactivas') ?></div>
        <div class="stat-value" style="color:var(--text-light);"><?= IntlHelper::formatNumber($total - $totalActivas) ?></div>
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
        <div class="search-wrapper" style="flex:2;min-width:180px;">
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

        <button type="button" id="cat-limpiar" class="clear-filters" style="display:none;white-space:nowrap;">
            <i data-i="close" aria-hidden="true"></i> <?= t('admin.categoria.limpiar') ?>
        </button>
    </div>

    <div id="cat-loading" class="filter-loading" style="display:none;">
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
    <div style="overflow-x:auto;">
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
                    <td style="max-width:260px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;color:var(--slate-mid);font-size:.88rem;">
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

<script>
(function () {
    'use strict';

    const csrfToken = (document.querySelector('meta[name="csrf-token"]') || {}).content || '';

    const busquedaEl = document.getElementById('cat-busqueda');
    const activoEl   = document.getElementById('cat-activo');
    const btnLimpiar = document.getElementById('cat-limpiar');
    const resultsEl  = document.getElementById('categorias-results');
    const loadingEl  = document.getElementById('cat-loading');

    const textos = {
        tituloActivar:   <?= json_encode(t('admin.categoria.confirm_activar_titulo')) ?>,
        tituloDesactivar: <?= json_encode(t('admin.categoria.confirm_desactivar_titulo')) ?>,
        msgActivar:      <?= json_encode(t('admin.categoria.confirm_estado_activar', ['nombre' => '{n}'])) ?>,
        msgDesactivar:   <?= json_encode(t('admin.categoria.confirm_estado_desactivar', ['nombre' => '{n}'])) ?>,
        okActivar:       <?= json_encode(t('admin.categoria.si_activar')) ?>,
        okDesactivar:    <?= json_encode(t('admin.categoria.si_desactivar')) ?>,
        cancelar:        <?= json_encode(t('admin.categoria.cancelar')) ?>,
        errorDesconocido: <?= json_encode(t('admin.categoria.error_desconocido')) ?>,
        errorEstado:     <?= json_encode(t('admin.categoria.error_estado')) ?>,
        toastActivada:   <?= json_encode(t('admin.categoria.toast_activada', ['nombre' => '{n}'])) ?>,
        toastDesactivada: <?= json_encode(t('admin.categoria.toast_desactivada', ['nombre' => '{n}'])) ?>,
        desactivar:      <?= json_encode(t('admin.categoria.desactivar')) ?>,
        activar:         <?= json_encode(t('admin.categoria.activar')) ?>,
    };

    let debounceTimer  = null;
    let currentRequest = null;

    function getAjaxParams(page) {
        const p = new URLSearchParams();
        p.set('page', page || 1);
        const b = busquedaEl.value.trim();
        if (b)                     p.set('busqueda', b);
        if (activoEl.value !== '') p.set('activo',   activoEl.value);
        return p;
    }

    function getUiParams(page) {
        const p = getAjaxParams(page);
        return p;
    }

    function updateLimpiar() {
        const has = busquedaEl.value.trim() || activoEl.value !== '';
        btnLimpiar.style.display = has ? 'inline-block' : 'none';
    }

    async function fetchResults(page) {
        if (currentRequest) currentRequest.abort();
        currentRequest = new AbortController();

        loadingEl.style.display    = 'flex';
        resultsEl.style.opacity    = '0.45';
        resultsEl.style.pointerEvents = 'none';

        history.replaceState(null, '', '?' + getUiParams(page).toString());

        try {
            const res = await fetch('ajax_categoria.php?' + getAjaxParams(page).toString(), {
                signal: currentRequest.signal
            });
            if (!res.ok) throw new Error('HTTP ' + res.status);
            resultsEl.innerHTML = await res.text();
            bindPagination();
        } catch (err) {
            if (err.name !== 'AbortError') {
                window.location.href = '?' + getUiParams(page).toString();
            }
        } finally {
            loadingEl.style.display    = 'none';
            resultsEl.style.opacity    = '';
            resultsEl.style.pointerEvents = '';
        }
    }

    function scheduleSearch() {
        clearTimeout(debounceTimer);
        updateLimpiar();
        debounceTimer = setTimeout(() => fetchResults(1), 380);
    }

    function bindPagination() {
        resultsEl.querySelectorAll('.pagination-link[data-page]').forEach(a => {
            a.addEventListener('click', e => {
                e.preventDefault();
                fetchResults(parseInt(a.dataset.page));
            });
        });
    }

    btnLimpiar.addEventListener('click', () => {
        busquedaEl.value = '';
        activoEl.value   = '';
        updateLimpiar();
        fetchResults(1);
    });

    busquedaEl.addEventListener('input',  scheduleSearch);
    activoEl.addEventListener('change',   () => { updateLimpiar(); fetchResults(1); });

    bindPagination();
    updateLimpiar();

    // ── Delegación de clics en botones toggle — idéntica al dashboard ─────
    document.getElementById('categorias-results').addEventListener('click', e => {
        const btn = e.target.closest('.btn-toggle-active');
        if (!btn) return;
        e.preventDefault();

        const accion = btn.dataset.accion;
        const titulo = btn.dataset.titulo;
        const id     = btn.dataset.id;

        RVAlerts.confirm({
            tipo:      accion === 'desactivar' ? 'warning' : 'success',
            titulo:    accion === 'desactivar' ? textos.tituloDesactivar : textos.tituloActivar,
            mensaje:   (accion === 'desactivar' ? textos.msgDesactivar : textos.msgActivar).replace('{n}', titulo),
            btnOk:     accion === 'desactivar' ? textos.okDesactivar : textos.okActivar,
            btnCancel: textos.cancelar,
            onOk: async () => {
                btn.disabled    = true;
                btn.textContent = '...';

                try {
                    const res  = await fetch(
                        `/categoria/toggleActiveAjax/${id}`,
                        { headers: { 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-Token': csrfToken } }
                    );
                    const data = await res.json();

                    if (!data.ok) throw new Error(data.error || textos.errorDesconocido);

                    const estaActivo     = data.activo === 1;
                    btn.textContent      = estaActivo ? textos.desactivar : textos.activar;
                    btn.dataset.accion   = estaActivo ? 'desactivar' : 'activar';
                    btn.className        = 'btn-toggle-active ' + (estaActivo ? 'btn-toggle--on' : 'btn-toggle--off');

                    RVAlerts.toast(
                        (estaActivo ? textos.toastActivada : textos.toastDesactivada).replace('{n}', data.nombre),
                        estaActivo ? 'success' : 'warning'
                    );

                } catch (err) {
                    RVAlerts.toast(textos.errorEstado, 'danger');
                    btn.disabled    = false;
                    btn.textContent = accion === 'desactivar' ? textos.desactivar : textos.activar;
                }

                btn.disabled = false;
            }
        });
    });

})();
</script>

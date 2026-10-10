<!-- views/admin/emuladores/index.php -->

<?php
require_once __DIR__ . '/../../config/IntlHelper.php';
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

<script>
(function () {
    'use strict';

    const csrfToken = (document.querySelector('meta[name="csrf-token"]') || {}).content || '';

    const busquedaEl = document.getElementById('em-busqueda');
    const activoEl   = document.getElementById('em-activo');
    const btnLimpiar = document.getElementById('em-limpiar');
    const resultsEl  = document.getElementById('emuladores-results');
    const loadingEl  = document.getElementById('em-loading');

    const textos = {
        tituloActivar:    <?= json_encode(t('admin.emulador.confirm_activar_titulo')) ?>,
        tituloDesactivar: <?= json_encode(t('admin.emulador.confirm_desactivar_titulo')) ?>,
        msgActivar:       <?= json_encode(t('admin.emulador.confirm_estado_activar', ['nombre' => '{n}'])) ?>,
        msgDesactivar:    <?= json_encode(t('admin.emulador.confirm_estado_desactivar', ['nombre' => '{n}'])) ?>,
        okActivar:        <?= json_encode(t('admin.emulador.si_activar')) ?>,
        okDesactivar:     <?= json_encode(t('admin.emulador.si_desactivar')) ?>,
        cancelar:         <?= json_encode(t('admin.emulador.cancelar')) ?>,
        errorDesconocido: <?= json_encode(t('admin.emulador.error_desconocido')) ?>,
        errorEstado:      <?= json_encode(t('admin.emulador.error_estado')) ?>,
        toastActivado:    <?= json_encode(t('admin.emulador.toast_activado', ['nombre' => '{n}'])) ?>,
        toastDesactivado: <?= json_encode(t('admin.emulador.toast_desactivado', ['nombre' => '{n}'])) ?>,
        desactivar:       <?= json_encode(t('admin.emulador.desactivar')) ?>,
        activar:          <?= json_encode(t('admin.emulador.activar')) ?>,
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
            const res = await fetch('ajax_emulador.php?' + getAjaxParams(page).toString(), {
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
    document.getElementById('emuladores-results').addEventListener('click', e => {
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
                        `/emulador/toggleActiveAjax/${id}`,
                        { headers: { 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-Token': csrfToken } }
                    );
                    const data = await res.json();

                    if (!data.ok) throw new Error(data.error || textos.errorDesconocido);

                    const estaActivo     = data.activo === 1;
                    btn.textContent      = estaActivo ? textos.desactivar : textos.activar;
                    btn.dataset.accion   = estaActivo ? 'desactivar' : 'activar';
                    btn.className        = 'btn-toggle-active ' + (estaActivo ? 'btn-toggle--on' : 'btn-toggle--off');

                    RVAlerts.toast(
                        (estaActivo ? textos.toastActivado : textos.toastDesactivado).replace('{n}', data.nombre),
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

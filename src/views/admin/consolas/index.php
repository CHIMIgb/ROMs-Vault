<!-- views/admin/consolas/index.php -->

<!-- ESTADÍSTICAS -->
<div class="admin-stats-grid">
    <div class="stat-card">
        <div class="stat-label"><?= t('admin.consola.total') ?></div>
        <div class="stat-value" style="color:var(--primary);"><?= number_format($total) ?></div>
    </div>
    <div class="stat-card">
        <div class="stat-label"><?= t('admin.consola.activas') ?></div>
        <div class="stat-value" style="color:var(--success);"><?= number_format($totalActivas) ?></div>
    </div>
    <div class="stat-card">
        <div class="stat-label"><?= t('admin.consola.inactivas') ?></div>
        <div class="stat-value" style="color:var(--text-light);"><?= number_format($total - $totalActivas) ?></div>
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
                    <td><?= date('d/m/Y', strtotime($c['created_at'])) ?></td>
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

<script>
(function () {
    'use strict';

    const csrfToken = (document.querySelector('meta[name="csrf-token"]') || {}).content || '';

    const busquedaEl = document.getElementById('cs-busqueda');
    const activoEl   = document.getElementById('cs-activo');
    const btnLimpiar = document.getElementById('cs-limpiar');
    const resultsEl  = document.getElementById('consolas-results');
    const loadingEl  = document.getElementById('cs-loading');

    const textos = {
        tituloActivar:    <?= json_encode(t('admin.consola.confirm_activar_titulo')) ?>,
        tituloDesactivar: <?= json_encode(t('admin.consola.confirm_desactivar_titulo')) ?>,
        msgActivar:       <?= json_encode(t('admin.consola.confirm_estado_activar', ['nombre' => '{n}'])) ?>,
        msgDesactivar:    <?= json_encode(t('admin.consola.confirm_estado_desactivar', ['nombre' => '{n}'])) ?>,
        tituloEmuActivar:    <?= json_encode(t('admin.consola.confirm_emulacion_activar_titulo')) ?>,
        tituloEmuDesactivar: <?= json_encode(t('admin.consola.confirm_emulacion_desactivar_titulo')) ?>,
        msgEmuActivar:       <?= json_encode(t('admin.consola.confirm_emulacion_activar', ['nombre' => '{n}'])) ?>,
        msgEmuDesactivar:    <?= json_encode(t('admin.consola.confirm_emulacion_desactivar', ['nombre' => '{n}'])) ?>,
        okActivar:        <?= json_encode(t('admin.consola.si_activar')) ?>,
        okDesactivar:     <?= json_encode(t('admin.consola.si_desactivar')) ?>,
        cancelar:         <?= json_encode(t('admin.consola.cancelar')) ?>,
        errorDesconocido: <?= json_encode(t('admin.consola.error_desconocido')) ?>,
        errorEstado:      <?= json_encode(t('admin.consola.error_estado')) ?>,
        toastActivada:    <?= json_encode(t('admin.consola.toast_activada', ['nombre' => '{n}'])) ?>,
        toastDesactivada: <?= json_encode(t('admin.consola.toast_desactivada', ['nombre' => '{n}'])) ?>,
        toastEmuActivada:    <?= json_encode(t('admin.consola.toast_emulacion_activada', ['nombre' => '{n}'])) ?>,
        toastEmuDesactivada: <?= json_encode(t('admin.consola.toast_emulacion_desactivada', ['nombre' => '{n}'])) ?>,
        desactivar:       <?= json_encode(t('admin.consola.desactivar')) ?>,
        activar:          <?= json_encode(t('admin.consola.activar')) ?>,
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
            const res = await fetch('ajax_consola.php?' + getAjaxParams(page).toString(), {
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
    document.getElementById('consolas-results').addEventListener('click', e => {
        const btn = e.target.closest('.btn-toggle-active');
        // El toggle de emulación online se gestiona en su propia delegación
        if (!btn || btn.classList.contains('btn-toggle-emulacion')) return;
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
                        `/consola/toggleActiveAjax/${id}`,
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

    // ── Toggle de emulación online (botón .btn-toggle-emulacion) ──────────
    document.getElementById('consolas-results').addEventListener('click', e => {
        const btn = e.target.closest('.btn-toggle-emulacion');
        if (!btn) return;
        e.preventDefault();

        const accion = btn.dataset.accion;   // 'activar' | 'desactivar'
        const titulo = btn.dataset.titulo;
        const id     = btn.dataset.id;

        RVAlerts.confirm({
            tipo:    accion === 'desactivar' ? 'warning' : 'success',
            titulo:  accion === 'desactivar' ? textos.tituloEmuDesactivar : textos.tituloEmuActivar,
            mensaje: (accion === 'desactivar' ? textos.msgEmuDesactivar : textos.msgEmuActivar).replace('{n}', titulo),
            btnOk:     accion === 'desactivar' ? textos.okDesactivar : textos.okActivar,
            btnCancel: textos.cancelar,
            onOk: async () => {
                btn.disabled    = true;
                btn.textContent = '...';

                try {
                    const res  = await fetch(
                        `/consola/toggleEmulacionAjax/${id}`,
                        { headers: { 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-Token': csrfToken } }
                    );
                    const data = await res.json();

                    if (!data.ok) throw new Error(data.error || textos.errorDesconocido);

                    const activada = data.emulacion_online === 1;
                    btn.textContent    = activada ? textos.desactivar : textos.activar;
                    btn.dataset.accion = activada ? 'desactivar' : 'activar';
                    btn.className      = 'btn-toggle-active btn-toggle-emulacion ' + (activada ? 'btn-toggle--on' : 'btn-toggle--off');

                    RVAlerts.toast(
                        (activada ? textos.toastEmuActivada : textos.toastEmuDesactivada).replace('{n}', data.nombre),
                        activada ? 'success' : 'warning'
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

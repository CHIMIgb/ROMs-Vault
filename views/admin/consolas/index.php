<!-- views/admin/consolas/index.php -->

<!-- ESTADÍSTICAS -->
<div class="admin-stats-grid">
    <div class="stat-card">
        <div class="stat-label">Total consolas</div>
        <div class="stat-value" style="color:var(--primary);"><?= number_format($total) ?></div>
    </div>
    <div class="stat-card">
        <div class="stat-label">Activas</div>
        <div class="stat-value" style="color:var(--success);"><?= number_format($totalActivas) ?></div>
    </div>
    <div class="stat-card">
        <div class="stat-label">Inactivas</div>
        <div class="stat-value" style="color:var(--text-light);"><?= number_format($total - $totalActivas) ?></div>
    </div>
</div>

<!-- CABECERA -->
<div class="admin-header">
    <h2>Consolas</h2>
    <div class="admin-header-actions">
        <a href="index.php?controller=admin&action=dashboard" class="btn-primary">
            <i data-i="arrow-left"></i> Dashboard
        </a>
        <a href="index.php?controller=consola&action=add" class="btn-primary">
            <i data-i="plus"></i> Nueva Consola
        </a>
    </div>
</div>

<!-- Alertas de estado -->
<?php 
require_once 'views/components/Alert.php';
if (isset($_GET['created'])): 
    Alert::render('success', 'Consola creada correctamente.', 'check', 'margin-bottom:1.25rem;');
elseif (isset($_GET['updated'])): 
    Alert::render('success', 'Consola actualizada correctamente.', 'check', 'margin-bottom:1.25rem;');
elseif (isset($_GET['deleted'])): 
    Alert::render('success', 'Consola eliminada correctamente.', 'check', 'margin-bottom:1.25rem;');
elseif (isset($_GET['error']) && $_GET['error'] === 'has_games'): 
    $count = (int)($_GET['count'] ?? 0);
    Alert::render('danger', "No se puede eliminar: esta consola tiene {$count} juego(s) asociado(s). Reasígnalos o elimínalos primero.", 'alert-triangle', 'margin-bottom:1.25rem;');
endif; 
?>

<!-- FILTROS — mismo patrón exacto que el dashboard -->
<div class="search-section">
    <div class="admin-filter-form">
        <div class="search-wrapper" style="flex:2;min-width:180px;">
            <input type="text"
                   id="cs-busqueda"
                   placeholder="Buscar por nombre..."
                   value="<?= htmlspecialchars($_GET['busqueda'] ?? '') ?>"
                   class="search-input"
                   autocomplete="off">
            <button type="button" class="search-button"
                    onclick="document.getElementById('cs-busqueda').dispatchEvent(new Event('input'))">
                <i data-i="search" aria-hidden="true"></i>
            </button>
        </div>

        <select id="cs-activo" class="admin-filter-select">
            <option value=""  <?= (!isset($_GET['activo']) || $_GET['activo'] === '') ? 'selected' : '' ?>>Todos los estados</option>
            <option value="1" <?= (($_GET['activo'] ?? '') === '1') ? 'selected' : '' ?>>Solo activas</option>
            <option value="0" <?= (($_GET['activo'] ?? '') === '0') ? 'selected' : '' ?>>Solo inactivas</option>
        </select>

        <button type="button" id="cs-limpiar" class="clear-filters" style="display:none;white-space:nowrap;">
            <i data-i="close" aria-hidden="true"></i> Limpiar
        </button>
    </div>

    <div id="cs-loading" class="filter-loading" style="display:none;">
        <span class="filter-loading-bar"></span>
        <span class="filter-loading-text">Buscando...</span>
    </div>
</div>

<!-- ZONA DE RESULTADOS — mismo patrón que #admin-results -->
<div id="consolas-results">

    <?php if (empty($consolas)): ?>
        <?php 
        require_once 'views/components/Alert.php';
        Alert::render('info', 'No hay consolas que coincidan con los filtros.', 'info', 'text-align:center;padding:3rem;justify-content:center;');
        ?>
    <?php else: ?>
    <div style="overflow-x:auto;">
        <table class="admin-table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Nombre</th>
                    <th>Fabricante</th>
                    <th>Descripción</th>
                    <th>Creada</th>
                    <th>Estado</th>
                    <th>Acciones</th>
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
                            <?= $c['activo'] ? 'Desactivar' : 'Activar' ?>
                        </button>
                    </td>
                    <td>
                        <!-- Solo btn-edit, exactamente igual que el dashboard -->
                        <a href="index.php?controller=consola&action=edit&id=<?= $c['id'] ?>" class="btn-edit">Editar</a>
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
    
    require_once 'views/components/Pagination.php';
    Pagination::render(
        $currentPage, 
        $totalPages ?? 1, 
        $paramStr, 
        'consola', 
        'index', 
        count($consolas), 
        $total, 
        'consolas', 
        'cs'
    );
    ?>
    <?php endif; ?>

</div><!-- /#consolas-results -->

<script>
(function () {
    'use strict';

    const busquedaEl = document.getElementById('cs-busqueda');
    const activoEl   = document.getElementById('cs-activo');
    const btnLimpiar = document.getElementById('cs-limpiar');
    const resultsEl  = document.getElementById('consolas-results');
    const loadingEl  = document.getElementById('cs-loading');

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
        p.set('controller', 'consola');
        p.set('action', 'index');
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
        if (!btn) return;
        e.preventDefault();

        const accion = btn.dataset.accion;
        const titulo = btn.dataset.titulo;
        const id     = btn.dataset.id;

        RVAlerts.confirm({
            tipo:      accion === 'desactivar' ? 'warning' : 'success',
            titulo:    accion === 'desactivar' ? '¿Desactivar consola?' : '¿Activar consola?',
            mensaje:   `<strong>${titulo}</strong> quedará ${accion === 'desactivar'
                            ? 'oculta en los filtros del catálogo'
                            : 'visible en los filtros del catálogo'}.`,
            btnOk:     accion === 'desactivar' ? 'Sí, desactivar' : 'Sí, activar',
            btnCancel: 'Cancelar',
            onOk: async () => {
                btn.disabled    = true;
                btn.textContent = '...';

                try {
                    const res  = await fetch(
                        `index.php?controller=consola&action=toggleActiveAjax&id=${id}`,
                        { headers: { 'X-Requested-With': 'XMLHttpRequest' } }
                    );
                    const data = await res.json();

                    if (!data.ok) throw new Error(data.error || 'Error desconocido');

                    const estaActivo     = data.activo === 1;
                    btn.textContent      = estaActivo ? 'Desactivar' : 'Activar';
                    btn.dataset.accion   = estaActivo ? 'desactivar' : 'activar';
                    btn.className        = 'btn-toggle-active ' + (estaActivo ? 'btn-toggle--on' : 'btn-toggle--off');

                    RVAlerts.toast(
                        `"${data.nombre}" ${estaActivo ? 'activada' : 'desactivada'} correctamente`,
                        estaActivo ? 'success' : 'warning'
                    );

                } catch (err) {
                    RVAlerts.toast('Error al cambiar el estado. Inténtalo de nuevo.', 'danger');
                    btn.disabled    = false;
                    btn.textContent = accion === 'desactivar' ? 'Desactivar' : 'Activar';
                }

                btn.disabled = false;
            }
        });
    });

})();
</script>

<!-- views/admin/categorias/index.php -->

<!-- ESTADÍSTICAS -->
<div class="admin-stats-grid">
    <div class="stat-card">
        <div class="stat-label">Total categorías</div>
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
    <h2>Categorías</h2>
    <div class="admin-header-actions">
        <a href="index.php?controller=admin&action=dashboard" class="btn-primary">
            <i data-i="arrow-left"></i> Dashboard
        </a>
        <a href="index.php?controller=categoria&action=add" class="btn-primary">
            <i data-i="plus"></i> Nueva Categoría
        </a>
    </div>
</div>

<!-- Alertas de estado -->
<?php if (isset($_GET['created'])): ?>
<div class="rv-inline-alert rv-inline--success rv-inline--visible" style="margin-bottom:1.25rem;">
    <span class="rv-inline-icon"><i data-i="check"></i></span>
    <span class="rv-inline-msg">Categoría creada correctamente.</span>
</div>
<?php elseif (isset($_GET['updated'])): ?>
<div class="rv-inline-alert rv-inline--success rv-inline--visible" style="margin-bottom:1.25rem;">
    <span class="rv-inline-icon"><i data-i="check"></i></span>
    <span class="rv-inline-msg">Categoría actualizada correctamente.</span>
</div>
<?php elseif (isset($_GET['deleted'])): ?>
<div class="rv-inline-alert rv-inline--success rv-inline--visible" style="margin-bottom:1.25rem;">
    <span class="rv-inline-icon"><i data-i="check"></i></span>
    <span class="rv-inline-msg">Categoría eliminada correctamente.</span>
</div>
<?php elseif (isset($_GET['error']) && $_GET['error'] === 'has_games'): ?>
<div class="rv-inline-alert rv-inline--danger rv-inline--visible" style="margin-bottom:1.25rem;">
    <span class="rv-inline-icon"><i data-i="warning"></i></span>
    <span class="rv-inline-msg">
        No se puede eliminar: esta categoría tiene <?= (int)($_GET['count'] ?? 0) ?> juego(s) asociado(s).
        Reasígnalos o elimínalos primero.
    </span>
</div>
<?php endif; ?>

<!-- FILTROS — mismo patrón exacto que el dashboard -->
<div class="search-section">
    <div class="admin-filter-form">
        <div class="search-wrapper" style="flex:2;min-width:180px;">
            <input type="text"
                   id="cat-busqueda"
                   placeholder="Buscar por nombre..."
                   value="<?= htmlspecialchars($_GET['busqueda'] ?? '') ?>"
                   class="search-input"
                   autocomplete="off">
            <button type="button" class="search-button"
                    onclick="document.getElementById('cat-busqueda').dispatchEvent(new Event('input'))">
                <i data-i="search" aria-hidden="true"></i>
            </button>
        </div>

        <select id="cat-activo" class="admin-filter-select">
            <option value=""  <?= (!isset($_GET['activo']) || $_GET['activo'] === '') ? 'selected' : '' ?>>Todos los estados</option>
            <option value="1" <?= (($_GET['activo'] ?? '') === '1') ? 'selected' : '' ?>>Solo activas</option>
            <option value="0" <?= (($_GET['activo'] ?? '') === '0') ? 'selected' : '' ?>>Solo inactivas</option>
        </select>

        <button type="button" id="cat-limpiar" class="clear-filters" style="display:none;white-space:nowrap;">
            <i data-i="close" aria-hidden="true"></i> Limpiar
        </button>
    </div>

    <div id="cat-loading" class="filter-loading" style="display:none;">
        <span class="filter-loading-bar"></span>
        <span class="filter-loading-text">Buscando...</span>
    </div>
</div>

<!-- ZONA DE RESULTADOS — mismo patrón que #admin-results -->
<div id="categorias-results">

    <?php if (empty($categorias)): ?>
        <div class="rv-inline-alert rv-inline--info rv-inline--visible" style="text-align:center;padding:3rem;justify-content:center;">
            <span class="rv-inline-icon"><i data-i="info"></i></span>
            <span class="rv-inline-msg">No hay categorías que coincidan con los filtros.</span>
        </div>
    <?php else: ?>
    <div style="overflow-x:auto;">
        <table class="admin-table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Nombre</th>
                    <th>Descripción</th>
                    <th>Creada</th>
                    <th>Estado</th>
                    <th>Acciones</th>
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
                    <td><?= date('d/m/Y', strtotime($cat['created_at'])) ?></td>
                    <td>
                        <!-- btn-toggle-active con data-titulo, exactamente igual que el dashboard -->
                        <button class="btn-toggle-active <?= $cat['activo'] ? 'btn-toggle--on' : 'btn-toggle--off' ?>"
                                data-id="<?= $cat['id'] ?>"
                                data-titulo="<?= htmlspecialchars($cat['nombre'], ENT_QUOTES) ?>"
                                data-accion="<?= $cat['activo'] ? 'desactivar' : 'activar' ?>">
                            <?= $cat['activo'] ? 'Desactivar' : 'Activar' ?>
                        </button>
                    </td>
                    <td>
                        <!-- Solo btn-edit, exactamente igual que el dashboard -->
                        <a href="index.php?controller=categoria&action=edit&id=<?= $cat['id'] ?>" class="btn-edit">Editar</a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <!-- PAGINACIÓN — misma estructura que el dashboard -->
    <?php
    $paramStr = '';
    foreach (['busqueda', 'activo'] as $p) {
        if (isset($_GET[$p]) && $_GET[$p] !== '') $paramStr .= '&' . $p . '=' . urlencode($_GET[$p]);
    }
    ?>
    <?php if (isset($totalPages) && $totalPages > 1): ?>
    <div class="pagination" style="margin-top:1.5rem;" id="cat-pagination">
        <?php if ($currentPage > 1): ?>
            <a href="?controller=categoria&action=index&page=<?= $currentPage - 1 ?><?= $paramStr ?>"
               class="pagination-link" data-page="<?= $currentPage - 1 ?>">
                <i data-i="chevron-left"></i> Anterior
            </a>
        <?php endif; ?>
        <?php
        $range = 2; $pages = [];
        for ($i = 1; $i <= $totalPages; $i++) {
            if ($i === 1 || $i === $totalPages || abs($i - $currentPage) <= $range) $pages[] = $i;
        }
        $prev = null;
        foreach ($pages as $i):
            if ($prev !== null && $i - $prev > 1): ?>
                <span style="padding:0 0.3rem;color:var(--slate-light);">...</span>
            <?php endif;
            if ($i === $currentPage): ?>
                <span class="pagination-current"><?= $i ?></span>
            <?php else: ?>
                <a href="?controller=categoria&action=index&page=<?= $i ?><?= $paramStr ?>"
                   class="pagination-link" data-page="<?= $i ?>"><?= $i ?></a>
            <?php endif;
            $prev = $i;
        endforeach; ?>
        <?php if ($currentPage < $totalPages): ?>
            <a href="?controller=categoria&action=index&page=<?= $currentPage + 1 ?><?= $paramStr ?>"
               class="pagination-link" data-page="<?= $currentPage + 1 ?>">
                Siguiente <i data-i="chevron-right"></i>
            </a>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <div class="pagination-info" style="margin-top:0.75rem;">
        Mostrando <?= count($categorias) ?> de <?= number_format($total) ?> categoría<?= $total !== 1 ? 's' : '' ?>
        <?php if (($totalPages ?? 1) > 1): ?>- Página <?= $currentPage ?> de <?= $totalPages ?><?php endif; ?>
    </div>
    <?php endif; ?>

</div><!-- /#categorias-results -->

<script>
(function () {
    'use strict';

    const busquedaEl = document.getElementById('cat-busqueda');
    const activoEl   = document.getElementById('cat-activo');
    const btnLimpiar = document.getElementById('cat-limpiar');
    const resultsEl  = document.getElementById('categorias-results');
    const loadingEl  = document.getElementById('cat-loading');

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
        p.set('controller', 'categoria');
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
            titulo:    accion === 'desactivar' ? '¿Desactivar categoría?' : '¿Activar categoría?',
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
                        `index.php?controller=categoria&action=toggleActiveAjax&id=${id}`,
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

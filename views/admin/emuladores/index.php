<!-- views/admin/emuladores/index.php -->

<!-- ESTADÍSTICAS — mismo patrón que consolas/categorías -->
<div class="admin-stats-grid">
    <div class="stat-card">
        <div class="stat-label">Consolas con emulador</div>
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

<!-- CABECERA — mismo patrón que consolas/categorías -->
<div class="admin-header">
    <h2>Emuladores recomendados</h2>
    <div class="admin-header-actions">
        <a href="index.php?controller=admin&action=dashboard" class="btn-primary">
            <i data-i="arrow-left"></i> Dashboard
        </a>
        <a href="index.php?controller=emulador&action=add" class="btn-primary">
            <i data-i="plus"></i> Nuevo Emulador
        </a>
    </div>
</div>

<!-- Alertas de estado — mismo componente Alert que el resto del panel -->
<?php 
require_once 'views/components/Alert.php';
if (isset($_GET['created'])): 
    Alert::render('success', 'Emulador registrado correctamente.', 'check', 'margin-bottom:1.25rem;');
elseif (isset($_GET['updated'])): 
    Alert::render('success', 'Emuladores actualizados correctamente.', 'check', 'margin-bottom:1.25rem;');
endif; 
?>

<!-- FILTROS — mismo patrón exacto que consolas/categorías -->
<div class="search-section">
    <div class="admin-filter-form">
        <div class="search-wrapper" style="flex:2;min-width:180px;">
            <input type="text"
                   id="em-busqueda"
                   placeholder="Buscar por consola o emulador..."
                   value="<?= htmlspecialchars($_GET['busqueda'] ?? '') ?>"
                   class="search-input"
                   autocomplete="off">
            <button type="button" class="search-button"
                    onclick="document.getElementById('em-busqueda').dispatchEvent(new Event('input'))">
                <i data-i="search" aria-hidden="true"></i>
            </button>
        </div>

        <select id="em-activo" class="admin-filter-select">
            <option value=""  <?= (!isset($_GET['activo']) || $_GET['activo'] === '') ? 'selected' : '' ?>>Todos los estados</option>
            <option value="1" <?= (($_GET['activo'] ?? '') === '1') ? 'selected' : '' ?>>Solo activos</option>
            <option value="0" <?= (($_GET['activo'] ?? '') === '0') ? 'selected' : '' ?>>Solo inactivos</option>
        </select>

        <button type="button" id="em-limpiar" class="clear-filters" style="display:none;white-space:nowrap;">
            <i data-i="close" aria-hidden="true"></i> Limpiar
        </button>
    </div>

    <div id="em-loading" class="filter-loading" style="display:none;">
        <span class="filter-loading-bar"></span>
        <span class="filter-loading-text">Buscando...</span>
    </div>
</div>

<!-- ZONA DE RESULTADOS — misma tabla admin-table que el resto del panel -->
<div id="emuladores-results">

    <?php if (empty($filas)): ?>
        <?php 
        require_once 'views/components/Alert.php';
        Alert::render('info', 'No hay consolas con emuladores que coincidan con los filtros.', 'info', 'text-align:center;padding:3rem;justify-content:center;');
        ?>
    <?php else: ?>
    <div style="overflow-x:auto;">
        <table class="admin-table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Consola</th>
                    <th>Emulador principal</th>
                    <th>Alternativo</th>
                    <th>Estado</th>
                    <th>Acciones</th>
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
                                <a href="<?= htmlspecialchars($f['principal']['url']) ?>" target="_blank" rel="noopener noreferrer" style="text-decoration:underline;">sitio oficial</a>
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
                                <a href="<?= htmlspecialchars($f['alterno']['url']) ?>" target="_blank" rel="noopener noreferrer" style="text-decoration:underline;">sitio oficial</a>
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
                            <?= $f['activo'] ? 'Desactivar' : 'Activar' ?>
                        </button>
                    </td>
                    <td>
                        <!-- Solo btn-edit, exactamente igual que el dashboard -->
                        <a href="index.php?controller=emulador&action=edit&id=<?= $f['id'] ?>" class="btn-edit">Editar</a>
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
        'emulador', 
        'index', 
        count($filas), 
        $total, 
        'consolas con emulador', 
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
        p.set('controller', 'emulador');
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
            titulo:    accion === 'desactivar' ? '¿Desactivar emulador?' : '¿Activar emulador?',
            mensaje:   `El emulador recomendado de <strong>${titulo}</strong> quedará ${accion === 'desactivar'
                            ? 'oculto en la ficha pública de sus juegos'
                            : 'visible en la ficha pública de sus juegos'}.`,
            btnOk:     accion === 'desactivar' ? 'Sí, desactivar' : 'Sí, activar',
            btnCancel: 'Cancelar',
            onOk: async () => {
                btn.disabled    = true;
                btn.textContent = '...';

                try {
                    const res  = await fetch(
                        `index.php?controller=emulador&action=toggleActiveAjax&id=${id}`,
                        { headers: { 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-Token': csrfToken } }
                    );
                    const data = await res.json();

                    if (!data.ok) throw new Error(data.error || 'Error desconocido');

                    const estaActivo     = data.activo === 1;
                    btn.textContent      = estaActivo ? 'Desactivar' : 'Activar';
                    btn.dataset.accion   = estaActivo ? 'desactivar' : 'activar';
                    btn.className        = 'btn-toggle-active ' + (estaActivo ? 'btn-toggle--on' : 'btn-toggle--off');

                    RVAlerts.toast(
                        `Emulador de "${data.nombre}" ${estaActivo ? 'activado' : 'desactivado'} correctamente`,
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

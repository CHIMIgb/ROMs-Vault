<!-- views/admin/dashboard.php -->

<!-- ESTADÍSTICAS GLOBALES REALES -->
<div class="admin-stats-grid">
    <div class="stat-card">
        <div class="stat-label">Total juegos</div>
        <div class="stat-value" style="color:var(--primary);"><?= number_format($stats['total']) ?></div>
    </div>
    <div class="stat-card">
        <div class="stat-label">Activos</div>
        <div class="stat-value" style="color:var(--success);"><?= number_format($stats['activos']) ?></div>
    </div>
    <div class="stat-card">
        <div class="stat-label">Inactivos</div>
        <div class="stat-value" style="color:var(--text-light);"><?= number_format($stats['inactivos']) ?></div>
    </div>
    <div class="stat-card">
        <div class="stat-label">Total descargas</div>
        <div class="stat-value" style="color:var(--primary);"><?= number_format($stats['total_descargas']) ?></div>
    </div>
    <div class="stat-card">
        <div class="stat-label">Total jugadas online</div>
        <div class="stat-value" style="color:var(--primary);"><?= number_format($stats['total_jugadas']) ?></div>
    </div>
</div>

<!-- RANKINGS -->
<?php if (!empty($topDescargas) || !empty($topJugados)): ?>
<div class="admin-rankings">
    <?php if (!empty($topDescargas)): ?>
    <div class="ranking-card">
        <h3 class="ranking-title">Top 5 mas descargados</h3>
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
                <span class="ranking-count"><?= number_format($j['downloads_count']) ?></span>
            </li>
            <?php endforeach; ?>
        </ol>
    </div>
    <?php endif; ?>
    <?php if (!empty($topJugados)): ?>
    <div class="ranking-card">
        <h3 class="ranking-title">Top 5 mas jugados online</h3>
        <ol class="ranking-list">
            <?php $maxP = $topJugados[0]['plays_count'] ?: 1;
            foreach ($topJugados as $i => $j):
                $pct = round(($j['plays_count'] / $maxP) * 100); ?>
            <li class="ranking-item">
                <span class="ranking-pos"><?= $i + 1 ?></span>
                <div class="ranking-info">
                    <span class="ranking-game"><?= htmlspecialchars($j['titulo']) ?></span>
                    <span class="ranking-platform"><?= htmlspecialchars($j['consola_nombre'] ?? '') ?></span>
                    <div class="ranking-bar-track"><div class="ranking-bar-fill" style="width:<?= $pct ?>%;background:var(--success);"></div></div>
                </div>
                <span class="ranking-count"><?= number_format($j['plays_count']) ?></span>
            </li>
            <?php endforeach; ?>
        </ol>
    </div>
    <?php endif; ?>
</div>
<?php endif; ?>

<!-- CABECERA -->
<div class="admin-header">
    <h2>Panel de Administracion</h2>
    <div class="admin-header-actions">
        <a href="index.php?controller=consola&action=index" class="btn-primary">
            <i data-i="gamepad"></i> Consolas
        </a>
        <a href="index.php?controller=categoria&action=index" class="btn-primary">
            <i data-i="dashboard"></i> Categorías
        </a>
        <a href="index.php?controller=admin&action=add" class="btn-primary">
            <i data-i="plus"></i> Añadir nuevo juego
        </a>
    </div>
</div>

<!-- FILTROS EXTENDIDOS EN TIEMPO REAL -->
<div class="search-section">
    <div class="admin-filter-form">
        <div class="search-wrapper" style="flex:2;min-width:180px;">
            <input type="text"
                   id="af-busqueda"
                   placeholder="Buscar por titulo..."
                   value="<?= htmlspecialchars($_GET['busqueda'] ?? '') ?>"
                   class="search-input"
                   autocomplete="off">
            <button type="button" class="search-button"
                    onclick="document.getElementById('af-busqueda').dispatchEvent(new Event('input'))">
                <i data-i="search" aria-hidden="true"></i>
            </button>
        </div>

        <select id="af-consola" class="admin-filter-select">
            <option value="">Todas las consolas</option>
            <?php foreach ($consolas as $c): ?>
                <option value="<?= $c['id'] ?>" <?= (($_GET['consola'] ?? '') == $c['id']) ? 'selected' : '' ?>>
                    <?= htmlspecialchars($c['nombre']) ?>
                </option>
            <?php endforeach; ?>
        </select>

        <select id="af-categoria" class="admin-filter-select">
            <option value="">Todas las categorias</option>
            <?php foreach ($categorias as $cat): ?>
                <option value="<?= $cat['id'] ?>" <?= (($_GET['categoria'] ?? '') == $cat['id']) ? 'selected' : '' ?>>
                    <?= htmlspecialchars($cat['nombre']) ?>
                </option>
            <?php endforeach; ?>
        </select>

        <select id="af-region" class="admin-filter-select">
            <option value="">Todas las regiones</option>
            <option value="PAL"    <?= (($_GET['region'] ?? '') === 'PAL')    ? 'selected' : '' ?>>PAL</option>
            <option value="NTSC"   <?= (($_GET['region'] ?? '') === 'NTSC')   ? 'selected' : '' ?>>NTSC</option>
            <option value="NTSC-J" <?= (($_GET['region'] ?? '') === 'NTSC-J') ? 'selected' : '' ?>>NTSC-J</option>
            <option value="NTSC-U" <?= (($_GET['region'] ?? '') === 'NTSC-U') ? 'selected' : '' ?>>NTSC-U</option>
        </select>

        <select id="af-activo" class="admin-filter-select">
            <option value=""  <?= (!isset($_GET['activo']) || $_GET['activo'] === '') ? 'selected' : '' ?>>Todos los estados</option>
            <option value="1" <?= (($_GET['activo'] ?? '') === '1') ? 'selected' : '' ?>>Solo activos</option>
            <option value="0" <?= (($_GET['activo'] ?? '') === '0') ? 'selected' : '' ?>>Solo inactivos</option>
        </select>

        <button type="button" id="af-limpiar" class="clear-filters" style="display:none;white-space:nowrap;"><i data-i="close" aria-hidden="true"></i> Limpiar</button>
    </div>

    <!-- Indicador de carga -->
    <div id="admin-filter-loading" class="filter-loading" style="display:none;">
        <span class="filter-loading-bar"></span>
        <span class="filter-loading-text">Buscando...</span>
    </div>
</div>

<!-- ZONA DE RESULTADOS ADMIN -->
<div id="admin-results">

    <?php if (empty($juegos)): ?>
        <div class="rv-inline-alert rv-inline--info rv-inline--visible" style="text-align:center;padding:3rem;justify-content:center;">
            <span class="rv-inline-icon"><i data-i="info"></i></span>
            <span class="rv-inline-msg">No hay juegos que coincidan con los filtros.</span>
        </div>
    <?php else: ?>
    <div style="overflow-x:auto;">
        <table class="admin-table">
            <thead>
                <tr>
                    <th>ID</th><th>Portada</th><th>Titulo</th><th>ID Juego</th>
                    <th>Consola</th><th>Categoria</th><th>Region</th>
                    <th style="text-align:center;" title="Descargas">D</th>
                    <th style="text-align:center;" title="Jugadas">J</th>
                    <th>Estado</th><th>Acciones</th>
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
                            <img src="<?= htmlspecialchars($juego['imagen']) ?>" alt="Portada"
                                 style="width:40px;height:40px;object-fit:cover;">
                        <?php else: ?>
                            <span style="color:var(--text-light);"><i data-i="image"></i></span>
                        <?php endif; ?>
                    </td>
                    <td><strong><?= htmlspecialchars($juego['titulo']) ?></strong></td>
                    <td><code style="font-size:0.78rem;"><?= htmlspecialchars($juego['game_id_code'] ?? '-') ?></code></td>
                    <td><?= htmlspecialchars($juego['consola_nombre']  ?? '-') ?></td>
                    <td><?= htmlspecialchars($juego['categoria_nombre'] ?? '-') ?></td>
                    <td>
                        <span class="game-tag" style="background:var(--background);color:var(--text);">
                            <?= htmlspecialchars($juego['region'] ?? '-') ?>
                        </span>
                    </td>
                    <td style="text-align:center;font-size:0.85rem;"><?= number_format($juego['downloads_count'] ?? 0) ?></td>
                    <td style="text-align:center;font-size:0.85rem;"><?= number_format($juego['plays_count'] ?? 0) ?></td>
                    <td>
                        <button class="btn-toggle-active <?= $juego['activo'] ? 'btn-toggle--on' : 'btn-toggle--off' ?>"
                                data-id="<?= $juego['id'] ?>"
                                data-titulo="<?= htmlspecialchars($juego['titulo'], ENT_QUOTES) ?>"
                                data-accion="<?= $juego['activo'] ? 'desactivar' : 'activar' ?>">
                            <?= $juego['activo'] ? 'Desactivar' : 'Activar' ?>
                        </button>
                    </td>
                    <td>
                        <a href="index.php?controller=admin&action=edit&id=<?= $juego['id'] ?>" class="btn-edit">Editar</a>
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
    ?>
    <?php if (isset($totalPages) && $totalPages > 1): ?>
    <div class="pagination" style="margin-top:1.5rem;" id="admin-pagination">
        <?php if ($currentPage > 1): ?>
            <a href="?controller=admin&action=dashboard&page=<?= $currentPage - 1 ?><?= $paramStr ?>"
               class="pagination-link" data-page="<?= $currentPage - 1 ?>"><i data-i="chevron-left"></i> Anterior</a>
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
                <a href="?controller=admin&action=dashboard&page=<?= $i ?><?= $paramStr ?>"
                   class="pagination-link" data-page="<?= $i ?>"><?= $i ?></a>
            <?php endif;
            $prev = $i;
        endforeach; ?>
        <?php if ($currentPage < $totalPages): ?>
            <a href="?controller=admin&action=dashboard&page=<?= $currentPage + 1 ?><?= $paramStr ?>"
               class="pagination-link" data-page="<?= $currentPage + 1 ?>">Siguiente <i data-i="chevron-right"></i></a>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <div class="pagination-info" style="margin-top:0.75rem;" id="admin-info">
        Mostrando <?= count($juegos) ?> de <?= number_format($totalJuegos) ?> juegos
        <?php if ($currentPage && $totalPages): ?>
            - Pagina <?= $currentPage ?> de <?= $totalPages ?>
        <?php endif; ?>
    </div>
    <?php endif; ?>

</div><!-- /#admin-results -->

<script>
(function () {
    'use strict';

    const busquedaEl  = document.getElementById('af-busqueda');
    const consolaEl   = document.getElementById('af-consola');
    const categoriaEl = document.getElementById('af-categoria');
    const regionEl    = document.getElementById('af-region');
    const activoEl    = document.getElementById('af-activo');
    const btnLimpiar  = document.getElementById('af-limpiar');
    const resultsEl   = document.getElementById('admin-results');
    const loadingEl   = document.getElementById('admin-filter-loading');

    let debounceTimer  = null;
    let currentRequest = null;

    function getAjaxParams(page) {
        const p = new URLSearchParams();
        p.set('page', page || 1);
        const b = busquedaEl.value.trim();
        if (b)                 p.set('busqueda',  b);
        if (consolaEl.value)   p.set('consola',   consolaEl.value);
        if (categoriaEl.value) p.set('categoria', categoriaEl.value);
        if (regionEl.value)    p.set('region',    regionEl.value);
        if (activoEl.value !== '') p.set('activo', activoEl.value);
        return p;
    }

    function getUiParams(page) {
        const p = getAjaxParams(page);
        p.set('controller', 'admin');
        p.set('action', 'dashboard');
        return p;
    }

    function updateLimpiar() {
        const has = busquedaEl.value.trim() || consolaEl.value
            || categoriaEl.value || regionEl.value || activoEl.value !== '';
        btnLimpiar.style.display = has ? 'inline-block' : 'none';
    }

    async function fetchResults(page) {
        if (currentRequest) currentRequest.abort();
        currentRequest = new AbortController();

        loadingEl.style.display = 'flex';
        resultsEl.style.opacity = '0.45';
        resultsEl.style.pointerEvents = 'none';

        history.replaceState(null, '', '?' + getUiParams(page).toString());

        try {
            const res = await fetch('ajax_admin.php?' + getAjaxParams(page).toString(), {
                signal: currentRequest.signal
            });
            if (!res.ok) throw new Error('HTTP ' + res.status);
            resultsEl.innerHTML = await res.text();
            bindPagination();
        } catch (err) {
            if (err.name !== 'AbortError') {
                RVAlerts.toast('Error al cargar los resultados. Intenta de nuevo.', 'danger');
            }
        } finally {
            loadingEl.style.display = 'none';
            resultsEl.style.opacity = '';
            resultsEl.style.pointerEvents = '';
        }
    }

    function scheduleSearch() {
        clearTimeout(debounceTimer);
        updateLimpiar();
        debounceTimer = setTimeout(() => fetchResults(1), 380);
    }

    function onSelectChange() {
        updateLimpiar();
        fetchResults(1);
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
        busquedaEl.value  = '';
        consolaEl.value   = '';
        categoriaEl.value = '';
        regionEl.value    = '';
        activoEl.value    = '';
        updateLimpiar();
        fetchResults(1);
    });

    busquedaEl.addEventListener('input',   scheduleSearch);
    consolaEl.addEventListener('change',   onSelectChange);
    categoriaEl.addEventListener('change', onSelectChange);
    regionEl.addEventListener('change',    onSelectChange);
    activoEl.addEventListener('change',    onSelectChange);

    bindPagination();
    updateLimpiar();

    // ── Delegación de clics en botones toggle (funciona con AJAX también) ──
    document.getElementById('admin-results').addEventListener('click', e => {
        const btn = e.target.closest('.btn-toggle-active');
        if (!btn) return;
        e.preventDefault();

        const accion = btn.dataset.accion;
        const titulo = btn.dataset.titulo;
        const id     = btn.dataset.id;

        RVAlerts.confirm({
            tipo:      accion === 'desactivar' ? 'warning' : 'success',
            titulo:    accion === 'desactivar' ? '¿Desactivar juego?' : '¿Activar juego?',
            mensaje:   `<strong>${titulo}</strong> quedará ${accion === 'desactivar'
                            ? 'oculto en el catálogo público'
                            : 'visible en el catálogo público'}.`,
            btnOk:     accion === 'desactivar' ? 'Sí, desactivar' : 'Sí, activar',
            btnCancel: 'Cancelar',
            onOk: async () => {
                btn.disabled    = true;
                btn.textContent = '...';

                try {
                    const res  = await fetch(
                        `index.php?controller=admin&action=toggleActiveAjax&id=${id}`,
                        { headers: { 'X-Requested-With': 'XMLHttpRequest' } }
                    );
                    const data = await res.json();

                    if (!data.ok) throw new Error(data.error || 'Error desconocido');

                    const estaActivo = data.activo === 1;
                    btn.textContent      = estaActivo ? 'Desactivar' : 'Activar';
                    btn.dataset.accion   = estaActivo ? 'desactivar' : 'activar';
                    btn.className        = 'btn-toggle-active ' + (estaActivo ? 'btn-toggle--on' : 'btn-toggle--off');

                    RVAlerts.toast(
                        `"${data.titulo}" ${estaActivo ? 'activado' : 'desactivado'} correctamente`,
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
<style>
/* Cabecera con múltiples acciones */
.admin-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: .75rem;
    margin-bottom: 1.5rem;
}

.admin-header-actions {
    display: flex;
    align-items: center;
    gap: .6rem;
    flex-wrap: wrap;
}
</style>

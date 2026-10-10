<!-- views/admin/dashboard.php -->

<?php
require_once __DIR__ . '/../../config/IntlHelper.php';
?>

<!-- ESTADÍSTICAS GLOBALES REALES -->
<div class="admin-stats-grid">
    <div class="stat-card">
        <div class="stat-label"><?= t('admin.dashboard.total_juegos') ?></div>
        <div class="stat-value" style="color:var(--primary);"><?= IntlHelper::formatNumber($stats['total']) ?></div>
    </div>
    <div class="stat-card">
        <div class="stat-label"><?= t('admin.dashboard.activos') ?></div>
        <div class="stat-value" style="color:var(--success);"><?= IntlHelper::formatNumber($stats['activos']) ?></div>
    </div>
    <div class="stat-card">
        <div class="stat-label"><?= t('admin.dashboard.inactivos') ?></div>
        <div class="stat-value" style="color:var(--text-light);"><?= IntlHelper::formatNumber($stats['inactivos']) ?></div>
    </div>
    <div class="stat-card">
        <div class="stat-label"><?= t('admin.dashboard.total_descargas') ?></div>
        <div class="stat-value" style="color:var(--primary);"><?= IntlHelper::formatNumber($stats['total_descargas']) ?></div>
    </div>
    <div class="stat-card">
        <div class="stat-label"><?= t('admin.dashboard.total_jugadas') ?></div>
        <div class="stat-value" style="color:var(--primary);"><?= IntlHelper::formatNumber($stats['total_jugadas']) ?></div>
    </div>
</div>

<!-- RANKINGS -->
<?php if (!empty($topDescargas) || !empty($topJugados)): ?>
<div class="admin-rankings">
    <?php if (!empty($topDescargas)): ?>
    <div class="ranking-card">
        <h3 class="ranking-title"><?= t('admin.dashboard.top_descargados') ?></h3>
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
                <span class="ranking-count"><?= IntlHelper::formatNumber($j['downloads_count']) ?></span>
            </li>
            <?php endforeach; ?>
        </ol>
    </div>
    <?php endif; ?>
    <?php if (!empty($topJugados)): ?>
    <div class="ranking-card">
        <h3 class="ranking-title"><?= t('admin.dashboard.top_jugados') ?></h3>
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
                <span class="ranking-count"><?= IntlHelper::formatNumber($j['plays_count']) ?></span>
            </li>
            <?php endforeach; ?>
        </ol>
    </div>
    <?php endif; ?>
</div>
<?php endif; ?>

<!-- CABECERA -->
<div class="admin-header">
    <h2><?= t('admin.dashboard.panel_administracion') ?></h2>
    <div class="admin-header-actions">
        <a href="/admin/tfa" class="btn-primary" title="<?= t('admin.dashboard.verificacion_dos_pasos') ?>">
            <i data-i="shield-2"></i> <?= t('admin.dashboard.seguridad_2fa') ?>
        </a>
        <a href="/consola/index" class="btn-primary">
            <i data-i="gamepad"></i> <?= t('admin.dashboard.consolas') ?>
        </a>
        <a href="/emulador/index" class="btn-primary">
            <i data-i="joystick"></i> <?= t('admin.dashboard.emuladores') ?>
        </a>
        <a href="/categoria/index" class="btn-primary">
            <i data-i="dashboard"></i> <?= t('admin.dashboard.categorias') ?>
        </a>
        <button type="button" id="btn-export" class="btn-primary" style="background-color: var(--success);">
            <i data-i="save"></i> <?= t('admin.dashboard.exportar') ?>
        </button>
        <a href="/admin/add" class="btn-primary">
            <i data-i="plus"></i> <?= t('admin.dashboard.anadir_juego') ?>
        </a>
    </div>
</div>

<!-- FILTROS EXTENDIDOS EN TIEMPO REAL -->
<div class="search-section">
    <div class="admin-filter-form">
        <div class="search-wrapper" style="flex:2;min-width:180px;">
            <input type="text"
                   id="af-busqueda"
                   placeholder="<?= t('admin.dashboard.buscar_titulo') ?>"
                   value="<?= htmlspecialchars($_GET['busqueda'] ?? '') ?>"
                   class="search-input"
                   autocomplete="off">
            <button type="button" class="search-button"
                    onclick="document.getElementById('af-busqueda').dispatchEvent(new Event('input'))">
                <i data-i="search" aria-hidden="true"></i>
            </button>
        </div>

        <select id="af-consola" class="admin-filter-select">
            <option value=""><?= t('admin.dashboard.todas_consolas') ?></option>
            <?php foreach ($consolas as $c): ?>
                <option value="<?= $c['id'] ?>" <?= (($_GET['consola'] ?? '') == $c['id']) ? 'selected' : '' ?>>
                    <?= htmlspecialchars($c['nombre']) ?>
                </option>
            <?php endforeach; ?>
        </select>

        <select id="af-categoria" class="admin-filter-select">
            <option value=""><?= t('admin.dashboard.todas_categorias') ?></option>
            <?php foreach ($categorias as $cat): ?>
                <option value="<?= $cat['id'] ?>" <?= (($_GET['categoria'] ?? '') == $cat['id']) ? 'selected' : '' ?>>
                    <?= htmlspecialchars($cat['nombre']) ?>
                </option>
            <?php endforeach; ?>
        </select>

        <select id="af-region" class="admin-filter-select">
            <option value=""><?= t('admin.dashboard.todas_regiones') ?></option>
            <option value="PAL"    <?= (($_GET['region'] ?? '') === 'PAL')    ? 'selected' : '' ?>>PAL</option>
            <option value="NTSC"   <?= (($_GET['region'] ?? '') === 'NTSC')   ? 'selected' : '' ?>>NTSC</option>
            <option value="NTSC-J" <?= (($_GET['region'] ?? '') === 'NTSC-J') ? 'selected' : '' ?>>NTSC-J</option>
            <option value="NTSC-U" <?= (($_GET['region'] ?? '') === 'NTSC-U') ? 'selected' : '' ?>>NTSC-U</option>
        </select>

        <select id="af-activo" class="admin-filter-select">
            <option value=""  <?= (!isset($_GET['activo']) || $_GET['activo'] === '') ? 'selected' : '' ?>><?= t('admin.dashboard.todos_estados') ?></option>
            <option value="1" <?= (($_GET['activo'] ?? '') === '1') ? 'selected' : '' ?>><?= t('admin.dashboard.solo_activos') ?></option>
            <option value="0" <?= (($_GET['activo'] ?? '') === '0') ? 'selected' : '' ?>><?= t('admin.dashboard.solo_inactivos') ?></option>
        </select>

        <button type="button" id="af-limpiar" class="clear-filters" style="display:none;white-space:nowrap;"><i data-i="close" aria-hidden="true"></i> <?= t('admin.dashboard.limpiar') ?></button>
    </div>

    <!-- Indicador de carga -->
    <div id="admin-filter-loading" class="filter-loading" style="display:none;">
        <span class="filter-loading-bar"></span>
        <span class="filter-loading-text"><?= t('admin.dashboard.buscando') ?></span>
    </div>
</div>

<!-- ZONA DE RESULTADOS ADMIN -->
<div id="admin-results">

    <?php if (empty($juegos)): ?>
        <?php 
        require_once __DIR__ . '/../components/Alert.php';
        Alert::render('info', t('admin.dashboard.sin_juegos'), 'info', 'text-align:center;padding:3rem;justify-content:center;');
        ?>
    <?php else: ?>
    <div style="overflow-x:auto;">
        <table class="admin-table">
            <thead>
                <tr>
                    <th><?= t('admin.juego.tabla_id') ?></th><th><?= t('admin.juego.tabla_portada') ?></th><th><?= t('admin.juego.tabla_titulo') ?></th><th><?= t('admin.juego.tabla_id_juego') ?></th>
                    <th><?= t('admin.juego.tabla_consola') ?></th><th><?= t('admin.juego.tabla_categoria') ?></th><th><?= t('admin.juego.tabla_region') ?></th>
                    <th style="text-align:center;" title="<?= t('admin.juego.tabla_descargas') ?>"><?= t('admin.juego.tabla_descargas_abrev') ?></th>
                    <th style="text-align:center;" title="<?= t('admin.juego.tabla_jugadas_online') ?>"><?= t('admin.juego.tabla_jugadas_abrev') ?></th>
                    <th><?= t('admin.juego.tabla_estado') ?></th><th><?= t('admin.juego.tabla_acciones') ?></th>
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
                            <img src="<?= htmlspecialchars($juego['imagen']) ?>" alt="<?= t('admin.juego.alt_portada') ?>"
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
                    <td style="text-align:center;font-size:0.85rem;"><?= IntlHelper::formatNumber($juego['downloads_count'] ?? 0) ?></td>
                    <td style="text-align:center;font-size:0.85rem;"><?= IntlHelper::formatNumber($juego['plays_count'] ?? 0) ?></td>
                    <td>
                        <button class="btn-toggle-active <?= $juego['activo'] ? 'btn-toggle--on' : 'btn-toggle--off' ?>"
                                data-id="<?= $juego['id'] ?>"
                                data-titulo="<?= htmlspecialchars($juego['titulo'], ENT_QUOTES) ?>"
                                data-accion="<?= $juego['activo'] ? 'desactivar' : 'activar' ?>">
                            <?= $juego['activo'] ? t('admin.juego.desactivar') : t('admin.juego.activar') ?>
                        </button>
                    </td>
                    <td>
                        <a href="/admin/edit/<?= $juego['id'] ?>" class="btn-edit"><?= t('admin.juego.editar') ?></a>
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
    
    require_once __DIR__ . '/../components/Pagination.php';
    Pagination::render(
        $currentPage, 
        $totalPages ?? 1, 
        $paramStr, 
        'admin', 
        'dashboard', 
        count($juegos), 
        $totalJuegos, 
        t('component.juegos'), 
        'admin'
    );
    ?>
    <?php endif; ?>

</div><!-- /#admin-results -->

<!-- MODAL DE EXPORTACION -->
<div id="export-modal" class="rv-modal-overlay" style="display:none;">
    <div class="rv-modal-box rv-modal--info rv-modal--open">
        <div class="rv-modal-header">
            <span class="rv-modal-icon"><i data-i="save"></i></span>
            <h3 class="rv-modal-title"><?= t('admin.dashboard.exportar_titulo') ?></h3>
        </div>
        <div class="rv-modal-body" style="padding-top:0.5rem; text-align:center;">
            <p style="margin-bottom: 1rem; color: var(--text-light);"><?= t('admin.dashboard.exportar_instruccion') ?></p>
            <select id="export-table" class="admin-filter-select" style="width:100%;">
                <option value="juegos"><?= t('admin.dashboard.exportar_tabla_juegos') ?></option>
                <option value="consolas"><?= t('admin.dashboard.exportar_tabla_consolas') ?></option>
                <option value="categorias"><?= t('admin.dashboard.exportar_tabla_categorias') ?></option>
                <option value="usuarios"><?= t('admin.dashboard.exportar_tabla_usuarios') ?></option>
                <option value="personas"><?= t('admin.dashboard.exportar_tabla_personas') ?></option>
                <option value="roles"><?= t('admin.dashboard.exportar_tabla_roles') ?></option>
                <option value="descargas"><?= t('admin.dashboard.exportar_tabla_descargas') ?></option>
            </select>
        </div>
        <div class="rv-modal-footer">
            <button class="rv-btn rv-btn-cancel" id="btn-export-cancel"><?= t('admin.dashboard.cancelar') ?></button>
            <button class="rv-btn rv-btn-ok rv-btn--success" id="btn-export-confirm"><?= t('admin.dashboard.descargar') ?></button>
        </div>
    </div>
</div>

<script>
(function () {
    'use strict';

    const csrfToken = (document.querySelector('meta[name="csrf-token"]') || {}).content || '';

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
                RVAlerts.toast(<?= json_encode(t('admin.juego.error_cargar_resultados')) ?>, 'danger');
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

        const textosConfirm = {
            tituloActivar:    <?= json_encode(t('admin.juego.confirmar_activar_titulo')) ?>,
            tituloDesactivar: <?= json_encode(t('admin.juego.confirmar_desactivar_titulo')) ?>,
            msgActivar:       <?= json_encode(t('admin.juego.confirmar_activar_mensaje', ['nombre' => '{n}'])) ?>,
            msgDesactivar:    <?= json_encode(t('admin.juego.confirmar_desactivar_mensaje', ['nombre' => '{n}'])) ?>,
            okActivar:        <?= json_encode(t('admin.juego.confirmar_btn_activar')) ?>,
            okDesactivar:     <?= json_encode(t('admin.juego.confirmar_btn_desactivar')) ?>,
            cancelar:         <?= json_encode(t('admin.juego.cancelar')) ?>,
            errorDesconocido: <?= json_encode(t('admin.juego.error_desconocido')) ?>,
            errorEstado:      <?= json_encode(t('admin.juego.error_cambiar_estado')) ?>,
            toastActivado:    <?= json_encode(t('admin.juego.toast_activado', ['nombre' => '{n}'])) ?>,
            toastDesactivado: <?= json_encode(t('admin.juego.toast_desactivado', ['nombre' => '{n}'])) ?>,
            desactivar:       <?= json_encode(t('admin.juego.desactivar')) ?>,
            activar:          <?= json_encode(t('admin.juego.activar')) ?>,
        };

        RVAlerts.confirm({
            tipo:      accion === 'desactivar' ? 'warning' : 'success',
            titulo:    accion === 'desactivar' ? textosConfirm.tituloDesactivar : textosConfirm.tituloActivar,
            mensaje:   (accion === 'desactivar' ? textosConfirm.msgDesactivar : textosConfirm.msgActivar).replace('{n}', titulo),
            btnOk:     accion === 'desactivar' ? textosConfirm.okDesactivar : textosConfirm.okActivar,
            btnCancel: textosConfirm.cancelar,
            onOk: async () => {
                btn.disabled    = true;
                btn.textContent = '...';

                try {
                    const res  = await fetch(
                        `/admin/toggleActiveAjax/${id}`,
                        { headers: { 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-Token': csrfToken } }
                    );
                    const data = await res.json();

                    if (!data.ok) throw new Error(data.error || textosConfirm.errorDesconocido);

                    const estaActivo = data.activo === 1;
                    btn.textContent      = estaActivo ? textosConfirm.desactivar : textosConfirm.activar;
                    btn.dataset.accion   = estaActivo ? 'desactivar' : 'activar';
                    btn.className        = 'btn-toggle-active ' + (estaActivo ? 'btn-toggle--on' : 'btn-toggle--off');

                    RVAlerts.toast(
                        (estaActivo ? textosConfirm.toastActivado : textosConfirm.toastDesactivado).replace('{n}', data.titulo),
                        estaActivo ? 'success' : 'warning'
                    );

                } catch (err) {
                    RVAlerts.toast(textosConfirm.errorEstado, 'danger');
                    btn.disabled    = false;
                    btn.textContent = accion === 'desactivar' ? textosConfirm.desactivar : textosConfirm.activar;
                }

                btn.disabled = false;
            }
        });
    });

    // ── Lógica de Exportación ──
    const btnExport = document.getElementById('btn-export');
    const modalExport = document.getElementById('export-modal');
    const btnCancelExport = document.getElementById('btn-export-cancel');
    const btnConfirmExport = document.getElementById('btn-export-confirm');
    const selectExportTable = document.getElementById('export-table');

    function closeExportModal() {
        const box = modalExport.querySelector('.rv-modal-box');
        box.classList.remove('rv-modal--open');
        box.classList.add('rv-modal--close');
        setTimeout(() => {
            modalExport.style.display = 'none';
            box.classList.remove('rv-modal--close');
        }, 220);
    }

    if (btnExport) {
        btnExport.addEventListener('click', () => {
            modalExport.style.display = 'flex';
            const box = modalExport.querySelector('.rv-modal-box');
            void box.offsetWidth;
            box.classList.add('rv-modal--open');
        });
    }

    if (btnCancelExport) {
        btnCancelExport.addEventListener('click', closeExportModal);
    }

    if (btnConfirmExport) {
        btnConfirmExport.addEventListener('click', () => {
            const table = selectExportTable.value;
            window.location.href = `/export/download?table=${table}`;
            closeExportModal();
        });
    }

    // Cerrar modal al hacer clic afuera
    window.addEventListener('click', (e) => {
        if (e.target === modalExport) {
            closeExportModal();
        }
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

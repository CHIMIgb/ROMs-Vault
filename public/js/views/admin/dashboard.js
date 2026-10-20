// views/admin/dashboard.php - confirmaciones, filtros y copia de enlaces
(function () {
    'use strict';

    const data = JSON.parse(
        (document.getElementById('rv-data-admin-dashboard') || {}).textContent || '{}'
    );

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
        if (!page || page === 1) p.delete('page');
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

        const uiQs = getUiParams(page).toString();
        history.replaceState(null, '', uiQs ? '?' + uiQs : location.pathname);

        try {
            const res = await fetch('ajax_admin.php?' + getAjaxParams(page).toString(), {
                signal: currentRequest.signal
            });
            if (!res.ok) throw new Error('HTTP ' + res.status);
            resultsEl.innerHTML = await res.text();
            bindPagination();
        } catch (err) {
            if (err.name !== 'AbortError') {
                RVAlerts.toast(data.t['admin.juego.error_cargar_resultados'], 'danger');
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
            tituloActivar:    data.t['admin.juego.confirmar_activar_titulo'],
            tituloDesactivar: data.t['admin.juego.confirmar_desactivar_titulo'],
            msgActivar:       data.t['admin.juego.confirmar_activar_mensaje'],
            msgDesactivar:    data.t['admin.juego.confirmar_desactivar_mensaje'],
            okActivar:        data.t['admin.juego.confirmar_btn_activar'],
            okDesactivar:     data.t['admin.juego.confirmar_btn_desactivar'],
            cancelar:         data.t['admin.juego.cancelar'],
            errorDesconocido: data.t['admin.juego.error_desconocido'],
            errorEstado:      data.t['admin.juego.error_cambiar_estado'],
            toastActivado:    data.t['admin.juego.toast_activado'],
            toastDesactivado: data.t['admin.juego.toast_desactivado'],
            desactivar:       data.t['admin.juego.desactivar'],
            activar:          data.t['admin.juego.activar'],
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

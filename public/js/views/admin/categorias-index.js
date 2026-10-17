// views/admin/categorias/index.php - borrado y ordenacion de categorias
(function () {
    'use strict';

    const data = JSON.parse(
        (document.getElementById('rv-data-admin-categorias-index') || {}).textContent || '{}'
    );

    const csrfToken = (document.querySelector('meta[name="csrf-token"]') || {}).content || '';

    const busquedaEl = document.getElementById('cat-busqueda');
    const activoEl   = document.getElementById('cat-activo');
    const btnLimpiar = document.getElementById('cat-limpiar');
    const resultsEl  = document.getElementById('categorias-results');
    const loadingEl  = document.getElementById('cat-loading');

    const textos = {
        tituloActivar:   data.t['admin.categoria.confirm_activar_titulo'],
        tituloDesactivar: data.t['admin.categoria.confirm_desactivar_titulo'],
        msgActivar:      data.t['admin.categoria.confirm_estado_activar'],
        msgDesactivar:   data.t['admin.categoria.confirm_estado_desactivar'],
        okActivar:       data.t['admin.categoria.si_activar'],
        okDesactivar:    data.t['admin.categoria.si_desactivar'],
        cancelar:        data.t['admin.categoria.cancelar'],
        errorDesconocido: data.t['admin.categoria.error_desconocido'],
        errorEstado:     data.t['admin.categoria.error_estado'],
        toastActivada:   data.t['admin.categoria.toast_activada'],
        toastDesactivada: data.t['admin.categoria.toast_desactivada'],
        desactivar:      data.t['admin.categoria.desactivar'],
        activar:         data.t['admin.categoria.activar'],
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

// views/admin/consolas/index.php - borrado y ordenacion de consolas
(function () {
    'use strict';

    const data = JSON.parse(
        (document.getElementById('rv-data-admin-consolas-index') || {}).textContent || '{}'
    );

    const csrfToken = (document.querySelector('meta[name="csrf-token"]') || {}).content || '';

    const busquedaEl = document.getElementById('cs-busqueda');
    const activoEl   = document.getElementById('cs-activo');
    const btnLimpiar = document.getElementById('cs-limpiar');
    const resultsEl  = document.getElementById('consolas-results');
    const loadingEl  = document.getElementById('cs-loading');

    const textos = {
        tituloActivar:    data.t['admin.consola.confirm_activar_titulo'],
        tituloDesactivar: data.t['admin.consola.confirm_desactivar_titulo'],
        msgActivar:       data.t['admin.consola.confirm_estado_activar'],
        msgDesactivar:    data.t['admin.consola.confirm_estado_desactivar'],
        tituloEmuActivar:    data.t['admin.consola.confirm_emulacion_activar_titulo'],
        tituloEmuDesactivar: data.t['admin.consola.confirm_emulacion_desactivar_titulo'],
        msgEmuActivar:       data.t['admin.consola.confirm_emulacion_activar'],
        msgEmuDesactivar:    data.t['admin.consola.confirm_emulacion_desactivar'],
        okActivar:        data.t['admin.consola.si_activar'],
        okDesactivar:     data.t['admin.consola.si_desactivar'],
        cancelar:         data.t['admin.consola.cancelar'],
        errorDesconocido: data.t['admin.consola.error_desconocido'],
        errorEstado:      data.t['admin.consola.error_estado'],
        toastActivada:    data.t['admin.consola.toast_activada'],
        toastDesactivada: data.t['admin.consola.toast_desactivada'],
        toastEmuActivada:    data.t['admin.consola.toast_emulacion_activada'],
        toastEmuDesactivada: data.t['admin.consola.toast_emulacion_desactivada'],
        desactivar:       data.t['admin.consola.desactivar'],
        activar:          data.t['admin.consola.activar'],
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
        if (!page || page === 1) p.delete('page');
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

        const uiQs = getUiParams(page).toString();
        history.replaceState(null, '', uiQs ? '?' + uiQs : location.pathname);

        try {
            const res = await fetch('ajax_consola.php?' + getAjaxParams(page).toString(), {
                signal: currentRequest.signal
            });
            if (!res.ok) throw new Error('HTTP ' + res.status);
            resultsEl.innerHTML = await res.text();
            bindPagination();
        } catch (err) {
            if (err.name !== 'AbortError') {
                window.location.href = uiQs ? '?' + uiQs : location.pathname;
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

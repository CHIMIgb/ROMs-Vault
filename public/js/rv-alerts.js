/**
 * rv-alerts.js — Sistema de alertas ROMs Vault
 * ================================================
 * USO RÁPIDO:
 *
 * // Modal de confirmación
 * RVAlerts.confirm({
 *     tipo:      'warning',           // 'warning' | 'danger' | 'success' | 'info'
 *     titulo:    '¿Desactivar juego?',
 *     mensaje:   'El juego quedará oculto del catálogo.',
 *     btnOk:     'Sí, desactivar',
 *     btnCancel: 'Cancelar',
 *     onOk:      () => { window.location.href = url; }
 * });
 *
 * // Toast / notificación flotante
 * RVAlerts.toast('Cambio guardado correctamente', 'success');
 * RVAlerts.toast('Ha ocurrido un error', 'danger');
 *
 * // Alerta inline (reemplaza div.rv-alert existente o crea uno nuevo)
 * RVAlerts.inline('#mi-contenedor', 'Datos guardados', 'success');
 * RVAlerts.inline('#mi-contenedor', 'Campo requerido', 'error');
 *
 * Tipos disponibles: 'success' | 'warning' | 'danger' | 'error' | 'info'
 * ('error' es alias de 'danger' para compatibilidad con clases CSS legacy)
 * ================================================
 */
(function (global) {
    'use strict';

    // ── Iconos por tipo ───────────────────────────────────────────────────
    const ICONS = {
        success : '✔',
        warning : '⚠',
        danger  : '✖',
        error   : '✖',
        info    : 'ℹ',
    };

    // Normaliza 'error' → 'danger' para las clases CSS
    function normTipo(t) {
        return t === 'error' ? 'danger' : (t || 'info');
    }

    // ─────────────────────────────────────────────────────────────────────
    //  MODAL DE CONFIRMACIÓN
    // ─────────────────────────────────────────────────────────────────────
    let _modalOverlay  = null;
    let _modalBox      = null;
    let _modalOnOk     = null;
    let _modalOnCancel = null;

    function buildModal() {
        if (_modalOverlay) return;

        _modalOverlay = document.createElement('div');
        _modalOverlay.id        = 'rv-modal-overlay';
        _modalOverlay.className = 'rv-modal-overlay';
        _modalOverlay.setAttribute('role', 'dialog');
        _modalOverlay.setAttribute('aria-modal', 'true');
        _modalOverlay.setAttribute('aria-labelledby', 'rv-modal-title');
        _modalOverlay.style.display = 'none';

        _modalOverlay.innerHTML = `
            <div class="rv-modal-box" id="rv-modal-box">
                <div class="rv-modal-header" id="rv-modal-header">
                    <span class="rv-modal-icon" id="rv-modal-icon"></span>
                    <h3  class="rv-modal-title" id="rv-modal-title"></h3>
                </div>
                <div class="rv-modal-body" id="rv-modal-body"></div>
                <div class="rv-modal-footer">
                    <button class="rv-btn rv-btn-cancel" id="rv-btn-cancel"></button>
                    <button class="rv-btn rv-btn-ok"     id="rv-btn-ok"></button>
                </div>
            </div>`;

        document.body.appendChild(_modalOverlay);
        _modalBox = _modalOverlay.querySelector('#rv-modal-box');

        // Cerrar al hacer clic en el overlay
        _modalOverlay.addEventListener('click', e => {
            if (e.target === _modalOverlay) closeModal(false);
        });

        // Cerrar con Escape
        document.addEventListener('keydown', e => {
            if (e.key === 'Escape' && _modalOverlay.style.display !== 'none') {
                closeModal(false);
            }
        });

        _modalOverlay.querySelector('#rv-btn-ok').addEventListener('click',     () => closeModal(true));
        _modalOverlay.querySelector('#rv-btn-cancel').addEventListener('click', () => closeModal(false));
    }

    function openConfirm({ tipo = 'info', titulo, mensaje, btnOk = 'Aceptar', btnCancel = 'Cancelar', onOk, onCancel } = {}) {
        buildModal();

        const t = normTipo(tipo);
        _modalOnOk     = onOk     || null;
        _modalOnCancel = onCancel || null;

        _modalBox.className = 'rv-modal-box rv-modal--' + t;
        _modalOverlay.querySelector('#rv-modal-icon').textContent  = ICONS[t] || 'ℹ';
        _modalOverlay.querySelector('#rv-modal-title').textContent = titulo  || '';
        _modalOverlay.querySelector('#rv-modal-body').innerHTML    = mensaje || '';
        _modalOverlay.querySelector('#rv-btn-ok').textContent      = btnOk;
        _modalOverlay.querySelector('#rv-btn-ok').className        = 'rv-btn rv-btn-ok rv-btn--' + t;
        _modalOverlay.querySelector('#rv-btn-cancel').textContent  = btnCancel;

        _modalOverlay.style.display = 'flex';
        void _modalBox.offsetWidth; // reflow para animación
        _modalBox.classList.add('rv-modal--open');
        document.body.style.overflow = 'hidden';

        // Foco en cancelar para evitar confirmaciones accidentales
        _modalOverlay.querySelector('#rv-btn-cancel').focus();
    }

    function closeModal(accepted) {
        if (!_modalBox) return;
        _modalBox.classList.remove('rv-modal--open');
        _modalBox.classList.add('rv-modal--close');

        setTimeout(() => {
            _modalOverlay.style.display = 'none';
            _modalBox.classList.remove('rv-modal--close');
            document.body.style.overflow = '';
            if (accepted  && typeof _modalOnOk     === 'function') _modalOnOk();
            if (!accepted && typeof _modalOnCancel === 'function') _modalOnCancel();
            _modalOnOk = _modalOnCancel = null;
        }, 220);
    }

    // ─────────────────────────────────────────────────────────────────────
    //  TOAST — notificación flotante
    // ─────────────────────────────────────────────────────────────────────
    let _toastContainer = null;

    function getToastContainer() {
        if (_toastContainer) return _toastContainer;
        _toastContainer = document.getElementById('rv-toast-container');
        if (!_toastContainer) {
            _toastContainer = document.createElement('div');
            _toastContainer.id        = 'rv-toast-container';
            _toastContainer.className = 'rv-toast-container';
            _toastContainer.setAttribute('aria-live', 'polite');
            document.body.appendChild(_toastContainer);
        }
        return _toastContainer;
    }

    function showToast(mensaje, tipo = 'info', duracion = 3500) {
        const t   = normTipo(tipo);
        const cnt = getToastContainer();

        const el = document.createElement('div');
        el.className = 'rv-toast rv-toast--' + t;
        el.innerHTML = `
            <span class="rv-toast-icon">${ICONS[t] || 'ℹ'}</span>
            <span class="rv-toast-msg">${escHtml(mensaje)}</span>
            <button class="rv-toast-close" aria-label="Cerrar">✕</button>`;

        cnt.appendChild(el);
        void el.offsetWidth;
        el.classList.add('rv-toast--visible');

        const remove = () => {
            el.classList.remove('rv-toast--visible');
            el.classList.add('rv-toast--hide');
            setTimeout(() => el.remove(), 300);
        };

        el.querySelector('.rv-toast-close').addEventListener('click', remove);
        setTimeout(remove, duracion);
    }

    // ─────────────────────────────────────────────────────────────────────
    //  INLINE — reemplaza una alerta estática en el DOM
    //  Uso: RVAlerts.inline('#contenedor', 'Mensaje', 'success')
    //  Si ya existe un .rv-inline-alert dentro, lo reemplaza.
    //  Si se pasa mensaje vacío o null, elimina la alerta existente.
    // ─────────────────────────────────────────────────────────────────────
    function showInline(selector, mensaje, tipo = 'info') {
        const container = typeof selector === 'string'
            ? document.querySelector(selector)
            : selector;
        if (!container) return;

        // Eliminar alerta previa si existe
        const prev = container.querySelector('.rv-inline-alert');
        if (prev) prev.remove();

        if (!mensaje) return;

        const t   = normTipo(tipo);
        const el  = document.createElement('div');
        el.className   = 'rv-inline-alert rv-inline--' + t;
        el.setAttribute('role', 'alert');
        el.innerHTML = `
            <span class="rv-inline-icon">${ICONS[t] || 'ℹ'}</span>
            <span class="rv-inline-msg">${escHtml(mensaje)}</span>
            <button class="rv-inline-close" aria-label="Cerrar">✕</button>`;

        // Insertar al principio del contenedor
        container.insertBefore(el, container.firstChild);

        void el.offsetWidth;
        el.classList.add('rv-inline--visible');

        el.querySelector('.rv-inline-close').addEventListener('click', () => {
            el.classList.add('rv-inline--hide');
            setTimeout(() => el.remove(), 250);
        });
    }

    // ─────────────────────────────────────────────────────────────────────
    //  Helper privado
    // ─────────────────────────────────────────────────────────────────────
    function escHtml(str) {
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    // ─────────────────────────────────────────────────────────────────────
    //  API pública
    // ─────────────────────────────────────────────────────────────────────
    global.RVAlerts = {
        confirm : openConfirm,
        toast   : showToast,
        inline  : showInline,
    };

})(window);

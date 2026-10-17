// views/home/index.js - filtros del catalogo y autocompletado
(function () {
    'use strict';

    const data = JSON.parse(
        (document.getElementById('rv-data-home-index') || {}).textContent || '{}'
    );

    const busquedaEl  = document.getElementById('f-busqueda');
    const consolaEl   = document.getElementById('f-consola');
    const categoriaEl = document.getElementById('f-categoria');
    const regionEl    = document.getElementById('f-region');
    const ordenEl     = document.getElementById('f-orden');
    const btnLimpiar  = document.getElementById('btn-limpiar');
    const resultsEl   = document.getElementById('catalog-results');
    const loadingEl   = document.getElementById('filter-loading');

    let debounceTimer  = null;
    let currentRequest = null;

    // ── Precarga en segundo plano de la página siguiente ─────────────────
    // El HTML de la página siguiente se descarga con prioridad baja cuando el
    // usuario se acerca al final (o el navegador queda inactivo); sus imágenes
    // se precargan en segundo plano para que, al navegar, todo esté en caché
    // y la transición sea instantánea (sin ráfagas de descarga al pintar).
    let prefetchedHtml    = null;
    let prefetchedParams  = null;
    let prefetchScheduled = false;
    let prefetchVersion   = 0;
    let prefetchObserver  = null;

    function getAjaxParams(page) {
        const p = new URLSearchParams();
        p.set('page', page || 1);
        const b = busquedaEl.value.trim();
        if (b)                 p.set('busqueda',  b);
        if (consolaEl.value)   p.set('consola',   consolaEl.value);
        if (categoriaEl.value) p.set('categoria', categoriaEl.value);
        if (regionEl.value)    p.set('region',    regionEl.value);
        if (ordenEl.value)     p.set('orden',     ordenEl.value);
        return p;
    }

    function getUiParams(page) {
        const p = getAjaxParams(page);
        if (!page || page === 1) p.delete('page');
        if (p.get('orden') === 'random') p.delete('orden');
        return p;
    }

    function updateLimpiar() {
        const has = busquedaEl.value.trim() || consolaEl.value || categoriaEl.value || regionEl.value
            || ordenEl.value !== 'random';
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
            const paramsStr = getAjaxParams(page).toString();
            let html = null;

            // Si esta página ya se precargó en segundo plano, pintarla sin descargar
            if (prefetchedParams === paramsStr && prefetchedHtml !== null) {
                html = prefetchedHtml;
                prefetchedHtml   = null;
                prefetchedParams = null;
            } else {
                const res = await fetch('ajax_catalog.php?' + paramsStr, {
                    signal: currentRequest.signal
                });
                if (!res.ok) throw new Error('HTTP ' + res.status);
                html = await res.text();
            }

            resultsEl.innerHTML = html;
            bindPagination();
            setupPrefetch(page);

            const top = resultsEl.getBoundingClientRect().top + window.scrollY - 80;
            if (window.scrollY > top) window.scrollTo({ top, behavior: 'smooth' });
        } catch (err) {
            if (err.name !== 'AbortError') {
                resultsEl.innerHTML = '<div class="alert alert-error">' + data.t['home.error_carga'] + '</div>';
            }
        } finally {
            loadingEl.style.display = 'none';
            resultsEl.style.opacity = '';
            resultsEl.style.pointerEvents = '';
        }
    }

    // ── PRECARGA EN SEGUNDO PLANO DE LA PÁGINA SIGUIENTE ─────────────────
    // Objetivo: el usuario navega páginas sin notar cargas. La página N+1 se
    // descarga con prioridad baja y sus imágenes se precargan en background
    // (en batches para no saturar la conexión). Al pasar a esa página, el
    // HTML ya está listo y las imágenes en caché → transición instantánea.

    function extractImageUrls(html) {
        const doc = new DOMParser().parseFromString(html, 'text/html');
        const urls = [];
        doc.querySelectorAll('img').forEach(img => {
            const src = img.getAttribute('src');
            if (src) urls.push(src);
        });
        return urls;
    }

    function preloadImagesInBackground(urls) {
        // Batches de 4 con pausa: carga progresiva, sin ráfagas
        const batch = 4;
        const delay = 150;
        const apply = from => {
            urls.slice(from, from + batch).forEach(url => {
                const img = new Image();
                if ('fetchPriority' in img) img.fetchPriority = 'low';
                img.src = url;
            });
            if (from + batch < urls.length) {
                setTimeout(() => apply(from + batch), delay);
            }
        };
        apply(0);
    }

    function prefetchNextPage(page) {
        if (prefetchScheduled) return;
        // Solo precargar si existe la página solicitada en la paginación actual
        if (!resultsEl.querySelector('.pagination-link[data-page="' + page + '"]')) return;

        prefetchScheduled = true;
        const v = prefetchVersion;
        const params = getAjaxParams(page);

        fetch('ajax_catalog.php?' + params.toString(), { priority: 'low' })
            .then(res => {
                if (!res.ok) throw new Error('HTTP ' + res.status);
                return res.text();
            })
            .then(html => {
                // Descartar respuestas obsoletas si el usuario cambió de filtros
                if (v !== prefetchVersion) return;
                prefetchedHtml   = html;
                prefetchedParams = params.toString();
                preloadImagesInBackground(extractImageUrls(html));
            })
            .catch(() => {
                prefetchScheduled = false; // reintentar en la siguiente pasada
            });
    }

    function setupPrefetch(page) {
        prefetchVersion++;      // invalida prefetches en vuelo de filtros viejos
        prefetchScheduled = false;
        const next = page + 1;

        // No hay página siguiente → no hacer nada
        if (!resultsEl.querySelector('.pagination-link[data-page="' + next + '"]')) return;

        // 1) Progresivo: cuando la última tarjeta se acerca al viewport
        const cards = resultsEl.querySelectorAll('.game-card');
        if (cards.length) {
            const last = cards[cards.length - 1];
            if (prefetchObserver) prefetchObserver.disconnect();
            prefetchObserver = new IntersectionObserver(entries => {
                entries.forEach(e => {
                    if (e.isIntersecting) {
                        prefetchNextPage(next);
                        prefetchObserver.disconnect();
                    }
                });
            }, { rootMargin: '800px 0px' });
            prefetchObserver.observe(last);
        }

        // 2) Refuerzo: si el navegador queda inactivo, precargar igualmente
        //    (segundo plano, prioridad baja — el usuario no nota la carga)
        const startPrefetch = () => prefetchNextPage(next);
        if (typeof requestIdleCallback === 'function') {
            requestIdleCallback(startPrefetch, { timeout: 4000 });
        } else {
            setTimeout(startPrefetch, 1500);
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
        ordenEl.value     = 'random'; // limpiar = estado inicial del index (URL limpia y orden aleatorio)
        updateLimpiar();
        fetchResults(1);
    });

    // Botón "Limpiar" del estado vacío (EmptyState): misma acción que #btn-limpiar.
    // Delegación en resultsEl para que funcione tras cada repintado por AJAX.
    resultsEl.addEventListener('click', e => {
        if (e.target.closest('.empty-state-clear')) btnLimpiar.click();
    });

    busquedaEl.addEventListener('input',   scheduleSearch);
    consolaEl.addEventListener('change',   onSelectChange);
    categoriaEl.addEventListener('change', onSelectChange);
    regionEl.addEventListener('change',    onSelectChange);
    ordenEl.addEventListener('change',     onSelectChange);

    bindPagination();
    updateLimpiar();

    // Precargar en segundo plano la página 2 desde la carga inicial
    setupPrefetch(1);

    // ── AUTOCOMPLETADO ────────────────────────────────────────────────────
    const acList        = document.getElementById('autocomplete-list');
    let   acTimer       = null;
    let   acRequest     = null;
    let   acIndex       = -1;
    let   acItems       = [];

    function closeAC() {
        acList.innerHTML = '';
        acList.style.display = 'none';
        busquedaEl.setAttribute('aria-expanded', 'false');
        acIndex = -1;
    }

    function renderAC(items) {
        acItems = items;
        acList.innerHTML = '';
        acIndex = -1;

        if (!items.length) { closeAC(); return; }

        items.forEach((item, i) => {
            const li = document.createElement('li');
            li.className  = 'autocomplete-item';
            li.setAttribute('role', 'option');
            li.setAttribute('id', 'ac-item-' + i);
            li.innerHTML  = `
                <div class="ac-cover">
                    ${item.imagen
                        ? `<img src="${item.imagen}" alt="" loading="lazy">`
                        : '<i data-i="disc" data-cls="pxi-cover-placeholder"></i>'
                    }
                </div>
                <div class="ac-text">
                    <span class="ac-titulo">${escHtml(item.titulo)}</span>
                    <span class="ac-consola">${escHtml(item.consola)}</span>
                </div>
                <div class="ac-actions">
                    ${item.emulacion_online
                        ? `<a href="${escHtml(item.play_url)}" class="ac-btn-play" title="${data.t['home.jugar_online']}"><i data-i="play"></i></a>`
                        : ''
                    }
                    <a href="${escHtml(item.download_url)}" class="ac-btn-dl" title="${data.t['home.descargar']}" target="_blank"><i data-i="download"></i></a>
                </div>`;

            li.addEventListener('mousedown', e => {
                if (e.target.closest('a')) return;
                busquedaEl.value = item.titulo;
                closeAC();
                fetchResults(1);
            });

            acList.appendChild(li);
        });

        acList.style.display = 'block';
        busquedaEl.setAttribute('aria-expanded', 'true');
    }

    function setActiveItem(idx) {
        const all = acList.querySelectorAll('.autocomplete-item');
        all.forEach((el, i) => el.classList.toggle('autocomplete-item--active', i === idx));
        if (all[idx]) {
            busquedaEl.setAttribute('aria-activedescendant', 'ac-item-' + idx);
            all[idx].scrollIntoView({ block: 'nearest' });
        }
    }

    function escHtml(str) {
        return str.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
    }

    async function fetchAC(term) {
        if (acRequest) acRequest.abort();
        acRequest = new AbortController();
        try {
            const res  = await fetch('ajax_autocomplete.php?q=' + encodeURIComponent(term), {
                signal: acRequest.signal
            });
            if (!res.ok) throw new Error();
            const data = await res.json();
            renderAC(Array.isArray(data) ? data : []);
        } catch (e) {
            if (e.name !== 'AbortError') closeAC();
        }
    }

    busquedaEl.addEventListener('input', () => {
        clearTimeout(acTimer);
        const val = busquedaEl.value.trim();
        if (val.length < 2) { closeAC(); return; }
        acTimer = setTimeout(() => fetchAC(val), 220);
    });

    busquedaEl.addEventListener('keydown', e => {
        if (!acItems.length) return;
        if (e.key === 'ArrowDown') {
            e.preventDefault();
            acIndex = Math.min(acIndex + 1, acItems.length - 1);
            setActiveItem(acIndex);
        } else if (e.key === 'ArrowUp') {
            e.preventDefault();
            acIndex = Math.max(acIndex - 1, -1);
            setActiveItem(acIndex);
        } else if (e.key === 'Enter' && acIndex >= 0) {
            e.preventDefault();
            busquedaEl.value = acItems[acIndex].titulo;
            closeAC();
            fetchResults(1);
        } else if (e.key === 'Escape') {
            closeAC();
        }
    });

    busquedaEl.addEventListener('blur', () => {
        setTimeout(closeAC, 180);
    });

})();

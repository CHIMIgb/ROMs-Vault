// views/home/play-emulator.js - arranque de EmulatorJS, precarga y errores del proxy
(function () {
    const data = JSON.parse(
        (document.getElementById('rv-data-play-emulator') || {}).textContent || '{}'
    );

    // ── Configuración base ────────────────────────────────────────────────
    window.EJS_player = '#game';
    window.EJS_core = data.core;
    window.EJS_gameName = data.gameName;
    window.EJS_gameUrl = data.gameUrl;
    window.EJS_pathtodata = data.pathtodata;
    window.EJS_color = '#c0392b';
    window.EJS_startOnLoaded = false;
    window.EJS_backgroundColor = '#1a1a1a';
    window.EJS_language = data.language;
    window.EJS_Buttons = {
        saveState: true,
        loadState: true,
        fullscreen: false,
        screenshot: true,
        cacheManager: false,
    };

    // BIOS requerido por este core (PS1, etc.)
    if (data.biosUrl) {
        window.EJS_biosUrl = data.biosUrl;
    }

    // Threads habilitados por los headers COOP/COEP que emite el
    // controlador (ahora: N64, PSP, DOSBox — se añaden cores de uno
    // en uno tras probar). Si el navegador no expone SharedArrayBuffer,
    // EmulatorJS usa single-thread sin romper.
    if (data.needsThreads) {
        window.EJS_threads = true;
    }

    // ── Flags de comportamiento ───────────────────────────────────────────
    const modoStreaming = !!data.modoStreaming;
    const totalBytes = data.totalBytes;

    // ── Carga el loader de EmulatorJS en el DOM ───────────────────────────
    function loadEmulatorJS() {
        const s = document.createElement('script');
        s.src = data.loaderUrl;
        s.async = true;
        s.onerror = () => {
            console.error('[EmulatorJS] No se pudo cargar loader.js — verifica tu conexión.');
        };
        document.body.appendChild(s);
    }

    // ── MODO STREAMING: PSP, PS1, Saturn, etc. ────────────────────────────
    // La ROM es demasiado grande para cargarla en memoria del navegador.
    // EmulatorJS recibe la URL del proxy y él mismo gestiona los Range Requests
    // para ir leyendo la ISO en bloques conforme los necesita.
    // ── Aspect ratio automático según consola ────────────────────────────
    // PSP → 16:9 (480x272 nativa)
    // El resto de consolas retro → 4:3
    const container = document.getElementById('emulator-container');
    const coreActual = container.dataset.core;
    const cores16x9 = ['ppsspp', 'psp'];   // PSP → 16:9 (480×272)
    const ratio = cores16x9.includes(coreActual) ? '16 / 9' : '4 / 3';
    container.style.aspectRatio = ratio;

    // ── Slider de tamaño de pantalla ──────────────────────────────────────
    const slider = document.getElementById('emulator-size-slider');
    const sizeLabel = document.getElementById('emulator-size-value');

    function applySize(pct) {
        container.style.width = pct + '%';
        container.style.marginLeft = 'auto';
        container.style.marginRight = 'auto';
        sizeLabel.textContent = pct + '%';
        // Guardar preferencia en localStorage
        try { localStorage.setItem('ejs_screen_size', pct); } catch (e) { }
    }

    // Recuperar preferencia guardada
    try {
        const saved = localStorage.getItem('ejs_screen_size');
        if (saved) {
            slider.value = saved;
            applySize(parseInt(saved));
        } else {
            applySize(parseInt(slider.value));
        }
    } catch (e) {
        applySize(parseInt(slider.value));
    }

    slider.addEventListener('input', () => applySize(parseInt(slider.value)));

    if (modoStreaming) {
        loadEmulatorJS();
        return;
    }

    // ── MODO PRECARGA: NES, SNES, GBA, N64, MD, etc. ─────────────────────
    // ROMs pequeñas (<64 MB): las descargamos completas en memoria,
    // las convertimos en Object URL y se las pasamos a EmulatorJS.
    // Esto evita problemas de CORS en navegadores estrictos y da carga instantánea.

    const loadBar = document.getElementById('proxy-load-bar');
    const loadFill = document.getElementById('proxy-load-fill');
    const loadPct = document.getElementById('proxy-load-pct');
    const loadText = document.getElementById('proxy-load-text');
    const loadDetail = document.getElementById('proxy-load-detail');
    const romUrl = window.EJS_gameUrl;

    function fmtBytes(b) {
        if (b >= 1073741824) return (b / 1073741824).toFixed(2) + ' GB';
        if (b >= 1048576) return (b / 1048576).toFixed(1) + ' MB';
        if (b >= 1024) return Math.round(b / 1024) + ' KB';
        return b + ' B';
    }

    async function preloadRom() {
        loadBar.style.display = 'block';
        try {
            const response = await fetch(romUrl);
            if (!response.ok) throw new Error('HTTP ' + response.status + ' — ' + response.statusText);

            const contentLength = parseInt(response.headers.get('Content-Length') || String(totalBytes), 10);
            const hasLength = contentLength > 0;
            const reader = response.body.getReader();
            const chunks = [];
            let received = 0;

            while (true) {
                const { done, value } = await reader.read();
                if (done) break;
                chunks.push(value);
                received += value.length;

                if (hasLength) {
                    const pct = Math.min(Math.round((received / contentLength) * 100), 99);
                    loadFill.style.width = pct + '%';
                    loadPct.textContent = pct + '%';
                    loadDetail.textContent = fmtBytes(received) + ' / ' + fmtBytes(contentLength);
                } else {
                    loadFill.classList.add('indeterminate');
                    loadPct.textContent = '…';
                    loadDetail.textContent = fmtBytes(received) + ' ' + data.t['play.recibidos'].replace('{valor}', '');
                }
            }

            loadFill.style.width = '100%';
            loadFill.classList.remove('indeterminate');
            loadPct.textContent = '100%';
            loadText.innerHTML = '<svg viewBox="0 0 24 24" fill="currentColor" class="pxi" width="14" height="14"><path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z"/></svg> ' + data.t['play.rom_cargada'];
            loadDetail.textContent = data.t['play.procesando'].replace('{valor}', fmtBytes(received));

            window.EJS_gameUrl = URL.createObjectURL(new Blob(chunks));

            await new Promise(r => setTimeout(r, 500));
            loadBar.style.display = 'none';

        } catch (err) {
            loadFill.style.background = 'var(--red)';
            loadFill.style.width = '100%';
            console.error('[proxy preload]', err);

            // Intentar leer el JSON de error del proxy para mostrar diagnóstico
            let errorType = 'network';
            let errorMsg = err.message;

            // Si el error viene de un HTTP no-OK, intentar parsear la respuesta JSON
            try {
                const errRes = await fetch(romUrl);
                if (!errRes.ok) {
                    const ct = errRes.headers.get('Content-Type') || '';
                    if (ct.includes('json')) {
                        const data = await errRes.json();
                        errorType = data.error_type || 'network';
                        errorMsg = data.error || errorMsg;
                    }
                }
            } catch (_) { /* ignorar errores al releer */ }

            // Mostrar página de error amigable en vez del emulador
            const iconMap = {
                not_found: ['<i data-i="trash"></i>', data.t['play.error_archivo_no_encontrado']],
                private: ['<i data-i="lock"></i>', data.t['play.error_archivo_privado']],
                quota: ['<i data-i="clock"></i>', data.t['play.error_limite_descargas']],
                network: ['<i data-i="wifi"></i>', data.t['play.error_conexion']],
            };
            const tipMap = {
                not_found: data.t['play.consejo_archivo_no_encontrado_js'],
                private: data.t['play.consejo_archivo_privado_js'],
                quota: data.t['play.consejo_limite_descargas_js'],
                network: data.t['play.consejo_conexion'],
            };
            const [icon, titulo] = iconMap[errorType] || ['<i data-i="warning"></i>', data.t['play.error_cargar_rom']];
            const consejo = tipMap[errorType] || data.t['play.consejo_reintentar'];
            // URL de descarga firmada por el servidor (inyectada desde PHP)
            const signedDownloadUrl = data.downloadUrl;

            document.querySelector('.emulator-wrapper').innerHTML = `
        <div class="proxy-error-page">
            <div class="proxy-error-icon">${icon}</div>
            <h2 class="proxy-error-title">${titulo}</h2>
            <p class="proxy-error-msg">${errorMsg}</p>
            <div class="proxy-error-tip">
                <span class="proxy-tip-label"><i data-i="lightbulb"></i> ${data.t['play.que_puedo_hacer']}</span>
                ${consejo}
            </div>
            <div class="proxy-error-actions">
                ${errorType !== 'quota'
                ? `<a href="${signedDownloadUrl}"
                           class="btn-download-big" target="_blank"><i data-i="download"></i> ${data.t['play.descargar_rom']}</a>`
                : `<a href="javascript:location.reload()" class="btn-retry"><i data-i="reload"></i> ${data.t['play.reintentar']}</a>`
            }
                <a href="/" class="btn-back-catalog"><i data-i="arrow-left"></i> ${data.t['play.volver_catalogo']}</a>
            </div>
            <details class="proxy-error-details">
                <summary>${data.t['play.detalles_tecnicos']}</summary>
                <div class="proxy-error-technical">
                    <div><span>${data.t['play.error_label']}</span> <code>${errorType}</code></div>
                    <div><span>${data.t['play.mensaje']}</span> <code>${errorMsg}</code></div>
                </div>
            </details>
        </div>`;
            return; // No cargar EmulatorJS
        }

        loadEmulatorJS();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', preloadRom);
    } else {
        preloadRom();
    }

})();

<?php
// views/home/play.php
// Variables: $juego, $core, $romUrl, $biosUrl, $needsThreads, $modoStreaming, $error
?>

<!-- Breadcrumb / Back -->
<div class="play-nav">
    <a href="index.php?controller=home&action=index" class="btn-back">
        ← Volver al Catálogo
    </a>
    <span class="play-nav-sep">|</span>
    <span class="play-nav-title">
        <?= htmlspecialchars($juego['titulo']) ?>
        &nbsp;<span class="play-nav-platform">[<?= htmlspecialchars($juego['consola_nombre'] ?? 'Unknown') ?>]</span>
    </span>
</div>

<?php
// ── Determinar si hay cualquier tipo de error que bloquee el emulador ────────
$hayError   = !empty($error) || !empty($proxyError);
$errorType  = $proxyError['type']    ?? 'unsupported';
$errorMsg   = !empty($proxyError)    ? $proxyError['message'] : ($error ?? '');
?>

<?php if ($hayError): ?>
<?php
// Icono y título según tipo
$iconMap  = [
    'not_found'   => ['🗑️',  'Archivo no encontrado'],
    'private'     => ['🔒',  'Archivo privado o bloqueado'],
    'quota'       => ['⏳',  'Límite de descargas alcanzado'],
    'network'     => ['📡',  'Error de conexión con Google Drive'],
    'unsupported' => ['⚙️',  'Emulación no disponible'],
];
[$icon, $titulo] = $iconMap[$errorType] ?? ['⚠️', 'Error desconocido'];

// Consejo específico por tipo
$consejo = match($errorType) {
    'not_found'   => 'El administrador del sitio tendrá que actualizar el enlace de Google Drive para este juego.',
    'private'     => 'Puede que el propietario del archivo haya cambiado sus permisos. El equipo del sitio puede solucionarlo.',
    'quota'       => 'Google Drive limita el número de descargas de archivos grandes. Vuelve a intentarlo en 24 horas o descarga la ROM directamente.',
    'network'     => 'Esto suele ser temporal. Espera unos minutos e inténtalo de nuevo.',
    default       => 'Puedes descargar la ROM y ejecutarla con un emulador local en tu equipo.',
};
?>
<!-- ====== Error con diagnóstico ====== -->
<div class="proxy-error-page">

    <div class="proxy-error-icon"><?= $icon ?></div>
    <h2 class="proxy-error-title"><?= htmlspecialchars($titulo) ?></h2>
    <p class="proxy-error-msg"><?= $errorMsg /* puede contener <code> */ ?></p>

    <div class="proxy-error-tip">
        <span class="proxy-tip-label">💡 ¿Qué puedo hacer?</span>
        <?= htmlspecialchars($consejo) ?>
    </div>

    <!-- Acciones disponibles -->
    <div class="proxy-error-actions">
        <?php if ($errorType !== 'quota'): ?>
        <a href="index.php?controller=home&action=download&file_id=<?= urlencode($juego['google_drive_file_id']) ?>"
           class="btn-download-big" target="_blank">
            ⬇ Descargar ROM
        </a>
        <?php endif; ?>
        <?php if ($errorType === 'quota'): ?>
        <a href="javascript:location.reload()" class="btn-retry">
            🔄 Reintentar ahora
        </a>
        <?php endif; ?>
        <a href="index.php?controller=home&action=index" class="btn-back-catalog">
            ← Volver al catálogo
        </a>
    </div>

    <!-- Detalles técnicos colapsables para el admin -->
    <details class="proxy-error-details">
        <summary>Detalles técnicos</summary>
        <div class="proxy-error-technical">
            <div><span>Juego:</span> <?= htmlspecialchars($juego['titulo']) ?></div>
            <div><span>Consola:</span> <?= htmlspecialchars($juego['consola_nombre'] ?? '—') ?></div>
            <div><span>File ID:</span> <code><?= htmlspecialchars($juego['google_drive_file_id']) ?></code></div>
            <div><span>Error:</span> <code><?= htmlspecialchars($errorType) ?></code></div>
        </div>
    </details>

</div>

<?php else: ?>
<!-- ====== Emulador ====== -->

<?php if ($core === 'psp'): ?>
<!-- Aviso experimental PSP -->
<div class="psp-warning">
    <strong>⚠️ PSP — Soporte experimental:</strong>
    El emulador de PSP en navegador es inestable. Juegos 3D complejos pueden no funcionar
    o ir lentos. Se recomienda Chrome o Edge en escritorio para mejores resultados.
</div>
<?php elseif ($core === 'psx'): ?>
<!-- Aviso experimental PSX -->
<div class="psp-warning">
    <strong>⚠️ PlayStation — Soporte experimental:</strong>
    El emulador de PS1 en navegador es inestable. Algunos juegos pueden presentar
    fallos gráficos, lentitud o no cargar correctamente.
    Se recomienda Chrome o Edge en escritorio para mejores resultados.
</div>
<?php endif; ?>

<div class="emulator-wrapper">

    <!-- Info panel -->
    <div class="emulator-info">
        <div class="emulator-game-meta">
            <?php if (!empty($juego['imagen'])): ?>
                <img src="<?= htmlspecialchars($juego['imagen']) ?>"
                     alt="<?= htmlspecialchars($juego['titulo']) ?>"
                     class="emulator-cover">
            <?php else: ?>
                <div class="emulator-cover emulator-cover--placeholder">📀</div>
            <?php endif; ?>

            <div class="emulator-game-details">
                <h2 class="emulator-game-title"><?= htmlspecialchars($juego['titulo']) ?></h2>

                <div class="emulator-tags">
                    <span class="game-tag platform"><?= htmlspecialchars($juego['consola_nombre'] ?? '') ?></span>
                    <?php if (!empty($juego['region'])): ?>
                        <span class="game-tag language"><?= htmlspecialchars($juego['region']) ?></span>
                    <?php endif; ?>
                    <?php if (!empty($juego['categoria_nombre'])): ?>
                        <span class="game-tag genre"><?= htmlspecialchars($juego['categoria_nombre']) ?></span>
                    <?php endif; ?>
                </div>

                <?php if (!empty($juego['descripcion'])): ?>
                    <p class="emulator-description"><?= htmlspecialchars($juego['descripcion']) ?></p>
                <?php endif; ?>

                <div class="emulator-meta-row">
                    <?php if (!empty($juego['size_bytes'])): ?>
                        <span class="emulator-meta-item">
                            💾 <?= number_format($juego['size_bytes'] / 1048576, 1) ?> MB
                        </span>
                    <?php endif; ?>
                    <?php if (!empty($juego['idiomas'])): ?>
                        <span class="emulator-meta-item">
                            🌐 <?= htmlspecialchars($juego['idiomas']) ?>
                        </span>
                    <?php endif; ?>
                </div>

                <div class="emulator-actions">
                    <a href="index.php?controller=home&action=download&file_id=<?= urlencode($juego['google_drive_file_id']) ?>"
                       class="btn-dl-small" target="_blank">⬇ Descargar ROM</a>
                    <button class="btn-fullscreen"
                            onclick="document.getElementById('emulator-container').requestFullscreen()">
                        ⛶ Pantalla completa
                    </button>
                </div>
            </div>
        </div>
    </div>

    <?php if (!$modoStreaming): ?>
    <!-- Barra de progreso — ROMs pequeñas (NES, SNES, GBA, N64…) -->
    <div id="proxy-load-bar" class="proxy-load-bar" style="display:none;">
        <div class="proxy-load-label">
            <span id="proxy-load-text">⏳ Cargando ROM desde el servidor…</span>
            <span id="proxy-load-pct">0%</span>
        </div>
        <div class="proxy-load-track">
            <div id="proxy-load-fill" class="proxy-load-fill"></div>
        </div>
        <div id="proxy-load-detail" class="proxy-load-detail">Conectando con Google Drive…</div>
    </div>
    <?php else: ?>
    <!-- Aviso streaming — ISOs grandes (PSP, PS1, Saturn…) -->
    <div class="proxy-streaming-notice">
        <span class="streaming-icon">📡</span>
        <span>
            Esta es una ISO grande<?php if (!empty($juego['size_bytes'])): ?>
                (<?= number_format($juego['size_bytes'] / 1048576, 0) ?> MB)<?php endif; ?>.
            Se cargará en streaming directo en el emulador.
            La carga inicial puede tardar unos segundos dependiendo de tu conexión.
        </span>
    </div>
    <?php endif; ?>

    <!-- Slider de tamaño de pantalla -->
    <div class="emulator-size-control">
        <span class="size-control-label">🖥️ Tamaño de pantalla</span>
        <div class="size-control-row">
            <span class="size-control-min">40%</span>
            <input type="range" id="emulator-size-slider"
                   min="40" max="100" value="80" step="5"
                   aria-label="Tamaño de la pantalla del emulador">
            <span class="size-control-max">100%</span>
        </div>
        <span id="emulator-size-value" class="size-control-value">80%</span>
    </div>

    <!-- Contenedor del emulador -->
    <div id="emulator-container" data-core="<?= htmlspecialchars($core) ?>">
        <div id="game"></div>
    </div>

    <!-- Controles de teclado -->
    <div class="emulator-controls-hint">
        <details>
            <summary>🎮 Controles por defecto (teclado)</summary>
            <div class="controls-grid">
                <div><strong>Mover</strong> — Flechas ↑ ↓ ← →</div>
                <div><strong>A / B / X / Y</strong> — Z / X / A / S</div>
                <div><strong>Start / Select</strong> — Enter / Shift</div>
                <div><strong>L / R</strong> — Q / W</div>
                <div><strong>Guardar estado</strong> — F2</div>
                <div><strong>Cargar estado</strong> — F4</div>
                <div><strong>Velocidad x2</strong> — Espacio (mantener)</div>
                <div><strong>Gamepad</strong> — Conecta un mando USB o Bluetooth</div>
            </div>
        </details>
    </div>

</div><!-- /.emulator-wrapper -->

<!-- ====== EmulatorJS ====================================================== -->
<script>
(function () {

    // ── Configuración base ────────────────────────────────────────────────
    window.EJS_player          = '#game';
    window.EJS_core            = '<?= addslashes($core) ?>';
    window.EJS_gameName        = '<?= addslashes(htmlspecialchars_decode($juego['titulo'])) ?>';
    window.EJS_gameUrl         = '<?= addslashes($romUrl) ?>';
    window.EJS_pathtodata      = 'https://cdn.emulatorjs.org/stable/data/';
    window.EJS_color           = '#c0392b';
    window.EJS_startOnLoaded   = false;
    window.EJS_backgroundColor = '#1a1a1a';
    window.EJS_Buttons = {
        saveState:    true,
        loadState:    true,
        fullscreen:   false,
        screenshot:   true,
        cacheManager: false,
    };

    <?php if (!empty($biosUrl)): ?>
    // BIOS requerido por este core (PS1, etc.)
    window.EJS_biosUrl = '<?= addslashes($biosUrl) ?>';
    <?php endif; ?>

    <?php if ($needsThreads): ?>
    // Threads requeridos (PSP, DOSBox) — necesita COOP/COEP headers en el servidor
    window.EJS_threads = true;
    <?php endif; ?>

    // ── Flags de comportamiento ───────────────────────────────────────────
    const modoStreaming = <?= $modoStreaming ? 'true' : 'false' ?>;
    const totalBytes    = <?= (int)($juego['size_bytes'] ?? 0) ?>;

    // ── Carga el loader de EmulatorJS en el DOM ───────────────────────────
    function loadEmulatorJS() {
        const s   = document.createElement('script');
        s.src     = 'https://cdn.emulatorjs.org/stable/data/loader.js';
        s.async   = true;
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
    const container   = document.getElementById('emulator-container');
    const coreActual  = container.dataset.core;
    const cores16x9   = ['ppsspp', 'psp'];   // PSP → 16:9 (480×272)
    const ratio       = cores16x9.includes(coreActual) ? '16 / 9' : '4 / 3';
    container.style.aspectRatio = ratio;

    // ── Slider de tamaño de pantalla ──────────────────────────────────────
    const slider     = document.getElementById('emulator-size-slider');
    const sizeLabel  = document.getElementById('emulator-size-value');

    function applySize(pct) {
        container.style.width     = pct + '%';
        container.style.marginLeft  = 'auto';
        container.style.marginRight = 'auto';
        sizeLabel.textContent     = pct + '%';
        // Guardar preferencia en localStorage
        try { localStorage.setItem('ejs_screen_size', pct); } catch(e) {}
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
    } catch(e) {
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

    const loadBar    = document.getElementById('proxy-load-bar');
    const loadFill   = document.getElementById('proxy-load-fill');
    const loadPct    = document.getElementById('proxy-load-pct');
    const loadText   = document.getElementById('proxy-load-text');
    const loadDetail = document.getElementById('proxy-load-detail');
    const romUrl     = window.EJS_gameUrl;

    function fmtBytes(b) {
        if (b >= 1073741824) return (b / 1073741824).toFixed(2) + ' GB';
        if (b >= 1048576)    return (b / 1048576).toFixed(1) + ' MB';
        if (b >= 1024)       return Math.round(b / 1024) + ' KB';
        return b + ' B';
    }

    async function preloadRom() {
        loadBar.style.display = 'block';
        try {
            const response = await fetch(romUrl);
            if (!response.ok) throw new Error('HTTP ' + response.status + ' — ' + response.statusText);

            const contentLength = parseInt(response.headers.get('Content-Length') || String(totalBytes), 10);
            const hasLength     = contentLength > 0;
            const reader        = response.body.getReader();
            const chunks        = [];
            let   received      = 0;

            while (true) {
                const { done, value } = await reader.read();
                if (done) break;
                chunks.push(value);
                received += value.length;

                if (hasLength) {
                    const pct = Math.min(Math.round((received / contentLength) * 100), 99);
                    loadFill.style.width   = pct + '%';
                    loadPct.textContent    = pct + '%';
                    loadDetail.textContent = fmtBytes(received) + ' / ' + fmtBytes(contentLength);
                } else {
                    loadFill.classList.add('indeterminate');
                    loadPct.textContent    = '…';
                    loadDetail.textContent = fmtBytes(received) + ' recibidos';
                }
            }

            loadFill.style.width   = '100%';
            loadFill.classList.remove('indeterminate');
            loadPct.textContent    = '100%';
            loadText.textContent   = '✅ ROM cargada — iniciando emulador…';
            loadDetail.textContent = 'Procesando ' + fmtBytes(received) + '…';

            window.EJS_gameUrl = URL.createObjectURL(new Blob(chunks));

            await new Promise(r => setTimeout(r, 500));
            loadBar.style.display = 'none';

        } catch (err) {
            loadFill.style.background = 'var(--red)';
            loadFill.style.width      = '100%';
            console.error('[proxy preload]', err);

            // Intentar leer el JSON de error del proxy para mostrar diagnóstico
            let errorType = 'network';
            let errorMsg  = err.message;

            // Si el error viene de un HTTP no-OK, intentar parsear la respuesta JSON
            try {
                const errRes = await fetch(romUrl);
                if (!errRes.ok) {
                    const ct = errRes.headers.get('Content-Type') || '';
                    if (ct.includes('json')) {
                        const data = await errRes.json();
                        errorType  = data.error_type || 'network';
                        errorMsg   = data.error      || errorMsg;
                    }
                }
            } catch (_) { /* ignorar errores al releer */ }

            // Mostrar página de error amigable en vez del emulador
            const iconMap = {
                not_found: ['🗑️',  'Archivo no encontrado'],
                private:   ['🔒',  'Archivo privado o bloqueado'],
                quota:     ['⏳',  'Límite de descargas alcanzado'],
                network:   ['📡',  'Error de conexión con Google Drive'],
            };
            const tipMap = {
                not_found: 'El administrador del sitio tendrá que actualizar el enlace de Google Drive para este juego.',
                private:   'Puede que el propietario haya cambiado los permisos. El equipo del sitio puede solucionarlo.',
                quota:     'Google Drive limita las descargas de archivos grandes. Vuelve a intentarlo en 24 horas.',
                network:   'Esto suele ser temporal. Espera unos minutos e inténtalo de nuevo.',
            };
            const [icon, titulo] = iconMap[errorType] || ['⚠️', 'Error al cargar la ROM'];
            const consejo        = tipMap[errorType]   || 'Inténtalo de nuevo o descarga la ROM.';
            const fileId         = romUrl.split('file_id=')[1] || '';

            document.querySelector('.emulator-wrapper').innerHTML = `
                <div class="proxy-error-page">
                    <div class="proxy-error-icon">${icon}</div>
                    <h2 class="proxy-error-title">${titulo}</h2>
                    <p class="proxy-error-msg">${errorMsg}</p>
                    <div class="proxy-error-tip">
                        <span class="proxy-tip-label">💡 ¿Qué puedo hacer?</span>
                        ${consejo}
                    </div>
                    <div class="proxy-error-actions">
                        ${errorType !== 'quota'
                            ? `<a href="index.php?controller=home&action=download&file_id=${encodeURIComponent(fileId)}"
                                  class="btn-download-big" target="_blank">⬇ Descargar ROM</a>`
                            : `<a href="javascript:location.reload()" class="btn-retry">🔄 Reintentar</a>`
                        }
                        <a href="index.php?controller=home&action=index" class="btn-back-catalog">← Volver al catálogo</a>
                    </div>
                    <details class="proxy-error-details">
                        <summary>Detalles técnicos</summary>
                        <div class="proxy-error-technical">
                            <div><span>Error:</span> <code>${errorType}</code></div>
                            <div><span>Mensaje:</span> <code>${errorMsg}</code></div>
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
</script>

<?php endif; ?>

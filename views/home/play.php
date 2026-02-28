<?php
// views/home/play.php
// Variables disponibles: $juego, $core, $romUrl, $error (opcional)
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

<?php if (!empty($error)): ?>
<!-- ====== Consola no soportada ====== -->
<div class="emulator-unsupported">
    <div class="unsupported-icon">⚠️</div>
    <h2 class="unsupported-title">Emulación no disponible</h2>
    <p class="unsupported-msg"><?= htmlspecialchars($error) ?></p>
    <p style="margin-bottom:1.5rem; color: var(--slate-mid);">
        Puedes descargar la ROM y ejecutarla con un emulador local.
    </p>
    <a href="index.php?controller=home&action=download&file_id=<?= urlencode($juego['google_drive_file_id']) ?>"
       class="btn-download-big" target="_blank">
        ⬇ Descargar ROM
    </a>
</div>

<?php else: ?>
<!-- ====== Emulador ====== -->
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

    <!-- Barra de progreso de carga de la ROM -->
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

    <!-- Contenedor del emulador -->
    <div id="emulator-container">
        <div id="game"></div>
    </div>

    <!-- Controles de teclado -->
    <div class="emulator-controls-hint">
        <details>
            <summary>🎮 Controles por defecto (teclado)</summary>
            <div class="controls-grid">
                <div><strong>Mover</strong> — Flechas ↑ ↓ ← →</div>
                <div><strong>A / B</strong> — Z / X</div>
                <div><strong>X / Y</strong> — A / S</div>
                <div><strong>Start / Select</strong> — Enter / Shift</div>
                <div><strong>L / R</strong> — Q / W</div>
                <div><strong>Guardar estado</strong> — F2</div>
                <div><strong>Cargar estado</strong> — F4</div>
                <div><strong>Velocidad x2</strong> — Espacio (mantener)</div>
                <div><strong>Gamepad</strong> — Conecta un mando USB o Bluetooth</div>
            </div>
        </details>
    </div>

</div>

<!-- ====== EmulatorJS — Configuración y carga con progreso ================= -->
<script>
(function () {
    // Configuración principal de EmulatorJS
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
    <?php if ($core === 'psp'): ?>
    window.EJS_threads = true;
    <?php endif; ?>

    // Referencias a los elementos de la barra de progreso
    const loadBar    = document.getElementById('proxy-load-bar');
    const loadFill   = document.getElementById('proxy-load-fill');
    const loadPct    = document.getElementById('proxy-load-pct');
    const loadText   = document.getElementById('proxy-load-text');
    const loadDetail = document.getElementById('proxy-load-detail');
    const romUrl     = window.EJS_gameUrl;
    const totalBytes = <?= (int)($juego['size_bytes'] ?? 0) ?>;

    function fmtBytes(b) {
        if (b >= 1073741824) return (b / 1073741824).toFixed(2) + ' GB';
        if (b >= 1048576)    return (b / 1048576).toFixed(1) + ' MB';
        if (b >= 1024)       return Math.round(b / 1024) + ' KB';
        return b + ' B';
    }

    // Carga el script de EmulatorJS en el DOM
    function loadEmulatorJS() {
        const s  = document.createElement('script');
        s.src    = 'https://cdn.emulatorjs.org/stable/data/loader.js';
        s.async  = true;
        s.onerror = () => console.error('[EmulatorJS] Fallo al cargar loader.js');
        document.body.appendChild(s);
    }

    // Descarga la ROM a través del proxy mostrando progreso real,
    // luego la convierte en una Object URL para que EmulatorJS la cargue sin red.
    async function preloadRom() {
        loadBar.style.display = 'block';

        try {
            const response = await fetch(romUrl);

            if (!response.ok) {
                throw new Error('HTTP ' + response.status + ' — ' + response.statusText);
            }

            const contentLength = parseInt(
                response.headers.get('Content-Length') || String(totalBytes), 10
            );
            const hasLength = contentLength > 0;
            const reader    = response.body.getReader();
            const chunks    = [];
            let   received  = 0;

            // Leer el stream en chunks y actualizar la barra
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

            // Completado: construir Blob → Object URL
            loadFill.style.width      = '100%';
            loadFill.classList.remove('indeterminate');
            loadPct.textContent       = '100%';
            loadText.textContent      = '✅ ROM cargada. Iniciando emulador…';
            loadDetail.textContent    = 'Procesando ' + fmtBytes(received) + '…';

            const blob      = new Blob(chunks);
            const objectUrl = URL.createObjectURL(blob);
            window.EJS_gameUrl = objectUrl;   // EmulatorJS usará esta URL local

            await new Promise(r => setTimeout(r, 500));
            loadBar.style.display = 'none';

        } catch (err) {
            // Si algo falla, mostramos el error y dejamos que EJS intente con la URL original
            loadText.textContent       = '❌ Error al precargar la ROM';
            loadDetail.textContent     = err.message + ' — Intentando carga directa…';
            loadFill.style.background  = '#c0392b';
            loadFill.style.width       = '100%';
            console.error('[proxy] Error preload:', err);

            await new Promise(r => setTimeout(r, 2500));
            loadBar.style.display = 'none';
            // EJS_gameUrl mantiene la URL del proxy, EmulatorJS la intentará por su cuenta
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

<?php
require_once __DIR__ . '/../../config/IntlHelper.php';

// Valores por defecto para evitar warnings cuando no hay juego cargado
$juego = $juego ?? null;
$downloadUrl = $downloadUrl ?? '#';
$core = $core ?? null;
$proxyError = $proxyError ?? null;
$error = $error ?? null;
$romUrl = $romUrl ?? '';
$sizeBytes = (int)($sizeBytes ?? ($juego['size_bytes'] ?? 0));
$needsThreads = $needsThreads ?? false;
$modoStreaming = $modoStreaming ?? false;
$biosUrl = $biosUrl ?? null;
$region = $region ?? ($juego['region'] ?? '');
$emuladorNombre = $emuladorNombre ?? ($juego['emulador_nombre'] ?? '');
$errorType = $errorType ?? ($proxyError['type'] ?? 'unsupported');
$errorMsg = $errorMsg ?? (!empty($proxyError) ? $proxyError['message'] : ($error ?? ''));

$hayError = ($hayError ?? false) || !empty($error) || !empty($proxyError);
if ($juego === null && empty($error) && empty($proxyError)) {
    $error = t('play.error_sin_juego') ?: 'Error: No se especificó el juego a reproducir.';
    $hayError = true;
}
?>

<?php if ($hayError): ?>
    <?php
    // Icono y título según tipo
    $iconMap = [
        'not_found' => ['<i data-i="trash"></i>', t('play.error_archivo_no_encontrado')],
        'private' => ['<i data-i="lock"></i>', t('play.error_archivo_privado')],
        'quota' => ['<i data-i="clock"></i>', t('play.error_limite_descargas')],
        'network' => ['<i data-i="wifi"></i>', t('play.error_conexion')],
        'unsupported' => ['<i data-i="settings-cog"></i>', t('play.error_no_disponible')],
    ];
    [$icon, $titulo] = $iconMap[$errorType] ?? ['<i data-i="warning"></i>', t('play.error_desconocido')];

    // Consejo específico por tipo
    $consejo = match ($errorType) {
        'not_found' => t('play.consejo_archivo_no_encontrado'),
        'private' => t('play.consejo_archivo_privado'),
        'quota' => t('play.consejo_limite_descargas'),
        'network' => t('play.consejo_conexion'),
        default => t('play.consejo_generico'),
    };
    ?>
    <!-- ====== Error con diagnóstico ====== -->
    <div class="proxy-error-page">

        <div class="proxy-error-icon"><?= $icon ?></div>
        <h2 class="proxy-error-title"><?= htmlspecialchars($titulo) ?></h2>
        <p class="proxy-error-msg"><?= $errorMsg /* puede contener <code> */ ?></p>

        <div class="proxy-error-tip">
            <span class="proxy-tip-label"><i data-i="lightbulb"></i> <?= t('play.que_puedo_hacer') ?></span>
            <?= htmlspecialchars($consejo) ?>
        </div>

        <!-- Acciones disponibles -->
        <div class="proxy-error-actions">
            <?php if ($errorType !== 'quota'): ?>
                <a href="<?= htmlspecialchars($downloadUrl) ?>"
                    class="btn-download-big" target="_blank">
<i data-i="download"></i> <?= t('play.descargar_rom') ?>
                </a>
            <?php endif; ?>
            <?php if ($errorType === 'quota'): ?>
                <a href="javascript:location.reload()" class="btn-retry">
                    <i data-i="reload"></i> <?= t('play.reintentar_ahora') ?>
                </a>
            <?php endif; ?>
            <a href="/" class="btn-back-catalog">
<i data-i="arrow-left"></i> <?= t('play.volver_catalogo') ?>
            </a>
        </div>

        <!-- Detalles técnicos colapsables para el admin -->
        <details class="proxy-error-details">
            <summary><?= t('play.detalles_tecnicos') ?></summary>
            <div class="proxy-error-technical">
                <div><span><?= t('play.juego') ?></span> <?= htmlspecialchars($juego['titulo']) ?></div>
                <div><span><?= t('play.consola') ?></span> <?= htmlspecialchars($juego['consola_nombre'] ?? '—') ?></div>
                <div><span><?= t('play.file_id') ?></span> <code><?= htmlspecialchars($juego['google_drive_file_id']) ?></code></div>
                <div><span><?= t('play.error_label') ?></span> <code><?= htmlspecialchars($errorType) ?></code></div>
            </div>
        </details>

    </div>

<?php else: ?>
    <!-- ====== Emulador ====== -->

    <?php if ($core === 'ppsspp'): ?>
        <!-- Aviso experimental PSP -->
        <div class="psp-warning">
            <strong><i data-i="warning"></i> <?= t('play.psp_soporte') ?></strong>
            <?= t('play.psp_aviso') ?>
        </div>
    <?php elseif ($core === 'psx'): ?>
        <!-- Aviso experimental PSX -->
        <div class="psp-warning">
            <strong><i data-i="warning"></i> <?= t('play.psx_soporte') ?></strong>
            <?= t('play.psx_aviso') ?>
        </div>
    <?php elseif ($core === 'n64'): ?>
        <!-- Aviso experimental N64 -->
        <div class="psp-warning">
            <strong><i data-i="warning"></i> <?= t('play.n64_soporte') ?></strong>
            <?= t('play.n64_aviso') ?>
        </div>
    <?php elseif ($core === 'nds'): ?>
        <!-- Aviso experimental NDS -->
        <div class="psp-warning">
            <strong><i data-i="warning"></i> <?= t('play.nds_soporte') ?></strong>
            <?= t('play.nds_aviso') ?>
        </div>
    <?php endif; ?>

    <div class="emulator-wrapper">

        <!-- Info panel -->
        <div class="emulator-info">
            <div class="emulator-game-meta">
                <!-- Contenedor de portada: tamaño variable, se adapta a cada imagen -->
                <div class="emulator-cover-frame">
                    <?php if (!empty($juego['imagen'])): ?>
                        <img src="<?= htmlspecialchars($juego['imagen']) ?>" alt="<?= htmlspecialchars($juego['titulo']) ?>"
                            class="emulator-cover-img">
                    <?php else: ?>
                        <div class="emulator-cover-placeholder"><i data-i="disc"
                                data-cls="pxi-cover-placeholder"></i></div>
                    <?php endif; ?>
                </div>

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
                                <i data-i="hard-drive"></i> <?= IntlHelper::formatBytes($juego['size_bytes']) ?>
                            </span>
                        <?php endif; ?>
                        <?php if (!empty($juego['idiomas'])): ?>
                            <span class="emulator-meta-item">
                                <i data-i="globe"></i> <?= htmlspecialchars($juego['idiomas']) ?>
                            </span>
                        <?php endif; ?>
                    </div>

                    <div class="emulator-actions">
                        <a href="<?= htmlspecialchars($downloadUrl) ?>"
                            class="btn-dl-small" target="_blank"><i data-i="download"></i> <?= t('play.descargar_rom') ?></a>
                        <button class="btn-fullscreen"
                            onclick="document.getElementById('emulator-container').requestFullscreen()">
                            <i data-i="expand"></i> <?= t('play.pantalla_completa') ?>
                        </button>
                        <button class="btn-share" id="btn-share-game"
                            data-url="<?= htmlspecialchars((isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . $_SERVER['REQUEST_URI']) ?>"
                            title="<?= t('play.copiar_enlace') ?>">
                            <i data-i="share"></i> <?= t('play.compartir') ?>
                        </button>
                    </div>
                    <div id="share-toast" class="share-toast" aria-live="polite"></div>
                </div>
            </div>
        </div>

        <?php if (!$modoStreaming): ?>
            <!-- Barra de progreso — ROMs pequeñas (NES, SNES, GBA, N64…) -->
            <div id="proxy-load-bar" class="proxy-load-bar" style="display:none;">
                <div class="proxy-load-label">
                    <span id="proxy-load-text"><i data-i="clock"></i> <?= t('play.cargando_rom') ?></span>
                    <span id="proxy-load-pct">0%</span>
                </div>
                <div class="proxy-load-track">
                    <div id="proxy-load-fill" class="proxy-load-fill"></div>
                </div>
                <span id="proxy-load-detail" class="proxy-load-detail"><?= t('play.conectando_servidor') ?></span>
            </div>
        <?php else: ?>
            <!-- Aviso streaming — ISOs grandes (PSP, PS1, Saturn…) -->
            <div class="proxy-streaming-notice">
                <span class="streaming-icon"><i data-i="wifi"></i></span>
                <span>
                    <?= t('play.aviso_streaming', [
                        'tamano' => !empty($juego['size_bytes'])
                            ? IntlHelper::formatBytes($juego['size_bytes'])
                            : '',
                    ]) ?>
                </span>
            </div>
        <?php endif; ?>

        <!-- Slider de tamaño de pantalla -->
        <div class="emulator-size-control">
            <span class="size-control-label"><svg viewBox="0 0 24 24" fill="currentColor" class="pxi" width="14"
                    height="14">
                    <path
                        d="M20 3H4c-1.1 0-2 .9-2 2v11c0 1.1.9 2 2 2h3l-1 1v2h12v-2l-1-1h3c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zm0 13H4V5h16v11z" />
                </svg> <?= t('play.tamano_pantalla') ?></span>
            <div class="size-control-row">
                <span class="size-control-min">40%</span>
                <input type="range" id="emulator-size-slider" min="40" max="100" value="80" step="5"
                    aria-label="<?= t('play.tamano_pantalla_aria') ?>">
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
                <summary><svg viewBox="0 0 24 24" fill="currentColor" class="pxi" width="14" height="14">
                        <path
                            d="M17 6H7c-2.8 0-5 2.2-5 5s2.2 5 5 5h10c2.8 0 5-2.2 5-5s-2.2-5-5-5zm-9 7H6v-2h2v-2h2v2h2v2h-2v2H8v-2zm7 1h-2v-2h2v2zm2-3h-2v-2h2v2z" />
                    </svg> <?= t('play.controles_teclado') ?></summary>
                <div class="controls-grid">
                    <div><strong><?= t('play.control_mover') ?></strong> — <?= t('play.control_mover_teclas') ?></div>
                    <div><strong><?= t('play.control_abxy') ?></strong> — <?= t('play.control_abxy_teclas') ?></div>
                    <div><strong><?= t('play.control_start_select') ?></strong> — <?= t('play.control_start_select_teclas') ?></div>
                    <div><strong><?= t('play.control_lr') ?></strong> — <?= t('play.control_lr_teclas') ?></div>
                    <div><strong><?= t('play.control_guardar_estado') ?></strong> — <?= t('play.control_guardar_estado_teclas') ?></div>
                    <div><strong><?= t('play.control_cargar_estado') ?></strong> — <?= t('play.control_cargar_estado_teclas') ?></div>
                    <div><strong><?= t('play.control_velocidad') ?></strong> — <?= t('play.control_velocidad_teclas') ?></div>
                    <div><strong><?= t('play.control_gamepad') ?></strong> — <?= t('play.control_gamepad_teclas') ?></div>
                </div>
            </details>
        </div>

    </div><!-- /.emulator-wrapper -->

    <!-- ====== EmulatorJS ====================================================== -->
    <script>
        (function () {

            // ── Configuración base ────────────────────────────────────────────────
            window.EJS_player = '#game';
            window.EJS_core = '<?= addslashes($core) ?>';
            window.EJS_gameName = '<?= addslashes(htmlspecialchars_decode($juego['titulo'])) ?>';
            window.EJS_gameUrl = '<?= addslashes($romUrl) ?>';
            window.EJS_pathtodata = 'https://cdn.emulatorjs.org/stable/data/';
            window.EJS_color = '#c0392b';
            window.EJS_startOnLoaded = false;
            window.EJS_backgroundColor = '#1a1a1a';
            window.EJS_language = '<?= IntlHelper::emulatorLanguage() ?>';  // UI del emulador según idioma activo (FASE 5.3)
            window.EJS_Buttons = {
                saveState: true,
                loadState: true,
                fullscreen: false,
                screenshot: true,
                cacheManager: false,
            };

            <?php if (!empty($biosUrl)): ?>
                // BIOS requerido por este core (PS1, etc.)
                window.EJS_biosUrl = '<?= addslashes($biosUrl) ?>';
            <?php endif; ?>

            <?php if ($needsThreads): ?>
                // Threads habilitados por los headers COOP/COEP que emite el
                // controlador (ahora: N64, PSP, DOSBox — se añaden cores de uno
                // en uno tras probar). Si el navegador no expone SharedArrayBuffer,
                // EmulatorJS usa single-thread sin romper.
                window.EJS_threads = true;
            <?php endif; ?>

            // ── Flags de comportamiento ───────────────────────────────────────────
            const modoStreaming = <?= $modoStreaming ? 'true' : 'false' ?>;
            const totalBytes = <?= (int) ($juego['size_bytes'] ?? 0) ?>;

            // ── Carga el loader de EmulatorJS en el DOM ───────────────────────────
            function loadEmulatorJS() {
                const s = document.createElement('script');
                s.src = 'https://cdn.emulatorjs.org/stable/data/loader.js';
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
                            loadDetail.textContent = fmtBytes(received) + ' ' + <?= json_encode(t('play.recibidos')) ?>.replace('{valor}', '');
                        }
                    }

                    loadFill.style.width = '100%';
                    loadFill.classList.remove('indeterminate');
                    loadPct.textContent = '100%';
                    loadText.innerHTML = '<svg viewBox="0 0 24 24" fill="currentColor" class="pxi" width="14" height="14"><path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z"/></svg> ' + <?= json_encode(t('play.rom_cargada')) ?>;
                    loadDetail.textContent = <?= json_encode(t('play.procesando', ['valor' => ''])) ?>.replace('{valor}', fmtBytes(received));

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
                        not_found: ['<i data-i="trash"></i>', <?= json_encode(t('play.error_archivo_no_encontrado')) ?>],
                        private: ['<i data-i="lock"></i>', <?= json_encode(t('play.error_archivo_privado')) ?>],
                        quota: ['<i data-i="clock"></i>', <?= json_encode(t('play.error_limite_descargas')) ?>],
                        network: ['<i data-i="wifi"></i>', <?= json_encode(t('play.error_conexion')) ?>],
                    };
                    const tipMap = {
                        not_found: <?= json_encode(t('play.consejo_archivo_no_encontrado_js')) ?>,
                        private: <?= json_encode(t('play.consejo_archivo_privado_js')) ?>,
                        quota: <?= json_encode(t('play.consejo_limite_descargas_js')) ?>,
                        network: <?= json_encode(t('play.consejo_conexion')) ?>,
                    };
                    const [icon, titulo] = iconMap[errorType] || ['<i data-i="warning"></i>', <?= json_encode(t('play.error_cargar_rom')) ?>];
                    const consejo = tipMap[errorType] || <?= json_encode(t('play.consejo_reintentar')) ?>;
                    // URL de descarga firmada por el servidor (inyectada desde PHP)
                    const signedDownloadUrl = '<?= addslashes($downloadUrl) ?>';

document.querySelector('.emulator-wrapper').innerHTML = `
                <div class="proxy-error-page">
                    <div class="proxy-error-icon">${icon}</div>
                    <h2 class="proxy-error-title">${titulo}</h2>
                    <p class="proxy-error-msg">${errorMsg}</p>
                    <div class="proxy-error-tip">
                        <span class="proxy-tip-label"><i data-i="lightbulb"></i> ${<?= json_encode(t('play.que_puedo_hacer')) ?>}</span>
                        ${consejo}
                    </div>
                    <div class="proxy-error-actions">
                        ${errorType !== 'quota'
                        ? `<a href="${signedDownloadUrl}"
                                   class="btn-download-big" target="_blank"><i data-i="download"></i> ${<?= json_encode(t('play.descargar_rom')) ?>}</a>`
                        : `<a href="javascript:location.reload()" class="btn-retry"><i data-i="reload"></i> ${<?= json_encode(t('play.reintentar')) ?>}</a>`
                    }
                        <a href="/" class="btn-back-catalog"><i data-i="arrow-left"></i> ${<?= json_encode(t('play.volver_catalogo')) ?>}</a>
                    </div>
                    <details class="proxy-error-details">
                        <summary>${<?= json_encode(t('play.detalles_tecnicos')) ?>}</summary>
                        <div class="proxy-error-technical">
                            <div><span>${<?= json_encode(t('play.error_label')) ?>}</span> <code>${errorType}</code></div>
                            <div><span>${<?= json_encode(t('play.mensaje')) ?>}</span> <code>${errorMsg}</code></div>
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

<!-- ====== JUEGOS RELACIONADOS ============================================= -->
<?php
// En la página del emulador las imágenes de recomendados se cargan primero,
// con prioridad ALTA (fetchpriority="high") y eager: se piden en paralelo nada
// más parsear el HTML, antes incluso del streaming de la ROM. En el resto de
// vistas ($relatedEager sin definir) siguen con loading="lazy".
$relatedEager = true;
require_once __DIR__ . '/../components/related_games.php';
?>

<!-- ====== SHARE + AUTOCOMPLETE JS ======================================== -->
<script>
    (function () {
        'use strict';

        // ── Botón Compartir ───────────────────────────────────────────────────
        const btnShare = document.getElementById('btn-share-game');
        const toast = document.getElementById('share-toast');

        if (btnShare) {
            btnShare.addEventListener('click', () => {
                const url = btnShare.dataset.url;
                copyToClipboard(url);
            });
        }

        function copyToClipboard(text) {
            // Método 1: API moderna (requiere HTTPS o localhost)
            if (navigator.clipboard && window.isSecureContext) {
                navigator.clipboard.writeText(text)
                    .then(() => showToast('<i data-i="check"></i> ' + <?= json_encode(t('play.enlace_copiado')) ?>))
                    .catch(() => legacyCopy(text));
                return;
            }
            // Método 2: execCommand — funciona en HTTP e IPs locales
            legacyCopy(text);
        }

        function legacyCopy(text) {
            const ta = document.createElement('textarea');
            ta.value = text;
            ta.style.cssText = 'position:fixed;top:0;left:0;opacity:0;pointer-events:none;';
            document.body.appendChild(ta);
            ta.focus();
            ta.select();
            try {
                const ok = document.execCommand('copy');
                showToast(ok
                    ? '<i data-i="check"></i> ' + <?= json_encode(t('play.enlace_copiado')) ?>
                    : '<i data-i="close"></i> ' + <?= json_encode(t('play.no_se_pudo_copiar')) ?>);
            } catch (_) {
                showToast('<i data-i="close"></i> ' + <?= json_encode(t('play.no_se_pudo_copiar_enlace')) ?>);
            }
            document.body.removeChild(ta);
        }

        function showToast(msg) {
            if (!toast) return;
            toast.innerHTML = msg;
            toast.classList.add('share-toast--visible');
            setTimeout(() => toast.classList.remove('share-toast--visible'), 2800);
        }

    })();
</script>
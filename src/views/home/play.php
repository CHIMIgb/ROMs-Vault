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
                <a href="<?= htmlspecialchars($_SERVER['REQUEST_URI'] ?? '/') ?>" class="btn-retry" data-reload>
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

    <script src="public/js/views/home/play-error.js" defer></script>

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
                    <div id="share-toast" class="share-toast" aria-live="polite" data-lang-skip></div>
                </div>
            </div>
        </div>

        <?php if (!$modoStreaming): ?>
            <!-- Barra de progreso — ROMs pequeñas (NES, SNES, GBA, N64…) -->
            <div id="proxy-load-bar" class="proxy-load-bar">
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
<script type="application/json" id="rv-data-play-emulator"><?= json_encode(
    [
        'core'          => $core,
        'gameName'      => htmlspecialchars_decode((string) $juego['titulo']),
        'gameUrl'       => $romUrl,
        'pathtodata'    => EmulatorJS::dataUrl(),
        'loaderUrl'     => EmulatorJS::loaderUrl(),
        'language'      => IntlHelper::emulatorLanguage(),
        'biosUrl'       => $biosUrl ?: null,
        'needsThreads'  => (bool) $needsThreads,
        'modoStreaming' => (bool) $modoStreaming,
        'totalBytes'    => (int) ($juego['size_bytes'] ?? 0),
        'downloadUrl'   => $downloadUrl,
        't' => [
            'play.recibidos' => t('play.recibidos'),
            'play.rom_cargada' => t('play.rom_cargada'),
            'play.procesando' => t('play.procesando', ['valor' => '']),
            'play.error_archivo_no_encontrado' => t('play.error_archivo_no_encontrado'),
            'play.error_archivo_privado' => t('play.error_archivo_privado'),
            'play.error_limite_descargas' => t('play.error_limite_descargas'),
            'play.error_conexion' => t('play.error_conexion'),
            'play.consejo_archivo_no_encontrado_js' => t('play.consejo_archivo_no_encontrado_js'),
            'play.consejo_archivo_privado_js' => t('play.consejo_archivo_privado_js'),
            'play.consejo_limite_descargas_js' => t('play.consejo_limite_descargas_js'),
            'play.consejo_conexion' => t('play.consejo_conexion'),
            'play.error_cargar_rom' => t('play.error_cargar_rom'),
            'play.consejo_reintentar' => t('play.consejo_reintentar'),
            'play.que_puedo_hacer' => t('play.que_puedo_hacer'),
            'play.descargar_rom' => t('play.descargar_rom'),
            'play.reintentar' => t('play.reintentar'),
            'play.volver_catalogo' => t('play.volver_catalogo'),
            'play.detalles_tecnicos' => t('play.detalles_tecnicos'),
            'play.error_label' => t('play.error_label'),
            'play.mensaje' => t('play.mensaje'),
        ],
    ],
    JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
) ?></script>

<script src="public/js/views/home/play-emulator.js" defer></script>

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
<script type="application/json" id="rv-data-play-share"><?= json_encode(
    [
        't' => [
            'play.enlace_copiado' => t('play.enlace_copiado'),
            'play.no_se_pudo_copiar' => t('play.no_se_pudo_copiar'),
            'play.no_se_pudo_copiar_enlace' => t('play.no_se_pudo_copiar_enlace'),
        ],
    ],
    JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
) ?></script>

<script src="public/js/views/home/play-share.js" defer></script>
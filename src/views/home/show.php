<?php
require_once __DIR__ . '/../../config/IntlHelper.php';
?>
<?php
// Helper de tamaño legible: B / KB / MB / GB según el valor
if (!function_exists('formatBytes')) {
    function formatBytes($bytes) {
        return IntlHelper::formatBytes($bytes);
    }
}
// Badge NUEVO: ROMs añadidas en los últimos 30 días
$esNuevo = !empty($juego['created_at'])
    && (time() - strtotime($juego['created_at'])) < 30 * 86400;

// ── Datos derivados para los pasos de instalación del emulador ──
$formato = strtoupper(trim($juego['formato_imagen'] ?? ''));
$esImagenDisco = in_array($formato, ['ISO', 'BIN/CUE', 'ISP'], true);

// ── Idiomas disponibles ──
// La BD guarda una lista separada por comas (y en datos antiguos, por puntos);
// se normaliza: recorte, mayúscula inicial y dedupe sin importar mayúsculas,
// preservando el orden original (el primer idioma suele ser el principal).
$idiomas = [];
if (!empty($juego['idiomas'])) {
    $vistos = [];
    foreach (preg_split('/[,.;]/u', $juego['idiomas']) as $idioma) {
        $idioma = trim($idioma);
        if ($idioma === '') continue;
        $clave = strtolower($idioma);
        if (isset($vistos[$clave])) continue;
        $vistos[$clave] = true;
        $idiomas[] = ucfirst($idioma);
    }
}

// ── Capturas (carrusel) ──
// Usa el campo `capturas` de la BD (JSON de rutas); si no hay, replica la portada.
$capturas = Juego::parseCapturas($juego['capturas'] ?? null);
if (!$capturas && !empty($juego['imagen'])) {
    $capturas = array_fill(0, 4, $juego['imagen']);
}

// Pasos de instalación del emulador recomendado
$pasosLocales = [];
if ($emuladorLocal) {
    $pasosLocales[] = t('show.paso_descarga', [
        'emulador'    => $emuladorLocal['nombre'],
        'plataformas' => implode(t('show.conector_y'), $emuladorLocal['plataformas']),
    ]);
    $pasosLocales[] = $esImagenDisco
        ? t('show.paso_abrir_disco')
        : t('show.paso_abrir_rom', ['formato' => htmlspecialchars($juego['formato_imagen'] ?? 'rom')]);
    $pasosLocales[] = t('show.paso_configurar');
}
?>
<div class="game-detail-page">

    <!-- ══════════════════════════════════════════════════════
         HERO — "caja de coleccionista"
         ══════════════════════════════════════════════════════ -->
    <div class="game-detail-hero">

        <!-- Cover art con marco CRT + franja de caja -->
        <div class="detail-cover-col">
            <div class="detail-cover-frame">
                <div class="detail-cover-scanlines"></div>
                <?php if (Juego::existeImagen($juego['imagen'] ?? null)): ?>
                    <img src="<?= htmlspecialchars($juego['imagen']) ?>" alt="<?= htmlspecialchars($juego['titulo']) ?>" class="detail-cover-img">
                <?php else: ?>
                    <div class="detail-cover-placeholder">
                        <i data-i="image" data-cls="pxi-cover-placeholder" aria-hidden="true"></i>
                    </div>
                <?php endif; ?>
                <?php if (!empty($juego['region']) && in_array($juego['region'], ['PAL', 'NTSC', 'NTSC-J', 'NTSC-U'])): ?>
                    <span class="detail-region-seal" title="<?= t('show.region') ?>"><?= htmlspecialchars($juego['region']) ?></span>
                <?php endif; ?>
                <?php if (($juego['formato_imagen'] ?? '') === 'Hack'): ?>
                    <span class="detail-hack-ribbon"><i data-i="zap" aria-hidden="true"></i> <?= t('show.hack') ?></span>
                <?php endif; ?>
                <?php if ($esNuevo): ?>
                    <span class="detail-new-badge"><?= t('show.nuevo') ?></span>
                <?php endif; ?>
            </div>
        </div>

        <!-- Info panel -->
        <div class="detail-info-panel">

            <!-- Título + año de lanzamiento -->
            <div class="detail-title-block">
                <div class="detail-title-row">
                    <h1 class="detail-title"><?= htmlspecialchars($juego['titulo']) ?></h1>
                    <?php if (!empty($juego['fecha_lanzamiento'])): ?>
                        <span class="detail-year"><?= htmlspecialchars(IntlHelper::formatYear($juego['fecha_lanzamiento'])) ?></span>
                    <?php endif; ?>
                </div>
                <div class="detail-tags">
                    <?php if (!empty($juego['categoria_id'])): ?>
                        <a href="/?categoria=<?= (int) $juego['categoria_id'] ?>" class="tag tag-categoria" title="<?= t('show.ver_mas_de', ['categoria' => htmlspecialchars($juego['categoria_nombre'] ?? t('show.esta_categoria'))]) ?>">
                            <?= htmlspecialchars($juego['categoria_nombre'] ?? t('show.sin_categoria')) ?>
                        </a>
                    <?php else: ?>
                        <span class="tag tag-categoria"><?= htmlspecialchars($juego['categoria_nombre'] ?? t('show.sin_categoria')) ?></span>
                    <?php endif; ?>
                    <?php if (($juego['formato_imagen'] ?? '') === 'Hack'): ?>
                        <span class="tag tag-hack"><?= t('show.hack_rom') ?></span>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Stats bar -->
            <div class="detail-stats-bar">
                <span class="stat-chip" title="<?= t('show.descargas') ?>">
                    <i data-i="download" aria-hidden="true"></i>
                    <span class="stat-chip-num"><?= htmlspecialchars(IntlHelper::formatNumber($juego['downloads_count'] ?? 0)) ?></span>
                    <span class="stat-chip-label"><?= t('show.descargas_label') ?></span>
                </span>
                <span class="stat-chip" title="<?= t('show.partidas_online') ?>">
                    <i data-i="play" aria-hidden="true"></i>
                    <span class="stat-chip-num"><?= htmlspecialchars(IntlHelper::formatNumber($juego['plays_count'] ?? 0)) ?></span>
                    <span class="stat-chip-label"><?= t('show.jugadas_label') ?></span>
                </span>
                <button type="button" class="stat-chip stat-chip--share" id="btn-share-game" title="<?= t('show.compartir_title') ?>">
                    <i data-i="share" aria-hidden="true"></i>
                    <?= t('show.compartir') ?>
                </button>
            </div>

            <!-- Etiquetas de la caja: consola / región / formato -->
            <?php if (!empty($juego['consola_nombre'])): ?>
                <div class="detail-cover-labels" aria-hidden="true">
                    <span><?= htmlspecialchars($juego['consola_nombre']) ?></span>
                    <span><?= htmlspecialchars($juego['region'] ?? 'ALL') ?></span>
                    <span><?= htmlspecialchars($juego['formato_imagen'] ?? 'ROM') ?></span>
                </div>
            <?php endif; ?>

            <!-- Idiomas disponibles -->
            <?php if (!empty($idiomas)): ?>
                <div class="detail-languages" aria-label="<?= t('show.idiomas_disponibles') ?>">
                    <span class="detail-languages-label">
                        <i data-i="globe" aria-hidden="true"></i>
                        <span><?= t('show.idiomas') ?></span>
                    </span>
                    <span class="detail-languages-list">
                        <?php foreach ($idiomas as $idioma): ?>
                            <span class="language-tag"><?= htmlspecialchars($idioma) ?></span>
                        <?php endforeach; ?>
                    </span>
                </div>
            <?php endif; ?>

            <!-- Action buttons -->
            <?php $detailContext = true; require __DIR__ . '/../components/game_actions.php'; ?>
        </div>
    </div>

    <!-- ── Sinopsis ── -->
    <?php if (!empty($juego['descripcion'])): ?>
    <section class="detail-synopsis">
        <h2 class="detail-section-title">
            <?= t('show.sinopsis') ?>
            <?php if (($juego['formato_imagen'] ?? '') === 'Hack'): ?>
                <span class="synopsis-hack-badge"><i data-i="zap" aria-hidden="true"></i> <?= t('show.rom_hack') ?></span>
            <?php endif; ?>
        </h2>
        <div class="detail-synopsis-content<?=
            // B4: capitular Press Start 2P solo si arranca en latín (ver game-details.css)
            preg_match('/^\s*\p{Latin}/u', $juego['descripcion']) ? ' synopsis-dropcap' : ''
        ?>">
            <?= nl2br(htmlspecialchars($juego['descripcion'])) ?>
        </div>
    </section>
    <?php endif; ?>

    <!-- ══════════════════════════════════════════════════════
         CAPTURAS — carrusel tipo pantalla CRT
         ══════════════════════════════════════════════════════ -->
    <?php if (!empty($capturas)): ?>
    <section class="detail-screenshots" aria-labelledby="capturas-title">
        <h2 class="detail-section-title" id="capturas-title"><i data-i="image" aria-hidden="true"></i> <?= t('show.capturas') ?></h2>

        <div class="screenshot-carousel" data-carousel>
            <!-- Viewport con marco CRT + scanlines -->
            <div class="screenshot-viewport">
                <div class="screenshot-track" data-carousel-track>
                    <?php foreach ($capturas as $i => $cap): ?>
                        <div class="screenshot-slide" role="group" aria-label="<?= t('show.captura_de', ['indice' => $i + 1, 'total' => count($capturas)]) ?>">
                            <img src="<?= htmlspecialchars($cap) ?>" alt="<?= t('show.captura_alt', ['indice' => $i + 1, 'titulo' => htmlspecialchars($juego['titulo'])]) ?>"
                                loading="lazy" decoding="async">
                            <button type="button" class="screenshot-open" data-carousel-open="<?= $i ?>"
                                aria-label="<?= t('show.ver_captura', ['indice' => $i + 1]) ?>">
                                <i data-i="expand" aria-hidden="true"></i>
                            </button>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Flechas prev / next -->
            <button type="button" class="screenshot-nav screenshot-nav--prev" data-carousel-prev aria-label="<?= t('show.captura_anterior') ?>">
                <i data-i="chevron-left" aria-hidden="true"></i>
            </button>
            <button type="button" class="screenshot-nav screenshot-nav--next" data-carousel-next aria-label="<?= t('show.siguiente_captura') ?>">
                <i data-i="chevron-right" aria-hidden="true"></i>
            </button>

            <!-- Contador estilo etiqueta de caja -->
            <span class="screenshot-counter" data-carousel-counter aria-live="polite">1 / <?= count($capturas) ?></span>
        </div>

        <!-- Miniaturas navegables -->
        <div class="screenshot-thumbs" data-carousel-thumbs>
            <?php foreach ($capturas as $i => $cap): ?>
                <button type="button" class="screenshot-thumb<?= $i === 0 ? ' is-active' : '' ?>"
                    data-carousel-thumb="<?= $i ?>"
                    aria-label="<?= t('show.ir_a_captura', ['indice' => $i + 1]) ?>"
                    aria-current="<?= $i === 0 ? 'true' : 'false' ?>">
                    <img src="<?= htmlspecialchars($cap) ?>" alt="" loading="lazy" decoding="async">
                </button>
            <?php endforeach; ?>
        </div>

        <!-- Visor a pantalla completa (lightbox): misma imagen, sin recorte,
             carrusel completo con flechas, contador y miniaturas -->
        <div class="screenshot-lightbox" data-lightbox hidden role="dialog" aria-modal="true" aria-label="<?= t('show.visor_capturas') ?>">
            <div class="screenshot-lightbox-backdrop" data-lightbox-close></div>

            <div class="screenshot-lightbox-content">
                <button type="button" class="screenshot-lightbox-close" data-lightbox-close aria-label="<?= t('show.cerrar_visor') ?>">
                    <i data-i="close" aria-hidden="true"></i>
                </button>

                <div class="screenshot-carousel screenshot-carousel--lightbox">
                    <div class="screenshot-viewport screenshot-viewport--lightbox">
                        <div class="screenshot-track screenshot-track--lightbox" data-lightbox-track></div>
                    </div>

<button type="button" class="screenshot-nav screenshot-nav--prev" data-lightbox-prev aria-label="<?= t('show.captura_anterior') ?>">
                    <i data-i="chevron-left" aria-hidden="true"></i>
                </button>
                <button type="button" class="screenshot-nav screenshot-nav--next" data-lightbox-next aria-label="<?= t('show.siguiente_captura') ?>">
                    <i data-i="chevron-right" aria-hidden="true"></i>
                </button>

                    <span class="screenshot-counter" data-lightbox-counter aria-live="polite">1 / <?= count($capturas) ?></span>
                </div>

                <div class="screenshot-thumbs screenshot-thumbs--lightbox" data-lightbox-thumbs></div>
            </div>
        </div>
    </section>
    <?php endif; ?>

    <!-- ══════════════════════════════════════════════════════
         CÓMO JUGAR — Instrucciones con el emulador recomendado
         ══════════════════════════════════════════════════════ -->
    <section class="detail-howto" aria-labelledby="howto-title">
        <h2 class="detail-section-title" id="howto-title"><i data-i="play" aria-hidden="true"></i> <?= t('show.como_jugar') ?></h2>

        <?php if ($emuladorLocal): ?>
        <div class="howto-local">
            <h3 class="howto-local-title"><i data-i="hard-drive" aria-hidden="true"></i> <?= t('show.en_tu_equipo') ?></h3>
            <p class="howto-local-intro">
                <?= t('show.esta_rom_es_de') ?> <strong><?= htmlspecialchars($juego['consola_nombre'] ?? t('component.la_consola')) ?></strong>.
                <?= t('show.emulador_recomendado') ?> <strong><?= htmlspecialchars($emuladorLocal['nombre']) ?></strong>
                (<span class="howto-platforms">
                    <?php foreach ($emuladorLocal['plataformas'] as $p): ?>
                        <span class="platform-tag"><?= htmlspecialchars($p) ?></span>
                    <?php endforeach; ?>
                </span>).
                <a href="<?= htmlspecialchars($emuladorLocal['url']) ?>" target="_blank" rel="noopener noreferrer" class="howto-dl-link">
                    <i data-i="download" aria-hidden="true"></i> <?= t('show.descargar_emulador') ?>
                </a>
            </p>
            <ol class="howto-steps">
                <?php foreach ($pasosLocales as $paso): ?>
                    <li><?= $paso ?></li>
                <?php endforeach; ?>
            </ol>
            <?php if (!empty($emuladorLocal['alterno'])): ?>
            <p class="howto-alt">
                <?= t('show.alternativa') ?>
                <a href="<?= htmlspecialchars($emuladorLocal['alterno']['url']) ?>" target="_blank" rel="noopener noreferrer">
                    <?= htmlspecialchars($emuladorLocal['alterno']['nombre']) ?>
                </a>
                <span class="howto-platforms">
                    <?php foreach ($emuladorLocal['alterno']['plataformas'] as $p): ?>
                        <span class="platform-tag"><?= htmlspecialchars($p) ?></span>
                    <?php endforeach; ?>
                </span>
            </p>
            <?php endif; ?>
        </div>
        <?php else: ?>
        <p class="howto-nomap">
            <?= t('show.sin_emulador', ['consola' => htmlspecialchars($juego['consola_nombre'] ?? t('show.esta_categoria'))]) ?>
        </p>
        <?php endif; ?>
    </section>

    <!-- ── Related games ── -->
    <?php require_once __DIR__ . '/../components/related_games.php'; ?>
</div>

<!-- Toast de compartir (reutiliza el sistema existente) -->
<div class="share-toast" id="share-toast" role="status" aria-live="polite" data-lang-skip><?= t('show.enlace_copiado') ?></div>

<script src="public/js/views/home/show-share.js" defer></script>

<script src="public/js/views/home/show-carousel.js" defer></script>

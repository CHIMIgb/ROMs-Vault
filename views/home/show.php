<?php
// Helper de tamaño legible: B / KB / MB / GB según el valor
if (!function_exists('formatBytes')) {
    function formatBytes($bytes) {
        $bytes = (float) $bytes;
        if ($bytes >= 1073741824) { return number_format($bytes / 1073741824, 2) . ' GB'; }
        if ($bytes >= 1048576)    { return number_format($bytes / 1048576, 2) . ' MB'; }
        if ($bytes >= 1024)       { return number_format($bytes / 1024, 1) . ' KB'; }
        return number_format($bytes, 0) . ' B';
    }
}
// Badge NUEVO: ROMs añadidas en los últimos 30 días
$esNuevo = !empty($juego['created_at'])
    && (time() - strtotime($juego['created_at'])) < 30 * 86400;

// ── Datos derivados para los pasos de instalación del emulador ──
$formato = strtoupper(trim($juego['formato_imagen'] ?? ''));
$esImagenDisco = in_array($formato, ['ISO', 'BIN/CUE', 'ISP'], true);

// Pasos de instalación del emulador recomendado
$pasosLocales = [];
if ($emuladorLocal) {
    $pasosLocales[] = 'Descarga ' . $emuladorLocal['nombre'] . ' para '
        . implode(' y ', $emuladorLocal['plataformas'])
        . ' desde su sitio oficial.';
    $pasosLocales[] = $esImagenDisco
        ? 'Ábrelo y carga la imagen de disco (ISO o .cue) desde el menú Archivo.'
        : 'Ábrelo y carga el archivo de la ROM (' . htmlspecialchars($juego['formato_imagen'] ?? 'rom') . ') desde Archivo > Abrir.';
    $pasosLocales[] = 'Configura el mando o el teclado y pulsa Play.';
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
                <?php if (!empty($juego['imagen']) && file_exists(ltrim($juego['imagen'], '/'))): ?>
                    <img src="<?= htmlspecialchars($juego['imagen']) ?>" alt="<?= htmlspecialchars($juego['titulo']) ?>" class="detail-cover-img">
                <?php else: ?>
                    <div class="detail-cover-placeholder">
                        <i data-i="image" data-cls="pxi-cover-placeholder" aria-hidden="true"></i>
                    </div>
                <?php endif; ?>
                <?php if (!empty($juego['region']) && in_array($juego['region'], ['PAL', 'NTSC', 'NTSC-J', 'NTSC-U'])): ?>
                    <span class="detail-region-seal" title="Región"><?= htmlspecialchars($juego['region']) ?></span>
                <?php endif; ?>
                <?php if (($juego['formato_imagen'] ?? '') === 'Hack'): ?>
                    <span class="detail-hack-ribbon"><i data-i="zap" aria-hidden="true"></i> HACK</span>
                <?php endif; ?>
                <?php if ($esNuevo): ?>
                    <span class="detail-new-badge"><i data-i="zap" aria-hidden="true"></i> NUEVO</span>
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
                        <span class="detail-year"><?= htmlspecialchars(date('Y', strtotime($juego['fecha_lanzamiento']))) ?></span>
                    <?php endif; ?>
                </div>
                <div class="detail-tags">
                    <?php if (!empty($juego['categoria_id'])): ?>
                        <a href="index.php?categoria=<?= (int) $juego['categoria_id'] ?>" class="tag tag-categoria" title="Ver más de <?= htmlspecialchars($juego['categoria_nombre'] ?? 'esta categoría') ?>">
                            <?= htmlspecialchars($juego['categoria_nombre'] ?? 'Sin Categoría') ?>
                        </a>
                    <?php else: ?>
                        <span class="tag tag-categoria"><?= htmlspecialchars($juego['categoria_nombre'] ?? 'Sin Categoría') ?></span>
                    <?php endif; ?>
                    <?php if (($juego['formato_imagen'] ?? '') === 'Hack'): ?>
                        <span class="tag tag-hack">Hack ROM</span>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Stats bar -->
            <div class="detail-stats-bar">
                <span class="stat-chip" title="Descargas">
                    <i data-i="download" aria-hidden="true"></i>
                    <span class="stat-chip-num"><?= number_format($juego['downloads_count']) ?></span>
                    <span class="stat-chip-label">descargas</span>
                </span>
                <span class="stat-chip" title="Partidas Online">
                    <i data-i="play" aria-hidden="true"></i>
                    <span class="stat-chip-num"><?= number_format($juego['plays_count']) ?></span>
                    <span class="stat-chip-label">jugadas</span>
                </span>
                <button type="button" class="stat-chip stat-chip--share" id="btn-share-game" title="Compartir este juego">
                    <i data-i="share" aria-hidden="true"></i>
                    Compartir
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

            <!-- Action buttons -->
            <?php $detailContext = true; require __DIR__ . '/../components/game_actions.php'; ?>
        </div>
    </div>

    <!-- ── Sinopsis ── -->
    <?php if (!empty($juego['descripcion'])): ?>
    <section class="detail-synopsis">
        <h2 class="detail-section-title">
            Sinopsis
            <?php if (($juego['formato_imagen'] ?? '') === 'Hack'): ?>
                <span class="synopsis-hack-badge"><i data-i="zap" aria-hidden="true"></i> ROM Hack</span>
            <?php endif; ?>
        </h2>
        <div class="detail-synopsis-content">
            <?= nl2br(htmlspecialchars($juego['descripcion'])) ?>
        </div>
    </section>
    <?php endif; ?>

    <!-- ══════════════════════════════════════════════════════
         CÓMO JUGAR — Instrucciones con el emulador recomendado
         ══════════════════════════════════════════════════════ -->
    <section class="detail-howto" aria-labelledby="howto-title">
        <h2 class="detail-section-title" id="howto-title"><i data-i="play" aria-hidden="true"></i> Cómo jugar</h2>

        <?php if ($emuladorLocal): ?>
        <div class="howto-local">
            <h3 class="howto-local-title"><i data-i="hard-drive" aria-hidden="true"></i> En tu equipo</h3>
            <p class="howto-local-intro">
                Esta ROM es de <strong><?= htmlspecialchars($juego['consola_nombre'] ?? 'su consola') ?></strong>.
                El emulador recomendado es <strong><?= htmlspecialchars($emuladorLocal['nombre']) ?></strong>
                (<span class="howto-platforms">
                    <?php foreach ($emuladorLocal['plataformas'] as $p): ?>
                        <span class="platform-tag"><?= htmlspecialchars($p) ?></span>
                    <?php endforeach; ?>
                </span>).
                <a href="<?= htmlspecialchars($emuladorLocal['url']) ?>" target="_blank" rel="noopener noreferrer" class="howto-dl-link">
                    <i data-i="download" aria-hidden="true"></i> Descargar emulador
                </a>
            </p>
            <ol class="howto-steps">
                <?php foreach ($pasosLocales as $paso): ?>
                    <li><?= $paso ?></li>
                <?php endforeach; ?>
            </ol>
            <?php if (!empty($emuladorLocal['alterno'])): ?>
            <p class="howto-alt">
                Alternativa:
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
            No tenemos un emulador recomendado registrado para <?= htmlspecialchars($juego['consola_nombre'] ?? 'esta consola') ?>.
        </p>
        <?php endif; ?>
    </section>

    <!-- ── Related games ── -->
    <?php require_once 'views/components/related_games.php'; ?>
</div>

<!-- Toast de compartir (reutiliza el sistema existente) -->
<div class="share-toast" id="share-toast" role="status" aria-live="polite">¡Enlace copiado al portapapeles!</div>

<script>
(function () {
    'use strict';
    var btn   = document.getElementById('btn-share-game');
    var toast = document.getElementById('share-toast');
    if (!btn || !toast) return;

    function showToast() {
        toast.classList.add('share-toast--visible');
        clearTimeout(showToast._t);
        showToast._t = setTimeout(function () {
            toast.classList.remove('share-toast--visible');
        }, 2200);
    }

    function fallbackCopy(url) {
        var ta = document.createElement('textarea');
        ta.value = url;
        ta.setAttribute('readonly', '');
        ta.style.position = 'fixed';
        ta.style.opacity = '0';
        document.body.appendChild(ta);
        ta.select();
        try { document.execCommand('copy'); } catch (e) {}
        document.body.removeChild(ta);
    }

    btn.addEventListener('click', function () {
        var url = window.location.href;
        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(url).then(showToast, function () {
                fallbackCopy(url);
                showToast();
            });
        } else {
            fallbackCopy(url);
            showToast();
        }
    });
})();
</script>

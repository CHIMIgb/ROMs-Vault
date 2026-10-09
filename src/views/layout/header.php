<!DOCTYPE html>
<html lang="<?= htmlspecialchars((string) I18n::getLang()) ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <!-- Base del documento: las URLs limpias (/controlador/accion) hacen que
         las rutas relativas (public/css, public/js, rom_proxy.php, etc.)
         deban resolverse siempre contra la raíz del sitio. -->
    <base href="/">
    <?php if (class_exists('CsrfService')): ?>
        <?= CsrfService::metaTag() ?>
    <?php endif; ?>
    <title><?= htmlspecialchars($pageTitle ?? t('header.catalogo_titulo')) ?></title>
    <?php if (!empty($pageDescription)): ?>
        <meta name="description" content="<?= htmlspecialchars($pageDescription) ?>">
    <?php endif; ?>

    <?php if (!empty($pageType)): ?>
        <!-- Open Graph / compartir en redes -->
        <meta property="og:type" content="<?= htmlspecialchars($pageType) ?>">
        <meta property="og:title" content="<?= htmlspecialchars($pageTitle ?? t('app.nombre')) ?>">
        <meta property="og:description" content="<?= htmlspecialchars($pageDescription ?? '') ?>">
        <meta property="og:image" content="<?= htmlspecialchars($pageImage ?? '') ?>">
        <meta property="og:url" content="<?= htmlspecialchars($pageUrl ?? '') ?>">
        <meta name="twitter:card" content="summary_large_image">
        <meta name="twitter:title" content="<?= htmlspecialchars($pageTitle ?? t('app.nombre')) ?>">
        <meta name="twitter:description" content="<?= htmlspecialchars($pageDescription ?? '') ?>">
        <meta name="twitter:image" content="<?= htmlspecialchars($pageImage ?? '') ?>">
    <?php endif; ?>

    <?php if (!empty($pageJsonLd)): ?>
        <!-- Datos estructurados VideoGame (SEO) -->
        <script type="application/ld+json"><?= json_encode($pageJsonLd, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?></script>
    <?php endif; ?>

    <link rel="icon" type="image/png" href="public/uploads/icon.png">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="public/css/style.css">
</head>
<body>
    <div class="container">
        <header>

            <!-- ── BRAND ── Logo + Título ─────────────────── -->
            <div class="header-brand">

                <!-- Logo: icono de la aplicación al inicio del título -->
                <a href="/" class="header-logo-link" title="<?= t('header.inicio_titulo') ?>">
                    <img src="public/uploads/icon.png"
                         alt="<?= t('app.nombre') ?>"
                         class="header-logo"
                         width="56" height="56">
                </a>

                <div class="header-brand-text">
                    <h1><?= t('app.nombre') ?></h1>
                    <p class="subtitle">
                        <!-- play icon -->
                        <i data-i="play" data-cls="pxi-subtitle" aria-hidden="true"></i>
                        <?= t('app.slogan') ?>
                    </p>
                </div>
            </div>
            <!-- ────────────────────────────────────────────── -->

            <nav>
                <a href="/">
                    <i data-i="gamepad"  aria-hidden="true"></i>
                    <?= t('nav.catalogo') ?>
                </a>
                <?php 
                $currentUser = AuthMiddleware::getUser();
                if ($currentUser): 
                ?>
                <a href="/admin/dashboard">
                    <i data-i="shield-2" aria-hidden="true"></i>
                    <?= t('nav.panel_admin') ?>
                </a>
                <a href="/auth/logout">
                    <i data-i="logout"   aria-hidden="true"></i>
                    <?= t('nav.cerrar_sesion') ?>
                </a>
                <?php endif; ?>
            </nav>
        </header>
        <main>

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

                <?php
                // ── Selector de idioma (FASE 5.3) ───────────────────────────
                // Reutiliza el flujo ?lang= de index.php: persiste la cookie
                // rv_lang y redirige a la misma URL sin el parámetro. El form
                // (GET) apunta a la URI actual para conservar la ruta.
                // Banderas como SVG inline: renderizado garantizado en todos
                // los SO (a diferencia de emoji) y compatible con la CSP.
                $lang = I18n::getLang();
                $langAction = htmlspecialchars($_SERVER['REQUEST_URI'] ?? '/', ENT_QUOTES, 'UTF-8');
                $langFlags = [
                    'es' => '<svg class="lang-flag" viewBox="0 0 20 14" xmlns="http://www.w3.org/2000/svg" aria-hidden="true" focusable="false">'
                          . '<rect width="20" height="14" fill="#ffffff"/>'
                          . '<rect width="7" height="14" fill="#006847"/>'
                          . '<rect x="13" width="7" height="14" fill="#ce1126"/>'
                          . '<circle cx="10" cy="7" r="1.8" fill="#8a6d3b"/>'
                          . '</svg>',
                    'en' => '<svg class="lang-flag" viewBox="0 0 20 14" xmlns="http://www.w3.org/2000/svg" aria-hidden="true" focusable="false">'
                          . '<rect width="20" height="14" fill="#ffffff"/>'
                          . '<g fill="#b22234">'
                          . '<rect y="0" width="20" height="1.08"/><rect y="2.15" width="20" height="1.08"/>'
                          . '<rect y="4.31" width="20" height="1.08"/><rect y="6.46" width="20" height="1.08"/>'
                          . '<rect y="8.62" width="20" height="1.08"/><rect y="10.77" width="20" height="1.08"/>'
                          . '<rect y="12.92" width="20" height="1.08"/>'
                          . '</g>'
                          . '<rect width="8" height="7.5" fill="#3c3b6e"/>'
                          . '<g fill="#ffffff">'
                          . '<circle cx="1.6" cy="1.5" r="0.34"/><circle cx="4" cy="1.5" r="0.34"/><circle cx="6.4" cy="1.5" r="0.34"/>'
                          . '<circle cx="2.8" cy="3.75" r="0.34"/><circle cx="5.2" cy="3.75" r="0.34"/>'
                          . '<circle cx="1.6" cy="6" r="0.34"/><circle cx="4" cy="6" r="0.34"/><circle cx="6.4" cy="6" r="0.34"/>'
                          . '</g>'
                          . '</svg>',
                ];
                ?>
                <form class="lang-switch" method="get" action="<?= $langAction ?>">
                    <?= $langFlags[$lang] ?? '' ?>
                    <select class="lang-select" name="lang"
                            aria-label="<?= htmlspecialchars(t('header.seleccionar_idioma')) ?>"
                            title="<?= htmlspecialchars(t('header.seleccionar_idioma')) ?>"
                            onchange="this.form.submit()">
                        <option value="es" <?= $lang === 'es' ? 'selected' : '' ?>>ES</option>
                        <option value="en" <?= $lang === 'en' ? 'selected' : '' ?>>EN</option>
                    </select>
                    <noscript><button type="submit" class="lang-submit">OK</button></noscript>
                </form>
            </nav>
        </header>
        <main>

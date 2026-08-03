<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?php if (class_exists('CsrfService')): ?>
        <?= CsrfService::metaTag() ?>
    <?php endif; ?>
    <title><?= htmlspecialchars($pageTitle ?? 'ROMs Vault - Catálogo de videojuegos') ?></title>
    <?php if (!empty($pageDescription)): ?>
        <meta name="description" content="<?= htmlspecialchars($pageDescription) ?>">
    <?php endif; ?>

    <?php if (!empty($pageType)): ?>
        <!-- Open Graph / compartir en redes -->
        <meta property="og:type" content="<?= htmlspecialchars($pageType) ?>">
        <meta property="og:title" content="<?= htmlspecialchars($pageTitle ?? 'ROMs Vault') ?>">
        <meta property="og:description" content="<?= htmlspecialchars($pageDescription ?? '') ?>">
        <meta property="og:image" content="<?= htmlspecialchars($pageImage ?? '') ?>">
        <meta property="og:url" content="<?= htmlspecialchars($pageUrl ?? '') ?>">
        <meta name="twitter:card" content="summary_large_image">
        <meta name="twitter:title" content="<?= htmlspecialchars($pageTitle ?? 'ROMs Vault') ?>">
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
                <a href="index.php?controller=home&action=index" class="header-logo-link" title="ROMs Vault - Inicio">
                    <img src="public/uploads/icon.png"
                         alt="ROMs Vault"
                         class="header-logo"
                         width="56" height="56">
                </a>

                <div class="header-brand-text">
                    <h1>ROMs Vault</h1>
                    <p class="subtitle">
                        <!-- play icon -->
                        <i data-i="play" data-cls="pxi-subtitle" aria-hidden="true"></i>
                        Catálogo de ROMs e ISOs
                    </p>
                </div>
            </div>
            <!-- ────────────────────────────────────────────── -->

            <nav>
                <a href="index.php?controller=home&action=index">
                    <i data-i="gamepad"  aria-hidden="true"></i>
                    Catálogo
                </a>
                <?php 
                $currentUser = AuthMiddleware::getUser();
                if ($currentUser): 
                ?>
                <a href="index.php?controller=admin&action=dashboard">
                    <i data-i="shield-2" aria-hidden="true"></i>
                    Panel Admin
                </a>
                <a href="index.php?controller=auth&action=logout">
                    <i data-i="logout"   aria-hidden="true"></i>
                    Cerrar sesión
                </a>
                <?php endif; ?>
            </nav>
        </header>
        <main>

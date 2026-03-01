<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ROMs Vault - Catálogo de videojuegos</title>

    <link rel="icon" type="image/png" href="public/uploads/icon.png">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="public/css/style.css">

    <!-- pixelicons.js: sistema de iconos pixel art (pixelarticons.com) -->
    <script src="public/js/pixelicons.js" defer></script>
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
                <?php if (isset($_SESSION['user_id'])): ?>
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

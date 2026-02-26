<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Roms Vault</title>
    <link rel="stylesheet" href="public/css/style.css">
</head>
<body>
    <div class="container">
        <header>
            <h1>📥 Roms Vault - Catálogo de ISOs</h1>
            <nav>
                <a href="index.php">Inicio</a>
                <?php if (isset($_SESSION['user_id'])): ?>
                    <a href="index.php?controller=admin&action=dashboard">Admin</a>
                    <a href="index.php?controller=auth&action=logout">Cerrar sesión</a>
                <?php else: ?>
                    <a href="index.php?controller=auth&action=login">Admin</a>
                <?php endif; ?>
            </nav>
        </header>
        <main>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ROMs Vault - Catálogo de videojuegos</title>
    <link rel="stylesheet" href="public/css/style.css">
</head>
<body>
    <div class="container">
        <header>
            <h1>ROMs Vault</h1>
            <p class="subtitle">Catálogo de ROMs e ISOs</p>
            
            <nav>
                <!--
                <a href="index.php">Inicio</a>    
                -->
                <a href="index.php?controller=home&action=index">Catálogo</a>
                <?php if (isset($_SESSION['user_id'])): ?>
                    <a href="index.php?controller=admin&action=dashboard">Panel Admin</a>
                    <a href="index.php?controller=auth&action=logout">Cerrar sesión</a>    
                <?php else: ?>
                    
                    <a href="index.php?controller=auth&action=login">Acceso Admin</a>
                    
                <?php endif; ?>
            </nav>
        </header>
        <main>
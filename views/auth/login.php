<div class="login-form">
    <h2>Acceso Administrador</h2>
    <?php if (isset($error)): ?>
        <div class="error"><?= $error ?></div>
    <?php endif; ?>
    <form method="POST" action="index.php?controller=auth&action=login">
        <div>
            <label for="username">Usuario:</label>
            <input type="text" name="username" id="username" required>
        </div>
        <div>
            <label for="password">Contraseña:</label>
            <input type="password" name="password" id="password" required>
        </div>
        <button type="submit">Iniciar sesión</button>
    </form>
</div>
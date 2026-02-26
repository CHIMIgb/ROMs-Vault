<!-- views/auth/login.php -->
<div class="login-container">
    <h2>Acceso Administrador</h2>
    
    <?php if (isset($error)): ?>
        <div class="alert alert-error">
            <?= htmlspecialchars($error) ?>
        </div>
    <?php endif; ?>
    
    <form method="POST" action="index.php?controller=auth&action=login" autocomplete="off">
        <div class="form-group">
            <label for="username">Usuario</label>
            <input type="text" 
                   name="username" 
                   id="username" 
                   required 
                   placeholder="Ingresa tu usuario"
                   autocomplete="off">
        </div>
        
        <div class="form-group">
            <label for="password">Contraseña</label>
            <input type="password" 
                   name="password" 
                   id="password" 
                   required 
                   placeholder="Ingresa tu contraseña"
                   autocomplete="off">
        </div>
        
        <button type="submit" class="btn-primary" style="width: 100%;">Iniciar sesión</button>
    </form>
    
    <div style="margin-top: 1.5rem; text-align: center; font-size: 0.85rem; color: var(--text-light);">
        <p>Acceso restringido a personal autorizado</p>
    </div>
</div>
<!-- views/auth/login.php -->
<div class="login-container">
    <h2>Acceso Administrador</h2>
    
    <?php if (isset($error)): ?>
        <?php 
        require_once 'views/components/Alert.php';
        Alert::render('danger', htmlspecialchars($error), 'close'); 
        ?>
    <?php endif; ?>
    
    <form method="POST" action="index.php?controller=auth&action=login" autocomplete="off">
        <?= CsrfService::field() ?>
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
            <div class="password-wrapper">
                <input type="password" 
                       name="password" 
                       id="password" 
                       required 
                       placeholder="Ingresa tu contraseña"
                       autocomplete="off">
                <button type="button" class="password-toggle" onclick="togglePassword()" aria-label="Mostrar contraseña">
                    <svg id="eye-icon" viewBox="0 0 24 24" fill="currentColor" width="18" height="18" aria-hidden="true">
                        <path d="M12 4.5C7 4.5 2.73 7.61 1 12c1.73 4.39 6 7.5 11 7.5s9.27-3.11 11-7.5c-1.73-4.39-6-7.5-11-7.5zM12 17c-2.76 0-5-2.24-5-5s2.24-5 5-5 5 2.24 5 5-2.24 5-5 5zm0-8c-1.66 0-3 1.34-3 3s1.34 3 3 3 3-1.34 3-3-1.34-3-3-3z"/>
                    </svg>
                    <svg id="eye-off-icon" viewBox="0 0 24 24" fill="currentColor" width="18" height="18" aria-hidden="true" style="display:none;">
                        <path d="M12 7c2.76 0 5 2.24 5 5 0 .65-.13 1.26-.36 1.83l2.92 2.92c1.51-1.26 2.7-2.89 3.43-4.75-1.73-4.39-6-7.5-11-7.5-1.4 0-2.74.25-3.98.7l2.16 2.16C10.74 7.13 11.35 7 12 7zM2 4.27l2.28 2.28.46.46A11.804 11.804 0 0 0 1 12c1.73 4.39 6 7.5 11 7.5 1.55 0 3.03-.3 4.38-.84l.42.42L19.73 22 21 20.73 3.27 3 2 4.27zM7.53 9.8l1.55 1.55c-.05.21-.08.43-.08.65 0 1.66 1.34 3 3 3 .22 0 .44-.03.65-.08l1.55 1.55c-.67.33-1.41.53-2.2.53-2.76 0-5-2.24-5-5 0-.79.2-1.53.53-2.2zm4.31-.78 3.15 3.15.02-.16c0-1.66-1.34-3-3-3l-.17.01z"/>
                    </svg>
                </button>
            </div>
        </div>
        
        <button type="submit" class="btn-primary" style="width: 100%;"><i data-i="shield-2"></i> Iniciar sesión</button>
    </form>
    
    <div style="margin-top: 1.5rem; text-align: center; font-size: 0.85rem; color: var(--text-light);">
        <p>Acceso restringido a personal autorizado</p>
    </div>
</div>

<script>
function togglePassword() {
    const input = document.getElementById('password');
    const eyeIcon = document.getElementById('eye-icon');
    const eyeOffIcon = document.getElementById('eye-off-icon');

    if (input.type === 'password') {
        input.type = 'text';
        eyeIcon.style.display = 'none';
        eyeOffIcon.style.display = 'block';
    } else {
        input.type = 'password';
        eyeIcon.style.display = 'block';
        eyeOffIcon.style.display = 'none';
    }
}
</script>

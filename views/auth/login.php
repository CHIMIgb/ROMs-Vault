<!-- views/auth/login.php -->
<div class="login-container">
    <h2>Acceso Administrador</h2>
    
    <?php if (isset($error)): ?>
        <div class="rv-inline-alert rv-inline--danger rv-inline--visible">
            <span class="rv-inline-icon">✖</span>
            <span class="rv-inline-msg"><?= htmlspecialchars($error) ?></span>
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
            <div class="password-wrapper">
                <input type="password" 
                       name="password" 
                       id="password" 
                       required 
                       placeholder="Ingresa tu contraseña"
                       autocomplete="off">
                <button type="button" class="password-toggle" onclick="togglePassword()" aria-label="Mostrar contraseña">
                    <svg id="eye-icon" xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
                        <circle cx="12" cy="12" r="3"/>
                    </svg>
                    <svg id="eye-off-icon" xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="display:none;">
                        <path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94"/>
                        <path d="M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19"/>
                        <line x1="1" y1="1" x2="23" y2="23"/>
                    </svg>
                </button>
            </div>
        </div>
        
        <button type="submit" class="btn-primary" style="width: 100%;">Iniciar sesión</button>
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

<!-- views/auth/two_factor.php -->
<div class="login-container">
    <h2>Verificación en dos pasos</h2>

    <?php if (isset($error)): ?>
        <?php 
        require_once __DIR__ . '/../components/Alert.php';
        Alert::render('danger', htmlspecialchars($error), 'close'); 
        ?>
    <?php endif; ?>

    <p style="margin-bottom: 1rem; color: var(--text-light); font-size: 0.9rem; text-align: center;">
        Introduce el código de 6 dígitos de tu aplicación autenticadora.
    </p>

    <form method="POST" action="/auth/twoFactor" autocomplete="off">
        <?= CsrfService::field() ?>
        <div class="form-group">
            <label for="code">Código de verificación</label>
            <input type="text"
                   name="code"
                   id="code"
                   required
                   maxlength="6"
                   inputmode="numeric"
                   pattern="[0-9]{6}"
                   placeholder="000000"
                   autocomplete="one-time-code">
        </div>

        <button type="submit" class="btn-primary" style="width: 100%;"><i data-i="shield-2"></i> Verificar</button>
    </form>

    <div style="margin-top: 1.5rem; text-align: center; font-size: 0.85rem; color: var(--text-light);">
        <a href="/auth/logout" style="color: var(--text-light); text-decoration: underline;">Cancelar e ir al inicio</a>
    </div>
</div>
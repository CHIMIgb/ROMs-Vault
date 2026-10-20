<!-- views/auth/two_factor.php -->
<div class="login-container">
    <h2><?= t('two_factor.titulo') ?></h2>

    <?php if (isset($error)): ?>
        <?php 
        require_once __DIR__ . '/../components/Alert.php';
        Alert::render('danger', htmlspecialchars($error), 'close'); 
        ?>
    <?php endif; ?>

    <p class="auth-hint auth-hint--tfa">
        <?= t('two_factor.instruccion') ?>
    </p>

    <form method="POST" action="/auth/twoFactor" autocomplete="off">
        <?= CsrfService::field() ?>
        <div class="form-group">
            <label for="code"><?= t('two_factor.codigo') ?></label>
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

        <button type="submit" class="btn-primary btn-block"><i data-i="shield-2"></i> <?= t('two_factor.verificar') ?></button>
    </form>

    <div class="auth-hint">
        <a href="/auth/logout" class="text-muted text-underline"><?= t('two_factor.cancelar') ?></a>
    </div>
</div>
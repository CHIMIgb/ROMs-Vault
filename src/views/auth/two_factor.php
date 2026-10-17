<!-- views/auth/two_factor.php -->
<div class="login-container">
    <h2><?= t('two_factor.titulo') ?></h2>

    <?php if (isset($error)): ?>
        <?php 
        require_once __DIR__ . '/../components/Alert.php';
        Alert::render('danger', htmlspecialchars($error), 'close'); 
        ?>
    <?php endif; ?>

    <p style="margin-bottom: 1rem; color: var(--slate-light); font-size: 0.9rem; text-align: center;">
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

        <button type="submit" class="btn-primary" style="width: 100%;"><i data-i="shield-2"></i> <?= t('two_factor.verificar') ?></button>
    </form>

    <div style="margin-top: 1.5rem; text-align: center; font-size: 0.85rem; color: var(--slate-light);">
        <a href="/auth/logout" style="color: var(--slate-light); text-decoration: underline;"><?= t('two_factor.cancelar') ?></a>
    </div>
</div>
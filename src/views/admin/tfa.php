<!-- views/admin/tfa.php -->
<div class="admin-header">
    <h2><?= t('admin.tfa.titulo') ?></h2>
    <div class="admin-header-actions">
        <a href="/admin/dashboard" class="btn-primary"><i data-i="dashboard"></i> <?= t('admin.tfa.volver_panel') ?></a>
    </div>
</div>

<?php if ($msg): ?>
    <?php
    require_once __DIR__ . '/../components/Alert.php';
    Alert::render($msgTipo, htmlspecialchars($msg), 'close');
    ?>
<?php endif; ?>

<div class="stat-card stat-card--mb-15">
    <div class="stat-label"><?= t('admin.tfa.estado') ?></div>
    <div class="stat-value <?= $tfaActivo ? 'stat-value--ok' : 'stat-value--muted' ?>">
        <?= $tfaActivo ? t('admin.tfa.activado') : t('admin.tfa.desactivado') ?>
    </div>
    <p class="admin-hint admin-hint--lead">
        <?= t('admin.tfa.descripcion') ?>
    </p>
</div>

<?php if ($tfaActivo): ?>
    <div class="stat-card stat-card--mb-1">
        <h3 class="ranking-title ranking-title--tight"><?= t('admin.tfa.desactivar') ?></h3>
        <p class="admin-hint">
            <?= t('admin.tfa.desactivar_ayuda') ?>
        </p>
        <form method="POST" action="/admin/tfa" autocomplete="off" class="form-row">
            <?= CsrfService::field() ?>
            <input type="hidden" name="accion" value="desactivar">
            <div class="form-group form-group--grow">
                <label for="tfa-password"><?= t('admin.tfa.password') ?></label>
                <input type="password" name="password" id="tfa-password" required autocomplete="off">
            </div>
            <button type="submit" class="btn-primary btn-primary--danger"><?= t('admin.tfa.desactivar') ?></button>
        </form>
    </div>
<?php elseif ($secretPendiente !== null): ?>
    <div class="stat-card stat-card--mb-1">
        <h3 class="ranking-title ranking-title--tight"><?= t('admin.tfa.paso1_titulo') ?></h3>
        <p class="admin-hint admin-hint--tight">
            <?= t('admin.tfa.paso1_ayuda') ?>
        </p>
        <div class="tfa-secret-row">
            <div>
                <div class="tfa-secret-label"><?= t('admin.tfa.clave_manual') ?></div>
                <code class="tfa-secret-code"><?= htmlspecialchars($secretPendiente) ?></code>
            </div>
            <div>
                <div class="tfa-secret-label"><?= t('admin.tfa.uri') ?></div>
                <code class="tfa-secret-code tfa-secret-code--sm"><?= htmlspecialchars($provisionUri) ?></code>
            </div>
        </div>

        <h3 class="ranking-title ranking-title--tight"><?= t('admin.tfa.paso2_titulo') ?></h3>
        <form method="POST" action="/admin/tfa" autocomplete="off" class="form-row">
            <?= CsrfService::field() ?>
            <input type="hidden" name="accion" value="confirmar">
            <div class="form-group form-group--grow-sm">
                <label for="tfa-code"><?= t('admin.tfa.codigo') ?></label>
                <input type="text" name="code" id="tfa-code" required maxlength="6" inputmode="numeric"
                       pattern="[0-9]{6}" placeholder="<?= t('admin.tfa.codigo_placeholder') ?>" autocomplete="one-time-code">
            </div>
            <button type="submit" class="btn-primary btn-primary--success"><?= t('admin.tfa.activar') ?></button>
        </form>
    </div>
<?php else: ?>
    <div class="stat-card stat-card--mb-1">
        <h3 class="ranking-title ranking-title--tight"><?= t('admin.tfa.activar') ?></h3>
        <p class="admin-hint">
            <?= t('admin.tfa.activar_ayuda') ?>
        </p>
        <form method="POST" action="/admin/tfa">
            <?= CsrfService::field() ?>
            <input type="hidden" name="accion" value="activar">
            <button type="submit" class="btn-primary btn-primary--success"><i data-i="shield-2"></i> <?= t('admin.tfa.activar') ?></button>
        </form>
    </div>
<?php endif; ?>
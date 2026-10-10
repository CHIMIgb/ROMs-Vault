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

<div class="stat-card" style="margin-bottom: 1.5rem;">
    <div class="stat-label"><?= t('admin.tfa.estado') ?></div>
    <div class="stat-value" style="color: <?= $tfaActivo ? 'var(--success)' : 'var(--text-light)' ?>;">
        <?= $tfaActivo ? t('admin.tfa.activado') : t('admin.tfa.desactivado') ?>
    </div>
    <p style="margin-top: 0.5rem; font-size: 0.9rem; color: var(--text-light);">
        <?= t('admin.tfa.descripcion') ?>
    </p>
</div>

<?php if ($tfaActivo): ?>
    <div class="stat-card" style="margin-bottom: 1rem;">
        <h3 class="ranking-title" style="margin-bottom: 0.75rem;"><?= t('admin.tfa.desactivar') ?></h3>
        <p style="margin-bottom: 1rem; font-size: 0.9rem; color: var(--text-light);">
            <?= t('admin.tfa.desactivar_ayuda') ?>
        </p>
        <form method="POST" action="/admin/tfa" autocomplete="off" style="display:flex; gap:0.6rem; flex-wrap:wrap; align-items:flex-end;">
            <?= CsrfService::field() ?>
            <input type="hidden" name="accion" value="desactivar">
            <div class="form-group" style="flex:1; min-width:220px; margin:0;">
                <label for="tfa-password"><?= t('admin.tfa.password') ?></label>
                <input type="password" name="password" id="tfa-password" required autocomplete="off">
            </div>
            <button type="submit" class="btn-primary" style="background-color: var(--danger, #c0392b);"><?= t('admin.tfa.desactivar') ?></button>
        </form>
    </div>
<?php elseif ($secretPendiente !== null): ?>
    <div class="stat-card" style="margin-bottom: 1rem;">
        <h3 class="ranking-title" style="margin-bottom: 0.75rem;"><?= t('admin.tfa.paso1') ?></h3>
        <p style="margin-bottom: 0.75rem; font-size: 0.9rem; color: var(--text-light);">
            <?= t('admin.tfa.paso1_ayuda') ?>
        </p>
        <div style="display:flex; gap:1.5rem; flex-wrap:wrap; align-items:center; margin-bottom: 1rem;">
            <div>
                <div style="font-size:0.85rem; color:var(--text-light); margin-bottom:0.3rem;"><?= t('admin.tfa.clave_manual') ?></div>
                <code style="font-size:0.9rem; word-break:break-all; user-select:all;"><?= htmlspecialchars($secretPendiente) ?></code>
            </div>
            <div>
                <div style="font-size:0.85rem; color:var(--text-light); margin-bottom:0.3rem;"><?= t('admin.tfa.uri') ?></div>
                <code style="font-size:0.8rem; word-break:break-all; user-select:all;"><?= htmlspecialchars($provisionUri) ?></code>
            </div>
        </div>

        <h3 class="ranking-title" style="margin-bottom: 0.75rem;"><?= t('admin.tfa.paso2') ?></h3>
        <form method="POST" action="/admin/tfa" autocomplete="off" style="display:flex; gap:0.6rem; flex-wrap:wrap; align-items:flex-end;">
            <?= CsrfService::field() ?>
            <input type="hidden" name="accion" value="confirmar">
            <div class="form-group" style="flex:1; min-width:160px; margin:0;">
                <label for="tfa-code"><?= t('admin.tfa.codigo') ?></label>
                <input type="text" name="code" id="tfa-code" required maxlength="6" inputmode="numeric"
                       pattern="[0-9]{6}" placeholder="<?= t('admin.tfa.codigo_placeholder') ?>" autocomplete="one-time-code">
            </div>
            <button type="submit" class="btn-primary" style="background-color: var(--success);"><?= t('admin.tfa.activar') ?></button>
        </form>
    </div>
<?php else: ?>
    <div class="stat-card" style="margin-bottom: 1rem;">
        <h3 class="ranking-title" style="margin-bottom: 0.75rem;"><?= t('admin.tfa.activar') ?></h3>
        <p style="margin-bottom: 1rem; font-size: 0.9rem; color: var(--text-light);">
            <?= t('admin.tfa.activar_ayuda') ?>
        </p>
        <form method="POST" action="/admin/tfa">
            <?= CsrfService::field() ?>
            <input type="hidden" name="accion" value="activar">
            <button type="submit" class="btn-primary" style="background-color: var(--success);"><i data-i="shield-2"></i> <?= t('admin.tfa.activar') ?></button>
        </form>
    </div>
<?php endif; ?>
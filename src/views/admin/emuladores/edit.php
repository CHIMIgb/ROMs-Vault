<!-- views/admin/emuladores/edit.php -->
<div class="form-container">

    <!-- Cabecera del formulario -->
    <div class="form-container-header">
        <h2><?= t('admin.emulador.editar_titulo', ['nombre' => htmlspecialchars($consola['nombre'])]) ?></h2>
    </div>

    <?php if (isset($error)): ?>
        <?php 
        require_once __DIR__ . '/../../components/Alert.php';
        Alert::render('danger', htmlspecialchars($error), 'close'); 
        ?>
    <?php endif; ?>

    <form method="POST" autocomplete="off">
        <?= CsrfService::field() ?>
        <p style="margin:0 0 1.5rem;color:var(--text-light);font-size:.92rem;">
            <?= t('admin.emulador.ayuda_edit') ?>
        </p>

        <!-- ═══ Emulador principal ═══ -->
        <div style="margin-bottom:2.25rem;padding-bottom:1.5rem;border-bottom:1px dashed var(--border-mid);">
            <h3 style="margin:0 0 1rem;font-size:1.05rem;color:var(--text);">
                <i data-i="gamepad"></i> <?= t('admin.emulador.principal') ?>
            </h3>

            <div class="form-group">
                <label for="principal_nombre"><?= t('admin.emulador.nombre_principal') ?></label>
                <input type="text"
                       id="principal_nombre"
                       name="principal_nombre"
                       value="<?= htmlspecialchars($actual['principal']['nombre'] ?? '') ?>"
                       placeholder="<?= t('admin.emulador.example_ppsspp') ?>"
                       required>
            </div>

            <div class="form-group">
                <label><?= t('admin.emulador.plataformas') ?></label>
                <div style="display:flex;gap:1.5rem;padding-top:.25rem;">
                    <?php
                    $platPrincipal = $actual['principal']['plataformas'] ?? [];
                    foreach (['PC', 'Android'] as $p): ?>
                    <label style="display:flex;align-items:center;gap:.4rem;cursor:pointer;">
                        <input type="checkbox"
                               name="principal_plataformas[]"
                               value="<?= $p ?>"
                               <?= in_array($p, $platPrincipal, true) ? 'checked' : '' ?>>
                        <?= $p ?>
                    </label>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="form-group">
                <label for="principal_url"><?= t('admin.emulador.url') ?></label>
                <input type="url"
                       id="principal_url"
                       name="principal_url"
                       value="<?= htmlspecialchars($actual['principal']['url'] ?? '') ?>"
                       placeholder="<?= t('admin.emulador.url_placeholder') ?>"
                       required>
            </div>
        </div>

        <!-- ═══ Emulador alternativo (opcional) ═══ -->
        <div style="margin-bottom:2.25rem;padding-bottom:1.5rem;border-bottom:1px dashed var(--border-mid);">
            <h3 style="margin:0 0 1rem;font-size:1.05rem;color:var(--text);">
                <i data-i="gamepad-2"></i> <?= t('admin.emulador.alternativo') ?> <span style="font-weight:400;color:var(--text-light);font-size:.85rem;"><?= t('admin.emulador.opcional') ?></span>
            </h3>

            <div class="form-group">
                <label for="alterno_nombre"><?= t('admin.emulador.nombre_alternativo') ?></label>
                <input type="text"
                       id="alterno_nombre"
                       name="alterno_nombre"
                       value="<?= htmlspecialchars($actual['alterno']['nombre'] ?? '') ?>"
                       placeholder="<?= t('admin.emulador.example_retroarch') ?>">
            </div>

            <div class="form-group">
                <label><?= t('admin.emulador.plataformas_opcional') ?></label>
                <div style="display:flex;gap:1.5rem;padding-top:.25rem;">
                    <?php
                    $platAlterno = $actual['alterno']['plataformas'] ?? [];
                    foreach (['PC', 'Android'] as $p): ?>
                    <label style="display:flex;align-items:center;gap:.4rem;cursor:pointer;">
                        <input type="checkbox"
                               name="alterno_plataformas[]"
                               value="<?= $p ?>"
                               <?= in_array($p, $platAlterno, true) ? 'checked' : '' ?>>
                        <?= $p ?>
                    </label>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="form-group">
                <label for="alterno_url"><?= t('admin.emulador.url_opcional') ?></label>
                <input type="url"
                       id="alterno_url"
                       name="alterno_url"
                       value="<?= htmlspecialchars($actual['alterno']['url'] ?? '') ?>"
                       placeholder="<?= t('admin.emulador.url_placeholder') ?>">
            </div>
        </div>

        <!-- Botones -->
        <div class="form-actions">
            <button type="submit" class="btn-primary">
                <i data-i="save"></i> <?= t('admin.emulador.guardar_cambios') ?>
            </button>
            <a href="/emulador/index"
               class="btn-primary"
               style="background:var(--cream-dark);color:var(--slate);box-shadow:3px 3px 0 var(--border-mid);border-color:var(--border-mid);">
                <i data-i="close"></i> <?= t('admin.emulador.cancelar') ?>
            </a>
        </div>
    </form>
</div>

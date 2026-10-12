<!-- views/admin/consolas/add.php -->
<div class="form-container">

    <!-- Cabecera del formulario — misma estructura que form-container-header definida en style.css -->
    <div class="form-container-header">
        <h2><?= t('admin.consola.nueva') ?></h2>
    </div>

    <?php if (isset($error)): ?>
        <?php 
        require_once __DIR__ . '/../../components/Alert.php';
        Alert::render('danger', htmlspecialchars($error), 'close'); 
        ?>
    <?php endif; ?>

    <form method="POST" autocomplete="off">
        <?= CsrfService::field() ?>

        <!-- Nombre -->
        <div class="form-group">
            <label for="nombre"><?= t('admin.consola.nombre_sistema') ?></label>
            <input type="text"
                   id="nombre"
                   name="nombre"
                   value="<?= htmlspecialchars($_POST['nombre'] ?? '') ?>"
                   placeholder="<?= t('admin.consola.nombre_placeholder') ?>"
                   required>
        </div>

        <!-- Slug (URL) -->
        <div class="form-group">
            <label for="slug"><?= t('admin.consola.slug') ?></label>
            <input type="text"
                   id="slug"
                   name="slug"
                   value="<?= htmlspecialchars($_POST['slug'] ?? '') ?>"
                   placeholder="<?= t('admin.consola.slug_placeholder') ?>">
        </div>

        <!-- Fabricante -->
        <div class="form-group">
            <label for="fabricante"><?= t('admin.consola.fabricante') ?></label>
            <input type="text"
                   id="fabricante"
                   name="fabricante"
                   value="<?= htmlspecialchars($_POST['fabricante'] ?? '') ?>"
                   placeholder="<?= t('admin.consola.fabricante_placeholder') ?>">
        </div>

        <!-- Descripción -->
        <div class="form-group">
            <label for="descripcion"><?= t('admin.consola.descripcion') ?></label>
            <textarea id="descripcion"
                      name="descripcion"
                      rows="4"
                      placeholder="<?= t('admin.consola.descripcion_placeholder') ?>"><?= htmlspecialchars($_POST['descripcion'] ?? '') ?></textarea>
        </div>

        <!-- Emulación online -->
        <div class="form-group" style="display:flex;align-items:center;gap:.75rem;padding:.9rem 1rem;background:var(--cream);border:1px solid var(--border-mid);border-radius:12px;">
            <input type="checkbox"
                   id="emulacion_online"
                   name="emulacion_online"
                   value="1"
                   <?= empty($_POST['emulacion_online']) ? 'checked' : '' ?>
                   style="width:18px;height:18px;accent-color:var(--primary);cursor:pointer;">
            <div>
                <label for="emulacion_online" style="margin:0;font-weight:600;cursor:pointer;"><?= t('admin.consola.emulacion_online') ?></label>
                <small style="display:block;color:var(--slate-mid);"><?= t('admin.consola.emulacion_online_ayuda') ?></small>
            </div>
        </div>

        <!-- Botones — mismo .form-actions que add.php de juegos -->
        <div class="form-actions">
            <button type="submit" class="btn-primary">
                <i data-i="save"></i> <?= t('admin.consola.guardar') ?>
            </button>
            <a href="/consola/index"
               class="btn-primary"
               style="background:var(--cream-dark);color:var(--slate);box-shadow:3px 3px 0 var(--border-mid);border-color:var(--border-mid);">
                <i data-i="close"></i> <?= t('admin.consola.cancelar') ?>
            </a>
        </div>
    </form>
</div>

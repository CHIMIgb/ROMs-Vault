<!-- views/admin/categorias/add.php -->
<div class="form-container">

    <!-- Cabecera del formulario -->
    <div class="form-container-header">
        <h2><?= t('admin.categoria.nueva') ?></h2>
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
            <label for="nombre"><?= t('admin.categoria.nombre_categoria') ?></label>
            <input type="text"
                   id="nombre"
                   name="nombre"
                   value="<?= htmlspecialchars($_POST['nombre'] ?? '') ?>"
                   placeholder="<?= t('admin.categoria.nombre_placeholder') ?>"
                   required>
        </div>

        <!-- Descripción -->
        <div class="form-group">
            <label for="descripcion"><?= t('admin.categoria.descripcion') ?></label>
            <textarea id="descripcion"
                      name="descripcion"
                      rows="4"
                      placeholder="<?= t('admin.categoria.descripcion_placeholder') ?>"><?= htmlspecialchars($_POST['descripcion'] ?? '') ?></textarea>
        </div>

        <!-- Botones -->
        <div class="form-actions">
            <button type="submit" class="btn-primary">
                <i data-i="save"></i> <?= t('admin.categoria.guardar') ?>
            </button>
            <a href="/categoria/index"
               class="btn-primary btn-primary--ghost">
                <i data-i="close"></i> <?= t('admin.categoria.cancelar') ?>
            </a>
        </div>
    </form>
</div>

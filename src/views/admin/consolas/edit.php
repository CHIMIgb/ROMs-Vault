<!-- views/admin/consolas/edit.php -->
<div class="form-container">

    <!-- Cabecera del formulario -->
    <div class="form-container-header">
        <h2><?= t('admin.consola.editar_titulo', ['nombre' => htmlspecialchars($consola['nombre'])]) ?></h2>
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
                   value="<?= htmlspecialchars($_POST['nombre'] ?? $consola['nombre']) ?>"
                   required>
        </div>

        <!-- Slug (URL) -->
        <div class="form-group">
            <label for="slug"><?= t('admin.consola.slug') ?></label>
            <input type="text"
                   id="slug"
                   name="slug"
                   value="<?= htmlspecialchars($_POST['slug'] ?? $consola['slug'] ?? '') ?>"
                   placeholder="<?= t('admin.consola.slug_placeholder') ?>">
            <small><?= t('admin.consola.slug_ayuda') ?></small>
        </div>

        <!-- Fabricante -->
        <div class="form-group">
            <label for="fabricante"><?= t('admin.consola.fabricante') ?></label>
            <input type="text"
                   id="fabricante"
                   name="fabricante"
                   value="<?= htmlspecialchars($_POST['fabricante'] ?? $consola['fabricante'] ?? '') ?>"
                   placeholder="<?= t('admin.consola.fabricante_placeholder') ?>">
        </div>

        <!-- Descripción -->
        <div class="form-group">
            <label for="descripcion"><?= t('admin.consola.descripcion') ?></label>
            <textarea id="descripcion"
                      name="descripcion"
                      rows="4"><?= htmlspecialchars($_POST['descripcion'] ?? $consola['descripcion'] ?? '') ?></textarea>
        </div>

        <!-- Emulación online -->
        <div class="form-group" style="display:flex;align-items:center;gap:.75rem;padding:.9rem 1rem;background:var(--cream);border:1px solid var(--border-mid);border-radius:12px;">
            <input type="checkbox"
                   id="emulacion_online"
                   name="emulacion_online"
                   value="1"
                   <?= (isset($_POST['emulacion_online']) ? $_POST['emulacion_online'] : $consola['emulacion_online']) ? 'checked' : '' ?>
                   style="width:18px;height:18px;accent-color:var(--primary);cursor:pointer;">
            <div>
                <label for="emulacion_online" style="margin:0;font-weight:600;cursor:pointer;"><?= t('admin.consola.emulacion_online') ?></label>
                <small style="display:block;color:var(--slate-mid);"><?= t('admin.consola.emulacion_online_ayuda') ?></small>
            </div>
        </div>

        <!-- Botones -->
        <div class="form-actions">
            <button type="submit" class="btn-primary">
                <i data-i="save"></i> <?= t('admin.consola.guardar_cambios') ?>
            </button>
            <a href="/consola/index"
               class="btn-primary"
               style="background:var(--cream-dark);color:var(--slate);box-shadow:3px 3px 0 var(--border-mid);border-color:var(--border-mid);">
                <i data-i="close"></i> <?= t('admin.consola.cancelar') ?>
            </a>
            <button type="button"
                    id="btn-eliminar-consola"
                    class="btn-primary"
                    style="background:var(--danger, #b00020);color:#fff;box-shadow:3px 3px 0 var(--border-dark);border-color:var(--border-dark);"
                    data-id="<?= (int) $consola['id'] ?>"
                    data-nombre="<?= htmlspecialchars($consola['nombre'], ENT_QUOTES) ?>">
                <i data-i="trash"></i> <?= t('admin.consola.eliminar') ?>
            </button>
        </div>
    </form>

    <!-- Formulario oculto de eliminación (POST con token CSRF) -->
    <form id="form-eliminar-consola" method="POST" action="/consola/delete">
        <?= CsrfService::field() ?>
        <input type="hidden" name="id" value="<?= (int) $consola['id'] ?>">
    </form>
</div>

<script>
(function () {
    'use strict';
    const btn = document.getElementById('btn-eliminar-consola');
    if (!btn) return;
    btn.addEventListener('click', function () {
        const id     = this.dataset.id;
        const nombre = this.dataset.nombre;
        RVAlerts.confirm({
            tipo:      'danger',
            titulo:    <?= json_encode(t('admin.consola.confirm_eliminar_titulo')) ?>,
            mensaje:   <?= json_encode(t('admin.consola.confirm_eliminar', ['nombre' => '{n}'])) ?>.replace('{n}', nombre),
            btnOk:     <?= json_encode(t('admin.consola.si_eliminar')) ?>,
            btnCancel: <?= json_encode(t('admin.consola.cancelar')) ?>,
            onOk: () => {
                document.getElementById('form-eliminar-consola').submit();
            }
        });
    });
})();
</script>

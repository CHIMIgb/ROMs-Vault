<!-- views/admin/consolas/edit.php -->
<div class="form-container">

    <!-- Cabecera del formulario -->
    <div class="form-container-header">
        <h2>Editar Consola: <?= htmlspecialchars($consola['nombre']) ?></h2>
    </div>

    <?php if (isset($error)): ?>
        <?php 
        require_once 'views/components/Alert.php';
        Alert::render('danger', htmlspecialchars($error), 'close'); 
        ?>
    <?php endif; ?>

    <form method="POST" autocomplete="off">

        <!-- Nombre -->
        <div class="form-group">
            <label for="nombre">Nombre del sistema *</label>
            <input type="text"
                   id="nombre"
                   name="nombre"
                   value="<?= htmlspecialchars($_POST['nombre'] ?? $consola['nombre']) ?>"
                   required>
        </div>

        <!-- Fabricante -->
        <div class="form-group">
            <label for="fabricante">Fabricante</label>
            <input type="text"
                   id="fabricante"
                   name="fabricante"
                   value="<?= htmlspecialchars($_POST['fabricante'] ?? $consola['fabricante'] ?? '') ?>"
                   placeholder="Ej: Sony, Nintendo, Sega">
        </div>

        <!-- Descripción -->
        <div class="form-group">
            <label for="descripcion">Descripción</label>
            <textarea id="descripcion"
                      name="descripcion"
                      rows="4"><?= htmlspecialchars($_POST['descripcion'] ?? $consola['descripcion'] ?? '') ?></textarea>
        </div>

        <!-- Botones -->
        <div class="form-actions">
            <button type="submit" class="btn-primary">
                <i data-i="save"></i> Guardar cambios
            </button>
            <a href="index.php?controller=consola&action=index"
               class="btn-primary"
               style="background:var(--cream-dark);color:var(--slate);box-shadow:3px 3px 0 var(--border-mid);border-color:var(--border-mid);">
                <i data-i="close"></i> Cancelar
            </a>
        </div>
    </form>
</div>

<script>
(function () {
    'use strict';
    document.getElementById('btn-eliminar-consola').addEventListener('click', function () {
        const id     = this.dataset.id;
        const nombre = this.dataset.nombre;
        RVAlerts.confirm({
            tipo:      'danger',
            titulo:    '¿Eliminar consola?',
            mensaje:   `Vas a eliminar permanentemente <strong>${nombre}</strong>.<br><br>
                        <small>Solo puedes eliminar consolas sin juegos asociados.</small>`,
            btnOk:     'Sí, eliminar',
            btnCancel: 'Cancelar',
            onOk: () => {
                window.location.href = `index.php?controller=consola&action=delete&id=${id}`;
            }
        });
    });
})();
</script>

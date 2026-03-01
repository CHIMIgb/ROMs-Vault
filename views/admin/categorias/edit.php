<!-- views/admin/categorias/edit.php -->
<div class="form-container">

    <!-- Cabecera del formulario -->
    <div class="form-container-header">
        <h2>Editar Categoría: <?= htmlspecialchars($categoria['nombre']) ?></h2>
    </div>

    <?php if (isset($error)): ?>
    <div class="rv-inline-alert rv-inline--danger rv-inline--visible">
        <span class="rv-inline-icon"><i data-i="close"></i></span>
        <span class="rv-inline-msg"><?= htmlspecialchars($error) ?></span>
    </div>
    <?php endif; ?>

    <form method="POST" autocomplete="off">

        <!-- Nombre -->
        <div class="form-group">
            <label for="nombre">Nombre de la categoría *</label>
            <input type="text"
                   id="nombre"
                   name="nombre"
                   value="<?= htmlspecialchars($_POST['nombre'] ?? $categoria['nombre']) ?>"
                   required>
        </div>

        <!-- Descripción -->
        <div class="form-group">
            <label for="descripcion">Descripción</label>
            <textarea id="descripcion"
                      name="descripcion"
                      rows="4"><?= htmlspecialchars($_POST['descripcion'] ?? $categoria['descripcion'] ?? '') ?></textarea>
        </div>

        <!-- Botones -->
        <div class="form-actions">
            <button type="submit" class="btn-primary">
                <i data-i="save"></i> Guardar cambios
            </button>
            <a href="index.php?controller=categoria&action=index"
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
    document.getElementById('btn-eliminar-categoria').addEventListener('click', function () {
        const id     = this.dataset.id;
        const nombre = this.dataset.nombre;
        RVAlerts.confirm({
            tipo:      'danger',
            titulo:    '¿Eliminar categoría?',
            mensaje:   `Vas a eliminar permanentemente <strong>${nombre}</strong>.<br><br>
                        <small>Solo puedes eliminar categorías sin juegos asociados.</small>`,
            btnOk:     'Sí, eliminar',
            btnCancel: 'Cancelar',
            onOk: () => {
                window.location.href = `index.php?controller=categoria&action=delete&id=${id}`;
            }
        });
    });
})();
</script>

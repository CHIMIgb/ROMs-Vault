<!-- views/admin/categorias/edit.php -->
<div class="form-container">

    <!-- Cabecera del formulario -->
    <div class="form-container-header">
        <h2>Editar Categoría: <?= htmlspecialchars($categoria['nombre']) ?></h2>
    </div>

    <?php if (isset($error)): ?>
        <?php 
        require_once 'views/components/Alert.php';
        Alert::render('danger', htmlspecialchars($error), 'close'); 
        ?>
    <?php endif; ?>

    <form method="POST" autocomplete="off">
        <?= CsrfService::field() ?>

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
            <a href="/categoria/index"
               class="btn-primary"
               style="background:var(--cream-dark);color:var(--slate);box-shadow:3px 3px 0 var(--border-mid);border-color:var(--border-mid);">
                <i data-i="close"></i> Cancelar
            </a>
            <button type="button"
                    id="btn-eliminar-categoria"
                    class="btn-primary"
                    style="background:var(--danger, #b00020);color:#fff;box-shadow:3px 3px 0 var(--border-dark);border-color:var(--border-dark);"
                    data-id="<?= (int) $categoria['id'] ?>"
                    data-nombre="<?= htmlspecialchars($categoria['nombre'], ENT_QUOTES) ?>">
                <i data-i="trash"></i> Eliminar
            </button>
        </div>
    </form>

    <!-- Formulario oculto de eliminación (POST con token CSRF) -->
    <form id="form-eliminar-categoria" method="POST" action="/categoria/delete">
        <?= CsrfService::field() ?>
        <input type="hidden" name="id" value="<?= (int) $categoria['id'] ?>">
    </form>
</div>

<script>
(function () {
    'use strict';
    const btn = document.getElementById('btn-eliminar-categoria');
    if (!btn) return;
    btn.addEventListener('click', function () {
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
                document.getElementById('form-eliminar-categoria').submit();
            }
        });
    });
})();
</script>

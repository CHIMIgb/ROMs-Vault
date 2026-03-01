<!-- views/admin/consolas/edit.php -->
<div class="form-container">

    <!-- Cabecera del formulario -->
    <div class="form-container-header">
        <h2>Editar Consola: <?= htmlspecialchars($consola['nombre']) ?></h2>
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

        <!-- Activo — mismo .form-checkbox del sistema -->
        <div class="form-group">
            <div class="form-checkbox">
                <input type="checkbox"
                       id="activo"
                       name="activo"
                       value="1"
                       <?= (isset($_POST['nombre']) ? isset($_POST['activo']) : $consola['activo']) ? 'checked' : '' ?>>
                <label for="activo">Consola activa (visible en filtros del catálogo)</label>
            </div>
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

    <!-- Zona peligrosa — usa la imagen actual de la consola igual que edit.php de juego usa la imagen -->
    <div style="margin-top:2.5rem;padding:1.25rem 1.5rem;border:3px solid var(--red);background:var(--cream-light);box-shadow:4px 4px 0 var(--red-dark);">
        <p style="font-family:'Press Start 2P',monospace;font-size:0.5rem;color:var(--red);margin-bottom:0.75rem;text-transform:uppercase;letter-spacing:0.06em;">
            <i data-i="warning"></i> Zona peligrosa
        </p>
        <p style="font-family:'Courier Prime',monospace;font-size:0.88rem;color:var(--slate-mid);margin-bottom:1rem;">
            Eliminar esta consola solo es posible si no tiene juegos asociados.
            Esta acción no se puede deshacer.
        </p>
        <button class="btn-delete" id="btn-eliminar-consola"
                style="font-family:'Press Start 2P',monospace;cursor:pointer;"
                data-id="<?= $consola['id'] ?>"
                data-nombre="<?= htmlspecialchars($consola['nombre'], ENT_QUOTES) ?>">
            <i data-i="trash"></i> Eliminar consola
        </button>
    </div>
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

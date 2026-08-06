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
        <?= CsrfService::field() ?>

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

        <!-- Emulación online -->
        <div class="form-group" style="display:flex;align-items:center;gap:.75rem;padding:.9rem 1rem;background:var(--cream);border:1px solid var(--border-mid);border-radius:12px;">
            <input type="checkbox"
                   id="emulacion_online"
                   name="emulacion_online"
                   value="1"
                   <?= (isset($_POST['emulacion_online']) ? $_POST['emulacion_online'] : $consola['emulacion_online']) ? 'checked' : '' ?>
                   style="width:18px;height:18px;accent-color:var(--primary);cursor:pointer;">
            <div>
                <label for="emulacion_online" style="margin:0;font-weight:600;cursor:pointer;">Emulación online</label>
                <small style="display:block;color:var(--slate-mid);">Permite jugar los juegos de esta consola en el navegador (EmulatorJS). La descarga siempre sigue disponible.</small>
            </div>
        </div>

        <!-- Botones -->
        <div class="form-actions">
            <button type="submit" class="btn-primary">
                <i data-i="save"></i> Guardar cambios
            </button>
            <a href="/consola/index"
               class="btn-primary"
               style="background:var(--cream-dark);color:var(--slate);box-shadow:3px 3px 0 var(--border-mid);border-color:var(--border-mid);">
                <i data-i="close"></i> Cancelar
            </a>
            <button type="button"
                    id="btn-eliminar-consola"
                    class="btn-primary"
                    style="background:var(--danger, #b00020);color:#fff;box-shadow:3px 3px 0 var(--border-dark);border-color:var(--border-dark);"
                    data-id="<?= (int) $consola['id'] ?>"
                    data-nombre="<?= htmlspecialchars($consola['nombre'], ENT_QUOTES) ?>">
                <i data-i="trash"></i> Eliminar
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
            titulo:    '¿Eliminar consola?',
            mensaje:   `Vas a eliminar permanentemente <strong>${nombre}</strong>.<br><br>
                        <small>Solo puedes eliminar consolas sin juegos asociados.</small>`,
            btnOk:     'Sí, eliminar',
            btnCancel: 'Cancelar',
            onOk: () => {
                document.getElementById('form-eliminar-consola').submit();
            }
        });
    });
})();
</script>

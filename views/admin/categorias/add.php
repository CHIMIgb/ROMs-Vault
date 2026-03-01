<!-- views/admin/categorias/add.php -->
<div class="form-container">

    <!-- Cabecera del formulario -->
    <div class="form-container-header">
        <h2>Nueva Categoría</h2>
        <a href="index.php?controller=categoria&action=index"
           class="btn-primary"
           style="font-size:0.45rem;">
            <i data-i="arrow-left"></i> Volver
        </a>
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
                   value="<?= htmlspecialchars($_POST['nombre'] ?? '') ?>"
                   placeholder="Ej: Acción, RPG, Deportes"
                   required>
        </div>

        <!-- Descripción -->
        <div class="form-group">
            <label for="descripcion">Descripción</label>
            <textarea id="descripcion"
                      name="descripcion"
                      rows="4"
                      placeholder="Breve descripción del género o tipo de juego..."><?= htmlspecialchars($_POST['descripcion'] ?? '') ?></textarea>
        </div>

        <!-- Activo — mismo .form-checkbox del sistema -->
        <div class="form-group">
            <div class="form-checkbox">
                <input type="checkbox"
                       id="activo"
                       name="activo"
                       value="1"
                       <?= (!isset($_POST['nombre']) || isset($_POST['activo'])) ? 'checked' : '' ?>>
                <label for="activo">Categoría activa (visible en filtros del catálogo)</label>
            </div>
        </div>

        <!-- Botones -->
        <div class="form-actions">
            <button type="submit" class="btn-primary">
                <i data-i="save"></i> Guardar categoría
            </button>
            <a href="index.php?controller=categoria&action=index"
               class="btn-primary"
               style="background:var(--cream-dark);color:var(--slate);box-shadow:3px 3px 0 var(--border-mid);border-color:var(--border-mid);">
                <i data-i="close"></i> Cancelar
            </a>
        </div>
    </form>
</div>

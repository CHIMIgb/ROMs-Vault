<!-- views/admin/consolas/add.php -->
<div class="form-container">

    <!-- Cabecera del formulario — misma estructura que form-container-header definida en style.css -->
    <div class="form-container-header">
        <h2>Nueva Consola</h2>
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
                   value="<?= htmlspecialchars($_POST['nombre'] ?? '') ?>"
                   placeholder="Ej: PlayStation Portable"
                   required>
        </div>

        <!-- Fabricante -->
        <div class="form-group">
            <label for="fabricante">Fabricante</label>
            <input type="text"
                   id="fabricante"
                   name="fabricante"
                   value="<?= htmlspecialchars($_POST['fabricante'] ?? '') ?>"
                   placeholder="Ej: Sony, Nintendo, Sega">
        </div>

        <!-- Descripción -->
        <div class="form-group">
            <label for="descripcion">Descripción</label>
            <textarea id="descripcion"
                      name="descripcion"
                      rows="4"
                      placeholder="Breve descripción de la consola..."><?= htmlspecialchars($_POST['descripcion'] ?? '') ?></textarea>
        </div>

        <!-- Botones — mismo .form-actions que add.php de juegos -->
        <div class="form-actions">
            <button type="submit" class="btn-primary">
                <i data-i="save"></i> Guardar consola
            </button>
            <a href="index.php?controller=consola&action=index"
               class="btn-primary"
               style="background:var(--cream-dark);color:var(--slate);box-shadow:3px 3px 0 var(--border-mid);border-color:var(--border-mid);">
                <i data-i="close"></i> Cancelar
            </a>
        </div>
    </form>
</div>

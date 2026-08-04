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
        <?= CsrfService::field() ?>

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

        <!-- Emulación online -->
        <div class="form-group" style="display:flex;align-items:center;gap:.75rem;padding:.9rem 1rem;background:var(--cream);border:1px solid var(--border-mid);border-radius:12px;">
            <input type="checkbox"
                   id="emulacion_online"
                   name="emulacion_online"
                   value="1"
                   <?= empty($_POST['emulacion_online']) ? 'checked' : '' ?>
                   style="width:18px;height:18px;accent-color:var(--primary);cursor:pointer;">
            <div>
                <label for="emulacion_online" style="margin:0;font-weight:600;cursor:pointer;">Emulación online</label>
                <small style="display:block;color:var(--slate-mid);">Permite jugar los juegos de esta consola en el navegador (EmulatorJS). La descarga siempre sigue disponible.</small>
            </div>
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

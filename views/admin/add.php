<!-- views/admin/add.php -->
<div class="form-container">
    <h2>Añadir Nuevo Juego</h2>
    
    <?php if (isset($error)): ?>
        <div class="rv-inline-alert rv-inline--danger rv-inline--visible">
            <span class="rv-inline-icon"><i data-i="close"></i></span>
            <span class="rv-inline-msg"><?= htmlspecialchars($error) ?></span>
        </div>
    <?php endif; ?>
    
    <form autocomplete="off" method="POST" enctype="multipart/form-data">
        <!-- Información básica -->
        <div class="form-group">
            <label for="titulo">Título del juego *</label>
            <input type="text" 
                   id="titulo"
                   name="titulo" 
                   value="<?= htmlspecialchars($_POST['titulo'] ?? '') ?>" 
                   required
                   placeholder="Ej: WipEout Pulse">
        </div>
        
        <div class="form-group">
            <label for="descripcion">Descripción</label>
            <textarea id="descripcion"
                      name="descripcion" 
                      rows="4"
                      placeholder="Breve descripción del juego..."><?= htmlspecialchars($_POST['descripcion'] ?? '') ?></textarea>
        </div>
        
        <!-- Plataforma y categoría -->
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
            <div class="form-group">
                <label for="consola_id">Consola *</label>
                <select id="consola_id" name="consola_id" required>
                    <option value="">Seleccionar consola</option>
                    <?php foreach ($consolas as $consola): ?>
                        <option value="<?= $consola['id'] ?>" 
                            <?= (isset($_POST['consola_id']) && $_POST['consola_id'] == $consola['id']) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($consola['nombre']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="form-group">
                <label for="categoria_id">Categoría *</label>
                <select id="categoria_id" name="categoria_id" required>
                    <option value="">Seleccionar categoría</option>
                    <?php foreach ($categorias as $categoria): ?>
                        <option value="<?= $categoria['id'] ?>" 
                            <?= (isset($_POST['categoria_id']) && $_POST['categoria_id'] == $categoria['id']) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($categoria['nombre']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        
        <!-- Región y fecha -->
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
            <div class="form-group">
                <label for="region">Región</label>
                <select id="region" name="region">
                    <option value="">Seleccionar región</option>
                    <option value="PAL" <?= (isset($_POST['region']) && $_POST['region'] == 'PAL') ? 'selected' : '' ?>>PAL</option>
                    <option value="NTSC" <?= (isset($_POST['region']) && $_POST['region'] == 'NTSC') ? 'selected' : '' ?>>NTSC</option>
                    <option value="NTSC-J" <?= (isset($_POST['region']) && $_POST['region'] == 'NTSC-J') ? 'selected' : '' ?>>NTSC-J</option>
                    <option value="NTSC-U" <?= (isset($_POST['region']) && $_POST['region'] == 'NTSC-U') ? 'selected' : '' ?>>NTSC-U</option>
                </select>
            </div>
            
            <div class="form-group">
                <label for="fecha_lanzamiento">Fecha de lanzamiento</label>
                <input type="date" 
                       id="fecha_lanzamiento"
                       name="fecha_lanzamiento" 
                       value="<?= htmlspecialchars($_POST['fecha_lanzamiento'] ?? '') ?>">
            </div>
        </div>
        
        <!-- Idiomas y formato -->
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
            <div class="form-group">
                <label for="idiomas">Idiomas</label>
                <input type="text" 
                       id="idiomas"
                       name="idiomas" 
                       value="<?= htmlspecialchars($_POST['idiomas'] ?? '') ?>"
                       placeholder="Ej: Español, Inglés">
            </div>
            
            <div class="form-group">
                <label for="formato_imagen">Formato</label>
                <input type="text" 
                       id="formato_imagen"
                       name="formato_imagen" 
                       value="<?= htmlspecialchars($_POST['formato_imagen'] ?? '') ?>"
                       placeholder="Ej: ISO, ROM, Hack">
            </div>
        </div>
        
        <!-- Códigos -->
        <div class="form-group">
            <label for="game_id_code">Game ID Code</label>
            <input type="text" 
                   id="game_id_code"
                   name="game_id_code" 
                   value="<?= htmlspecialchars($_POST['game_id_code'] ?? '') ?>"
                   placeholder="Ej: ULES-12345">
        </div>
        
        <!-- Google Drive -->
        <div class="form-group">
            <label for="google_drive_file_id">Google Drive File ID *</label>
            <input type="text" 
                   id="google_drive_file_id"
                   name="google_drive_file_id" 
                   value="<?= htmlspecialchars($_POST['google_drive_file_id'] ?? '') ?>" 
                   required
                   placeholder="Ej: 1fVWgArFcMMUFVX5SXkdMR5pQYU2uyXYj">
            <small>El ID del archivo en Google Drive</small>
        </div>
        
        <div class="form-group">
            <label for="google_drive_view_link">Google Drive View Link</label>
            <input type="url" 
                   id="google_drive_view_link"
                   name="google_drive_view_link" 
                   value="<?= htmlspecialchars($_POST['google_drive_view_link'] ?? '') ?>"
                   placeholder="https://drive.google.com/file/d/...">
        </div>
        
        <!-- Tamaño -->
        <div class="form-group">
            <label for="size_bytes">Tamaño en bytes</label>
            <input type="text" 
                   id="size_bytes"
                   name="size_bytes" 
                   value="<?= htmlspecialchars($_POST['size_bytes'] ?? '') ?>"
                   pattern="[0-9]+"
                   oninput="this.value = this.value.replace(/[^0-9]/g, '')"
                   placeholder="Ej: 452984832">
            <small>Solo números enteros positivos</small>
        </div>
        
        <!-- Imagen -->
        <div class="form-group">
            <label for="imagen">Portada del juego</label>
            <div class="file-input-wrapper">
                <input type="file"
                       id="imagen"
                       name="imagen"
                       accept="image/jpeg,image/png,image/gif,image/webp">
                <div class="file-input-display" onclick="document.getElementById('imagen').click()">
                    <div class="file-input-btn"><i data-i="upload-2"></i> Elegir archivo
                    </div>
                    <div class="file-input-name" id="imagen-name">Sin archivo seleccionado</div>
                </div>
            </div>
            <small>Formatos permitidos: JPG, PNG, GIF, WEBP. Máximo 2MB</small>
        </div>

        <!-- Campo oculto: activo siempre en true al crear -->
        <input type="hidden" name="activo" value="1">
        
        <!-- Botones -->
        <div class="form-actions">
            <button type="submit" class="btn-primary"><i data-i="save"></i> Guardar juego</button>
            <a href="index.php?controller=admin&action=dashboard" class="btn-secondary" style="padding: 0.6rem 1.2rem; background: var(--background); color: var(--text); text-decoration: none; border-radius: 6px;"><i data-i="close"></i> Cancelar</a>
        </div>
    </form>
</div>

<script>
document.getElementById('imagen').addEventListener('change', function() {
    const nameEl = document.getElementById('imagen-name');
    if (this.files && this.files[0]) {
        nameEl.textContent = this.files[0].name;
        nameEl.classList.add('has-file');
    } else {
        nameEl.textContent = 'Sin archivo seleccionado';
        nameEl.classList.remove('has-file');
    }
});
</script>

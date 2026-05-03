<!-- views/admin/edit.php -->
<div class="form-container">
    <h2>Editar Juego: <?= htmlspecialchars($juego['titulo']) ?></h2>
    
    <?php if (isset($error)): ?>
        <?php 
        require_once 'views/components/Alert.php';
        Alert::render('danger', htmlspecialchars($error), 'close'); 
        ?>
    <?php endif; ?>
    
    <form autocomplete="off" method="POST" enctype="multipart/form-data">
        <!-- Información básica -->
        <div class="form-group">
            <label for="titulo">Título del juego *</label>
            <input type="text" 
                   id="titulo"
                   name="titulo" 
                   value="<?= htmlspecialchars($juego['titulo'] ?? '') ?>" 
                   required>
        </div>
        
        <div class="form-group">
            <label for="descripcion">Descripción</label>
            <textarea id="descripcion"
                      name="descripcion" 
                      rows="4"><?= htmlspecialchars($juego['descripcion'] ?? '') ?></textarea>
        </div>
        
        <!-- Plataforma y categoría -->
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
            <div class="form-group">
                <label for="consola_id">Consola *</label>
                <select id="consola_id" name="consola_id" required>
                    <option value="">Seleccionar consola</option>
                    <?php foreach ($consolas as $consola): ?>
                        <option value="<?= $consola['id'] ?>" 
                            <?= ($juego['consola_id'] == $consola['id']) ? 'selected' : '' ?>>
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
                            <?= ($juego['categoria_id'] == $categoria['id']) ? 'selected' : '' ?>>
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
                    <option value="PAL" <?= ($juego['region'] == 'PAL') ? 'selected' : '' ?>>PAL</option>
                    <option value="NTSC" <?= ($juego['region'] == 'NTSC') ? 'selected' : '' ?>>NTSC</option>
                    <option value="NTSC-J" <?= ($juego['region'] == 'NTSC-J') ? 'selected' : '' ?>>NTSC-J</option>
                    <option value="NTSC-U" <?= ($juego['region'] == 'NTSC-U') ? 'selected' : '' ?>>NTSC-U</option>
                </select>
            </div>
            
            <div class="form-group">
                <label for="fecha_lanzamiento">Fecha de lanzamiento</label>
                <input type="date" 
                       id="fecha_lanzamiento"
                       name="fecha_lanzamiento" 
                       value="<?= $juego['fecha_lanzamiento'] ?? '' ?>">
            </div>
        </div>
        
        <!-- Idiomas y formato -->
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
            <div class="form-group">
                <label for="idiomas">Idiomas</label>
                <input type="text" 
                       id="idiomas"
                       name="idiomas" 
                       value="<?= htmlspecialchars($juego['idiomas'] ?? '') ?>"
                       placeholder="Ej: Español, Inglés">
            </div>
            
            <div class="form-group">
                <label for="formato_imagen">Formato</label>
                <input type="text" 
                       id="formato_imagen"
                       name="formato_imagen" 
                       value="<?= htmlspecialchars($juego['formato_imagen'] ?? '') ?>"
                       placeholder="Ej: ISO, ROM, Hack">
            </div>
        </div>
        
        <!-- Códigos -->
        <div class="form-group">
            <label for="game_id_code">Game ID Code</label>
            <input type="text" 
                   id="game_id_code"
                   name="game_id_code" 
                   value="<?= htmlspecialchars($juego['game_id_code'] ?? '') ?>"
                   placeholder="Ej: ULES-12345">
        </div>
        
        <!-- Google Drive -->
        <div class="form-group">
            <label for="google_drive_file_id">Google Drive File ID *</label>
            <input type="text" 
                   id="google_drive_file_id"
                   name="google_drive_file_id" 
                   value="<?= htmlspecialchars($juego['google_drive_file_id'] ?? '') ?>" 
                   required>
            <small>El ID del archivo en Google Drive</small>
        </div>
        
        <div class="form-group">
            <label for="google_drive_view_link">Google Drive View Link</label>
            <input type="url" 
                   id="google_drive_view_link"
                   name="google_drive_view_link" 
                   value="<?= htmlspecialchars($juego['google_drive_view_link'] ?? '') ?>">
        </div>
        
        <!-- Tamaño -->
        <div class="form-group">
            <label for="size_bytes">Tamaño en bytes</label>
            <input type="text" 
                   id="size_bytes"
                   name="size_bytes" 
                   value="<?= $juego['size_bytes'] ?? 0 ?>"
                   pattern="[0-9]+"
                   oninput="this.value = this.value.replace(/[^0-9]/g, '')">
            <small>Solo números enteros positivos</small>
        </div>
        
        <!-- Imagen -->
        <div class="form-group">
            <label for="imagen">Portada del juego</label>
            <?php if (!empty($juego['imagen'])): ?>
                <div style="display:flex; align-items:flex-start; gap:1rem; margin-bottom:0.75rem; padding:0.75rem; background:var(--cream-dark); border:2px solid var(--border-dark); box-shadow:var(--raise-shadow);">
                    <img src="<?= htmlspecialchars($juego['imagen']) ?>"
                         alt="Portada actual"
                         style="width:80px; height:80px; object-fit:cover; border:2px solid var(--border-dark); flex-shrink:0;">
                    <div style="font-family:'Courier Prime',monospace;">
                        <p style="font-size:0.78rem; font-weight:700; color:var(--slate); margin-bottom:0.3rem; text-transform:uppercase; letter-spacing:0.05em;">Imagen actual</p>
                        <p style="font-size:0.78rem; color:var(--slate-mid); font-style:italic;"><i data-i="warning"></i> Sube una nueva imagen para reemplazarla</p>
                    </div>
                </div>
            <?php endif; ?>
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
                
        <!-- Botones -->
        <div class="form-actions">
            <button type="submit" class="btn-primary"><i data-i="save"></i> Guardar cambios</button>
                <a href="index.php?controller=admin&action=dashboard" class="btn-primary" style="background:var(--cream-dark);color:var(--slate);box-shadow:3px 3px 0 var(--border-mid);border-color:var(--border-mid);">
                    <i data-i="close"></i> Cancelar
                </a>
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
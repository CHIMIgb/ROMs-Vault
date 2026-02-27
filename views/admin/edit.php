<!-- views/admin/edit.php -->
<div class="form-container">
    <h2>Editar Juego: <?= htmlspecialchars($juego['titulo']) ?></h2>
    
    <?php if (isset($error)): ?>
        <div class="alert alert-error">
            <?= $error ?>
        </div>
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
            <input type="file" 
                   id="imagen"
                   name="imagen" 
                   accept="image/jpeg,image/png,image/gif,image/webp">
            <?php if (!empty($juego['imagen'])): ?>
                <div style="margin-top: 1rem; padding: 1rem; background: var(--background); border-radius: 6px;">
                    <p style="margin-bottom: 0.5rem; font-size: 0.9rem;">Imagen actual:</p>
                    <img src="<?= htmlspecialchars($juego['imagen']) ?>" 
                         alt="Portada actual" 
                         style="max-width: 150px; border-radius: 4px;">
                    <p style="margin-top: 0.5rem; font-size: 0.8rem; color: var(--text-light);">
                        ⚠️ Sube una nueva imagen para reemplazarla
                    </p>
                </div>
            <?php endif; ?>
        </div>
                
        <!-- Botones -->
        <div class="form-actions">
            <button type="submit" class="btn-primary">Guardar cambios</button>
            <a href="index.php?controller=admin&action=dashboard" class="btn-secondary" style="padding: 0.6rem 1.2rem; background: var(--background); color: var(--text); text-decoration: none; border-radius: 6px;">Cancelar</a>
        </div>
    </form>
</div>
<!-- views/admin/edit.php -->
<h2>Editar Juego: <?= htmlspecialchars($juego['titulo']) ?></h2>

<?php if (isset($error)): ?>
    <div class="error" style="color: red; background: #ffeeee; padding: 10px; border-radius: 5px; margin-bottom: 15px;">
        <?= $error ?>
    </div>
<?php endif; ?>

<form method="POST" enctype="multipart/form-data">
    <div>
        <label>Título:</label>
        <input type="text" name="titulo" value="<?= htmlspecialchars($juego['titulo'] ?? '') ?>" required>
    </div>
    
    <div>
        <label>Descripción:</label>
        <textarea name="descripcion" rows="4"><?= htmlspecialchars($juego['descripcion'] ?? '') ?></textarea>
    </div>
    
    <div>
        <label>Consola:</label>
        <select name="consola_id" required>
            <option value="">Selecciona una consola</option>
            <?php foreach ($consolas as $consola): ?>
                <option value="<?= $consola['id'] ?>" 
                    <?= ($juego['consola_id'] == $consola['id']) ? 'selected' : '' ?>>
                    <?= htmlspecialchars($consola['nombre']) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>
    
    <div>
        <label>Categoría:</label>
        <select name="categoria_id" required>
            <option value="">Selecciona una categoría</option>
            <?php foreach ($categorias as $categoria): ?>
                <option value="<?= $categoria['id'] ?>" 
                    <?= ($juego['categoria_id'] == $categoria['id']) ? 'selected' : '' ?>>
                    <?= htmlspecialchars($categoria['nombre']) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>
    
    <div>
        <label>Región:</label>
        <select name="region">
            <option value="">Selecciona región</option>
            <option value="PAL" <?= ($juego['region'] == 'PAL') ? 'selected' : '' ?>>PAL</option>
            <option value="NTSC" <?= ($juego['region'] == 'NTSC') ? 'selected' : '' ?>>NTSC</option>
            <option value="NTSC-J" <?= ($juego['region'] == 'NTSC-J') ? 'selected' : '' ?>>NTSC-J</option>
        </select>
    </div>
    
    <div>
        <label>Fecha de lanzamiento:</label>
        <input type="date" name="fecha_lanzamiento" value="<?= $juego['fecha_lanzamiento'] ?? '' ?>">
    </div>
    
    <div>
        <label>Idiomas:</label>
        <input type="text" name="idiomas" value="<?= htmlspecialchars($juego['idiomas'] ?? '') ?>">
    </div>
    
    <div>
        <label>Formato de imagen:</label>
        <input type="text" name="formato_imagen" value="<?= htmlspecialchars($juego['formato_imagen'] ?? '') ?>">
    </div>
    
    <div>
        <label>Game ID Code:</label>
        <input type="text" name="game_id_code" value="<?= htmlspecialchars($juego['game_id_code'] ?? '') ?>">
    </div>
    
    <div>
        <label>Google Drive File ID:</label>
        <input type="text" name="google_drive_file_id" value="<?= htmlspecialchars($juego['google_drive_file_id'] ?? '') ?>" required>
    </div>
    
    <div>
        <label>Google Drive View Link:</label>
        <input type="url" name="google_drive_view_link" value="<?= htmlspecialchars($juego['google_drive_view_link'] ?? '') ?>">
    </div>
    
    <div>
        <label>Tamaño en bytes:</label>
        <input type="number" name="size_bytes" value="<?= $juego['size_bytes'] ?? 0 ?>">
    </div>
    
    <div>
        <label>Portada del juego:</label>
        <input type="file" name="imagen" accept="image/jpeg,image/png,image/gif,image/webp">
        <?php if (!empty($juego['imagen'])): ?>
            <div style="margin-top: 10px;">
                <img src="<?= $juego['imagen'] ?>" width="100" alt="Portada actual">
                <p><small>Imagen actual (sube una nueva para reemplazarla)</small></p>
            </div>
        <?php endif; ?>
    </div>
    
    <div>
        <label>
            <input type="checkbox" name="activo" <?= ($juego['activo']) ? 'checked' : '' ?>> Activo
        </label>
    </div>
    
    <div style="margin-top: 20px;">
        <button type="submit">Guardar cambios</button>
        <a href="index.php?controller=admin&action=dashboard" style="margin-left: 10px;">Cancelar</a>
    </div>
</form>
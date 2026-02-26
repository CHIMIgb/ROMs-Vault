<h2><?= isset($juego) ? 'Editar' : 'Añadir' ?> Juego</h2>
<?php if (isset($error)) echo "<p class='error'>$error</p>"; ?>

<form autocomplete="off" method="POST" enctype="multipart/form-data">
    <div>
        <label>Título:</label>
        <input type="text" name="titulo" value="<?= $juego['titulo'] ?? '' ?>" required>
    </div>
    <div>
        <label>Descripción:</label>
        <textarea name="descripcion"><?= $juego['descripcion'] ?? '' ?></textarea>
    </div>
    <div>
        <label>Consola:</label>
        <select name="consola_id" required>
            <?php foreach ($consolas as $consola): ?>
                <option value="<?= $consola['id'] ?>" <?= (isset($juego) && $juego['consola_id'] == $consola['id']) ? 'selected' : '' ?>>
                    <?= htmlspecialchars($consola['nombre']) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>
    <div>
        <label>Categoría:</label>
        <select name="categoria_id" required>
            <?php foreach ($categorias as $categoria): ?>
                <option value="<?= $categoria['id'] ?>" <?= (isset($juego) && $juego['categoria_id'] == $categoria['id']) ? 'selected' : '' ?>>
                    <?= htmlspecialchars($categoria['nombre']) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>
    <div>
        <label>Región:</label>
        <select name="region">
            <option value="PAL" <?= (isset($juego) && $juego['region'] == 'PAL') ? 'selected' : '' ?>>PAL</option>
            <option value="NTSC" <?= (isset($juego) && $juego['region'] == 'NTSC') ? 'selected' : '' ?>>NTSC</option>
            <option value="NTSC-J" <?= (isset($juego) && $juego['region'] == 'NTSC-J') ? 'selected' : '' ?>>NTSC-J</option>
        </select>
    </div>
    <div>
        <label>Fecha de lanzamiento:</label>
        <input type="date" name="fecha_lanzamiento" value="<?= $juego['fecha_lanzamiento'] ?? '' ?>">
    </div>
    <div>
        <label>Idiomas:</label>
        <input type="text" name="idiomas" value="<?= $juego['idiomas'] ?? '' ?>">
    </div>
    <div>
        <label>Formato de imagen:</label>
        <input type="text" name="formato_imagen" value="<?= $juego['formato_imagen'] ?? '' ?>">
    </div>
    <div>
        <label>Game ID Code:</label>
        <input type="text" name="game_id_code" value="<?= $juego['game_id_code'] ?? '' ?>">
    </div>
    <div>
        <label>Google Drive File ID:</label>
        <input type="text" name="google_drive_file_id" value="<?= $juego['google_drive_file_id'] ?? '' ?>" required>
    </div>
    <div>
        <label>Google Drive View Link:</label>
        <input type="url" name="google_drive_view_link" value="<?= $juego['google_drive_view_link'] ?? '' ?>">
    </div>
    <div>
        <label>Tamaño en bytes:</label>
        <input type="text" name="size_bytes" value="<?= $juego['size_bytes'] ?? '' ?>" pattern="[0-9]+" oninput="this.value = this.value.replace(/[^0-9]/g, '')">
    </div>
    <div>
        <label>Portada:</label>
        <input type="file" name="imagen" accept="image/*">
        <?php if (isset($juego) && $juego['imagen']): ?>
            <img src="<?= $juego['imagen'] ?>" width="100">
        <?php endif; ?>
    </div>
    <div>
        <label>
            <input type="checkbox" name="activo" <?= (isset($juego) && $juego['activo']) ? 'checked' : '' ?>> Activo
        </label>
    </div>
    <button type="submit">Guardar</button>
</form>
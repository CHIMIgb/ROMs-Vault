<!-- views/admin/add.php -->
<div class="form-container">
    <h2>Añadir Nuevo Juego</h2>
    
    <?php if (isset($error)): ?>
        <?php 
        require_once 'views/components/Alert.php';
        Alert::render('danger', htmlspecialchars($error), 'close'); 
        ?>
    <?php endif; ?>
    
    <form autocomplete="off" method="POST" enctype="multipart/form-data">
        <?= CsrfService::field() ?>
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
        
        <!-- Portada -->
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
            <div class="image-preview-grid" id="imagen-preview-grid" hidden></div>
            <small>Formatos permitidos: JPG, PNG, GIF, WEBP. Máximo 2MB.<br>
            <i>Las imágenes se optimizarán y convertirán automáticamente a formato WebP.</i></small>
        </div>

        <!-- Capturas -->
        <div class="form-group">
            <label>Capturas del juego</label>
            <input type="file"
                   id="capturas"
                   name="capturas[]"
                   accept="image/jpeg,image/png,image/gif,image/webp"
                   multiple
                   hidden>
            <div class="image-preview-grid" id="capturas-preview-grid"></div>
            <small>Opcional. Formatos: JPG, PNG, GIF, WEBP. Máx. 2MB por archivo y 7 capturas.<br>
            <i>Puedes ir añadiendo imágenes de a varias: las selecciones se acumulan y cada una se puede quitar.</i><br>
            <i>Se mostrarán en el carrusel de la ficha del juego.</i></small>
        </div>

        <!-- Campo oculto: activo siempre en true al crear -->
        <input type="hidden" name="activo" value="1">
        
        <!-- Botones -->
        <div class="form-actions">
            <button type="submit" class="btn-primary"><i data-i="save"></i> Guardar juego</button>
                <a href="/admin/dashboard" class="btn-primary" style="background:var(--cream-dark);color:var(--slate);box-shadow:3px 3px 0 var(--border-mid);border-color:var(--border-mid);">
                    <i data-i="close"></i> Cancelar
                </a>
        </div>
    </form>
</div>

<script>
(function() {
    'use strict';

    // ── Portada: preview de un solo archivo + botón quitar ──
    var portadaInput = document.getElementById('imagen');
    var portadaGrid  = document.getElementById('imagen-preview-grid');

    function renderPortada() {
        var f = portadaInput.files && portadaInput.files[0];
        portadaGrid.innerHTML = '';
        if (!f) {
            portadaGrid.hidden = true;
            return;
        }

        var item = document.createElement('div');
        item.className = 'image-preview-item';

        var img = document.createElement('img');
        img.src = URL.createObjectURL(f);
        img.alt = 'Preview de la portada';

        var remove = document.createElement('button');
        remove.type = 'button';
        remove.className = 'image-preview-remove';
        remove.setAttribute('aria-label', 'Quitar portada seleccionada');
        remove.textContent = '\u2715';
        remove.addEventListener('click', function() {
            portadaInput.value = '';
            renderPortada();
        });

        item.appendChild(img);
        item.appendChild(remove);
        portadaGrid.appendChild(item);
        portadaGrid.hidden = false;
    }

    portadaInput.addEventListener('change', renderPortada);

    // ── Capturas: selección acumulada con previews ──
    var capturasInput = document.getElementById('capturas');
    var capturasGrid  = document.getElementById('capturas-preview-grid');
    var capturasFiles = [];
    var MAX_CAPTURAS  = 7;

    capturasInput.addEventListener('change', function() {
        // Acumular los archivos recién elegidos sin perder los anteriores
        for (var i = 0; i < capturasInput.files.length; i++) {
            if (capturasFiles.length >= MAX_CAPTURAS) break;
            capturasFiles.push(capturasInput.files[i]);
        }
        capturasInput.value = ''; // permite elegir más en la próxima selección
        renderCapturas();
    });

    function renderCapturas() {
        capturasGrid.innerHTML = '';

        capturasFiles.forEach(function(file, index) {
            var item = document.createElement('div');
            item.className = 'image-preview-item';

            var img = document.createElement('img');
            img.src = URL.createObjectURL(file);
            img.alt = 'Captura ' + (index + 1);

            var badge = document.createElement('span');
            badge.className = 'image-preview-badge';
            badge.textContent = (index + 1) + '/' + capturasFiles.length;

            var remove = document.createElement('button');
            remove.type = 'button';
            remove.className = 'image-preview-remove';
            remove.setAttribute('aria-label', 'Quitar captura ' + (index + 1));
            remove.textContent = '\u2715';
            remove.addEventListener('click', function(i) {
                return function() {
                    capturasFiles.splice(i, 1);
                    renderCapturas();
                };
            }(index));

            item.appendChild(img);
            item.appendChild(badge);
            item.appendChild(remove);
            capturasGrid.appendChild(item);
        });

        // Tile para añadir más capturas (acumula)
        var addBtn = document.createElement('button');
        addBtn.type = 'button';
        addBtn.className = 'image-preview-add' + (capturasFiles.length >= MAX_CAPTURAS ? ' is-disabled' : '');
        addBtn.textContent = '+';
        addBtn.setAttribute('aria-label', 'Añadir capturas');
        addBtn.addEventListener('click', function() {
            if (capturasFiles.length < MAX_CAPTURAS) {
                capturasInput.click();
            }
        });
        capturasGrid.appendChild(addBtn);

        if (capturasFiles.length >= MAX_CAPTURAS) {
            var limit = document.createElement('span');
            limit.className = 'image-preview-limit';
            limit.textContent = 'Límite alcanzado: ' + MAX_CAPTURAS + ' capturas. Quita alguna para añadir más.';
            capturasGrid.appendChild(limit);
        }
    }

    // Al enviar: reconstruir el FileList completo en el input de capturas
    capturasInput.closest('form').addEventListener('submit', function() {
        if (typeof DataTransfer !== 'undefined') {
            var dt = new DataTransfer();
            capturasFiles.forEach(function(f) { dt.items.add(f); });
            capturasInput.files = dt.files;
        }
    });

    renderCapturas();
})();
</script>

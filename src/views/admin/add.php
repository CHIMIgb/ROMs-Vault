<!-- views/admin/add.php -->
<div class="form-container">
    <h2><?= t('admin.juego.titulo_nuevo') ?></h2>
    
    <?php if (isset($error)): ?>
        <?php 
        require_once __DIR__ . '/../components/Alert.php';
        Alert::render('danger', htmlspecialchars($error), 'close'); 
        ?>
    <?php endif; ?>
    
    <form autocomplete="off" method="POST" enctype="multipart/form-data">
        <?= CsrfService::field() ?>
        <!-- Información básica -->
        <div class="form-group">
            <label for="titulo"><?= t('admin.juego.titulo') ?></label>
            <input type="text" 
                   id="titulo"
                   name="titulo" 
                   value="<?= htmlspecialchars($_POST['titulo'] ?? '') ?>" 
                   maxlength="200"
                   required
                   placeholder="<?= t('admin.juego.titulo_placeholder') ?>">
        </div>

        <div class="form-group">
            <label for="descripcion"><?= t('admin.juego.descripcion') ?></label>
            <textarea id="descripcion"
                      name="descripcion" 
                      rows="4"
                      placeholder="<?= t('admin.juego.descripcion_placeholder') ?>"><?= htmlspecialchars($_POST['descripcion'] ?? '') ?></textarea>
        </div>
        
        <!-- Plataforma y categoría -->
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
            <div class="form-group">
                <label for="consola_id"><?= t('admin.juego.consola') ?></label>
                <select id="consola_id" name="consola_id" required>
                    <option value=""><?= t('admin.juego.seleccionar_consola') ?></option>
                    <?php foreach ($consolas as $consola): ?>
                        <option value="<?= $consola['id'] ?>" 
                            <?= (isset($_POST['consola_id']) && $_POST['consola_id'] == $consola['id']) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($consola['nombre']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="form-group">
                <label for="categoria_id"><?= t('admin.juego.categoria') ?></label>
                <select id="categoria_id" name="categoria_id" required>
                    <option value=""><?= t('admin.juego.seleccionar_categoria') ?></option>
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
                <label for="region"><?= t('admin.juego.region') ?></label>
                <select id="region" name="region">
                    <option value=""><?= t('admin.juego.seleccionar_region') ?></option>
                    <option value="PAL" <?= (isset($_POST['region']) && $_POST['region'] == 'PAL') ? 'selected' : '' ?>><?= t('admin.juego.region_pal') ?></option>
                    <option value="NTSC" <?= (isset($_POST['region']) && $_POST['region'] == 'NTSC') ? 'selected' : '' ?>><?= t('admin.juego.region_ntsc') ?></option>
                    <option value="NTSC-J" <?= (isset($_POST['region']) && $_POST['region'] == 'NTSC-J') ? 'selected' : '' ?>><?= t('admin.juego.region_ntsc_j') ?></option>
                    <option value="NTSC-U" <?= (isset($_POST['region']) && $_POST['region'] == 'NTSC-U') ? 'selected' : '' ?>><?= t('admin.juego.region_ntsc_u') ?></option>
                </select>
            </div>
            
            <div class="form-group">
                <label for="fecha_lanzamiento"><?= t('admin.juego.fecha_lanzamiento') ?></label>
                <input type="date" 
                       id="fecha_lanzamiento"
                       name="fecha_lanzamiento" 
                       value="<?= htmlspecialchars($_POST['fecha_lanzamiento'] ?? '') ?>">
            </div>
        </div>
        
        <!-- Idiomas y formato -->
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
            <div class="form-group">
                <label for="idiomas"><?= t('admin.juego.idiomas') ?></label>
                <input type="text" 
                       id="idiomas"
                       name="idiomas" 
                       value="<?= htmlspecialchars($_POST['idiomas'] ?? '') ?>"
                       maxlength="200"
                       placeholder="<?= t('admin.juego.idiomas_placeholder') ?>">
            </div>
            
            <div class="form-group">
                <label for="formato_imagen"><?= t('admin.juego.formato') ?></label>
                <input type="text" 
                       id="formato_imagen"
                       name="formato_imagen" 
                       value="<?= htmlspecialchars($_POST['formato_imagen'] ?? '') ?>"
                       maxlength="50"
                       placeholder="<?= t('admin.juego.formato_placeholder') ?>">
            </div>
        </div>
        
        <!-- Códigos -->
        <div class="form-group">
            <label for="game_id_code"><?= t('admin.juego.game_id_code') ?></label>
            <input type="text" 
                   id="game_id_code"
                   name="game_id_code" 
                   value="<?= htmlspecialchars($_POST['game_id_code'] ?? '') ?>"
                   maxlength="50"
                   placeholder="<?= t('admin.juego.game_id_code_placeholder') ?>">
        </div>
        
        <!-- Google Drive -->
        <div class="form-group">
            <label for="google_drive_file_id"><?= t('admin.juego.google_drive_file_id') ?></label>
            <input type="text" 
                   id="google_drive_file_id"
                   name="google_drive_file_id" 
                   value="<?= htmlspecialchars($_POST['google_drive_file_id'] ?? '') ?>" 
                   maxlength="100"
                   required
                   placeholder="<?= t('admin.juego.google_drive_file_id_placeholder') ?>">
            <small><?= t('admin.juego.google_drive_file_id_ayuda') ?></small>
        </div>
        
        <div class="form-group">
            <label for="google_drive_view_link"><?= t('admin.juego.google_drive_view_link') ?></label>
            <input type="url" 
                   id="google_drive_view_link"
                   name="google_drive_view_link" 
                   value="<?= htmlspecialchars($_POST['google_drive_view_link'] ?? '') ?>"
                   maxlength="500"
                   placeholder="<?= t('admin.juego.google_drive_view_link_placeholder') ?>">
        </div>
        
        <!-- Tamaño -->
        <div class="form-group">
            <label for="size_bytes"><?= t('admin.juego.tamano') ?></label>
            <input type="text" 
                   id="size_bytes"
                   name="size_bytes" 
                   value="<?= htmlspecialchars($_POST['size_bytes'] ?? '') ?>"
                   pattern="[0-9]+"
                   oninput="this.value = this.value.replace(/[^0-9]/g, '')"
                   placeholder="<?= t('admin.juego.tamano_placeholder') ?>">
            <small><?= t('admin.juego.tamano_ayuda') ?></small>
        </div>
        
        <!-- Portada -->
        <div class="form-group">
            <label for="imagen"><?= t('admin.juego.portada') ?></label>
            <div class="file-input-wrapper">
                <input type="file"
                       id="imagen"
                       name="imagen"
                       accept="image/jpeg,image/png,image/gif,image/webp">
                <div class="file-input-display" onclick="document.getElementById('imagen').click()">
                    <div class="file-input-btn"><i data-i="upload-2"></i> <?= t('admin.juego.elegir_archivo') ?>
                    </div>
                    <div class="file-input-name" id="imagen-name"><?= t('admin.juego.sin_archivo') ?></div>
                </div>
            </div>
            <div class="image-preview-grid" id="imagen-preview-grid" hidden></div>
            <small><?= t('admin.juego.portada_formatos') ?><br>
            <i><?= t('admin.juego.portada_optimizacion') ?></i></small>
        </div>

        <!-- Capturas -->
        <div class="form-group">
            <label><?= t('admin.juego.capturas') ?></label>
            <input type="file"
                   id="capturas"
                   name="capturas[]"
                   accept="image/jpeg,image/png,image/gif,image/webp"
                   multiple
                   hidden>
            <div class="image-preview-grid" id="capturas-preview-grid"></div>
            <small><?= t('admin.juego.capturas_ayuda') ?><br>
            <i><?= t('admin.juego.capturas_ayuda_acumular') ?></i><br>
            <i><?= t('admin.juego.capturas_ayuda_carrusel') ?></i></small>
        </div>

        <!-- Campo oculto: activo siempre en true al crear -->
        <input type="hidden" name="activo" value="1">
        
        <!-- Botones -->
        <div class="form-actions">
            <button type="submit" class="btn-primary"><i data-i="save"></i> <?= t('admin.juego.guardar') ?></button>
                <a href="/admin/dashboard" class="btn-primary" style="background:var(--cream-dark);color:var(--slate);box-shadow:3px 3px 0 var(--border-mid);border-color:var(--border-mid);">
                    <i data-i="close"></i> <?= t('admin.juego.cancelar') ?>
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
        img.alt = <?= json_encode(t('admin.juego.alt_preview_portada')) ?>;

        var remove = document.createElement('button');
        remove.type = 'button';
        remove.className = 'image-preview-remove';
        remove.setAttribute('aria-label', <?= json_encode(t('admin.juego.aria_quitar_portada')) ?>);
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
            img.alt = <?= json_encode(t('admin.juego.captura_i', ['i' => '{n}'])) ?>.replace('{n}', index + 1);

            var badge = document.createElement('span');
            badge.className = 'image-preview-badge';
            badge.textContent = (index + 1) + '/' + capturasFiles.length;

            var remove = document.createElement('button');
            remove.type = 'button';
            remove.className = 'image-preview-remove';
            remove.setAttribute('aria-label', <?= json_encode(t('admin.juego.quitar_captura_i', ['i' => '{n}'])) ?>.replace('{n}', index + 1));
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
        addBtn.setAttribute('aria-label', <?= json_encode(t('admin.juego.aria_anadir_capturas')) ?>);
        addBtn.addEventListener('click', function() {
            if (capturasFiles.length < MAX_CAPTURAS) {
                capturasInput.click();
            }
        });
        capturasGrid.appendChild(addBtn);

        if (capturasFiles.length >= MAX_CAPTURAS) {
            var limit = document.createElement('span');
            limit.className = 'image-preview-limit';
            limit.textContent = <?= json_encode(t('admin.juego.limite_capturas', ['total' => '{n}'])) ?>.replace('{n}', MAX_CAPTURAS);
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

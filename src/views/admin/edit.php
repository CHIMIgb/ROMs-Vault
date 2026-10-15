<!-- views/admin/edit.php -->
<div class="form-container">
    <h2><?= t('admin.juego.titulo_editar', ['nombre' => htmlspecialchars($juego['titulo'])]) ?></h2>
    
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
                   value="<?= htmlspecialchars($juego['titulo'] ?? '') ?>" 
                   maxlength="200"
                   required>
        </div>

        <div class="form-group">
            <label for="descripcion"><?= t('admin.juego.descripcion') ?></label>
            <textarea id="descripcion"
                      name="descripcion" 
                      rows="4"><?= htmlspecialchars($juego['descripcion'] ?? '') ?></textarea>
        </div>
        
        <!-- Plataforma y categoría -->
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
            <div class="form-group">
                <label for="consola_id"><?= t('admin.juego.consola') ?></label>
                <select id="consola_id" name="consola_id" required>
                    <option value=""><?= t('admin.juego.seleccionar_consola') ?></option>
                    <?php foreach ($consolas as $consola): ?>
                        <option value="<?= $consola['id'] ?>" 
                            <?= ($juego['consola_id'] == $consola['id']) ? 'selected' : '' ?>>
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
                <label for="region"><?= t('admin.juego.region') ?></label>
                <select id="region" name="region">
                    <option value=""><?= t('admin.juego.seleccionar_region') ?></option>
                    <option value="PAL" <?= ($juego['region'] == 'PAL') ? 'selected' : '' ?>><?= t('admin.juego.region_pal') ?></option>
                    <option value="NTSC" <?= ($juego['region'] == 'NTSC') ? 'selected' : '' ?>><?= t('admin.juego.region_ntsc') ?></option>
                    <option value="NTSC-J" <?= ($juego['region'] == 'NTSC-J') ? 'selected' : '' ?>><?= t('admin.juego.region_ntsc_j') ?></option>
                    <option value="NTSC-U" <?= ($juego['region'] == 'NTSC-U') ? 'selected' : '' ?>><?= t('admin.juego.region_ntsc_u') ?></option>
                </select>
            </div>
            
            <div class="form-group">
                <label for="fecha_lanzamiento"><?= t('admin.juego.fecha_lanzamiento') ?></label>
                <input type="date" 
                       id="fecha_lanzamiento"
                       name="fecha_lanzamiento" 
                       value="<?= $juego['fecha_lanzamiento'] ?? '' ?>">
            </div>
        </div>
        
        <!-- Idiomas y formato -->
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
            <div class="form-group">
                <label for="idiomas"><?= t('admin.juego.idiomas') ?></label>
                <input type="text" 
                       id="idiomas"
                       name="idiomas" 
                       value="<?= htmlspecialchars($juego['idiomas'] ?? '') ?>"
                       maxlength="200"
                       placeholder="<?= t('admin.juego.idiomas_placeholder') ?>">
            </div>
            
            <div class="form-group">
                <label for="formato_imagen"><?= t('admin.juego.formato') ?></label>
                <input type="text" 
                       id="formato_imagen"
                       name="formato_imagen" 
                       value="<?= htmlspecialchars($juego['formato_imagen'] ?? '') ?>"
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
                   value="<?= htmlspecialchars($juego['game_id_code'] ?? '') ?>"
                   maxlength="50"
                   placeholder="<?= t('admin.juego.game_id_code_placeholder') ?>">
        </div>
        
        <!-- Google Drive -->
        <div class="form-group">
            <label for="google_drive_file_id"><?= t('admin.juego.google_drive_file_id') ?></label>
            <input type="text" 
                   id="google_drive_file_id"
                   name="google_drive_file_id" 
                   value="<?= htmlspecialchars($juego['google_drive_file_id'] ?? '') ?>" 
                   maxlength="100"
                   required>
            <small><?= t('admin.juego.google_drive_file_id_ayuda') ?></small>
        </div>
        
        <div class="form-group">
            <label for="google_drive_view_link"><?= t('admin.juego.google_drive_view_link') ?></label>
            <input type="url" 
                   id="google_drive_view_link"
                   name="google_drive_view_link" 
                   value="<?= htmlspecialchars($juego['google_drive_view_link'] ?? '') ?>"
                   maxlength="500">
        </div>
        
        <!-- Tamaño -->
        <div class="form-group">
            <label for="size_bytes"><?= t('admin.juego.tamano') ?></label>
            <input type="text" 
                   id="size_bytes"
                   name="size_bytes" 
                   value="<?= $juego['size_bytes'] ?? 0 ?>"
                   pattern="[0-9]+"
                   oninput="this.value = this.value.replace(/[^0-9]/g, '')">
            <small><?= t('admin.juego.tamano_ayuda') ?></small>
        </div>
        
        <!-- Portada -->
        <div class="form-group">
            <label for="imagen"><?= t('admin.juego.portada') ?></label>
            <?php if (!empty($juego['imagen'])): ?>
                <div style="display:flex; align-items:flex-start; gap:1rem; margin-bottom:0.75rem; padding:0.75rem; background:var(--cream-dark); border:2px solid var(--border-dark); box-shadow:var(--raise-shadow);">
                    <img src="<?= htmlspecialchars($juego['imagen']) ?>"
                         alt="<?= t('admin.juego.alt_portada_actual') ?>"
                         style="width:80px; height:80px; object-fit:cover; border:2px solid var(--border-dark); flex-shrink:0;">
                    <div style="font-family:'Courier Prime',monospace;">
                        <p style="font-size:0.78rem; font-weight:700; color:var(--slate); margin-bottom:0.3rem; text-transform:uppercase; letter-spacing:0.05em;"><?= t('admin.juego.imagen_actual') ?></p>
                        <p style="font-size:0.78rem; color:var(--slate-mid); font-style:italic;"><i data-i="warning"></i> <?= t('admin.juego.portada_reemplaza_aviso') ?></p>
                    </div>
                </div>
            <?php endif; ?>
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
            <?php $capturasActuales = Juego::parseCapturas($juego['capturas'] ?? null); ?>
            <?php if ($capturasActuales): ?>
                <div class="image-preview-grid" id="capturas-actuales-grid">
                    <?php foreach ($capturasActuales as $i => $ruta): ?>
                        <div class="image-preview-item" data-ruta="<?= htmlspecialchars($ruta) ?>">
                            <img src="<?= htmlspecialchars($ruta) ?>" alt="<?= t('admin.juego.captura_actual_i', ['i' => $i + 1]) ?>">
                            <span class="image-preview-badge"><?= t('admin.juego.actual_i', ['i' => $i + 1]) ?></span>
                            <button type="button"
                                    class="image-preview-remove"
                                    aria-label="<?= t('admin.juego.marcar_captura_i', ['i' => $i + 1]) ?>">&#10005;</button>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
            <input type="file"
                   id="capturas"
                   name="capturas[]"
                   accept="image/jpeg,image/png,image/gif,image/webp"
                   multiple
                   hidden>
            <div class="image-preview-grid" id="capturas-nuevas-grid"></div>
            <small><?= t('admin.juego.capturas_ayuda') ?><br>
            <i><?= t('admin.juego.capturas_actuales_ayuda') ?></i></small>
        </div>
                
        <!-- Botones -->
        <div class="form-actions">
            <button type="submit" class="btn-primary"><i data-i="save"></i> <?= t('admin.juego.guardar_cambios') ?></button>
                <a href="/admin/dashboard" class="btn-primary" style="background:var(--cream-dark);color:var(--slate);box-shadow:3px 3px 0 var(--border-mid);border-color:var(--border-mid);">
                    <i data-i="close"></i> <?= t('admin.juego.cancelar') ?>
                </a>
        </div>
    </form>
</div>

<script>
(function() {
    'use strict';

    var MAX_CAPTURAS = 7;

    // ── Portada: preview de la nueva seleccionada; ✕ descarta y conserva la actual ──
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
        img.alt = <?= json_encode(t('admin.juego.alt_nueva_portada')) ?>;

        var badge = document.createElement('span');
        badge.className = 'image-preview-badge';
        badge.textContent = <?= json_encode(t('admin.juego.badge_nueva')) ?>;

        var remove = document.createElement('button');
        remove.type = 'button';
        remove.className = 'image-preview-remove';
        remove.setAttribute('aria-label', <?= json_encode(t('admin.juego.aria_quitar_portada_nueva')) ?>);
        remove.textContent = '\u2715';
        remove.addEventListener('click', function() {
            portadaInput.value = '';
            renderPortada();
        });

        item.appendChild(img);
        item.appendChild(badge);
        item.appendChild(remove);
        portadaGrid.appendChild(item);
        portadaGrid.hidden = false;
    }

    portadaInput.addEventListener('change', renderPortada);

    // ── Capturas actuales: ✕ marca para eliminar (toggle) ──
    var gridActuales = document.getElementById('capturas-actuales-grid');

    function capturasActivas() {
        if (!gridActuales) return 0;
        return gridActuales.querySelectorAll('.image-preview-item:not(.is-removing)').length;
    }

    if (gridActuales) {
        gridActuales.querySelectorAll('.image-preview-item').forEach(function(item) {
            var ruta = item.getAttribute('data-ruta');
            var btn = item.querySelector('.image-preview-remove');
            btn.addEventListener('click', function() {
                item.classList.toggle('is-removing');

                // Sincronizar hidden input que indica al backend la ruta a eliminar
                var hidden = item.querySelector('input[type="hidden"]');
                if (item.classList.contains('is-removing')) {
                    if (!hidden) {
                        hidden = document.createElement('input');
                        hidden.type = 'hidden';
                        hidden.name = 'eliminar_capturas[]';
                        hidden.value = ruta;
                        item.appendChild(hidden);
                    }
                } else if (hidden) {
                    hidden.parentNode.removeChild(hidden);
                }

                renderNuevas();
            });
        });
    }

    // ── Capturas nuevas: selección acumulada con previews ──
    var capturasInput = document.getElementById('capturas');
    var capturasGrid  = document.getElementById('capturas-nuevas-grid');
    var capturasFiles = [];

    capturasInput.addEventListener('change', function() {
        for (var i = 0; i < capturasInput.files.length; i++) {
            if (capturasFiles.length >= MAX_CAPTURAS - capturasActivas()) break;
            capturasFiles.push(capturasInput.files[i]);
        }
        capturasInput.value = ''; // permite elegir más en la próxima selección
        renderNuevas();
    });

    function renderNuevas() {
        capturasGrid.innerHTML = '';

        capturasFiles.forEach(function(file, index) {
            var item = document.createElement('div');
            item.className = 'image-preview-item';

            var img = document.createElement('img');
            img.src = URL.createObjectURL(file);
            img.alt = <?= json_encode(t('admin.juego.nueva_captura_i', ['i' => '{n}'])) ?>.replace('{n}', index + 1);

            var badge = document.createElement('span');
            badge.className = 'image-preview-badge';
            badge.textContent = <?= json_encode(t('admin.juego.badge_nueva')) ?>;

            var remove = document.createElement('button');
            remove.type = 'button';
            remove.className = 'image-preview-remove';
            remove.setAttribute('aria-label', <?= json_encode(t('admin.juego.quitar_nueva_captura_i', ['i' => '{n}'])) ?>.replace('{n}', index + 1));
            remove.textContent = '\u2715';
            remove.addEventListener('click', function(i) {
                return function() {
                    capturasFiles.splice(i, 1);
                    renderNuevas();
                };
            }(index));

            item.appendChild(img);
            item.appendChild(badge);
            item.appendChild(remove);
            capturasGrid.appendChild(item);
        });

        var cupoLleno = (capturasActivas() + capturasFiles.length) >= MAX_CAPTURAS;

        var addBtn = document.createElement('button');
        addBtn.type = 'button';
        addBtn.className = 'image-preview-add' + (cupoLleno ? ' is-disabled' : '');
        addBtn.textContent = '+';
        addBtn.setAttribute('aria-label', <?= json_encode(t('admin.juego.aria_anadir_capturas')) ?>);
        addBtn.addEventListener('click', function() {
            if (!cupoLleno) {
                capturasInput.click();
            }
        });
        capturasGrid.appendChild(addBtn);

        if (cupoLleno) {
            var limit = document.createElement('span');
            limit.className = 'image-preview-limit';
            limit.textContent = <?= json_encode(t('admin.juego.limite_capturas', ['total' => '{n}'])) ?>.replace('{n}', MAX_CAPTURAS);
            capturasGrid.appendChild(limit);
        }
    }

    // Al enviar: reconstruir el FileList completo con las nuevas capturas
    capturasInput.closest('form').addEventListener('submit', function() {
        if (typeof DataTransfer !== 'undefined') {
            var dt = new DataTransfer();
            capturasFiles.forEach(function(f) { dt.items.add(f); });
            capturasInput.files = dt.files;
        }
    });

    renderNuevas();
})();
</script>
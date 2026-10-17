// views/admin/add.php - validaciones y ayudas del formulario de alta
(function() {
    'use strict';

    const data = JSON.parse(
        (document.getElementById('rv-data-admin-add') || {}).textContent || '{}'
    );

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
        img.alt = data.t['admin.juego.alt_preview_portada'];

        var remove = document.createElement('button');
        remove.type = 'button';
        remove.className = 'image-preview-remove';
        remove.setAttribute('aria-label', data.t['admin.juego.aria_quitar_portada']);
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
            img.alt = data.t['admin.juego.captura_i'].replace('{n}', index + 1);

            var badge = document.createElement('span');
            badge.className = 'image-preview-badge';
            badge.textContent = (index + 1) + '/' + capturasFiles.length;

            var remove = document.createElement('button');
            remove.type = 'button';
            remove.className = 'image-preview-remove';
            remove.setAttribute('aria-label', data.t['admin.juego.quitar_captura_i'].replace('{n}', index + 1));
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
        addBtn.setAttribute('aria-label', data.t['admin.juego.aria_anadir_capturas']);
        addBtn.addEventListener('click', function() {
            if (capturasFiles.length < MAX_CAPTURAS) {
                capturasInput.click();
            }
        });
        capturasGrid.appendChild(addBtn);

        if (capturasFiles.length >= MAX_CAPTURAS) {
            var limit = document.createElement('span');
            limit.className = 'image-preview-limit';
            limit.textContent = data.t['admin.juego.limite_capturas'].replace('{n}', MAX_CAPTURAS);
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

// views/admin/edit.php - validaciones y ayudas del formulario de edicion
(function() {
    'use strict';

    const data = JSON.parse(
        (document.getElementById('rv-data-admin-edit') || {}).textContent || '{}'
    );

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
        img.alt = data.t['admin.juego.alt_nueva_portada'];

        var badge = document.createElement('span');
        badge.className = 'image-preview-badge';
        badge.textContent = data.t['admin.juego.badge_nueva'];

        var remove = document.createElement('button');
        remove.type = 'button';
        remove.className = 'image-preview-remove';
        remove.setAttribute('aria-label', data.t['admin.juego.aria_quitar_portada_nueva']);
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
            img.alt = data.t['admin.juego.nueva_captura_i'].replace('{n}', index + 1);

            var badge = document.createElement('span');
            badge.className = 'image-preview-badge';
            badge.textContent = data.t['admin.juego.badge_nueva'];

            var remove = document.createElement('button');
            remove.type = 'button';
            remove.className = 'image-preview-remove';
            remove.setAttribute('aria-label', data.t['admin.juego.quitar_nueva_captura_i'].replace('{n}', index + 1));
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
        addBtn.setAttribute('aria-label', data.t['admin.juego.aria_anadir_capturas']);
        addBtn.addEventListener('click', function() {
            if (!cupoLleno) {
                capturasInput.click();
            }
        });
        capturasGrid.appendChild(addBtn);

        if (cupoLleno) {
            var limit = document.createElement('span');
            limit.className = 'image-preview-limit';
            limit.textContent = data.t['admin.juego.limite_capturas'].replace('{n}', MAX_CAPTURAS);
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

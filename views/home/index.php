<!-- views/home/index.php -->

<!-- BARRA DE BÚSQUEDA -->
<div class="search-section">
    <div class="search-wrapper" style="position:relative;">
        <input type="text"
               id="f-busqueda"
               placeholder="Buscar juego por título..."
               value="<?= htmlspecialchars($_GET['busqueda'] ?? '') ?>"
               class="search-input"
               autocomplete="off"
               aria-label="Buscar juego"
               aria-autocomplete="list"
               aria-controls="autocomplete-list"
               aria-expanded="false">
        <button type="button" class="search-button" onclick="document.getElementById('f-busqueda').dispatchEvent(new Event('input'))" aria-label="Buscar">
            <i data-i="search" aria-hidden="true"></i>
        </button>
        <!-- Dropdown autocompletado -->
        <ul id="autocomplete-list" class="autocomplete-list" role="listbox" aria-label="Sugerencias"></ul>
    </div>
</div>

<!-- FILTROS -->
<div class="filters">
    <div class="filters-row">
        <select id="f-consola">
            <option value="">Todas las plataformas</option>
            <?php foreach ($consolas as $consola): ?>
                <option value="<?= $consola['id'] ?>" <?= (($_GET['consola'] ?? '') == $consola['id']) ? 'selected' : '' ?>>
                    <?= htmlspecialchars($consola['nombre']) ?>
                </option>
            <?php endforeach; ?>
        </select>

        <select id="f-categoria">
            <option value="">Todos los géneros</option>
            <?php foreach ($categorias as $categoria): ?>
                <option value="<?= $categoria['id'] ?>" <?= (($_GET['categoria'] ?? '') == $categoria['id']) ? 'selected' : '' ?>>
                    <?= htmlspecialchars($categoria['nombre']) ?>
                </option>
            <?php endforeach; ?>
        </select>

        <select id="f-region">
            <option value="">Todas las regiones</option>
            <option value="PAL"    <?= (($_GET['region'] ?? '') === 'PAL')    ? 'selected' : '' ?>>PAL</option>
            <option value="NTSC"   <?= (($_GET['region'] ?? '') === 'NTSC')   ? 'selected' : '' ?>>NTSC</option>
            <option value="NTSC-J" <?= (($_GET['region'] ?? '') === 'NTSC-J') ? 'selected' : '' ?>>NTSC-J</option>
            <option value="NTSC-U" <?= (($_GET['region'] ?? '') === 'NTSC-U') ? 'selected' : '' ?>>NTSC-U</option>
        </select>

        <select id="f-orden">
            <option value="titulo"    <?= (($_GET['orden'] ?? '') === 'titulo')    ? 'selected' : '' ?>>A - Z</option>
            <option value="recientes" <?= (($_GET['orden'] ?? '') === 'recientes') ? 'selected' : '' ?>>Mas recientes</option>
            <option value="descargas" <?= (($_GET['orden'] ?? '') === 'descargas') ? 'selected' : '' ?>>Mas descargados</option>
            <option value="jugados"   <?= (($_GET['orden'] ?? '') === 'jugados')   ? 'selected' : '' ?>>Mas jugados</option>
            <option value="año_desc"  <?= (($_GET['orden'] ?? '') === 'año_desc')  ? 'selected' : '' ?>>Año nuevos primero</option>
            <option value="año_asc"   <?= (($_GET['orden'] ?? '') === 'año_asc')   ? 'selected' : '' ?>>Año clasicos primero</option>
        </select>

        <button type="button" id="btn-limpiar" class="clear-filters" style="display:none;"><i data-i="close" aria-hidden="true"></i> Limpiar</button>
    </div>

    <!-- Indicador de carga -->
    <div id="filter-loading" class="filter-loading" style="display:none;">
        <span class="filter-loading-bar"></span>
        <span class="filter-loading-text">Buscando</span>
    </div>
</div>

<!-- ZONA DE RESULTADOS (reemplazada por AJAX) -->
<div id="catalog-results">

    <!-- GRID DE JUEGOS -->
    <div class="games-grid" id="games-grid">
        <?php if (empty($juegos)): ?>
            <div class="alert alert-info" style="grid-column:1/-1;text-align:center;padding:3rem;">
                No hay juegos disponibles que coincidan con los filtros.
            </div>
        <?php else: ?>
            <?php foreach ($juegos as $juego): ?>
                <div class="game-card">
                    <div class="game-card-inner">
                        <a href="index.php?controller=home&action=show&id=<?= $juego['id'] ?>" class="game-detail-link" style="text-decoration:none; color:inherit; display:block;">
                            <div class="game-cover <?= empty($juego['imagen']) ? 'no-image' : '' ?>">
                                <?php if (!empty($juego['imagen'])): ?>
                                    <img src="<?= htmlspecialchars($juego['imagen']) ?>" alt="<?= htmlspecialchars($juego['titulo']) ?>">
                                <?php else: ?>
                                    <i data-i="disc" data-cls="pxi-cover-placeholder" aria-hidden="true"></i>
                                <?php endif; ?>
                            </div>
                            <h3 class="game-title"><?= htmlspecialchars($juego['titulo']) ?></h3>
                        </a>
                        <div class="game-metadata">
                            <span class="game-tag genre"><?= htmlspecialchars($juego['categoria_nombre'] ?? 'Sin genero') ?></span>
                            <span class="game-tag platform"><?= htmlspecialchars($juego['consola_nombre'] ?? 'Multi') ?></span>
                            <span class="game-tag language"><?= htmlspecialchars($juego['region'] ?? 'All') ?></span>
                        </div>
                        <?php if (!empty($juego['formato_imagen']) && $juego['formato_imagen'] === 'Hack'): ?>
                            <div class="game-hack-info"><?= htmlspecialchars($juego['descripcion'] ?? 'ROM Hack') ?></div>
                        <?php endif; ?>
                        <div class="game-info">
                            <div class="game-info-item">
                                <span class="game-info-label">Idiomas</span>
                                <span class="game-info-value"><?= htmlspecialchars($juego['idiomas'] ?? 'English') ?></span>
                            </div>
                            <div class="game-info-item">
                                <span class="game-info-label">Tamano</span>
                                <span class="game-info-value"><?= number_format($juego['size_bytes'] / 1048576, 2) ?> MB</span>
                            </div>
                            <?php if (!empty($juego['downloads_count'])): ?>
                            <div class="game-info-item">
                                <span class="game-info-label">Descargas</span>
                                <span class="game-info-value"><?= number_format($juego['downloads_count']) ?></span>
                            </div>
                            <?php endif; ?>
                            <?php if (!empty($juego['plays_count'])): ?>
                            <div class="game-info-item">
                                <span class="game-info-label">Jugadas</span>
                                <span class="game-info-value"><?= number_format($juego['plays_count']) ?></span>
                            </div>
                            <?php endif; ?>
                        </div>
                        <div class="game-actions">
                            <?php if (strtoupper($juego['consola_nombre'] ?? '') !== 'PSP'): ?>
                                <a href="index.php?controller=home&action=play&file_id=<?= urlencode($juego['google_drive_file_id']) ?>" class="game-play"><i data-i="play" aria-hidden="true"></i> Jugar Online</a>
                            <?php endif; ?>
                            <a href="index.php?controller=home&action=download&file_id=<?= urlencode($juego['google_drive_file_id']) ?>" class="game-download" target="_blank"><i data-i="download" aria-hidden="true"></i> Descargar</a>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <!-- PAGINACION -->
    <?php if (!empty($juegos) && isset($totalPages) && $totalPages > 0): ?>
    <?php
    $qParts = [];
    foreach (['busqueda','consola','categoria','region','orden'] as $p) {
        if (!empty($_GET[$p])) $qParts[] = $p . '=' . urlencode($_GET[$p]);
    }
    $qBase = $qParts ? '&' . implode('&', $qParts) : '';
    
    require_once 'views/components/Pagination.php';
    $extra = isset($_GET['busqueda']) && $_GET['busqueda'] !== '' 
        ? ' - Búsqueda: "' . htmlspecialchars($_GET['busqueda']) . '"' 
        : '';
        
    Pagination::render(
        $currentPage, 
        $totalPages ?? 1, 
        $qBase, 
        'home', 
        'index', 
        count($juegos), 
        $totalJuegos ?? 0, 
        'juegos', 
        'catalog',
        $extra
    );
    ?>
    <?php endif; ?>

</div><!-- /#catalog-results -->

<script>
(function () {
    'use strict';

    const busquedaEl  = document.getElementById('f-busqueda');
    const consolaEl   = document.getElementById('f-consola');
    const categoriaEl = document.getElementById('f-categoria');
    const regionEl    = document.getElementById('f-region');
    const ordenEl     = document.getElementById('f-orden');
    const btnLimpiar  = document.getElementById('btn-limpiar');
    const resultsEl   = document.getElementById('catalog-results');
    const loadingEl   = document.getElementById('filter-loading');

    let debounceTimer  = null;
    let currentRequest = null;

    function getAjaxParams(page) {
        const p = new URLSearchParams();
        p.set('page', page || 1);
        const b = busquedaEl.value.trim();
        if (b)                 p.set('busqueda',  b);
        if (consolaEl.value)   p.set('consola',   consolaEl.value);
        if (categoriaEl.value) p.set('categoria', categoriaEl.value);
        if (regionEl.value)    p.set('region',    regionEl.value);
        if (ordenEl.value)     p.set('orden',     ordenEl.value);
        return p;
    }

    function getUiParams(page) {
        const p = getAjaxParams(page);
        p.set('controller', 'home');
        p.set('action', 'index');
        return p;
    }

    function updateLimpiar() {
        const has = busquedaEl.value.trim() || consolaEl.value || categoriaEl.value || regionEl.value;
        btnLimpiar.style.display = has ? 'inline-block' : 'none';
    }

    async function fetchResults(page) {
        if (currentRequest) currentRequest.abort();
        currentRequest = new AbortController();

        loadingEl.style.display = 'flex';
        resultsEl.style.opacity = '0.45';
        resultsEl.style.pointerEvents = 'none';

        history.replaceState(null, '', '?' + getUiParams(page).toString());

        try {
            const res = await fetch('ajax_catalog.php?' + getAjaxParams(page).toString(), {
                signal: currentRequest.signal
            });
            if (!res.ok) throw new Error('HTTP ' + res.status);
            resultsEl.innerHTML = await res.text();
            bindPagination();

            const top = resultsEl.getBoundingClientRect().top + window.scrollY - 80;
            if (window.scrollY > top) window.scrollTo({ top, behavior: 'smooth' });
        } catch (err) {
            if (err.name !== 'AbortError') {
                resultsEl.innerHTML = '<div class="alert alert-error">Error al cargar. Intenta de nuevo.</div>';
            }
        } finally {
            loadingEl.style.display = 'none';
            resultsEl.style.opacity = '';
            resultsEl.style.pointerEvents = '';
        }
    }

    function scheduleSearch() {
        clearTimeout(debounceTimer);
        updateLimpiar();
        debounceTimer = setTimeout(() => fetchResults(1), 380);
    }

    function onSelectChange() {
        updateLimpiar();
        fetchResults(1);
    }

    function bindPagination() {
        resultsEl.querySelectorAll('.pagination-link[data-page]').forEach(a => {
            a.addEventListener('click', e => {
                e.preventDefault();
                fetchResults(parseInt(a.dataset.page));
            });
        });
    }

    btnLimpiar.addEventListener('click', () => {
        busquedaEl.value  = '';
        consolaEl.value   = '';
        categoriaEl.value = '';
        regionEl.value    = '';
        ordenEl.value     = 'titulo';
        updateLimpiar();
        fetchResults(1);
    });

    busquedaEl.addEventListener('input',   scheduleSearch);
    consolaEl.addEventListener('change',   onSelectChange);
    categoriaEl.addEventListener('change', onSelectChange);
    regionEl.addEventListener('change',    onSelectChange);
    ordenEl.addEventListener('change',     onSelectChange);

    bindPagination();
    updateLimpiar();

    // ── AUTOCOMPLETADO ────────────────────────────────────────────────────
    const acList        = document.getElementById('autocomplete-list');
    let   acTimer       = null;
    let   acRequest     = null;
    let   acIndex       = -1;
    let   acItems       = [];

    function closeAC() {
        acList.innerHTML = '';
        acList.style.display = 'none';
        busquedaEl.setAttribute('aria-expanded', 'false');
        acIndex = -1;
    }

    function renderAC(items) {
        acItems = items;
        acList.innerHTML = '';
        acIndex = -1;

        if (!items.length) { closeAC(); return; }

        items.forEach((item, i) => {
            const li = document.createElement('li');
            li.className  = 'autocomplete-item';
            li.setAttribute('role', 'option');
            li.setAttribute('id', 'ac-item-' + i);
            li.innerHTML  = `
                <div class="ac-cover">
                    ${item.imagen
                        ? `<img src="${item.imagen}" alt="" loading="lazy">`
                        : '<i data-i="disc" data-cls="pxi-cover-placeholder"></i>'
                    }
                </div>
                <div class="ac-text">
                    <span class="ac-titulo">${escHtml(item.titulo)}</span>
                    <span class="ac-consola">${escHtml(item.consola)}</span>
                </div>
                <div class="ac-actions">
                    ${item.consola.toUpperCase() !== 'PSP'
                        ? `<a href="${item.play_url}" class="ac-btn-play" title="Jugar online"><i data-i="play"></i></a>`
                        : ''
                    }
                    <a href="${item.download_url}" class="ac-btn-dl" title="Descargar" target="_blank"><i data-i="download"></i></a>
                </div>`;

            li.addEventListener('mousedown', e => {
                if (e.target.closest('a')) return;
                busquedaEl.value = item.titulo;
                closeAC();
                fetchResults(1);
            });

            acList.appendChild(li);
        });

        acList.style.display = 'block';
        busquedaEl.setAttribute('aria-expanded', 'true');
    }

    function setActiveItem(idx) {
        const all = acList.querySelectorAll('.autocomplete-item');
        all.forEach((el, i) => el.classList.toggle('autocomplete-item--active', i === idx));
        if (all[idx]) {
            busquedaEl.setAttribute('aria-activedescendant', 'ac-item-' + idx);
            all[idx].scrollIntoView({ block: 'nearest' });
        }
    }

    function escHtml(str) {
        return str.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
    }

    async function fetchAC(term) {
        if (acRequest) acRequest.abort();
        acRequest = new AbortController();
        try {
            const res  = await fetch('ajax_autocomplete.php?q=' + encodeURIComponent(term), {
                signal: acRequest.signal
            });
            if (!res.ok) throw new Error();
            const data = await res.json();
            renderAC(Array.isArray(data) ? data : []);
        } catch (e) {
            if (e.name !== 'AbortError') closeAC();
        }
    }

    busquedaEl.addEventListener('input', () => {
        clearTimeout(acTimer);
        const val = busquedaEl.value.trim();
        if (val.length < 2) { closeAC(); return; }
        acTimer = setTimeout(() => fetchAC(val), 220);
    });

    busquedaEl.addEventListener('keydown', e => {
        if (!acItems.length) return;
        if (e.key === 'ArrowDown') {
            e.preventDefault();
            acIndex = Math.min(acIndex + 1, acItems.length - 1);
            setActiveItem(acIndex);
        } else if (e.key === 'ArrowUp') {
            e.preventDefault();
            acIndex = Math.max(acIndex - 1, -1);
            setActiveItem(acIndex);
        } else if (e.key === 'Enter' && acIndex >= 0) {
            e.preventDefault();
            busquedaEl.value = acItems[acIndex].titulo;
            closeAC();
            fetchResults(1);
        } else if (e.key === 'Escape') {
            closeAC();
        }
    });

    busquedaEl.addEventListener('blur', () => {
        setTimeout(closeAC, 180);
    });

})();
</script>

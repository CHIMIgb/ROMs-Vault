<!-- views/admin/emuladores/add.php -->
<?php require_once 'views/components/Alert.php'; ?>
<div class="form-container">

    <!-- Cabecera del formulario -->
    <div class="form-container-header">
        <h2>Registrar Emulador</h2>
    </div>

    <?php if (isset($error)): ?>
        <?php Alert::render('danger', htmlspecialchars($error), 'close'); ?>
    <?php endif; ?>

    <?php if (empty($consolasSin)): ?>
        <?php Alert::render('info', 'Todas las consolas ya tienen un emulador configurado. Usa "Editar" en el listado para modificarlos.', 'info', 'text-align:center;padding:2rem;justify-content:center;'); ?>
        <div class="form-actions">
            <a href="/emulador/index"
               class="btn-primary"
               style="background:var(--cream-dark);color:var(--slate);box-shadow:3px 3px 0 var(--border-mid);border-color:var(--border-mid);">
                <i data-i="arrow-left"></i> Volver al listado
            </a>
        </div>
    <?php else: ?>
    <?php $consolaSeleccionada = $_POST['consola_id'] ?? ''; ?>
    <form method="POST" autocomplete="off">
        <?= CsrfService::field() ?>

        <!-- Consola a la que se registra el emulador -->
        <div class="form-group">
            <label for="consola_id">Consola *</label>
            <select id="consola_id" name="consola_id" class="admin-filter-select" required>
                <option value="">Selecciona una consola...</option>
                <?php foreach ($consolasSin as $c): ?>
                    <option value="<?= $c['id'] ?>" <?= ((string) $consolaSeleccionada === (string) $c['id']) ? 'selected' : '' ?>>
                        <?= htmlspecialchars($c['nombre']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <p style="margin:0 0 1.5rem;color:var(--text-light);font-size:.92rem;">
            Configura el emulador recomendado que se mostrará en la ficha de los juegos de la consola elegida.
            El campo alternativo es opcional.
        </p>

        <!-- ═══ Emulador principal ═══ -->
        <div style="margin-bottom:2.25rem;padding-bottom:1.5rem;border-bottom:1px dashed var(--border-mid);">
            <h3 style="margin:0 0 1rem;font-size:1.05rem;color:var(--text);">
                <i data-i="gamepad"></i> Emulador principal
            </h3>

            <div class="form-group">
                <label for="principal_nombre">Nombre del emulador *</label>
                <input type="text"
                       id="principal_nombre"
                       name="principal_nombre"
                       value="<?= htmlspecialchars($actual['principal']['nombre'] ?? '') ?>"
                       placeholder="Ej: PPSSPP, Dolphin, mGBA..."
                       required>
            </div>

            <div class="form-group">
                <label>Plataformas *</label>
                <div style="display:flex;gap:1.5rem;padding-top:.25rem;">
                    <?php
                    $platPrincipal = $actual['principal']['plataformas'] ?? [];
                    foreach (['PC', 'Android'] as $p): ?>
                    <label style="display:flex;align-items:center;gap:.4rem;cursor:pointer;">
                        <input type="checkbox"
                               name="principal_plataformas[]"
                               value="<?= $p ?>"
                               <?= in_array($p, $platPrincipal, true) ? 'checked' : '' ?>>
                        <?= $p ?>
                    </label>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="form-group">
                <label for="principal_url">URL oficial *</label>
                <input type="url"
                       id="principal_url"
                       name="principal_url"
                       value="<?= htmlspecialchars($actual['principal']['url'] ?? '') ?>"
                       placeholder="https://..."
                       required>
            </div>
        </div>

        <!-- ═══ Emulador alternativo (opcional) ═══ -->
        <div style="margin-bottom:2.25rem;padding-bottom:1.5rem;border-bottom:1px dashed var(--border-mid);">
            <h3 style="margin:0 0 1rem;font-size:1.05rem;color:var(--text);">
                <i data-i="gamepad-2"></i> Emulador alternativo <span style="font-weight:400;color:var(--text-light);font-size:.85rem;">(opcional)</span>
            </h3>

            <div class="form-group">
                <label for="alterno_nombre">Nombre del emulador</label>
                <input type="text"
                       id="alterno_nombre"
                       name="alterno_nombre"
                       value="<?= htmlspecialchars($actual['alterno']['nombre'] ?? '') ?>"
                       placeholder="Ej: RetroArch, ePSXe...">
            </div>

            <div class="form-group">
                <label>Plataformas</label>
                <div style="display:flex;gap:1.5rem;padding-top:.25rem;">
                    <?php
                    $platAlterno = $actual['alterno']['plataformas'] ?? [];
                    foreach (['PC', 'Android'] as $p): ?>
                    <label style="display:flex;align-items:center;gap:.4rem;cursor:pointer;">
                        <input type="checkbox"
                               name="alterno_plataformas[]"
                               value="<?= $p ?>"
                               <?= in_array($p, $platAlterno, true) ? 'checked' : '' ?>>
                        <?= $p ?>
                    </label>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="form-group">
                <label for="alterno_url">URL oficial</label>
                <input type="url"
                       id="alterno_url"
                       name="alterno_url"
                       value="<?= htmlspecialchars($actual['alterno']['url'] ?? '') ?>"
                       placeholder="https://...">
            </div>
        </div>

        <!-- Botones -->
        <div class="form-actions">
            <button type="submit" class="btn-primary">
                <i data-i="save"></i> Guardar emulador
            </button>
            <a href="/emulador/index"
               class="btn-primary"
               style="background:var(--cream-dark);color:var(--slate);box-shadow:3px 3px 0 var(--border-mid);border-color:var(--border-mid);">
                <i data-i="close"></i> Cancelar
            </a>
        </div>
    </form>
    <?php endif; ?>
</div>

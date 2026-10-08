<!-- views/admin/tfa.php -->
<div class="admin-header">
    <h2>Seguridad — Verificación en dos pasos (2FA)</h2>
    <div class="admin-header-actions">
        <a href="/admin/dashboard" class="btn-primary"><i data-i="dashboard"></i> Volver al panel</a>
    </div>
</div>

<?php if ($msg): ?>
    <?php
    require_once __DIR__ . '/../components/Alert.php';
    Alert::render($msgTipo, htmlspecialchars($msg), 'close');
    ?>
<?php endif; ?>

<div class="stat-card" style="margin-bottom: 1.5rem;">
    <div class="stat-label">Estado actual</div>
    <div class="stat-value" style="color: <?= $tfaActivo ? 'var(--success)' : 'var(--text-light)' ?>;">
        <?= $tfaActivo ? 'Activado' : 'Desactivado' ?>
    </div>
    <p style="margin-top: 0.5rem; font-size: 0.9rem; color: var(--text-light);">
        El 2FA añade un código de 6 dígitos generado por tu aplicación autenticadora
        (Google Authenticator, 1Password, Aegis…) al iniciar sesión como administrador.
    </p>
</div>

<?php if ($tfaActivo): ?>
    <div class="stat-card" style="margin-bottom: 1rem;">
        <h3 class="ranking-title" style="margin-bottom: 0.75rem;">Desactivar 2FA</h3>
        <p style="margin-bottom: 1rem; font-size: 0.9rem; color: var(--text-light);">
            Para desactivarlo introduce tu contraseña actual. Al desactivarlo se cerrarán
            todas las sesiones.
        </p>
        <form method="POST" action="/admin/tfa" autocomplete="off" style="display:flex; gap:0.6rem; flex-wrap:wrap; align-items:flex-end;">
            <?= CsrfService::field() ?>
            <input type="hidden" name="accion" value="desactivar">
            <div class="form-group" style="flex:1; min-width:220px; margin:0;">
                <label for="tfa-password">Contraseña actual</label>
                <input type="password" name="password" id="tfa-password" required autocomplete="off">
            </div>
            <button type="submit" class="btn-primary" style="background-color: var(--danger, #c0392b);">Desactivar 2FA</button>
        </form>
    </div>
<?php elseif ($secretPendiente !== null): ?>
    <div class="stat-card" style="margin-bottom: 1rem;">
        <h3 class="ranking-title" style="margin-bottom: 0.75rem;">Paso 1 — Añade la clave a tu app</h3>
        <p style="margin-bottom: 0.75rem; font-size: 0.9rem; color: var(--text-light);">
            Abre tu aplicación autenticadora y usa la opción «añadir una cuenta
            escribiendo una clave» con esta clave manual:
        </p>
        <div style="display:flex; gap:1.5rem; flex-wrap:wrap; align-items:center; margin-bottom: 1rem;">
            <div>
                <div style="font-size:0.85rem; color:var(--text-light); margin-bottom:0.3rem;">Clave manual (base32):</div>
                <code style="font-size:0.9rem; word-break:break-all; user-select:all;"><?= htmlspecialchars($secretPendiente) ?></code>
            </div>
            <div>
                <div style="font-size:0.85rem; color:var(--text-light); margin-bottom:0.3rem;">URI de aprovisionamiento:</div>
                <code style="font-size:0.8rem; word-break:break-all; user-select:all;"><?= htmlspecialchars($provisionUri) ?></code>
            </div>
        </div>

        <h3 class="ranking-title" style="margin-bottom: 0.75rem;">Paso 2 — Confirma con un código</h3>
        <form method="POST" action="/admin/tfa" autocomplete="off" style="display:flex; gap:0.6rem; flex-wrap:wrap; align-items:flex-end;">
            <?= CsrfService::field() ?>
            <input type="hidden" name="accion" value="confirmar">
            <div class="form-group" style="flex:1; min-width:160px; margin:0;">
                <label for="tfa-code">Código de 6 dígitos</label>
                <input type="text" name="code" id="tfa-code" required maxlength="6" inputmode="numeric"
                       pattern="[0-9]{6}" placeholder="000000" autocomplete="one-time-code">
            </div>
            <button type="submit" class="btn-primary" style="background-color: var(--success);">Activar 2FA</button>
        </form>
    </div>
<?php else: ?>
    <div class="stat-card" style="margin-bottom: 1rem;">
        <h3 class="ranking-title" style="margin-bottom: 0.75rem;">Activar 2FA</h3>
        <p style="margin-bottom: 1rem; font-size: 0.9rem; color: var(--text-light);">
            Al activarlo, cada inicio de sesión pedirá tu código de verificación.
            Al activar el 2FA se cerrarán todas las sesiones.
        </p>
        <form method="POST" action="/admin/tfa">
            <?= CsrfService::field() ?>
            <input type="hidden" name="accion" value="activar">
            <button type="submit" class="btn-primary" style="background-color: var(--success);"><i data-i="shield-2"></i> Activar 2FA</button>
        </form>
    </div>
<?php endif; ?>
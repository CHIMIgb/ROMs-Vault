<?php
require_once __DIR__ . '/Model.php';

class Usuario extends Model {
    protected $table = 'usuarios';

    /**
     * Busca un usuario por nombre.
     *
     * @return array|null Fila del usuario o null si no existe (nunca false:
     *                    contrato honesto para estaBloqueado() y callers).
     */
    public function findByUsername($username): ?array {
        $stmt = $this->pdo->prepare("SELECT * FROM usuarios WHERE username = ?");
        $stmt->execute([$username]);
        return $stmt->fetch() ?: null;
    }

    public function verifyPassword($password, $hash) {
        return password_verify($password, $hash);
    }

    /**
     * ¿La cuenta está temporalmente bloqueada por exceso de intentos fallidos?
     *
     * @param array|null $user Fila de usuarios (o null si el usuario no existe)
     * @return bool true si locked_until es un timestamp futuro
     */
    public function estaBloqueado(?array $user): bool {
        if (!$user || empty($user['locked_until'])) {
            return false;
        }
        $lock = strtotime((string) $user['locked_until']);
        return $lock !== false && $lock > time();
    }

    /**
     * Cuenta cuántos fallos consecutivos acumula la cuenta (contexto 2.2).
     * Se usa para decidir el bloqueo sin exponer el valor directamente.
     *
     * @param array $user Fila de usuarios
     * @return int Fallos acumulados (0 si no hay dato)
     */
    public function fallosActuales(array $user): int {
        return max(0, (int) ($user['login_failed_attempts'] ?? 0));
    }

    /**
     * Incrementa el contador de intentos fallidos de login.
     * Devuelve el nuevo valor del contador (para comparar con AUTH_LOCKOUT_MAX).
     */
    public function incrementarFallos(int $id): int {
        $stmt = $this->pdo->prepare(
            'UPDATE usuarios SET login_failed_attempts = login_failed_attempts + 1, '
            . 'updated_at = CURRENT_TIMESTAMP WHERE id = ? RETURNING login_failed_attempts'
        );
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row ? (int) $row['login_failed_attempts'] : 0;
    }

    /**
     * Bloquea la cuenta durante $seconds. También reinicia el contador de
     * fallos: al expirar el bloqueo, la cuenta arranca con ventana completa.
     */
    public function bloquear(int $id, int $seconds): bool {
        $stmt = $this->pdo->prepare(
            'UPDATE usuarios SET locked_until = CURRENT_TIMESTAMP + (? || \' seconds\')::interval, '
            . 'login_failed_attempts = 0, updated_at = CURRENT_TIMESTAMP WHERE id = ?'
        );
        return $stmt->execute([$seconds, $id]);
    }

    /**
     * Desbloquea la cuenta y reinicia el contador de fallos (login exitoso).
     */
    public function desbloquear(int $id): bool {
        $stmt = $this->pdo->prepare(
            'UPDATE usuarios SET login_failed_attempts = 0, locked_until = NULL, '
            . 'updated_at = CURRENT_TIMESTAMP WHERE id = ?'
        );
        return $stmt->execute([$id]);
    }

    /**
     * Configura (o rota) el secreto TOTP de 2FA. No activa 2FA hasta
     * habilitarTfa(); verificar primero con un código evita secret basura.
     */
    public function registrarSecretTfa(int $id, string $secret): bool {
        $stmt = $this->pdo->prepare(
            'UPDATE usuarios SET tfa_secret = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?'
        );
        return $stmt->execute([$secret, $id]);
    }

    /** Activa el 2FA (tras confirmar un código TOTP válido). */
    public function habilitarTfa(int $id): bool {
        $stmt = $this->pdo->prepare(
            'UPDATE usuarios SET tfa_enabled = TRUE, updated_at = CURRENT_TIMESTAMP WHERE id = ?'
        );
        return $stmt->execute([$id]);
    }

    /** Desactiva el 2FA y borra el secreto (revocación completa). */
    public function deshabilitarTfa(int $id): bool {
        $stmt = $this->pdo->prepare(
            'UPDATE usuarios SET tfa_enabled = FALSE, tfa_secret = NULL, '
            . 'updated_at = CURRENT_TIMESTAMP WHERE id = ?'
        );
        return $stmt->execute([$id]);
    }

    /** Secreto TOTP actual del usuario (o null si no tiene 2FA configurado). */
    public function obtenerSecretTfa(int $id): ?string {
        $stmt = $this->pdo->prepare('SELECT tfa_secret FROM usuarios WHERE id = ?');
        $stmt->execute([$id]);
        $secret = $stmt->fetchColumn();
        return $secret ? (string) $secret : null;
    }
}
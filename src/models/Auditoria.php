<?php
// models/Auditoria.php
require_once __DIR__ . '/../config/database.php';

/**
 * Registro de auditoría de eventos (A09, OWASP Top 10).
 *
 * A diferencia de los demás modelos, NO extiende Model a propósito:
 * Model llama a Database::getInstance(), que ante BD caída hace die()/503.
 * Auditoria debe ser fail-open: si la BD no está disponible, registrar()
 * devuelve false (el LoggerService cae al archivo como respaldo) en lugar
 * de matar el request.
 */
class Auditoria {
    private $pdo;

    public function __construct() {
        $this->pdo = Database::tryGetInstance();
    }

    /**
     * Registra un evento de auditoría.
     *
     * @param array $evento Claves: evento (string, requerido), nivel, username, user_id, rol_id, ip, contexto (array)
     * @return bool true si se persistió en BD; false si BD no disponible o falló el INSERT.
     */
    public function registrar(array $evento): bool {
        if ($this->pdo === null) {
            return false;
        }

        $contexto = $evento['contexto'] ?? [];
        // Nunca persistir secretos en contexto por si el llamador se descuida.
        unset($contexto['password'], $contexto['password_hash'], $contexto['token']);

        $sql = "INSERT INTO public.auditoria
                    (evento, nivel, username, user_id, rol_id, ip, contexto)
                VALUES
                    (:evento, :nivel, :username, :user_id, :rol_id, :ip, CAST(:contexto AS jsonb))";
        $stmt = $this->pdo->prepare($sql);

        try {
            $stmt->execute([
                'evento'   => $evento['evento'] ?? '',
                'nivel'    => $evento['nivel'] ?? 'info',
                'username' => $evento['username'] ?? null,
                'user_id'  => $evento['user_id'] ?? null,
                'rol_id'   => $evento['rol_id'] ?? null,
                'ip'       => $evento['ip'] ?? null,
                'contexto' => json_encode($contexto, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            ]);
            return true;
        } catch (PDOException $e) {
            // El fallo de INSERT (p. ej. FK inexistente) no debe romper nada.
            // Se omite el log en tests (ROMV_TESTING) para no ensuciar PHPUnit.
            if (!defined('ROMV_TESTING')) {
                error_log("Error al registrar auditoría: " . $e->getMessage());
            }
            return false;
        }
    }

    /**
     * Devuelve los eventos recientes (más nuevos primero).
     */
    public function recientes(int $limite = 50): array {
        if ($this->pdo === null) {
            return [];
        }
        $stmt = $this->pdo->prepare(
            "SELECT id, evento, nivel, username, user_id, rol_id, ip, contexto, created_at
             FROM public.auditoria
             ORDER BY id DESC
             LIMIT :limite"
        );
        $stmt->bindValue(':limite', $limite, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Cuenta eventos de un tipo en los últimos N días (para alertas/paneles).
     */
    public function contarEventos(string $evento, int $dias = 30): int {
        if ($this->pdo === null) {
            return 0;
        }
        $stmt = $this->pdo->prepare(
            "SELECT COUNT(*) FROM public.auditoria
             WHERE evento = :evento AND created_at >= now() - make_interval(days => :dias)"
        );
        $stmt->bindValue(':evento', $evento, PDO::PARAM_STR);
        $stmt->bindValue(':dias', $dias, PDO::PARAM_INT);
        $stmt->execute();
        return (int) $stmt->fetchColumn();
    }

    /**
     * Elimina eventos más antiguos que N días. Devuelve cuántas filas borró.
     * La retención/podado programado queda documentada en docs/ (futura tarea).
     */
    public function podarAntiguos(int $dias): int {
        if ($this->pdo === null) {
            return 0;
        }
        $stmt = $this->pdo->prepare(
            "DELETE FROM public.auditoria
             WHERE created_at < now() - make_interval(days => :dias)"
        );
        $stmt->bindValue(':dias', $dias, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->rowCount();
    }
}
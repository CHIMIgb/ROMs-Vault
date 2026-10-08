<?php
/**
 * config/AuthMiddleware.php
 * Middleware de autenticación reutilizable basado en JWT.
 * Proporciona guards para controllers y endpoints AJAX.
 */

require_once __DIR__ . '/JWTService.php';
require_once __DIR__ . '/TfaService.php';
require_once __DIR__ . '/../models/Usuario.php';

class AuthMiddleware {

    /** Rol con acceso total al panel de administración */
    private const ADMIN_ROLE_ID = 1;

    /**
     * ¿El JWT de la sesión no pasó el 2FA pese a que la cuenta lo exige?
     * Consulta la BD: solo se fuerza el paso 2FA si la cuenta tiene
     * tfa_enabled = TRUE y el token no declara 'tfa' => true (defensa en
     * profundidad: los tokens emitidos antes de activar 2FA quedan sin valor).
     */
    private static function requiereSegundoFactor(array $user): bool {
        if (!empty($user['tfa'])) {
            return false;
        }
        try {
            $row = (new Usuario())->find((int) $user['user_id']);
            return $row && !empty($row['tfa_enabled']);
        } catch (\Throwable $e) {
            // Fail-open: si la BD no responde, no bloquear al admin legítimo.
            return false;
        }
    }

    /**
     * Verifica que el usuario esté autenticado (JWT válido).
     * Si no lo está, redirige al login.
     * Uso: en constructores de controllers protegidos.
     *
     * @return array Datos del usuario autenticado
     */
    public static function requireAuth(): array {
        $user = JWTService::getCurrentUser();
        if ($user === null) {
            header('Location: /auth/login');
            exit;
        }
        return $user;
    }

    /**
     * Verifica que el usuario esté autenticado (JWT válido).
     * Si no lo está, responde con HTTP 403 y HTML de error.
     * Uso: en endpoints AJAX que devuelven HTML.
     *
     * @return array Datos del usuario autenticado
     */
    public static function requireAuthAjax(): array {
        $user = JWTService::getCurrentUser();
        if ($user === null) {
            http_response_code(403);
            require_once __DIR__ . '/../views/components/Alert.php';
            Alert::render('danger', 'Acceso denegado.', '✖');
            exit;
        }
        return $user;
    }

    /**
     * Verifica que el usuario esté autenticado Y sea administrador.
     * - Sin sesión válida → redirige al login.
     * - Autenticado pero sin rol admin → redirige al catálogo público.
     * - Admin con 2FA habilitado que no pasó el código → redirige al paso 2FA.
     * Uso: en constructores de controllers del panel de administración.
     *
     * @return array Datos del usuario administrador
     */
    public static function requireAdmin(): array {
        $user = JWTService::getCurrentUser();
        if ($user === null) {
            header('Location: /auth/login');
            exit;
        }
        if ((int) ($user['rol_id'] ?? 0) !== self::ADMIN_ROLE_ID) {
            // Ya está autenticado pero no es admin: fuera del panel
            header('Location: /');
            exit;
        }
        if (self::requiereSegundoFactor($user)) {
            TfaService::iniciarPending((int) $user['user_id']);
            header('Location: /auth/twoFactor');
            exit;
        }
        return $user;
    }

    /**
     * Verifica que el usuario esté autenticado Y sea administrador (AJAX).
     * Si no lo está, responde con HTTP 403 y HTML de error.
     * Uso: en endpoints AJAX del panel de administración.
     *
     * @return array Datos del usuario administrador
     */
    public static function requireAdminAjax(): array {
        $user = JWTService::getCurrentUser();
        if ($user === null) {
            http_response_code(403);
            require_once __DIR__ . '/../views/components/Alert.php';
            Alert::render('danger', 'Acceso denegado.', '✖');
            exit;
        }
        if ((int) ($user['rol_id'] ?? 0) !== self::ADMIN_ROLE_ID) {
            http_response_code(403);
            require_once __DIR__ . '/../views/components/Alert.php';
            Alert::render('danger', 'Acceso denegado.', '✖');
            exit;
        }
        if (self::requiereSegundoFactor($user)) {
            http_response_code(403);
            require_once __DIR__ . '/../views/components/Alert.php';
            Alert::render('danger', 'Verificación de dos factores pendiente.', '✖');
            exit;
        }
        return $user;
    }

    /**
     * Obtiene los datos del usuario actual sin redirigir ni bloquear.
     * Retorna null si no hay sesión activa.
     * Uso: en vistas y rutas públicas que necesitan saber si el usuario está logueado.
     *
     * @return array|null Datos del usuario o null
     */
    public static function getUser(): ?array {
        return JWTService::getCurrentUser();
    }
}

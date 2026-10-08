<?php
/**
 * config/AuthMiddleware.php
 * Middleware de autenticación reutilizable basado en JWT.
 * Proporciona guards para controllers y endpoints AJAX.
 */

require_once __DIR__ . '/JWTService.php';

class AuthMiddleware {

    /** Rol con acceso total al panel de administración */
    private const ADMIN_ROLE_ID = 1;

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

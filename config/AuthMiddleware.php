<?php
/**
 * config/AuthMiddleware.php
 * Middleware de autenticación reutilizable basado en JWT.
 * Proporciona guards para controllers y endpoints AJAX.
 */

require_once __DIR__ . '/JWTService.php';

class AuthMiddleware {

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
            header('Location: index.php?controller=auth&action=login');
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
            echo '<div class="rv-inline-alert rv-inline--danger rv-inline--visible">'
               . '<span class="rv-inline-icon">✖</span>'
               . '<span class="rv-inline-msg">Acceso denegado.</span>'
               . '</div>';
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

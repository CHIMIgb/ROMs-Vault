<?php
require_once __DIR__ . '/../models/Usuario.php';
require_once __DIR__ . '/../config/JWTService.php';
require_once __DIR__ . '/../config/CsrfService.php';
require_once __DIR__ . '/../config/RateLimiter.php';
require_once __DIR__ . '/../config/LoggerService.php';

class AuthController {
    public function login() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $ip       = RateLimiter::clientIp();
            $username = trim((string) ($_POST['username'] ?? ''));
            $password = $_POST['password'] ?? '';

            // Rate limiting por IP: 5 intentos por ventana de 15 minutos
            $rlMax    = (int)($_ENV['AUTH_LOGIN_MAX']    ?? 5);
            $rlWindow = (int)($_ENV['AUTH_LOGIN_WINDOW'] ?? 900);
            if (!RateLimiter::check($ip, $rlMax, $rlWindow, 'login')) {
                LoggerService::rateLimited($username, $ip);
                RateLimiter::respond429($rlWindow);
            }

            $usuarioModel = new Usuario();
            $user = $usuarioModel->findByUsername($username);

            if ($user && $usuarioModel->verifyPassword($password, $user['password_hash'])) {
                // Login exitoso: reiniciar contador de intentos fallidos
                RateLimiter::reset($ip, 'login');
                $token = JWTService::generate($user);
                JWTService::setTokenCookie($token);
                LoggerService::loginSuccess($user, $ip);
                header('Location: /admin/dashboard');
                exit;
            } else {
                LoggerService::loginFailed($username, $ip);
                $error = "Usuario o contraseña incorrectos";
            }
        }
        require_once __DIR__ . '/../views/layout/header.php';
        require_once __DIR__ . '/../views/auth/login.php';
        require_once __DIR__ . '/../views/layout/footer.php';
    }

    public function logout() {
        // Registrar quién cerraba sesión antes de invalidar la cookie
        $user = JWTService::getCurrentUser();
        LoggerService::logout($user, RateLimiter::clientIp());
        JWTService::clearTokenCookie();
        header('Location: /');
        exit;
    }
}
<?php
require_once 'models/Usuario.php';
require_once __DIR__ . '/../config/JWTService.php';
require_once __DIR__ . '/../config/CsrfService.php';
require_once __DIR__ . '/../config/RateLimiter.php';

class AuthController {
    public function login() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            // Rate limiting por IP: 5 intentos por ventana de 15 minutos
            $rlMax    = (int)($_ENV['AUTH_LOGIN_MAX']    ?? 5);
            $rlWindow = (int)($_ENV['AUTH_LOGIN_WINDOW'] ?? 900);
            if (!RateLimiter::check(RateLimiter::clientIp(), $rlMax, $rlWindow, 'login')) {
                RateLimiter::respond429($rlWindow);
            }

            $username = $_POST['username'] ?? '';
            $password = $_POST['password'] ?? '';

            $usuarioModel = new Usuario();
            $user = $usuarioModel->findByUsername($username);

            if ($user && $usuarioModel->verifyPassword($password, $user['password_hash'])) {
                // Login exitoso: reiniciar contador de intentos fallidos
                RateLimiter::reset(RateLimiter::clientIp(), 'login');
                $token = JWTService::generate($user);
                JWTService::setTokenCookie($token);
                header('Location: index.php?controller=admin&action=dashboard');
                exit;
            } else {
                $error = "Usuario o contraseña incorrectos";
            }
        }
        require_once 'views/layout/header.php';
        require_once 'views/auth/login.php';
        require_once 'views/layout/footer.php';
    }

    public function logout() {
        JWTService::clearTokenCookie();
        header('Location: index.php');
        exit;
    }
}
<?php
require_once 'models/Usuario.php';
require_once __DIR__ . '/../config/JWTService.php';

class AuthController {
    public function login() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $username = $_POST['username'] ?? '';
            $password = $_POST['password'] ?? '';

            $usuarioModel = new Usuario();
            $user = $usuarioModel->findByUsername($username);

            if ($user && $usuarioModel->verifyPassword($password, $user['password_hash'])) {
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
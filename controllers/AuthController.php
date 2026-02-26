<?php
require_once 'models/Usuario.php';

class AuthController {
    public function login() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $username = $_POST['username'] ?? '';
            $password = $_POST['password'] ?? '';

            $usuarioModel = new Usuario();
            $user = $usuarioModel->findByUsername($username);

            if ($user && $usuarioModel->verifyPassword($password, $user['password_hash'])) {
                session_start();
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['rol_id'] = $user['rol_id'];
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
        session_start();
        session_destroy();
        header('Location: index.php');
        exit;
    }
}
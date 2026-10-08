<?php
require_once __DIR__ . '/../models/Usuario.php';
require_once __DIR__ . '/../config/JWTService.php';
require_once __DIR__ . '/../config/CsrfService.php';
require_once __DIR__ . '/../config/RateLimiter.php';
require_once __DIR__ . '/../config/LoggerService.php';
require_once __DIR__ . '/../config/TfaService.php';

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

            // Lockout por cuenta (2.2): si la cuenta está temporalmente
            // bloqueada, rechazo genérico (no revela que la cuenta existe).
            if ($usuarioModel->estaBloqueado($user)) {
                LoggerService::loginBlockedAccount($username, $ip);
                $error = "Usuario o contraseña incorrectos";
            } elseif ($user && $usuarioModel->verifyPassword($password, $user['password_hash'])) {
                // Contraseña correcta: reiniciar contador de fallos y
                // desbloquear la cuenta (aunque el 2FA siga pendiente).
                $usuarioModel->desbloquear((int) $user['id']);
                RateLimiter::reset($ip, 'login');

                if (!empty($user['tfa_enabled'])) {
                    // 2FA obligatorio: pasar al segundo factor antes de la sesión
                    TfaService::iniciarPending((int) $user['id']);
                    header('Location: /auth/twoFactor');
                    exit;
                }

                $token = JWTService::generate($user);
                JWTService::setTokenCookie($token);
                LoggerService::loginSuccess($user, $ip);
                header('Location: /admin/dashboard');
                exit;
            } else {
                LoggerService::loginFailed($username, $ip);

                // Lockout por cuenta: acumular fallos y bloquear al llegar al
                // máximo (solo si la cuenta existe; sino, el fallo queda en el
                // rate limit por IP e igual no se revela la existencia).
                if ($user) {
                    $lockoutMax     = (int) ($_ENV['AUTH_LOCKOUT_MAX']     ?? 5);
                    $lockoutSeconds = (int) ($_ENV['AUTH_LOCKOUT_SECONDS'] ?? 900);
                    if ($lockoutMax > 0 && $usuarioModel->incrementarFallos((int) $user['id']) >= $lockoutMax) {
                        $usuarioModel->bloquear((int) $user['id'], $lockoutSeconds);
                        LoggerService::accountLocked($username, $ip);
                    }
                }
                $error = "Usuario o contraseña incorrectos";
            }
        }
        require_once __DIR__ . '/../views/layout/header.php';
        require_once __DIR__ . '/../views/auth/login.php';
        require_once __DIR__ . '/../views/layout/footer.php';
    }

    /**
     * Segundo factor (TOTP). GET = formulario; POST = verificación.
     * Solo accesible con una cookie "pendiente" válida (creada al introducir
     * la contraseña correcta en el login con 2FA habilitado).
     */
    public function twoFactor() {
        $usuarioModel = new Usuario();
        $ip           = RateLimiter::clientIp();

        // Si ya hay sesión completa, el paso 2FA sobra (redirigir al panel)
        if (JWTService::getCurrentUser() !== null) {
            header('Location: /admin/dashboard');
            exit;
        }

        $userId = TfaService::usuarioPendiente();
        if ($userId === null) {
            header('Location: /auth/login');
            exit;
        }

        $user = $usuarioModel->find($userId);
        if (!$user || empty($user['tfa_enabled'])) {
            // Estado inválido (cuenta sin 2FA o borrada): limpiar y volver
            TfaService::limpiarPending();
            header('Location: /auth/login');
            exit;
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            // Rate limit por IP específico del segundo factor (fuerza bruta TOTP)
            $rlMax    = (int) ($_ENV['TFA_RATE_LIMIT_MAX']    ?? 10);
            $rlWindow = (int) ($_ENV['TFA_RATE_LIMIT_WINDOW'] ?? 900);
            if (!RateLimiter::check($ip, $rlMax, $rlWindow, 'two_factor')) {
                LoggerService::rateLimited($user['username'], $ip);
                RateLimiter::respond429($rlWindow);
            }

            $code   = trim((string) ($_POST['code'] ?? ''));
            $secret = $usuarioModel->obtenerSecretTfa((int) $user['id']);

            if ($secret !== null && TfaService::verificar($secret, $code)) {
                // Código válido: emitir sesión con claim tfa=true y terminar
                $token = JWTService::generate($user, true);
                JWTService::setTokenCookie($token);
                TfaService::limpiarPending();
                LoggerService::loginSuccess($user, $ip);
                header('Location: /admin/dashboard');
                exit;
            }

            LoggerService::tfaFailed($user['username'], $ip);
            $error = "Código incorrecto o expirado. Inténtalo de nuevo.";
        }

        require_once __DIR__ . '/../views/layout/header.php';
        require_once __DIR__ . '/../views/auth/two_factor.php';
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
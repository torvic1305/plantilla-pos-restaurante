<?php
include 'data.php';
include 'security.php';

// Solo procesar si es POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Rate limiting
        if (!checkRateLimit('login_attempt', 5, 300)) {
            http_response_code(429);
            throw new Exception('Demasiados intentos. Intente más tarde.');
        }

        // Validar CSRF token
        $csrf_token = $_POST['csrf_token'] ?? '';
        if (!validateCsrfToken($csrf_token)) {
            throw new Exception('Token de seguridad inválido');
        }

        // Validar entrada
        $usuario = sanitizeString($_POST['username'] ?? '', 100);
        $password = $_POST['password'] ?? '';

        if (empty($usuario) || empty($password)) {
            throw new Exception('Usuario y contraseña son requeridos');
        }

        if (strlen($password) < 8) {
            throw new Exception('Credenciales inválidas');
        }

        // Verificar login
        $user = verificarLogin($usuario, $password);
        if (!$user) {
            // Log de intento fallido
            registrarAuditoria(null, 'LOGIN_FALLIDO', 'USUARIO', null, "Usuario: {$usuario}");
            throw new Exception('Usuario o contraseña incorrectos');
        }

        // Procesar datos de usuario
        if (isset($user['permisos']) && is_string($user['permisos'])) {
            $user['permisos'] = json_decode($user['permisos'], true) ?: [];
        }
        if (isset($user['configuraciones']) && is_string($user['configuraciones'])) {
            $user['configuraciones'] = json_decode($user['configuraciones'], true) ?: [];
        }

        $_SESSION['user'] = $user;

        // Log de login exitoso
        registrarAuditoria($user['id'], 'LOGIN_EXITOSO', 'USUARIO', $user['id'], "Usuario: {$usuario}");

        header('Location: dashboard.php');
        exit;
    } catch (Exception $e) {
        $_SESSION['login_error'] = $e->getMessage();
        header('Location: index.php?error=1');
        exit;
    }
}

// Si no es POST, redirigir a index.php
header('Location: index.php');
exit;
?>
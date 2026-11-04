<?php
/**
 * Funciones de seguridad comunes
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Validar y sanitizar entrada de string
 */
function sanitizeString($input, $maxLength = 255)
{
    $input = trim(strval($input));
    $input = strip_tags($input);
    if ($maxLength > 0 && mb_strlen($input) > $maxLength) {
        $input = mb_substr($input, 0, $maxLength);
    }
    return $input;
}

/**
 * Validar que sea un número entero válido
 */
function validateInteger($input, $min = 0, $max = PHP_INT_MAX)
{
    if (filter_var($input, FILTER_VALIDATE_INT) === false) {
        return null;
    }
    $int = intval($input);
    return ($int >= $min && $int <= $max) ? $int : null;
}

/**
 * Validar que sea un número decimal válido
 */
function validateDecimal($input, $min = 0, $max = PHP_FLOAT_MAX)
{
    if (!is_numeric($input)) {
        return null;
    }
    $float = round(floatval($input), 2);
    return ($float >= $min && $float <= $max) ? $float : null;
}

/**
 * Validar email
 */
function validateEmail($email)
{
    $email = trim(strval($email));
    return filter_var($email, FILTER_VALIDATE_EMAIL) ? $email : null;
}

/**
 * Validar teléfono (formato básico)
 */
function validatePhone($phone)
{
    $phone = preg_replace('/[^\d\s\-\+\(\)]/', '', $phone);
    if (strlen($phone) >= 7 && strlen($phone) <= 20) {
        return $phone;
    }
    return null;
}

/**
 * Validar URL
 */
function validateUrl($url)
{
    return filter_var($url, FILTER_VALIDATE_URL) ? $url : null;
}

/**
 * Validar contraseña (mínimo 8 caracteres, debe tener números y letras)
 */
function validatePassword($password)
{
    if (strlen($password) < 8) {
        return null;
    }
    if (!preg_match('/[a-zA-Z]/', $password) || !preg_match('/[0-9]/', $password)) {
        return null;
    }
    return $password;
}

/**
 * Escapar HTML para prevenir XSS
 */
function escapeHtml($text)
{
    return htmlspecialchars($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
}

/**
 * Escapar atributo JSON en HTML
 */
function escapeJsonAttribute($data)
{
    if (!is_string($data)) {
        $data = json_encode($data);
    }
    return htmlspecialchars($data, ENT_QUOTES | ENT_HTML5, 'UTF-8');
}

/**
 * Verificar permiso de usuario
 */
function requirePermission($permiso)
{
    if (!isset($_SESSION['user'])) {
        http_response_code(401);
        echo json_encode(['status' => 'error', 'message' => 'No autorizado']);
        exit;
    }

    $permisos = $_SESSION['user']['permisos'] ?? [];
    if (is_string($permisos)) {
        $permisos = json_decode($permisos, true);
    }
    if (!is_array($permisos)) {
        $permisos = [];
    }

    if (!($permisos[$permiso] ?? false)) {
        http_response_code(403);
        echo json_encode(['status' => 'error', 'message' => 'Permisos insuficientes']);
        exit;
    }
}

/**
 * Verificar rol de usuario
 */
function requireRole($rol)
{
    if (!isset($_SESSION['user'])) {
        http_response_code(401);
        echo json_encode(['status' => 'error', 'message' => 'No autorizado']);
        exit;
    }

    if ($_SESSION['user']['rol'] !== $rol) {
        http_response_code(403);
        echo json_encode(['status' => 'error', 'message' => 'Rol insuficiente']);
        exit;
    }
}

/**
 * Validar método HTTP
 */
function requireMethod($method)
{
    if ($_SERVER['REQUEST_METHOD'] !== strtoupper($method)) {
        http_response_code(405);
        echo json_encode(['status' => 'error', 'message' => 'Método no permitido']);
        exit;
    }
}

/**
 * Validar CSRF token
 */
function validateCsrfToken($token)
{
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Generar CSRF token
 */
function generateCsrfToken()
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Obtener CSRF token
 */
function getCsrfToken()
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Respuesta JSON segura
 */
function jsonResponse($status, $message = '', $data = null)
{
    header('Content-Type: application/json; charset=utf-8');
    $response = ['status' => $status];
    if (!empty($message)) {
        $response['message'] = $message;
    }
    if ($data !== null) {
        $response['data'] = $data;
    }
    echo json_encode($response, JSON_UNESCAPED_UNICODE);
}

/**
 * Loguear actividad
 */
function logActivity($usuario_id, $accion, $entidad, $entidad_id = null, $descripcion = '')
{
    global $pdo;
    if (!$pdo)
        return false;
    try {
        $accion = strtoupper(sanitizeString($accion, 50));
        $entidad = strtoupper(sanitizeString($entidad, 50));
        $stmt = $pdo->prepare("INSERT INTO audit_log (usuario_id, accion, entidad, entidad_id, descripcion) VALUES (?, ?, ?, ?, ?)");
        return $stmt->execute([$usuario_id, $accion, $entidad, $entidad_id, $descripcion]);
    } catch (PDOException $e) {
        error_log("Activity log failed: " . $e->getMessage());
        return false;
    }
}

/**
 * Rate limiting simple (basado en sesión)
 */
function checkRateLimit($action, $max_attempts = 5, $time_window = 60)
{
    $key = "ratelimit_" . md5($action);
    $now = time();

    if (!isset($_SESSION[$key])) {
        $_SESSION[$key] = [];
    }

    // Limpiar intentos antiguos
    $_SESSION[$key] = array_filter($_SESSION[$key], function ($timestamp) use ($now, $time_window) {
        return ($now - $timestamp) < $time_window;
    });

    if (count($_SESSION[$key]) >= $max_attempts) {
        return false;
    }

    $_SESSION[$key][] = $now;
    return true;
}
?>
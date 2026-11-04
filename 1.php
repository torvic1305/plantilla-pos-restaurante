<?php
include 'data.php';
include 'security.php';
requireMethod('POST');

$csrf_token = $_POST['csrf_token'] ?? '';
if (!validateCsrfToken($csrf_token)) {
    header('Location: index.php?error=csrf');
    exit;
}

$usuario = sanitizeString($_POST['username'] ?? '', 100);
$password = $_POST['password'] ?? '';

if (empty($usuario) || empty($password)) {
    header('Location: index.php?error=2');
    exit;
}

$user = verificarLogin($usuario, $password);
if ($user) {
    if (isset($user['permisos']) && is_string($user['permisos'])) {
        $user['permisos'] = json_decode($user['permisos'], true) ?: [];
    }
    if (isset($user['configuraciones']) && is_string($user['configuraciones'])) {
        $user['configuraciones'] = json_decode($user['configuraciones'], true) ?: [];
    }
    $_SESSION['user'] = $user;
    header('Location: dashboard.php');
    exit;
}

header('Location: index.php?error=1');
exit;
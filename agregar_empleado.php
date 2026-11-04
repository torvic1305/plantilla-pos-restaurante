<?php
include 'data.php';
include 'security.php';

requireMethod('POST');
requirePermission('empleados');

try {
    if (!is_string($_POST['csrf_token'] ?? null) || !validateCsrfToken($_POST['csrf_token'])) {
        throw new Exception('Token de seguridad inválido');
    }

    $nombre = sanitizeString($_POST['nombre'] ?? '', 100);
    $usuario = sanitizeString($_POST['usuario'] ?? '', 100);
    $password = $_POST['password'] ?? '';
    $rol = $_POST['rol'] ?? '';

    if (empty($nombre)) {
        throw new Exception('Nombre es requerido');
    }
    if (empty($usuario) || strlen($usuario) < 3) {
        throw new Exception('Usuario debe tener al menos 3 caracteres');
    }
    if (!in_array($rol, ['mesero', 'cocinero', 'gerente'])) {
        throw new Exception('Rol inválido');
    }
    $roleQuery = $pdo->prepare('SELECT rol FROM empleados WHERE id = ?');
    $roleQuery->execute([$_SESSION['user']['id']]);
    $currentRole = $roleQuery->fetchColumn();
    if ($currentRole === false) {
        throw new Exception('No se pudo verificar el rol de la sesión');
    }
    if ($rol === 'gerente' && $currentRole !== 'gerente') {
        throw new Exception('Solo un gerente puede crear otra cuenta de gerente');
    }

    // Validar contraseña
    if (validatePassword($password) === null) {
        throw new Exception('Contraseña debe tener al menos 8 caracteres con letras y números');
    }

    // Verificar si usuario existe
    $stmt = $pdo->prepare("SELECT id FROM empleados WHERE usuario = ?");
    $stmt->execute([$usuario]);
    if ($stmt->fetch()) {
        throw new Exception('Usuario ya existe');
    }

    // Agregar empleado
    addEmpleado($nombre, $usuario, $password, $rol);
    registrarAuditoria($_SESSION['user']['id'], 'CREAR_EMPLEADO', 'EMPLEADO', null, "Usuario: {$usuario}, Rol: {$rol}");

    if (isset($_POST['return_json'])) {
        jsonResponse('ok', 'Empleado agregado exitosamente');
    } else {
        header('Location: empleados.php?success=1');
    }
    exit;
} catch (Exception $e) {
    if (isset($_POST['return_json'])) {
        http_response_code(400);
        jsonResponse('error', $e->getMessage());
    } else {
        header('Location: empleados.php?error=' . urlencode($e->getMessage()));
    }
    exit;
}
?>
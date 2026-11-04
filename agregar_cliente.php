<?php
include 'data.php';
include 'security.php';

requireMethod('POST');
requirePermission('menu_editar');

try {
    // Validar CSRF obligatorio
    if (!isset($_POST['csrf_token']) || !validateCsrfToken($_POST['csrf_token'])) {
        throw new Exception('Token de seguridad inválido');
    }

    $nombre = sanitizeString($_POST['nombre'] ?? '', 100);
    $email = sanitizeString($_POST['email'] ?? '', 100);
    $telefono = sanitizeString($_POST['telefono'] ?? '', 20);
    $direccion = sanitizeString($_POST['direccion'] ?? '', 255);

    if (empty($nombre)) {
        throw new Exception('Nombre es requerido');
    }

    // Validar email si se proporciona
    if (!empty($email) && !validateEmail($email)) {
        throw new Exception('Email inválido');
    }

    // Validar teléfono si se proporciona
    if (!empty($telefono) && !validatePhone($telefono)) {
        throw new Exception('Teléfono inválido (7-20 caracteres)');
    }

    addCliente($nombre, $telefono, $email, $direccion);
    registrarAuditoria($_SESSION['user']['id'], 'CREAR_CLIENTE', 'CLIENTE', null, "Cliente: {$nombre}");

    if (isset($_POST['return_json'])) {
        jsonResponse('ok', 'Cliente agregado exitosamente');
    } else {
        header('Location: clientes.php?success=1');
    }
    exit;
} catch (Exception $e) {
    if (isset($_POST['return_json'])) {
        http_response_code(400);
        jsonResponse('error', $e->getMessage());
    } else {
        header('Location: clientes.php?error=' . urlencode($e->getMessage()));
    }
    exit;
}
?>
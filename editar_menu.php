<?php
include 'data.php';
include 'security.php';

requireMethod('POST');
requirePermission('menu_editar');

try {
    if (!isset($_POST['csrf_token']) || !validateCsrfToken($_POST['csrf_token'])) {
        throw new Exception('Token de seguridad inválido');
    }

    $id = validateInteger($_POST['id'] ?? 0, 1);
    $nombre = sanitizeString($_POST['nombre'] ?? '', 100);
    $precio = validateDecimal($_POST['precio'] ?? 0, 0.01, 9999);
    $categoria = sanitizeString($_POST['categoria'] ?? '', 50);
    $allowedCategories = ['Comida', 'Bebidas'];

    if ($id === null || empty($nombre) || $precio === null || empty($categoria) || !in_array($categoria, $allowedCategories, true)) {
        throw new Exception('Datos inválidos');
    }

    $stmt = $pdo->prepare("UPDATE menu SET nombre = ?, precio = ?, categoria = ? WHERE id = ?");
    $stmt->execute([$nombre, $precio, $categoria, $id]);

    registrarAuditoria($_SESSION['user']['id'], 'EDITAR_MENU', 'MENU', $id, "Item: {$nombre}");
    header('Location: menu.php?success=1');
    exit;
} catch (Exception $e) {
    header('Location: menu.php?error=' . urlencode($e->getMessage()));
    exit;
}
?>
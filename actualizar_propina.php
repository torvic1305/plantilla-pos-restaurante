<?php
include 'data.php';
include 'security.php';

requireMethod('POST');
requirePermission('propinas');

if (!isset($_SESSION['user'])) {
    http_response_code(401);
    jsonResponse('error', 'No autorizado');
    exit;
}

try {
    $data = json_decode(file_get_contents('php://input'), true);
    $csrf_token = trim($data['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
    if (!validateCsrfToken($csrf_token)) {
        throw new Exception('Token de seguridad inválido');
    }
    if (!is_array($data) || empty($data['id']) || !isset($data['propina'])) {
        throw new Exception('Datos inválidos');
    }

    $id = validateInteger($data['id']);
    if ($id === null || $id <= 0) {
        throw new Exception('ID de venta inválido');
    }

    $propina = validateDecimal($data['propina'], 0, 10000);
    if ($propina === null) {
        throw new Exception('Valor de propina inválido');
    }

    // Verificar que la venta existe
    global $pdo;
    $stmt = $pdo->prepare("SELECT id, propina FROM ventas WHERE id = ?");
    $stmt->execute([$id]);
    $venta = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$venta) {
        throw new Exception('Venta no encontrada');
    }

    // Prevenir duplicado de propinas
    if ($venta['propina'] > 0 && $venta['propina'] !== $propina) {
        throw new Exception('Esta venta ya tiene propina registrada');
    }

    updateVentaPropina($id, $propina);
    registrarAuditoria($_SESSION['user']['id'], 'UPDATE_PROPINA', 'VENTA', $id, "Propina actualizada a: {$propina}");

    jsonResponse('ok', 'Propina registrada correctamente');
} catch (Exception $e) {
    http_response_code(400);
    jsonResponse('error', $e->getMessage());
}
?>
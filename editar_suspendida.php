<?php
include 'data.php';
include 'security.php';

requireMethod('POST');
requirePermission('pos');
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
    if (!is_array($data) || empty($data['id']) || !isset($data['nuevos_items'])) {
        throw new Exception('Datos inválidos');
    }

    $id = validateInteger($data['id'], 1);
    if ($id === null) {
        throw new Exception('ID inválido');
    }

    $venta = getVentaById($id);
    if (!$venta || !$venta['suspendida']) {
        throw new Exception('Venta suspendida no encontrada');
    }

    $orden = json_decode($venta['orden'], true);
    if (!is_array($orden)) {
        $orden = [];
    }

    $nuevos_items = is_array($data['nuevos_items']) ? $data['nuevos_items'] : [];
    $orden_actualizada = $orden;
    foreach ($nuevos_items as $item) {
        if (!isset($item['id'], $item['nombre'], $item['precio'])) {
            continue;
        }
        $orden_actualizada[] = [
            'id' => intval($item['id']),
            'nombre' => sanitizeString($item['nombre'], 100),
            'precio' => validateDecimal($item['precio'], 0, 999999),
            'cantidad' => max(1, intval($item['cantidad'] ?? 1))
        ];
    }

    updateVentaOrden($venta['id'], $orden_actualizada);
    registrarAuditoria($_SESSION['user']['id'], 'EDITAR_SUSPENDIDA', 'VENTA', $venta['id'], 'Orden actualizada');

    header('Content-Type: application/json');
    echo json_encode(['status' => 'ok', 'orden' => $orden_actualizada]);
} catch (Exception $e) {
    http_response_code(400);
    jsonResponse('error', $e->getMessage());
}
?>
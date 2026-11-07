<?php
include 'data.php';
include 'security.php';

requireMethod('POST');
requirePermission('ventas_suspendidas_ver');
if (!isset($_SESSION['user'])) {
    http_response_code(401);
    jsonResponse('error', 'No autorizado');
    exit;
}

try {
    $data = json_decode(file_get_contents('php://input'), true);
    if (!is_array($data)) {
        throw new Exception('Datos inválidos');
    }
    $csrfInput = $data['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    $csrf_token = is_string($csrfInput) ? trim($csrfInput) : '';
    if (!validateCsrfToken($csrf_token)) {
        throw new Exception('Token de seguridad inválido');
    }
    if (empty($data['id']) || (!array_key_exists('nuevos_items', $data) && !array_key_exists('items_existentes', $data))) {
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

    $nuevos_items = $data['nuevos_items'] ?? [];
    if (!is_array($nuevos_items)) {
        throw new Exception('Lista de productos nuevos inválida.');
    }
    if (array_key_exists('items_existentes', $data)) {
        $orden_actualizada = updateSuspendedOrderItems($orden, $data['items_existentes'], $nuevos_items);
    } else {
        $normalizedNewItems = normalizeMenuOrderItems($nuevos_items);
        $orden_actualizada = array_merge($orden, $normalizedNewItems);
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
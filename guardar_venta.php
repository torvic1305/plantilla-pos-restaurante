<?php
include 'data.php';
include 'security.php';
requirePermission('pos');

// Validar autenticación
if (!isset($_SESSION['user'])) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'No autorizado']);
    exit;
}

// Validar método POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'Método no permitido']);
    exit;
}

try {
    $requestBody = file_get_contents('php://input');
    $data = json_decode($requestBody, true);
    $csrf_token = trim($data['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
    if (!validateCsrfToken($csrf_token)) {
        throw new Exception('Token de seguridad inválido');
    }
    if (!is_array($data) || empty($data['mesero']) || empty($data['mesa']) || !isset($data['orden'])) {
        throw new Exception('Datos inválidos');
    }

    // Validar mesa
    $mesa = intval($data['mesa']);
    if ($mesa <= 0 || $mesa > 1000) {
        throw new Exception('Número de mesa inválido');
    }

    // Validar orden
    $orden = is_array($data['orden']) ? $data['orden'] : [];
    if (empty($orden)) {
        throw new Exception('Orden vacía');
    }

    // Validar estructura de orden
    $total = 0;
    foreach ($orden as $item) {
        if (
            !isset($item['id'], $item['nombre'], $item['precio']) ||
            !is_numeric($item['id']) || !is_numeric($item['precio'])
        ) {
            throw new Exception('Estructura de orden inválida');
        }
        $precio = floatval($item['precio']);
        $cantidad = max(1, intval($item['cantidad'] ?? 1));
        if ($precio < 0 || $cantidad <= 0) {
            throw new Exception('Precio o cantidad inválida');
        }
        $total += $precio * $cantidad;
    }

    if ($total <= 0) {
        throw new Exception('Total debe ser mayor a cero');
    }

    $suspendida = !empty($data['suspendida']);
    $tipo_pago = $suspendida ? null : trim($data['tipo_pago'] ?? '');
    if (!$suspendida && empty($tipo_pago)) {
        throw new Exception('Debe seleccionar un tipo de pago para completar la venta');
    }

    $propina = max(0, floatval($data['propina'] ?? 0));
    $monto_pagado = floatval($data['monto_pagado'] ?? 0);
    $descuento = max(0, floatval($data['descuento'] ?? 0));
    $impuesto = max(0, floatval($data['impuesto'] ?? 0));

    // Validar descuento
    if ($descuento > $total) {
        throw new Exception('Descuento no puede ser mayor al total');
    }

    // Validar monto pagado para efectivo
    if (!$suspendida && $tipo_pago === 'Efectivo') {
        if ($monto_pagado < $total) {
            throw new Exception('El monto pagado debe ser mayor o igual al total para efectivo');
        }
    }
    if (!$suspendida && $tipo_pago !== 'Efectivo' && $monto_pagado <= 0) {
        $monto_pagado = $total;
    }

    $saleId = addVenta($data['mesero'], $mesa, $orden, $suspendida, $tipo_pago, $propina, $monto_pagado, $descuento, $impuesto);
    registrarAuditoria($_SESSION['user']['id'], 'VENTA_' . ($suspendida ? 'SUSPENDIDA' : 'COMPLETADA'), 'VENTA', $saleId, "Mesa: {$mesa}, Total: {$total}");

    header('Content-Type: application/json');
    echo json_encode(['status' => 'ok', 'id' => $saleId]);
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
?>
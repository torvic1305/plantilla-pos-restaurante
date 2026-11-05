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
    if (!is_array($data)) {
        throw new Exception('Datos inválidos');
    }
    $csrfInput = $data['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    $csrf_token = is_string($csrfInput) ? trim($csrfInput) : '';
    if (!validateCsrfToken($csrf_token)) {
        throw new Exception('Token de seguridad inválido');
    }
    if (empty($data['mesero']) || !isset($data['mesa'], $data['orden'])) {
        throw new Exception('Datos inválidos');
    }

    $appConfig = getAppConfig();
    $mesaRequerida = !empty($appConfig['pos_require_table']);
    $mesa = validateInteger($data['mesa'], $mesaRequerida ? 1 : 0, 1000);
    if ($mesa === null) {
        throw new Exception($mesaRequerida ? 'Seleccione una mesa válida.' : 'Número de mesa inválido.');
    }

    $orden = normalizeMenuOrderItems($data['orden']);
    if (empty($orden)) {
        throw new Exception('Orden vacía');
    }

    $total = 0;
    foreach ($orden as $item) {
        $total += $item['precio'] * $item['cantidad'];
    }

    if ($total <= 0) {
        throw new Exception('Total debe ser mayor a cero');
    }

    $suspendida = !empty($data['suspendida']);
    if ($suspendida) {
        requirePermission('ventas_suspendidas_ver');
    }
    $paymentInput = $data['tipo_pago'] ?? '';
    if (!$suspendida && !is_string($paymentInput)) {
        throw new Exception('Debe seleccionar una forma de pago válida.');
    }
    $tipo_pago = $suspendida ? null : trim($paymentInput);
    if (!$suspendida && empty($tipo_pago)) {
        throw new Exception('Debe seleccionar un tipo de pago para completar la venta');
    }
    if (!$suspendida && !in_array($tipo_pago, $appConfig['formas_pago'], true)) {
        throw new Exception('La forma de pago ya no está habilitada. Actualice el POS e inténtelo de nuevo.');
    }

    $propina = validateDecimal($data['propina'] ?? 0, 0, 10000);
    $monto_pagado = validateDecimal($data['monto_pagado'] ?? 0, 0, 99999999);
    $descuento = validateDecimal($data['descuento'] ?? 0, 0, $total);
    if ($propina === null || $monto_pagado === null || $descuento === null) {
        throw new Exception('El monto, descuento o propina no es válido');
    }

    $impuesto = 0;
    if (!$suspendida) {
        $baseImponible = max(0, $total - $descuento);
        $impuesto = round($baseImponible * (float) ($appConfig['impuesto'] ?? 0) / 100, 2);
        $totalVenta = $baseImponible + $impuesto;
        if ($tipo_pago === 'Efectivo' && $monto_pagado < $totalVenta) {
            throw new Exception('El monto pagado debe ser mayor o igual al total para efectivo');
        }
        if ($tipo_pago !== 'Efectivo' && $monto_pagado <= 0) {
            $monto_pagado = $totalVenta;
        }
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
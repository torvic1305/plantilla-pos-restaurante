<?php
include 'data.php';
include 'security.php';

requireMethod('POST');
requirePermission('pos');
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
    if (empty($data['id']) || !is_string($data['tipo_pago'] ?? null) || empty($data['tipo_pago'])) {
        throw new Exception('Datos inválidos');
    }

    $appConfig = getAppConfig();
    if (!in_array($data['tipo_pago'], $appConfig['formas_pago'], true)) {
        throw new Exception('La forma de pago ya no está habilitada. Actualice el POS e inténtelo de nuevo.');
    }

    $id = validateInteger($data['id'], 1);
    if ($id === null) {
        throw new Exception('ID inválido');
    }

    $tipo_pago = sanitizeString($data['tipo_pago'], 50);
    if (empty($tipo_pago)) {
        throw new Exception('Tipo de pago inválido');
    }

    $venta = getVentaById($id);
    if (!$venta || !$venta['suspendida']) {
        throw new Exception('Venta suspendida no encontrada');
    }

    $orden = json_decode($venta['orden'], true);
    if (!is_array($orden)) {
        $orden = [];
    }

    $nuevos_items = isset($data['nuevos_items']) && is_array($data['nuevos_items'])
        ? normalizeMenuOrderItems($data['nuevos_items'])
        : [];
    $orden_actualizada = array_merge($orden, $nuevos_items);

    $propina = validateDecimal($data['propina'] ?? 0, 0, 10000);
    if ($propina === null) {
        throw new Exception('Propina inválida');
    }

    $monto_pagado = floatval($data['monto_pagado'] ?? 0);
    $descuento = validateDecimal($data['descuento'] ?? 0, 0, 100000);
    if ($descuento === null) {
        throw new Exception('Descuento inválido');
    }

    $subtotal = 0;
    foreach ($orden_actualizada as $item) {
        $cantidad = max(1, intval($item['cantidad'] ?? 1));
        $subtotal += floatval($item['precio'] ?? 0) * $cantidad;
    }
    if ($subtotal <= 0 || $descuento > $subtotal) {
        throw new Exception('Total de la orden inválido');
    }

    $baseImponible = max(0, $subtotal - $descuento);
    $impuesto = round($baseImponible * (float) ($appConfig['impuesto'] ?? 0) / 100, 2);
    $total = $baseImponible + $impuesto;
    if ($tipo_pago === 'Efectivo' && $monto_pagado < $total) {
        throw new Exception('El monto pagado debe ser mayor o igual al total para efectivo');
    }
    if ($tipo_pago !== 'Efectivo' && $monto_pagado <= 0) {
        $monto_pagado = $total;
    }

    updateVentaOrden($venta['id'], $orden_actualizada);
    completarVenta($venta['id'], $tipo_pago, $propina, $monto_pagado, $descuento, $impuesto);
    registrarAuditoria($_SESSION['user']['id'], 'COMPLETAR_SUSPENDIDA', 'VENTA', $venta['id'], "Propina: {$propina}, Monto pagado: {$monto_pagado}");

    header('Content-Type: application/json');
    echo json_encode(['status' => 'ok', 'orden' => $orden_actualizada, 'venta_id' => $venta['id']]);
} catch (Exception $e) {
    http_response_code(400);
    jsonResponse('error', $e->getMessage());
}
?>
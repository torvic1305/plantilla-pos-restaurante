<?php
include 'data.php';
include 'security.php';

requireMethod('POST');
requirePermission('pos');

header('Content-Type: application/json; charset=utf-8');

function printerResponse($status, $message, $httpCode = 200)
{
    http_response_code($httpCode);
    echo json_encode(['status' => $status, 'message' => $message], JSON_UNESCAPED_UNICODE);
    exit;
}

function printerText($value, $width = 42)
{
    $value = preg_replace('/[\x00-\x1F\x7F]/u', ' ', strip_tags((string) $value));
    if (function_exists('iconv')) {
        $converted = iconv('UTF-8', 'CP850//TRANSLIT', $value);
        if ($converted !== false) {
            $value = $converted;
        }
    }

    $lines = [];
    foreach (explode("\n", wordwrap($value, $width, "\n", true)) as $line) {
        $lines[] = $line;
    }
    return implode("\n", $lines);
}

function printerMoney($value)
{
    return number_format((float) $value, 2, '.', '');
}

try {
    $data = json_decode(file_get_contents('php://input'), true);
    if (!is_array($data)) {
        printerResponse('error', 'Solicitud de impresión inválida.', 400);
    }
    $csrf = $data['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (!is_string($csrf) || !validateCsrfToken($csrf)) {
        printerResponse('error', 'Token de seguridad inválido.', 403);
    }

    $station = $data['estacion'] ?? '';
    if (!in_array($station, ['recibo', 'cocina', 'barra'], true)) {
        printerResponse('error', 'Estación de impresión inválida.', 400);
    }

    $config = getAppConfig();
    $ip = $config['impresora_' . $station . '_ip'] ?? '';
    $port = validateInteger($config['impresora_' . $station . '_puerto'] ?? null, 1, 65535);
    if (!is_string($ip) || !validatePrinterIPv4($ip) || $port === null) {
        printerResponse('error', 'Configure una IP privada y puerto válidos para esta impresora.', 400);
    }

    $isTest = !empty($data['prueba']);
    $order = $isTest
        ? [['nombre' => 'Prueba de impresión', 'categoria' => '', 'precio' => 0, 'cantidad' => 1]]
        : ($data['orden'] ?? null);
    if (!is_array($order) || count($order) < 1 || count($order) > 100) {
        printerResponse('error', 'La comanda no contiene artículos válidos.', 400);
    }

    $menuCategories = [];
    $itemIds = array_values(array_unique(array_filter(array_map(
        static fn($item) => is_array($item) && (is_string($item['id'] ?? null) || is_int($item['id'] ?? null))
            ? filter_var($item['id'], FILTER_VALIDATE_INT)
            : false,
        $order
    ), static fn($id) => $id !== false && $id > 0)));
    if ($itemIds !== []) {
        $placeholders = implode(',', array_fill(0, count($itemIds), '?'));
        $categoryQuery = $pdo->prepare("SELECT id, categoria FROM menu WHERE id IN ($placeholders)");
        $categoryQuery->execute($itemIds);
        foreach ($categoryQuery->fetchAll(PDO::FETCH_ASSOC) as $menuItem) {
            $menuCategories[(int) $menuItem['id']] = (string) ($menuItem['categoria'] ?? '');
        }
    }

    $items = [];
    foreach ($order as $item) {
        if (!is_array($item) || !is_string($item['nombre'] ?? null) || !is_numeric($item['precio'] ?? null)) {
            printerResponse('error', 'Un artículo de la comanda no es válido.', 400);
        }
        $name = sanitizeString($item['nombre'], 100);
        $categoryInput = is_string($item['categoria'] ?? null) && trim($item['categoria']) !== ''
            ? $item['categoria']
            : ($menuCategories[(int) (is_string($item['id'] ?? null) || is_int($item['id'] ?? null) ? $item['id'] : 0)] ?? '');
        $category = sanitizeString($categoryInput, 80);
        $note = is_string($item['nota'] ?? null) ? sanitizeString($item['nota'], 180) : '';
        $price = validateDecimal($item['precio'], 0, 999999);
        $quantity = validateInteger($item['cantidad'] ?? 1, 1, 999);
        if ($name === '' || $price === null || $quantity === null) {
            printerResponse('error', 'Un artículo de la comanda contiene valores inválidos.', 400);
        }
        $items[] = compact('name', 'category', 'note', 'price', 'quantity');
    }

    if (!$isTest && $station !== 'recibo') {
        $barCategories = $config['impresora_categorias_barra'] ?? [];
        $barCategories = is_array($barCategories)
            ? array_map(static fn($category) => mb_strtolower(trim((string) $category)), $barCategories)
            : [];
        $items = array_values(array_filter($items, static function ($item) use ($station, $barCategories) {
            $category = mb_strtolower(trim($item['category']));
            $isBarItem = $barCategories !== []
                ? in_array($category, $barCategories, true)
                : (preg_match('/bebida|cerveza|vino/i', $item['category'] . ' ' . $item['name']) === 1);
            return $station === 'barra' ? $isBarItem : !$isBarItem;
        }));
        if ($items === []) {
            printerResponse('ok', $station === 'barra'
                ? 'Esta orden no contiene productos asignados a barra.'
                : 'Esta orden no contiene productos asignados a cocina.');
        }
    }

    $restaurantInput = $config['recibo_titulo'] ?? $config['nombre_restaurante'] ?? 'Restaurante POS';
    $restaurant = sanitizeString(is_string($restaurantInput) ? $restaurantInput : 'Restaurante POS', 100);
    $isPreReceipt = $station === 'recibo' && !empty($data['pre_recibo']) && !$isTest;
    $title = $station === 'recibo'
        ? ($isPreReceipt ? 'PRE-RECIBO' : 'RECIBO')
        : ($station === 'cocina' ? 'COMANDA COCINA' : 'COMANDA BARRA');
    $lines = ["\x1B\x40", "\x1B\x74\x02", "\x1B\x61\x01", "\x1B\x45\x01"];
    $lines[] = printerText($restaurant, 42);
    $lines[] = "\x1B\x45\x00";
    $lines[] = printerText($isTest ? 'PRUEBA DE IMPRESION' : $title, 42);
    $lines[] = str_repeat('-', 42);
    if (!$isTest) {
        $mesa = $data['mesa'] ?? '';
        if (!is_string($mesa) && !is_int($mesa)) {
            $mesa = '';
        }
        $mesero = sanitizeString(is_string($data['mesero'] ?? null) ? $data['mesero'] : '', 100);
        $lines[] = printerText('Mesa: ' . ((string) $mesa === '0' ? 'Mostrador' : $mesa), 42);
        if ($mesero !== '') {
            $lines[] = printerText('Mesero: ' . $mesero, 42);
        }
        if (!empty($config['recibo_mostrar_fecha'])) {
            $lines[] = printerText('Fecha: ' . date('d/m/Y H:i:s'), 42);
        }
        if ($station === 'recibo' && isset($data['venta_id'])) {
            $saleId = is_string($data['venta_id']) || is_int($data['venta_id']) ? (string) $data['venta_id'] : '';
            $lines[] = printerText('Venta: #' . sanitizeString($saleId, 20), 42);
        }
        $lines[] = str_repeat('-', 42);
    }

    $subtotal = 0;
    foreach ($items as $item) {
        $subtotal += $item['price'] * $item['quantity'];
        $lines[] = printerText($item['quantity'] . ' x ' . $item['name'], 42);
        if ($station === 'recibo' && !empty($config['recibo_mostrar_precios'])) {
            $lines[] = printerText('   $' . printerMoney($item['price'] * $item['quantity']), 42);
        }
        if ($item['note'] !== '') {
            $lines[] = printerText('   Nota: ' . $item['note'], 42);
        }
    }

    if ($station === 'recibo' && !$isTest) {
        $discount = validateDecimal($data['descuento'] ?? 0, 0, 999999) ?? 0;
        $tax = validateDecimal($data['impuesto'] ?? 0, 0, 999999) ?? 0;
        $total = max(0, $subtotal - $discount + $tax);
        $lines[] = str_repeat('-', 42);
        $lines[] = printerText('Subtotal: $' . printerMoney($subtotal), 42);
        if (!empty($config['recibo_mostrar_descuentos']) && $discount > 0) {
            $lines[] = printerText('Descuento: -$' . printerMoney($discount), 42);
        }
        if (!empty($config['recibo_mostrar_impuestos']) && $tax > 0) {
            $lines[] = printerText('Impuesto: $' . printerMoney($tax), 42);
        }
        $lines[] = "\x1B\x45\x01";
        $lines[] = printerText('TOTAL: $' . printerMoney($total), 42);
        $lines[] = "\x1B\x45\x00";
        if (!$isPreReceipt) {
            $paymentType = is_string($data['tipo_pago'] ?? null) ? sanitizeString($data['tipo_pago'], 40) : '';
            $lines[] = printerText('Pago: ' . $paymentType, 42);
            if ($paymentType === 'Efectivo') {
                $paid = validateDecimal($data['monto_pagado'] ?? 0, 0, 99999999) ?? 0;
                $lines[] = printerText('Pagado: $' . printerMoney($paid), 42);
                $lines[] = printerText('Cambio: $' . printerMoney(max(0, $paid - $total)), 42);
            }
        }
        $footer = sanitizeString($config['recibo_pie'] ?? '', 160);
        if ($footer !== '') {
            $lines[] = str_repeat('-', 42);
            $lines[] = printerText($footer, 42);
        }
    }

    $lines[] = "\n\n\n\x1D\x56\x00";
    $payload = implode("\n", $lines);
    $socket = @stream_socket_client(
        'tcp://' . $ip . ':' . $port,
        $errorNumber,
        $errorMessage,
        3,
        STREAM_CLIENT_CONNECT
    );
    if ($socket === false) {
        error_log("Network printer connection failed ({$station}): {$errorMessage} ({$errorNumber})");
        printerResponse('error', 'No se pudo conectar con la impresora. Verifique la IP, el puerto y que esté encendida.', 502);
    }

    stream_set_timeout($socket, 3);
    $written = 0;
    $payloadLength = strlen($payload);
    while ($written < $payloadLength) {
        $chunk = fwrite($socket, substr($payload, $written));
        if ($chunk === false || $chunk === 0) {
            fclose($socket);
            printerResponse('error', 'La impresora cerró la conexión antes de recibir todo el trabajo.', 502);
        }
        $written += $chunk;
    }
    fclose($socket);

    printerResponse('ok', $isTest
        ? 'Prueba enviada correctamente.'
        : 'Impresión enviada a la estación de ' . $station . '.');
} catch (Throwable $e) {
    error_log('Print job failed: ' . $e->getMessage());
    printerResponse('error', 'No se pudo procesar el trabajo de impresión.', 500);
}
?>

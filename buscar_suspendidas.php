<?php
include 'data.php';
include 'security.php';
requirePermission('pos');
if (!isset($_SESSION['user'])) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'No autorizado']);
    exit;
}
$search = trim($_GET['search'] ?? '');
$ventas = getVentasSuspendidas($search);
foreach ($ventas as &$venta) {
    $orden = json_decode($venta['orden'], true);
    $venta['orden'] = is_array($orden) ? $orden : [];
}
header('Content-Type: application/json');
echo json_encode(['status' => 'ok', 'ventas' => $ventas]);
?>
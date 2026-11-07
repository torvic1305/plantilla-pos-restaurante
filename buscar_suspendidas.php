<?php
include 'data.php';
include 'security.php';
requirePermission('ventas_suspendidas_ver');
if (!isset($_SESSION['user'])) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'No autorizado']);
    exit;
}
$searchInput = $_GET['search'] ?? '';
$search = is_string($searchInput) ? trim($searchInput) : '';
try {
    $ventas = getVentasSuspendidas($search);
    foreach ($ventas as &$venta) {
        $orden = json_decode($venta['orden'], true);
        $venta['orden'] = is_array($orden) ? $orden : [];
    }
    unset($venta);
    header('Content-Type: application/json');
    echo json_encode(['status' => 'ok', 'ventas' => $ventas]);
} catch (Throwable $e) {
    error_log('Unable to load suspended sales: ' . $e->getMessage());
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode(['status' => 'error', 'message' => 'No se pudieron cargar las ventas en espera.']);
}
?>
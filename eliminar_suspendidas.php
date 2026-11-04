<?php
include 'data.php';
include 'security.php';

requireMethod('POST');
requireRole('gerente');

try {
    if (!isset($_POST['csrf_token']) || !validateCsrfToken($_POST['csrf_token'])) {
        throw new Exception('Token de seguridad inválido');
    }

    $stmt = $pdo->prepare("DELETE FROM ventas WHERE suspendida = 1");
    $stmt->execute();
    registrarAuditoria($_SESSION['user']['id'], 'ELIMINAR_SUSPENDIDAS', 'VENTA', null, 'Se eliminaron todas las ventas suspendidas');
    jsonResponse('ok', 'Ventas suspendidas eliminadas');
} catch (Exception $e) {
    http_response_code(400);
    jsonResponse('error', $e->getMessage());
}
?>
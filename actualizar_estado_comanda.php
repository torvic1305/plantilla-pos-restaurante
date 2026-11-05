<?php
include 'data.php';
include 'security.php';

header('Content-Type: application/json; charset=utf-8');
requireMethod('POST');
requirePermission('cocina');

try {
    $data = json_decode(file_get_contents('php://input'), true);
    if (!is_array($data)) {
        throw new Exception('Datos inválidos.');
    }

    $csrfInput = $data['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    $csrfToken = is_string($csrfInput) ? trim($csrfInput) : '';
    if (!validateCsrfToken($csrfToken)) {
        throw new Exception('Token de seguridad inválido.');
    }

    $id = validateInteger($data['id'] ?? null, 1);
    $estadoNuevo = $data['estado'] ?? '';
    $estadosValidos = [
        'preparando' => 'pendiente',
        'lista' => 'preparando',
        'entregada' => 'lista',
    ];
    if ($id === null || !is_string($estadoNuevo) || !isset($estadosValidos[$estadoNuevo])) {
        throw new Exception('Comanda o estado inválido.');
    }

    global $pdo;
    $stmt = $pdo->prepare(
        'UPDATE ventas
         SET estado_cocina = ?, cocina_actualizada = NOW()
         WHERE id = ? AND COALESCE(suspendida, 0) = 0 AND estado_cocina = ?'
    );
    $stmt->execute([$estadoNuevo, $id, $estadosValidos[$estadoNuevo]]);
    if ($stmt->rowCount() !== 1) {
        http_response_code(409);
        jsonResponse('error', 'La comanda ya cambió o dejó de estar activa. Actualice la pantalla.');
        exit;
    }

    registrarAuditoria(
        (int) $_SESSION['user']['id'],
        'ACTUALIZAR_ESTADO_COCINA',
        'VENTA',
        $id,
        'Nuevo estado: ' . $estadoNuevo
    );
    jsonResponse('ok', 'Estado de comanda actualizado.', ['estado' => $estadoNuevo]);
} catch (PDOException $e) {
    error_log('Kitchen order update failed: ' . $e->getMessage());
    http_response_code(500);
    jsonResponse('error', 'No se pudo actualizar la comanda. Inténtelo de nuevo.');
} catch (Exception $e) {
    http_response_code(400);
    jsonResponse('error', $e->getMessage());
}

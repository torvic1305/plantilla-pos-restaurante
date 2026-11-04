<?php
include 'data.php';
include 'security.php';

requireMethod('POST');
requirePermission('menu_editar');

try {
    if (!isset($_POST['csrf_token']) || !validateCsrfToken($_POST['csrf_token'])) {
        throw new Exception('Token de seguridad inválido');
    }

    $id = validateInteger($_POST['id'] ?? 0, 1);
    if ($id === null) {
        throw new Exception('ID inválido');
    }

    $stmt = $pdo->prepare("DELETE FROM menu WHERE id = ?");
    $stmt->execute([$id]);

    registrarAuditoria($_SESSION['user']['id'], 'ELIMINAR_MENU', 'MENU', $id, 'Item eliminado');
    header('Location: menu.php?success=1');
    exit;
} catch (Exception $e) {
    header('Location: menu.php?error=' . urlencode($e->getMessage()));
    exit;
}
?>
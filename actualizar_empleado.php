<?php
include 'data.php';
include 'security.php';
requireMethod('POST');
requirePermission('empleados');

if (!is_string($_POST['csrf_token'] ?? null) || !validateCsrfToken($_POST['csrf_token'])) {
    header('Location: empleados.php?error=El%20token%20de%20seguridad%20no%20es%20v%C3%A1lido');
    exit;
}

$id = validateInteger($_POST['id'] ?? null, 1, PHP_INT_MAX);
if ($id === null) {
    header('Location: empleados.php?error=El%20empleado%20seleccionado%20no%20es%20v%C3%A1lido');
    exit;
}

$stmt = $pdo->prepare('SELECT usuario FROM empleados WHERE id = ?');
$stmt->execute([$id]);
$target = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$target || $target['usuario'] === 'super') {
    header('Location: empleados.php?error=El%20empleado%20seleccionado%20no%20se%20puede%20modificar');
    exit;
}

$submittedPermissions = $_POST['permisos'] ?? [];
if (!is_array($submittedPermissions)) {
    header('Location: empleados.php?error=La%20lista%20de%20permisos%20no%20es%20v%C3%A1lida');
    exit;
}

$permissions = [];
foreach (array_keys(getDefaultPermissions('gerente')) as $key) {
    $permissions[$key] = isset($submittedPermissions[$key]);
}

setPermisos($id, $permissions);
logAction("Actualizó permisos del empleado ID {$id}", $_SESSION['user']['id']);

header('Location: empleados.php?success=1');
exit;
?>
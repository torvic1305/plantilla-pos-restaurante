<?php
include 'data.php';
include 'security.php';
requireMethod('POST');
requirePermission('mermas');

if (!isset($_POST['csrf_token']) || !validateCsrfToken($_POST['csrf_token'])) {
    header('Location: merma.php?error=csrf');
    exit;
}

$item = sanitizeString($_POST['item'] ?? '', 255);
$cantidad = validateInteger($_POST['cantidad'] ?? 0, 1, 999999);
$razon = sanitizeString($_POST['razon'] ?? '', 255);

if ($item === '' || $cantidad === null || $razon === '') {
    header('Location: merma.php?error=invalid');
    exit;
}

addMerma($item, $cantidad, $razon);
logAction("Registró merma: {$item}", $_SESSION['user']['id']);
header('Location: merma.php');
exit;
?>
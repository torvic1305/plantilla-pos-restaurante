<?php
include 'data.php';
include 'security.php';
requireMethod('POST');
requirePermission('promociones');

if (!isset($_POST['csrf_token']) || !validateCsrfToken($_POST['csrf_token'])) {
    header('Location: promociones.php?error=csrf');
    exit;
}

$nombre = sanitizeString($_POST['nombre'] ?? '', 255);
$descuento = validateDecimal($_POST['descuento'] ?? 0, 0, 100);
$desde = sanitizeString($_POST['desde'] ?? '', 19);
$hasta = sanitizeString($_POST['hasta'] ?? '', 19);

if ($nombre === '' || $descuento === null || strtotime($desde) === false || strtotime($hasta) === false) {
    header('Location: promociones.php?error=invalid');
    exit;
}

addPromocion($nombre, $descuento, $desde, $hasta);
logAction("Agregó promoción: {$nombre}", $_SESSION['user']['id']);
header('Location: promociones.php');
exit;
?>
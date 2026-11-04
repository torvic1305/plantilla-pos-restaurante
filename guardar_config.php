<?php
include 'data.php';
include 'security.php';
requireMethod('POST');
requirePermission('config');

if (!isset($_POST['csrf_token']) || !validateCsrfToken($_POST['csrf_token'])) {
    header('Location: pos.php?error=csrf');
    exit;
}

$config = getAppConfig();
$config['mesas'] = validateInteger($_POST['mesas'] ?? $config['mesas'], 1, 1000) ?? $config['mesas'];
saveAppConfig($config);
header('Location: pos.php');
exit;
?>
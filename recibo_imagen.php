<?php
include 'data.php';

if (!isset($_SESSION['user']) || !is_array($_SESSION['user']['permisos'] ?? null)) {
    http_response_code(403);
    exit;
}
$permissions = $_SESSION['user']['permisos'];
if (empty($permissions['config']) && empty($permissions['pos'])) {
    http_response_code(403);
    exit;
}

$type = $_GET['tipo'] ?? '';
$imageKey = $type === 'logo' ? 'recibo_logo_imagen' : ($type === 'qr' ? 'recibo_qr_imagen' : null);
if ($imageKey === null) {
    http_response_code(404);
    exit;
}
$filename = getAppConfig()[$imageKey] ?? null;
if (!is_string($filename)) {
    http_response_code(404);
    exit;
}
$path = __DIR__ . '/uploads/recibos/' . $filename;
if (!is_file($path)) {
    http_response_code(404);
    exit;
}
$mime = (new finfo(FILEINFO_MIME_TYPE))->file($path);
if (!in_array($mime, ['image/png', 'image/jpeg', 'image/webp'], true)) {
    http_response_code(404);
    exit;
}

header('Content-Type: ' . $mime);
header('X-Content-Type-Options: nosniff');
header('Cache-Control: private, max-age=300');
readfile($path);

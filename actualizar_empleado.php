<?php
include 'data.php';
include 'security.php';
requireMethod('POST');
requirePermission('empleados');

if (isset($_POST['id'])) {
    if (!isset($_POST['csrf_token']) || !validateCsrfToken($_POST['csrf_token'])) {
        header('Location: empleados.php?error=csrf');
        exit;
    }

    $id = validateInteger($_POST['id'], 1, PHP_INT_MAX);
    if ($id === null) {
        header('Location: empleados.php?error=invalid');
        exit;
    }

    $permisos = $_POST['permisos'] ?? [];
    $config = $_POST['config'] ?? [];

    $permisos_bool = [];
    $permisos_keys = ['pos', 'menu_ver', 'menu_editar', 'empleados', 'informes', 'mermas', 'promociones', 'config', 'cocina', 'eliminar_suspendidas'];
    foreach ($permisos_keys as $key) {
        $permisos_bool[$key] = isset($permisos[$key]);
    }

    $config_clean = [
        'idioma' => in_array($config['idioma'] ?? 'es', ['es', 'en'], true) ? $config['idioma'] : 'es',
        'tema' => in_array($config['tema'] ?? 'claro', ['claro', 'oscuro'], true) ? $config['tema'] : 'claro',
        'notificaciones' => isset($config['notificaciones']),
        'horario' => sanitizeString($config['horario'] ?? '08:00-18:00', 50),
        'salario' => validateDecimal($config['salario'] ?? 0, 0, 999999) ?? 0
    ];

    setPermisos($id, $permisos_bool);
    setConfiguraciones($id, $config_clean);

    logAction("Actualizó permisos/config de empleado ID {$id}", $_SESSION['user']['id']);
}

header('Location: empleados.php');
exit;
?>
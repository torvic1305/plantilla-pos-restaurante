<?php
include 'data.php';
include 'security.php';

requireMethod('POST');
requirePermission('empleados');
$currentRoleQuery = $pdo->prepare('SELECT rol FROM empleados WHERE id = ?');
$currentRoleQuery->execute([(int) $_SESSION['user']['id']]);
if ($currentRoleQuery->fetchColumn() !== 'gerente') {
    http_response_code(403);
    exit('Solo un gerente puede cambiar el estado de los empleados.');
}

$redirect = static function ($parameter, $value) {
    header('Location: empleados.php?' . rawurlencode($parameter) . '=' . rawurlencode($value));
    exit;
};

if (!is_string($_POST['csrf_token'] ?? null) || !validateCsrfToken($_POST['csrf_token'])) {
    $redirect('error', 'El token de seguridad no es válido.');
}

$employeeId = validateInteger($_POST['id'] ?? null, 1, PHP_INT_MAX);
$action = is_string($_POST['accion'] ?? null) ? $_POST['accion'] : '';
if ($employeeId === null || !in_array($action, ['suspender', 'reactivar', 'eliminar', 'restaurar'], true)) {
    $redirect('error', 'La acción o el empleado seleccionado no son válidos.');
}

if ($employeeId === (int) $_SESSION['user']['id']) {
    $redirect('error', 'No puedes suspender, archivar o restaurar tu propia cuenta.');
}

try {
    $pdo->beginTransaction();
    $employeeQuery = $pdo->prepare(
        'SELECT id, usuario, rol, suspendido, eliminado_en
         FROM empleados WHERE id = ? FOR UPDATE'
    );
    $employeeQuery->execute([$employeeId]);
    $employee = $employeeQuery->fetch(PDO::FETCH_ASSOC);
    if (!$employee || $employee['usuario'] === 'super') {
        throw new RuntimeException('El empleado no existe o no se puede administrar.');
    }

    $activeActions = ['suspender', 'eliminar'];
    $isActive = $employee['eliminado_en'] === null && empty($employee['suspendido']);
    if (in_array($action, $activeActions, true) && !$isActive) {
        throw new RuntimeException('El empleado ya no está activo.');
    }
    if ($action === 'reactivar' && ($employee['eliminado_en'] !== null || empty($employee['suspendido']))) {
        throw new RuntimeException('El empleado no está suspendido.');
    }
    if ($action === 'restaurar' && $employee['eliminado_en'] === null) {
        throw new RuntimeException('El empleado no está archivado.');
    }

    if (in_array($action, $activeActions, true) && $employee['rol'] === 'gerente') {
        $activeManagers = $pdo->query(
            'SELECT id FROM empleados
             WHERE rol = \'gerente\' AND suspendido = 0 AND eliminado_en IS NULL
             FOR UPDATE'
        )->fetchAll(PDO::FETCH_COLUMN);
        $managerCount = count($activeManagers);
        if ($managerCount <= 1) {
            throw new RuntimeException('No puedes suspender ni archivar al último gerente activo.');
        }
    }

    switch ($action) {
        case 'suspender':
            $reason = is_string($_POST['motivo'] ?? null)
                ? sanitizeString($_POST['motivo'], 255)
                : '';
            if (!in_array($reason, ['Vacaciones', 'Suspensión', 'Permiso temporal'], true)) {
                throw new RuntimeException('Selecciona un motivo válido para la suspensión.');
            }
            $update = $pdo->prepare(
                'UPDATE empleados SET suspendido = 1, motivo_suspension = ? WHERE id = ?'
            );
            $update->execute([$reason, $employeeId]);
            $success = 'suspendido';
            $auditAction = 'SUSPENDER_EMPLEADO';
            $auditDescription = "Empleado {$employee['usuario']}: {$reason}";
            break;

        case 'reactivar':
            $update = $pdo->prepare(
                'UPDATE empleados SET suspendido = 0, motivo_suspension = NULL WHERE id = ?'
            );
            $update->execute([$employeeId]);
            $success = 'reactivado';
            $auditAction = 'REACTIVAR_EMPLEADO';
            $auditDescription = "Empleado {$employee['usuario']}";
            break;

        case 'eliminar':
            $update = $pdo->prepare(
                'UPDATE empleados
                 SET eliminado_en = CURRENT_TIMESTAMP, suspendido = 0, motivo_suspension = NULL
                 WHERE id = ?'
            );
            $update->execute([$employeeId]);
            $success = 'eliminado';
            $auditAction = 'ARCHIVAR_EMPLEADO';
            $auditDescription = "Empleado {$employee['usuario']}";
            break;

        case 'restaurar':
            $update = $pdo->prepare(
                'UPDATE empleados SET eliminado_en = NULL, suspendido = 0, motivo_suspension = NULL WHERE id = ?'
            );
            $update->execute([$employeeId]);
            $success = 'restaurado';
            $auditAction = 'RESTAURAR_EMPLEADO';
            $auditDescription = "Empleado {$employee['usuario']}";
            break;
    }

    $pdo->commit();
    registrarAuditoria(
        (int) $_SESSION['user']['id'],
        $auditAction,
        'EMPLEADO',
        $employeeId,
        $auditDescription
    );
    $redirect('success', $success);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('Employee lifecycle update failed: ' . $e->getMessage());
    $redirect('error', $e instanceof RuntimeException
        ? $e->getMessage()
        : 'No se pudo actualizar el estado del empleado.');
}
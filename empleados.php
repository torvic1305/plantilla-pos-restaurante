<?php
include 'data.php';
include 'security.php';
requirePermission('empleados');

$permissionGroups = getPermissionDefinitions();
$employees = array_values(array_filter(getEmpleados(), static function ($employee) {
    return $employee['usuario'] !== 'super';
}));
$deletedEmployees = array_values(array_filter(getEmpleadosEliminados(), static function ($employee) {
    return $employee['usuario'] !== 'super';
}));
$canManageStaff = ($_SESSION['user']['rol'] ?? '') === 'gerente';
$showDeleted = ($_GET['vista'] ?? '') === 'eliminados';
$escape = static fn($value) => htmlspecialchars((string) $value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
$errorMessage = isset($_GET['error']) ? sanitizeString($_GET['error'], 180) : '';
$successAction = is_string($_GET['success'] ?? null) ? $_GET['success'] : '';
$successMessages = [
    '1' => 'Permisos guardados correctamente.',
    'suspendido' => 'El empleado quedó suspendido y no podrá iniciar sesión.',
    'reactivado' => 'El acceso del empleado fue reactivado.',
    'eliminado' => 'El empleado fue archivado. Sus ventas históricas se conservaron.',
    'restaurado' => 'El empleado fue restaurado.',
];
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Empleados y permisos - Restaurante POS</title>
    <link rel="stylesheet" href="style.php">
</head>

<body>
    <header>
        <h1>Empleados y permisos</h1>
        <a href="dashboard.php">Volver al panel</a>
    </header>
    <main class="employees-page">
        <div class="page-heading">
            <div>
                <h2>Acceso del personal</h2>
                <p>Administra accesos, descansos y bajas del personal sin perder el historial de ventas.</p>
            </div>
        </div>

        <?php if ($errorMessage !== ''): ?>
            <div class="settings-message error" role="alert"><?= $escape($errorMessage) ?></div>
        <?php endif; ?>
        <?php if (isset($successMessages[$successAction])): ?>
            <div class="settings-message success" role="status"><?= $escape($successMessages[$successAction]) ?></div>
        <?php endif; ?>

        <div class="employee-summary-grid">
            <section class="employee-summary-card">
                <span class="employee-summary-icon" aria-hidden="true">
                    <svg viewBox="0 0 24 24"><path d="M16 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2M10 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8ZM20 8v6m3-3h-6"/></svg>
                </span>
                <span><strong><?= count($employees) ?></strong><small>Personal activo o suspendido</small></span>
            </section>
            <section class="employee-summary-card">
                <span class="employee-summary-icon employee-summary-icon-muted" aria-hidden="true">
                    <svg viewBox="0 0 24 24"><path d="M3 6h18M8 6V4h8v2m3 0-1 14H6L5 6m5 4v6m4-6v6"/></svg>
                </span>
                <span><strong><?= count($deletedEmployees) ?></strong><small>Empleados archivados</small></span>
            </section>
        </div>

        <nav class="employee-tabs" aria-label="Vistas de empleados">
            <a href="empleados.php" class="<?= !$showDeleted ? 'is-active' : '' ?>" <?= !$showDeleted ? 'aria-current="page"' : '' ?>>Personal <span><?= count($employees) ?></span></a>
            <a href="empleados.php?vista=eliminados" class="<?= $showDeleted ? 'is-active' : '' ?>" <?= $showDeleted ? 'aria-current="page"' : '' ?>>Archivados <span><?= count($deletedEmployees) ?></span></a>
        </nav>

        <?php if (!$showDeleted): ?>
        <section class="employees-card employee-list-card">
            <div class="employee-card-heading">
                <div>
                    <h2>Personal</h2>
                    <p>Suspende temporalmente el acceso o archiva al personal que ya no trabaja aquí.</p>
                </div>
            </div>
            <div class="table-scroll">
                <table class="employees-table">
                    <thead>
                        <tr><th>Empleado</th><th>Usuario</th><th>Rol</th><th>Estado</th><th>Permisos</th><th>Acciones</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach ($employees as $employee): ?>
                            <?php
                            $employeePermissions = getPermisos($employee['id']);
                            $enabledPermissions = count(array_filter($employeePermissions));
                            $permissionCount = count($employeePermissions);
                            ?>
                            <tr>
                                <td data-label="Empleado"><strong><?= $escape($employee['nombre']) ?></strong></td>
                                <td data-label="Usuario"><?= $escape($employee['usuario']) ?></td>
                                <td data-label="Rol"><span class="role-badge"><?= $escape(ucfirst($employee['rol'])) ?></span></td>
                                <td data-label="Estado">
                                    <?php if (!empty($employee['suspendido'])): ?>
                                        <span class="employee-status employee-status-suspended">Suspendido</span>
                                        <?php if (!empty($employee['motivo_suspension'])): ?><small class="employee-status-note"><?= $escape($employee['motivo_suspension']) ?></small><?php endif; ?>
                                    <?php else: ?>
                                        <span class="employee-status employee-status-active">Activo</span>
                                    <?php endif; ?>
                                </td>
                                <td data-label="Permisos"><?= $enabledPermissions ?> de <?= $permissionCount ?></td>
                                <td class="employee-actions">
                                    <button type="button" class="button-secondary employee-action-button" aria-expanded="false" aria-controls="permissions-<?= (int) $employee['id'] ?>" onclick="togglePermissions(this, <?= (int) $employee['id'] ?>)" title="Editar permisos"><span aria-hidden="true">⚙️</span> Permisos</button>
                                    <?php if ($canManageStaff && $employee['usuario'] !== 'super' && (int) $employee['id'] !== (int) $_SESSION['user']['id']): ?>
                                        <?php if (!empty($employee['suspendido'])): ?>
                                            <form action="gestionar_empleado.php" method="post">
                                                <input type="hidden" name="csrf_token" value="<?= $escape(getCsrfToken()) ?>">
                                                <input type="hidden" name="id" value="<?= (int) $employee['id'] ?>">
                                                <input type="hidden" name="accion" value="reactivar">
                                                <button type="submit" class="button-secondary employee-action-button" title="Reactivar empleado"><span aria-hidden="true">▶️</span> Reactivar</button>
                                            </form>
                                        <?php else: ?>
                                            <details class="employee-suspend-details">
                                                <summary class="button-secondary employee-action-button" title="Suspender temporalmente"><span aria-hidden="true">⏸️</span> Suspender</summary>
                                                <form action="gestionar_empleado.php" method="post" class="employee-inline-form">
                                                    <input type="hidden" name="csrf_token" value="<?= $escape(getCsrfToken()) ?>">
                                                    <input type="hidden" name="id" value="<?= (int) $employee['id'] ?>">
                                                    <input type="hidden" name="accion" value="suspender">
                                                    <label>Motivo
                                                        <select name="motivo" required>
                                                            <option value="Vacaciones">Vacaciones</option>
                                                            <option value="Suspensión">Suspensión</option>
                                                            <option value="Permiso temporal">Permiso temporal</option>
                                                        </select>
                                                    </label>
                                                    <button type="submit" class="button-warning">Confirmar suspensión</button>
                                                </form>
                                            </details>
                                        <?php endif; ?>
                                        <form action="gestionar_empleado.php" method="post" onsubmit="return confirm('¿Archivar a este empleado? No podrá iniciar sesión. Sus ventas y reportes históricos se conservarán.')">
                                            <input type="hidden" name="csrf_token" value="<?= $escape(getCsrfToken()) ?>">
                                            <input type="hidden" name="id" value="<?= (int) $employee['id'] ?>">
                                            <input type="hidden" name="accion" value="eliminar">
                                            <button type="submit" class="button-danger employee-action-button" title="Archivar empleado"><span aria-hidden="true">🗑️</span> Archivar</button>
                                        </form>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <tr id="permissions-<?= (int) $employee['id'] ?>" class="permission-row" hidden>
                                <td colspan="6">
                                    <form action="actualizar_empleado.php" method="post">
                                        <input type="hidden" name="csrf_token" value="<?= $escape(getCsrfToken()) ?>">
                                        <input type="hidden" name="id" value="<?= (int) $employee['id'] ?>">
                                        <div class="permission-editor-heading">
                                            <div>
                                                <h3>Permisos de <?= $escape($employee['nombre']) ?></h3>
                                                <p>Los permisos se aplican de inmediato, incluso en sesiones abiertas.</p>
                                            </div>
                                        </div>
                                        <div class="permission-groups">
                                            <?php foreach ($permissionGroups as $groupName => $permissions): ?>
                                                <fieldset class="permission-group">
                                                    <legend><?= $escape($groupName) ?></legend>
                                                    <?php foreach ($permissions as $key => $label): ?>
                                                        <label class="permission-option">
                                                            <input type="checkbox" name="permisos[<?= $escape($key) ?>]" value="1" <?= !empty($employeePermissions[$key]) ? 'checked' : '' ?>>
                                                            <span><?= $escape($label) ?></span>
                                                        </label>
                                                    <?php endforeach; ?>
                                                </fieldset>
                                            <?php endforeach; ?>
                                        </div>
                                        <div class="settings-actions">
                                            <button type="submit">Guardar permisos</button>
                                            <button type="button" class="button-secondary" onclick="togglePermissions(this, <?= (int) $employee['id'] ?>)">Cancelar</button>
                                        </div>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if ($employees === []): ?>
                            <tr><td colspan="6" class="employee-empty-state">No hay empleados activos registrados.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>
        <?php else: ?>
            <section class="employees-card employee-list-card">
                <div class="employee-card-heading">
                    <div>
                        <h2>Empleados archivados</h2>
                        <p>El archivo es reversible y mantiene intactas las ventas y los reportes históricos.</p>
                    </div>
                </div>
                <div class="table-scroll">
                    <table class="employees-table">
                        <thead><tr><th>Empleado</th><th>Usuario</th><th>Rol</th><th>Archivado</th><th></th></tr></thead>
                        <tbody>
                            <?php foreach ($deletedEmployees as $employee): ?>
                                <tr>
                                    <td data-label="Empleado"><strong><?= $escape($employee['nombre']) ?></strong></td>
                                    <td data-label="Usuario"><?= $escape($employee['usuario']) ?></td>
                                    <td data-label="Rol"><span class="role-badge"><?= $escape(ucfirst($employee['rol'])) ?></span></td>
                                    <td data-label="Archivado"><?= $escape($employee['eliminado_en'] ?? '') ?></td>
                                    <td>
                                        <?php if ($canManageStaff): ?>
                                            <form action="gestionar_empleado.php" method="post" onsubmit="return confirm('¿Restaurar a este empleado y permitirle volver a iniciar sesión?')">
                                                <input type="hidden" name="csrf_token" value="<?= $escape(getCsrfToken()) ?>">
                                                <input type="hidden" name="id" value="<?= (int) $employee['id'] ?>">
                                                <input type="hidden" name="accion" value="restaurar">
                                                <button type="submit" class="button-secondary employee-action-button" title="Restaurar empleado"><span aria-hidden="true">♻️</span> Restaurar</button>
                                            </form>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            <?php if ($deletedEmployees === []): ?>
                                <tr><td colspan="5" class="employee-empty-state">No hay empleados archivados.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </section>
        <?php endif; ?>

        <?php if ($canManageStaff && !$showDeleted): ?>
            <section class="employees-card">
                <h2 class="employee-add-heading">
                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M16 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2M10 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8ZM20 8v6m3-3h-6"/></svg>
                    Agregar empleado
                </h2>
                <p>Se asignan permisos iniciales de acuerdo con el rol; puedes ajustarlos después.</p>
                <form action="agregar_empleado.php" method="post" class="new-employee-form">
                    <input type="hidden" name="csrf_token" value="<?= $escape(getCsrfToken()) ?>">
                    <label>Nombre
                        <input type="text" name="nombre" maxlength="100" required>
                    </label>
                    <label>Usuario
                        <input type="text" name="usuario" minlength="3" maxlength="100" required>
                    </label>
                    <label>Contraseña
                        <input type="password" name="password" minlength="8" autocomplete="new-password" required>
                    </label>
                    <label>Rol
                        <select name="rol" required>
                            <option value="mesero">Mesero</option>
                            <option value="cocinero">Cocinero</option>
                            <option value="gerente">Gerente</option>
                        </select>
                    </label>
                    <button type="submit">Crear empleado</button>
                </form>
            </section>
        <?php endif; ?>
    </main>
    <script>
        function togglePermissions(button, id) {
            const panel = document.getElementById('permissions-' + id);
            panel.hidden = !panel.hidden;
            const trigger = document.querySelector('[aria-controls="permissions-' + id + '"]');
            trigger.setAttribute('aria-expanded', String(!panel.hidden));
        }
    </script>
</body>

</html>

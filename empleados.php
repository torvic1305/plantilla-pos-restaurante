<?php
include 'data.php';
include 'security.php';
requirePermission('empleados');

$permissionGroups = getPermissionDefinitions();
$employees = array_filter(getEmpleados(), static function ($employee) {
    return $employee['usuario'] !== 'super';
});
$escape = static fn($value) => htmlspecialchars((string) $value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
$errorMessage = isset($_GET['error']) ? sanitizeString($_GET['error'], 180) : '';
$success = ($_GET['success'] ?? '') === '1';
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
                <p>Asigna permisos por área. Cada permiso se verifica al abrir las páginas y al ejecutar acciones.</p>
            </div>
        </div>

        <?php if ($errorMessage !== ''): ?>
            <div class="settings-message error" role="alert"><?= $escape($errorMessage) ?></div>
        <?php endif; ?>
        <?php if ($success): ?>
            <div class="settings-message success" role="status">Permisos guardados correctamente.</div>
        <?php endif; ?>

        <section class="employees-card">
            <div class="table-scroll">
                <table class="employees-table">
                    <thead>
                        <tr><th>Empleado</th><th>Usuario</th><th>Rol</th><th>Acceso asignado</th><th></th></tr>
                    </thead>
                    <tbody>
                        <?php foreach ($employees as $employee): ?>
                            <?php
                            $employeePermissions = getPermisos($employee['id']);
                            $enabledPermissions = count(array_filter($employeePermissions));
                            $permissionCount = count($employeePermissions);
                            ?>
                            <tr>
                                <td><strong><?= $escape($employee['nombre']) ?></strong></td>
                                <td><?= $escape($employee['usuario']) ?></td>
                                <td><span class="role-badge"><?= $escape(ucfirst($employee['rol'])) ?></span></td>
                                <td><?= $enabledPermissions ?> de <?= $permissionCount ?> permisos</td>
                                <td><button type="button" class="button-secondary" aria-expanded="false" aria-controls="permissions-<?= (int) $employee['id'] ?>" onclick="togglePermissions(this, <?= (int) $employee['id'] ?>)">Administrar permisos</button></td>
                            </tr>
                            <tr id="permissions-<?= (int) $employee['id'] ?>" class="permission-row" hidden>
                                <td colspan="5">
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
                    </tbody>
                </table>
            </div>
        </section>

        <?php if ($_SESSION['user']['rol'] === 'gerente'): ?>
            <section class="employees-card">
                <h2>Agregar empleado</h2>
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

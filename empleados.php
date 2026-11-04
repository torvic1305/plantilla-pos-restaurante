<?php
include 'data.php';
include 'security.php';
if (!isset($_SESSION['user']) || $_SESSION['user']['rol'] != 'gerente') {
    header('Location: dashboard.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Empleados - Restaurante POS</title>
    <link rel="stylesheet" href="style.php">
</head>

<body>
    <header>
        <h1>Gestión de Empleados</h1>
        <a href="dashboard.php">Volver</a>
    </header>
    <main>
        <h2>Empleados</h2>
        <table border="1" style="width:100%; border-collapse:collapse;">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Nombre</th>
                    <th>Usuario</th>
                    <th>Rol</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php $empleados = array_filter(getEmpleados(), function ($e) {
                    return $e['usuario'] !== 'super';
                });
                foreach ($empleados as $emp) {
                    $permisos = getPermisos($emp['id']);
                    $config = getConfiguraciones($emp['id']);
                    ?>
                    <tr>
                        <td><?php echo $emp['id']; ?></td>
                        <td><?php echo htmlspecialchars($emp['nombre']); ?></td>
                        <td><?php echo htmlspecialchars($emp['usuario']); ?></td>
                        <td><?php echo htmlspecialchars($emp['rol']); ?></td>
                        <td>
                            <button onclick="editarEmpleado(<?php echo $emp['id']; ?>)">Editar Permisos/Config</button>
                        </td>
                    </tr>
                    <tr id="edit-<?php echo $emp['id']; ?>" style="display:none;">
                        <td colspan="5">
                            <h3>Editar <?php echo htmlspecialchars($emp['nombre']); ?></h3>
                            <form action="actualizar_empleado.php" method="post">
                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(getCsrfToken()) ?>">
                                <input type="hidden" name="id" value="<?php echo $emp['id']; ?>">
                                <h4>Permisos</h4>
                                <label><input type="checkbox" name="permisos[pos]" <?php echo $permisos['pos'] ? 'checked' : ''; ?>> Acceso a POS</label><br>
                                <label><input type="checkbox" name="permisos[menu_ver]" <?php echo $permisos['menu_ver'] ? 'checked' : ''; ?>> Ver Menú</label><br>
                                <label><input type="checkbox" name="permisos[menu_editar]" <?php echo $permisos['menu_editar'] ? 'checked' : ''; ?>> Editar Menú</label><br>
                                <label><input type="checkbox" name="permisos[empleados]" <?php echo $permisos['empleados'] ? 'checked' : ''; ?>> Gestionar Empleados</label><br>
                                <label><input type="checkbox" name="permisos[informes]" <?php echo $permisos['informes'] ? 'checked' : ''; ?>> Ver Informes</label><br>
                                <label><input type="checkbox" name="permisos[mermas]" <?php echo $permisos['mermas'] ? 'checked' : ''; ?>> Gestionar Mermas</label><br>
                                <label><input type="checkbox" name="permisos[promociones]" <?php echo $permisos['promociones'] ? 'checked' : ''; ?>> Gestionar Promociones</label><br>
                                <label><input type="checkbox" name="permisos[config]" <?php echo $permisos['config'] ? 'checked' : ''; ?>> Acceso a Configuración</label><br>
                                <label><input type="checkbox" name="permisos[cocina]" <?php echo $permisos['cocina'] ? 'checked' : ''; ?>> Acceso a Cocina</label><br>
                                <label><input type="checkbox" name="permisos[eliminar_suspendidas]" <?php echo $permisos['eliminar_suspendidas'] ? 'checked' : ''; ?>> Eliminar Ventas
                                    Suspendidas</label><br>
                                <h4>Configuraciones</h4>
                                <label>Idioma: <select name="config[idioma]">
                                        <option value="es" <?php echo $config['idioma'] == 'es' ? 'selected' : ''; ?>>Español
                                        </option>
                                        <option value="en" <?php echo $config['idioma'] == 'en' ? 'selected' : ''; ?>>Inglés
                                        </option>
                                    </select></label><br>
                                <label>Tema: <select name="config[tema]">
                                        <option value="claro" <?php echo $config['tema'] == 'claro' ? 'selected' : ''; ?>>
                                            Claro</option>
                                        <option value="oscuro" <?php echo $config['tema'] == 'oscuro' ? 'selected' : ''; ?>>
                                            Oscuro</option>
                                    </select></label><br>
                                <label><input type="checkbox" name="config[notificaciones]" <?php echo $config['notificaciones'] ? 'checked' : ''; ?>> Notificaciones</label><br>
                                <label>Horario: <input type="text" name="config[horario]"
                                        value="<?php echo htmlspecialchars($config['horario']); ?>"></label><br>
                                <label>Salario: <input type="number" step="0.01" name="config[salario]"
                                        value="<?php echo $config['salario']; ?>"></label><br>
                                <button type="submit">Guardar</button>
                                <button type="button" onclick="cerrarEditar(<?php echo $emp['id']; ?>)">Cancelar</button>
                            </form>
                        </td>
                    </tr>
                <?php } ?>
            </tbody>
        </table>
        <h2>Agregar Empleado</h2>
        <form action="agregar_empleado.php" method="post">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(getCsrfToken()) ?>">
            <input type="text" name="nombre" placeholder="Nombre" required>
            <input type="text" name="usuario" placeholder="Usuario" required>
            <input type="password" name="password" placeholder="Contraseña" required>
            <select name="rol">
                <option value="mesero">Mesero</option>
                <option value="cocinero">Cocinero</option>
                <option value="gerente">Gerente</option>
            </select>
            <button type="submit">Agregar</button>
        </form>
    </main>
    <script src="2.js"></script>
    <script>
        function editarEmpleado(id) {
            document.getElementById('edit-' + id).style.display = 'table-row';
        }
        function cerrarEditar(id) {
            document.getElementById('edit-' + id).style.display = 'none';
        }
    </script>
</body>

</html>
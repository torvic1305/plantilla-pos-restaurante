<?php
include 'data.php';
include 'security.php';
if (!isset($_SESSION['user'])) {
    header('Location: index.php');
    exit;
}

requirePermission('menu_editar');

$message = '';
$editCliente = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (!isset($_POST['csrf_token']) || !validateCsrfToken($_POST['csrf_token'])) {
            throw new Exception('Token de seguridad inválido');
        }

        $action = $_POST['action'] ?? 'add';
        $id = validateInteger($_POST['id'] ?? 0, 0);
        $nombre = sanitizeString($_POST['nombre'] ?? '', 100);
        $telefono = sanitizeString($_POST['telefono'] ?? '', 20);
        $email = sanitizeString($_POST['email'] ?? '', 100);
        $direccion = sanitizeString($_POST['direccion'] ?? '', 255);

        if ($action === 'save') {
            if (empty($nombre)) {
                throw new Exception('Nombre es requerido');
            }
            if (!empty($email) && validateEmail($email) === null) {
                throw new Exception('Email inválido');
            }
            if (!empty($telefono) && validatePhone($telefono) === null) {
                throw new Exception('Teléfono inválido');
            }

            if ($id > 0) {
                updateCliente($id, $nombre, $telefono, $email, $direccion);
                $message = 'Cliente actualizado correctamente.';
                registrarAuditoria($_SESSION['user']['id'], 'EDITAR_CLIENTE', 'CLIENTE', $id, $nombre);
            } else {
                addCliente($nombre, $telefono, $email, $direccion);
                $message = 'Cliente agregado correctamente.';
                registrarAuditoria($_SESSION['user']['id'], 'CREAR_CLIENTE', 'CLIENTE', null, $nombre);
            }
        } elseif ($action === 'delete' && $id > 0) {
            deleteCliente($id);
            $message = 'Cliente eliminado correctamente.';
            registrarAuditoria($_SESSION['user']['id'], 'ELIMINAR_CLIENTE', 'CLIENTE', $id, 'Cliente eliminado');
        }

        header('Location: clientes.php?success=1');
        exit;
    } catch (Exception $e) {
        $message = $e->getMessage();
    }
}

if (isset($_GET['edit'])) {
    $editCliente = getClienteById(intval($_GET['edit']));
}

$clientes = getClientes();
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Clientes - Restaurante POS</title>
    <link rel="stylesheet" href="style.php">
</head>

<body>
    <header>
        <h1>Clientes</h1>
        <a href="dashboard.php">Volver</a>
    </header>
    <main>
        <?php if ($message): ?>
            <div
                style="background: #e7f7e7; color: #155724; border: 1px solid #c3e6cb; padding: 12px; border-radius: 6px; margin-bottom: 18px;">
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>

        <section
            style="margin-bottom: 30px; background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.08);">
            <h2><?php echo $editCliente ? 'Editar cliente' : 'Agregar nuevo cliente'; ?></h2>
            <form method="post">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(getCsrfToken()) ?>">
                <input type="hidden" name="action" value="save">
                <input type="hidden" name="id" value="<?php echo intval($editCliente['id'] ?? 0); ?>">
                <label>Nombre:<br>
                    <input type="text" name="nombre" required style="width:100%;"
                        value="<?php echo htmlspecialchars($editCliente['nombre'] ?? ''); ?>">
                </label><br><br>
                <label>Teléfono:<br>
                    <input type="text" name="telefono" style="width:100%;"
                        value="<?php echo htmlspecialchars($editCliente['telefono'] ?? ''); ?>">
                </label><br><br>
                <label>Email:<br>
                    <input type="email" name="email" style="width:100%;"
                        value="<?php echo htmlspecialchars($editCliente['email'] ?? ''); ?>">
                </label><br><br>
                <label>Dirección:<br>
                    <textarea name="direccion" style="width:100%;"
                        rows="3"><?php echo htmlspecialchars($editCliente['direccion'] ?? ''); ?></textarea>
                </label><br><br>
                <button type="submit"
                    style="padding: 10px 20px; background: #007bff; color: white; border: none; border-radius: 5px; cursor: pointer;">
                    <?php echo $editCliente ? 'Actualizar cliente' : 'Agregar cliente'; ?></button>
                <?php if ($editCliente): ?>
                    <a href="clientes.php" style="margin-left: 12px; color: #555;">Cancelar</a>
                <?php endif; ?>
            </form>
        </section>

        <section>
            <h2>Lista de clientes</h2>
            <?php if (empty($clientes)): ?>
                <p>No hay clientes registrados.</p>
            <?php else: ?>
                <table style="width:100%; border-collapse: collapse;">
                    <thead>
                        <tr style="background: #f2f2f2;">
                            <th style="padding: 10px; text-align:left;">Nombre</th>
                            <th style="padding: 10px; text-align:left;">Teléfono</th>
                            <th style="padding: 10px; text-align:left;">Email</th>
                            <th style="padding: 10px; text-align:left;">Dirección</th>
                            <th style="padding: 10px; text-align:center;">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($clientes as $cliente): ?>
                            <tr style="border-bottom: 1px solid #ddd;">
                                <td style="padding: 10px;"><?php echo htmlspecialchars($cliente['nombre']); ?></td>
                                <td style="padding: 10px;"><?php echo htmlspecialchars($cliente['telefono']); ?></td>
                                <td style="padding: 10px;"><?php echo htmlspecialchars($cliente['email']); ?></td>
                                <td style="padding: 10px;"><?php echo nl2br(htmlspecialchars($cliente['direccion'])); ?></td>
                                <td style="padding: 10px; text-align:center;">
                                    <a href="clientes.php?edit=<?php echo intval($cliente['id']); ?>"
                                        style="margin-right: 8px;">Editar</a>
                                    <form method="post" style="display:inline;"
                                        onsubmit="return confirm('¿Eliminar este cliente?');">
                                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(getCsrfToken()) ?>">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="id" value="<?php echo intval($cliente['id']); ?>">
                                        <button type="submit"
                                            style="background: transparent; border: none; color: #c00; cursor: pointer;">Eliminar</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </section>
    </main>
    <script src="2.js"></script>
</body>

</html>
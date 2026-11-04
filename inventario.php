<?php
include 'data.php';
include 'security.php';
if (!isset($_SESSION['user'])) {
    header('Location: index.php');
    exit;
}

requirePermission('menu_editar');

$message = '';
$editItem = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (!isset($_POST['csrf_token']) || !validateCsrfToken($_POST['csrf_token'])) {
            throw new Exception('Token de seguridad inválido');
        }

        $action = $_POST['action'] ?? 'add';
        $item = sanitizeString($_POST['item'] ?? '', 100);
        $cantidad = max(0, intval($_POST['cantidad'] ?? 0));
        $cantidad_minima = max(0, intval($_POST['cantidad_minima'] ?? 0));
        $cantidad_maxima = max(0, intval($_POST['cantidad_maxima'] ?? 0));
        $precio_costo = validateDecimal($_POST['precio_costo'] ?? 0, 0, 1000000);
        $proveedor = sanitizeString($_POST['proveedor'] ?? '', 100);
        $id = validateInteger($_POST['id'] ?? 0, 0);

        if ($action === 'save') {
            if (empty($item)) {
                throw new Exception('Nombre de artículo es requerido');
            }
            if ($precio_costo === null) {
                throw new Exception('Precio de costo inválido');
            }
            if ($cantidad_minima < 0 || $cantidad < 0 || $cantidad_maxima < 0) {
                throw new Exception('Las cantidades no pueden ser negativas');
            }
            if ($id > 0) {
                updateInventarioItem($id, $item, $cantidad, $cantidad_minima, $cantidad_maxima, $precio_costo, $proveedor);
                $message = 'Item actualizado correctamente.';
            } else {
                addInventarioItem($item, $cantidad, $cantidad_minima, $cantidad_maxima, $precio_costo, $proveedor);
                $message = 'Item agregado correctamente.';
            }
            registrarAuditoria($_SESSION['user']['id'], 'GUARDAR_INVENTARIO', 'INVENTARIO', $id > 0 ? $id : null, $item);
        } elseif ($action === 'delete' && $id > 0) {
            deleteInventarioItem($id);
            $message = 'Item eliminado correctamente.';
            registrarAuditoria($_SESSION['user']['id'], 'ELIMINAR_INVENTARIO', 'INVENTARIO', $id, 'Item eliminado');
        }

        header('Location: inventario.php?success=1');
        exit;
    } catch (Exception $e) {
        $message = $e->getMessage();
    }
}

if (isset($_GET['edit'])) {
    $editItem = getInventarioItemById(intval($_GET['edit']));
}

$inventario = getInventario();
$lowStockItems = getInventarioBajoStock();
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inventario - Restaurante POS</title>
    <link rel="stylesheet" href="style.php">
</head>

<body>
    <header>
        <h1>Inventario</h1>
        <a href="dashboard.php">Volver</a>
    </header>
    <main>
        <?php if ($message): ?>
            <div
                style="background: #e7f7e7; color: #155724; border: 1px solid #c3e6cb; padding: 12px; border-radius: 6px; margin-bottom: 18px;">
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>

        <?php if (!empty($lowStockItems)): ?>
            <section
                style="margin-bottom: 20px; padding: 15px; background: #fff4e5; border: 1px solid #ffd8a8; border-radius: 8px;">
                <h2>Artículos con stock bajo</h2>
                <ul>
                    <?php foreach ($lowStockItems as $item): ?>
                        <li><?php echo htmlspecialchars($item['item']); ?> — <?php echo intval($item['cantidad']); ?> en stock,
                            mínimo <?php echo intval($item['cantidad_minima']); ?></li>
                    <?php endforeach; ?>
                </ul>
            </section>
        <?php endif; ?>

        <section
            style="margin-bottom: 30px; background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.08);">
            <h2><?php echo $editItem ? 'Editar artículo' : 'Agregar nuevo artículo'; ?></h2>
            <form method="post">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(getCsrfToken()) ?>">
                <input type="hidden" name="action" value="save">
                <input type="hidden" name="id" value="<?php echo intval($editItem['id'] ?? 0); ?>">
                <label>Nombre del artículo:<br>
                    <input type="text" name="item" required style="width:100%;"
                        value="<?php echo htmlspecialchars($editItem['item'] ?? ''); ?>">
                </label><br><br>
                <label>Cantidad:<br>
                    <input type="number" name="cantidad" min="0" style="width:100%;"
                        value="<?php echo intval($editItem['cantidad'] ?? 0); ?>">
                </label><br><br>
                <label>Cantidad mínima de alerta:<br>
                    <input type="number" name="cantidad_minima" min="0" style="width:100%;"
                        value="<?php echo intval($editItem['cantidad_minima'] ?? 0); ?>">
                </label><br><br>
                <label>Cantidad máxima sugerida:<br>
                    <input type="number" name="cantidad_maxima" min="0" style="width:100%;"
                        value="<?php echo intval($editItem['cantidad_maxima'] ?? 0); ?>">
                </label><br><br>
                <label>Precio de costo:<br>
                    <input type="number" name="precio_costo" min="0" step="0.01" style="width:100%;"
                        value="<?php echo htmlspecialchars($editItem['precio_costo'] ?? ''); ?>">
                </label><br><br>
                <label>Proveedor:<br>
                    <input type="text" name="proveedor" style="width:100%;"
                        value="<?php echo htmlspecialchars($editItem['proveedor'] ?? ''); ?>">
                </label><br><br>
                <button type="submit"
                    style="padding: 10px 20px; background: #28a745; color: white; border: none; border-radius: 5px; cursor: pointer;">
                    <?php echo $editItem ? 'Actualizar artículo' : 'Agregar artículo'; ?></button>
                <?php if ($editItem): ?>
                    <a href="inventario.php" style="margin-left: 12px; color: #555;">Cancelar</a>
                <?php endif; ?>
            </form>
        </section>

        <section>
            <h2>Lista de inventario</h2>
            <?php if (empty($inventario)): ?>
                <p>No hay artículos registrados en el inventario.</p>
            <?php else: ?>
                <table style="width:100%; border-collapse: collapse;">
                    <thead>
                        <tr style="background: #f2f2f2;">
                            <th style="padding: 10px; text-align:left;">Artículo</th>
                            <th style="padding: 10px; text-align:right;">Cantidad</th>
                            <th style="padding: 10px; text-align:right;">Mínimo</th>
                            <th style="padding: 10px; text-align:right;">Máximo</th>
                            <th style="padding: 10px; text-align:right;">Costo</th>
                            <th style="padding: 10px; text-align:left;">Proveedor</th>
                            <th style="padding: 10px; text-align:center;">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($inventario as $item): ?>
                            <tr style="border-bottom: 1px solid #ddd;">
                                <td style="padding: 10px;"><?php echo htmlspecialchars($item['item']); ?></td>
                                <td style="padding: 10px; text-align:right;"><?php echo intval($item['cantidad']); ?></td>
                                <td style="padding: 10px; text-align:right;"><?php echo intval($item['cantidad_minima']); ?>
                                </td>
                                <td style="padding: 10px; text-align:right;"><?php echo intval($item['cantidad_maxima']); ?>
                                </td>
                                <td style="padding: 10px; text-align:right;">
                                    $<?php echo number_format($item['precio_costo'] ?? 0, 2); ?></td>
                                <td style="padding: 10px; text-align:left;"><?php echo htmlspecialchars($item['proveedor']); ?>
                                </td>
                                <td style="padding: 10px; text-align:center;">
                                    <a href="inventario.php?edit=<?php echo intval($item['id']); ?>"
                                        style="margin-right: 8px;">Editar</a>
                                    <form method="post" style="display:inline;"
                                        onsubmit="return confirm('¿Eliminar este artículo de inventario?');">
                                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(getCsrfToken()) ?>">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="id" value="<?php echo intval($item['id']); ?>">
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
<?php
include 'data.php';
include 'security.php';
if (!isset($_SESSION['user'])) {
    header('Location: dashboard.php');
    exit;
}
requirePermission('propinas');

function validateDateParam($value, $default)
{
    $value = trim($value);
    $date = date_create($value);
    return $date ? $date->format('Y-m-d') : $default;
}

$desde = validateDateParam($_GET['desde'] ?? '', date('Y-m-d'));
$hasta = validateDateParam($_GET['hasta'] ?? '', date('Y-m-d'));
$error_message = '';

if ($desde > $hasta) {
    $error_message = 'La fecha de inicio no puede superar la fecha final. Se ajustó el rango automáticamente.';
    $hasta = $desde;
}

$propinas_por_mesero = getTotalPropinasPorMesero($desde, $hasta);
$total_propinas = 0;
foreach ($propinas_por_mesero as $mesero) {
    $total_propinas += floatval($mesero['total_propinas'] ?? 0);
}

$propinas_detalle = [];
$stmtDetalle = $pdo->prepare("SELECT v.id, v.mesa, v.propina, v.tipo_pago, v.fecha, e.nombre AS mesero_nombre FROM ventas v LEFT JOIN empleados e ON v.mesero_id = e.id WHERE v.fecha BETWEEN ? AND ? AND v.suspendida = 0 AND v.propina > 0 ORDER BY v.fecha DESC");
$stmtDetalle->execute([$desde . ' 00:00:00', $hasta . ' 23:59:59']);
$propinas_detalle = $stmtDetalle->fetchAll(PDO::FETCH_ASSOC);

$total_propinas_por_pago = [];
$stmtPago = $pdo->prepare("SELECT v.tipo_pago, SUM(v.propina) AS total_propina, COUNT(*) AS ventas FROM ventas v WHERE DATE(v.fecha) BETWEEN ? AND ? AND v.suspendida = 0 AND v.propina > 0 GROUP BY v.tipo_pago");
$stmtPago->execute([$desde, $hasta]);
foreach ($stmtPago->fetchAll(PDO::FETCH_ASSOC) as $row) {
    $total_propinas_por_pago[$row['tipo_pago']] = $row;
}
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Propinas - Restaurante POS</title>
    <link rel="stylesheet" href="style.php">
</head>

<body>
    <header>
        <h1>Gestión de Propinas</h1>
        <a href="dashboard.php">Volver</a>
    </header>
    <main>
        <h2>Propinas por Rango de Fechas</h2>
        <form method="get">
            <label>Desde: <input type="date" name="desde" value="<?php echo htmlspecialchars($desde, ENT_QUOTES | ENT_HTML5, 'UTF-8'); ?>"></label>
            <label>Hasta: <input type="date" name="hasta" value="<?php echo htmlspecialchars($hasta, ENT_QUOTES | ENT_HTML5, 'UTF-8'); ?>"></label>
            <button type="submit">Filtrar</button>
        </form>
        <?php if (!empty($error_message)): ?>
            <div style="margin: 15px 0; padding: 12px; background: #fff3cd; border: 1px solid #ffeeba; color: #856404; border-radius: 6px;">
                <?php echo htmlspecialchars($error_message, ENT_QUOTES | ENT_HTML5, 'UTF-8'); ?>
            </div>
        <?php endif; ?>

        <?php if (empty($propinas_por_mesero) && empty($propinas_detalle)): ?>
            <div style="margin: 15px 0; padding: 14px; background: #e7f3fe; border: 1px solid #bee5eb; color: #31708f; border-radius: 6px;">
                No se encontraron registros de propinas para el rango seleccionado.
            </div>
        <?php endif; ?>

        <h3>Resumen por Empleado</h3>
        <p><strong>Total de propinas: $<?php echo number_format($total_propinas, 2); ?></strong></p>
        <table border="1" style="width:100%; border-collapse:collapse; margin-top:10px;">
            <thead>
                <tr>
                    <th>Mesero</th>
                    <th>Total Propinas</th>
                    <th>Número de ventas</th>
                </tr>
            </thead>
            <tbody>
                <?php
                foreach ($propinas_por_mesero as $mesero) {
                    $nombre = $mesero['nombre'] ?? 'N/A';
                    $total = floatval($mesero['total_propinas'] ?? 0);
                    $cnt = intval($mesero['ventas_con_propina'] ?? 0);
                    echo "<tr><td>" . htmlspecialchars($nombre, ENT_QUOTES | ENT_HTML5, 'UTF-8') . "</td><td>$" . number_format($total, 2) . "</td><td>" . $cnt . "</td></tr>";
                }
                ?>
            </tbody>
        </table>

        <h3>Resumen por Tipo de Pago</h3>
        <table border="1" style="width:100%; border-collapse:collapse; margin-top:10px;">
            <thead>
                <tr>
                    <th>Tipo de Pago</th>
                    <th>Total de Propinas</th>
                    <th>Ventas con Propina</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($total_propinas_por_pago as $tipo => $row) {
                    $totalPago = floatval($row['total_propina'] ?? 0);
                    $countPago = intval($row['ventas'] ?? 0);
                    echo "<tr><td>" . htmlspecialchars($tipo) . "</td><td>$" . number_format($totalPago, 2) . "</td><td>" . $countPago . "</td></tr>";
                } ?>
            </tbody>
        </table>

        <h3>Detalle de Propinas</h3>
        <table border="1" style="width:100%; border-collapse:collapse; margin-top:10px;">
            <thead>
                <tr>
                    <th>Venta ID</th>
                    <th>Mesa</th>
                    <th>Mesero</th>
                    <th>Tipo de Pago</th>
                    <th>Propina</th>
                    <th>Fecha</th>
                </tr>
            </thead>
            <tbody>
                <?php
                foreach ($propinas_detalle as $propina) {
                    $monto = floatval($propina['propina'] ?? 0);
                    if ($monto > 0) {
                        echo "<tr>
                            <td>" . htmlspecialchars($propina['id']) . "</td>
                            <td>" . htmlspecialchars($propina['mesa']) . "</td>
                            <td>" . htmlspecialchars($propina['mesero_nombre'] ?? 'N/A') . "</td>
                            <td>" . htmlspecialchars($propina['tipo_pago']) . "</td>
                            <td>$" . number_format($monto, 2) . "</td>
                            <td>" . htmlspecialchars(date('H:i:s', strtotime($propina['fecha']))) . "</td>
                        </tr>";
                    }
                }
                ?>
            </tbody>
        </table>
    </main>
    <script src="utilidades.js"></script>
</body>

</html>
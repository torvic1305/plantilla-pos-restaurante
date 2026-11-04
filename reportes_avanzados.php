<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

include 'data.php';
include 'security.php';
if (!isset($_SESSION['user'])) {
    header('Location: dashboard.php');
    exit;
}
requirePermission('informes');

$user = $_SESSION['user'];

function validateDateParam($value, $default)
{
    $value = trim($value);
    $date = date_create($value);
    return $date ? $date->format('Y-m-d') : $default;
}

// Parámetros de filtros
$fecha_inicio = validateDateParam($_GET['fecha_inicio'] ?? '', date('Y-m-d', strtotime('-30 days')));
$fecha_fin = validateDateParam($_GET['fecha_fin'] ?? '', date('Y-m-d'));
$tipo_reporte = in_array($_GET['tipo_reporte'] ?? 'ventas', ['ventas', 'meseros', 'horario'], true) ? $_GET['tipo_reporte'] : 'ventas';
$error_message = '';

if ($fecha_inicio > $fecha_fin) {
    $error_message = 'La fecha de inicio no puede ser mayor a la fecha fin. Se ajustó el rango automáticamente.';
    $fecha_fin = $fecha_inicio;
}

$fecha_inicio_dt = $fecha_inicio . ' 00:00:00';
$fecha_fin_dt = $fecha_fin . ' 23:59:59';

// Variables para datos
$datos_reporte = [];
$totales = [];

if ($tipo_reporte === 'ventas') {
    // Reporte de ventas por día
    $stmt = $GLOBALS['pdo']->prepare("
        SELECT 
            DATE(v.fecha) as fecha,
            COUNT(*) as total_ventas,
            COUNT(DISTINCT v.mesa) as total_mesas,
            SUM(CASE WHEN v.tipo_pago = 'Efectivo' THEN v.monto_pagado ELSE 0 END) as efectivo,
            SUM(CASE WHEN v.tipo_pago = 'Tarjeta' THEN v.monto_pagado ELSE 0 END) as tarjeta,
            SUM(CASE WHEN v.tipo_pago = 'Transferencia' THEN v.monto_pagado ELSE 0 END) as transferencia,
            SUM(v.descuento) as descuentos,
            SUM(v.impuesto) as impuestos,
            SUM(v.propina) as propinas,
            SUM(v.monto_pagado) as total_ingresos
        FROM ventas v
        WHERE NOT v.suspendida 
        AND v.fecha BETWEEN ? AND ?
        GROUP BY DATE(v.fecha)
        ORDER BY v.fecha DESC
    ");
    $stmt->execute([$fecha_inicio_dt, $fecha_fin_dt]);
    $datos_reporte = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Totales generales
    $stmt = $GLOBALS['pdo']->prepare("
        SELECT 
            COUNT(*) as total_ventas,
            COUNT(DISTINCT v.mesa) as total_mesas,
            COUNT(DISTINCT v.mesero_id) as total_meseros,
            SUM(CASE WHEN v.tipo_pago = 'Efectivo' THEN v.monto_pagado ELSE 0 END) as efectivo,
            SUM(CASE WHEN v.tipo_pago = 'Tarjeta' THEN v.monto_pagado ELSE 0 END) as tarjeta,
            SUM(CASE WHEN v.tipo_pago = 'Transferencia' THEN v.monto_pagado ELSE 0 END) as transferencia,
            SUM(v.descuento) as descuentos,
            SUM(v.impuesto) as impuestos,
            SUM(v.propina) as propinas,
            SUM(v.monto_pagado) as total_ingresos
        FROM ventas v
        WHERE NOT v.suspendida 
        AND v.fecha BETWEEN ? AND ?
    ");
    $stmt->execute([$fecha_inicio_dt, $fecha_fin_dt]);
    $totales = $stmt->fetch(PDO::FETCH_ASSOC);

} elseif ($tipo_reporte === 'meseros') {
    // Reporte por mesero
    $stmt = $GLOBALS['pdo']->prepare("
        SELECT 
            e.id,
            e.nombre,
            COUNT(*) as total_ventas,
            AVG(v.monto_pagado) as ticket_promedio,
            SUM(v.monto_pagado) as total_ingresos,
            SUM(v.propina) as total_propinas,
            SUM(v.descuento) as total_descuentos
        FROM ventas v
        LEFT JOIN empleados e ON v.mesero_id = e.id
        WHERE NOT v.suspendida 
        AND v.fecha BETWEEN ? AND ?
        GROUP BY e.id, e.nombre
        ORDER BY total_ingresos DESC
    ");
    $stmt->execute([$fecha_inicio_dt, $fecha_fin_dt]);
    $datos_reporte = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Totales
    $stmt = $GLOBALS['pdo']->prepare("
        SELECT 
            COUNT(*) as total_ventas,
            SUM(v.monto_pagado) as total_ingresos,
            SUM(v.propina) as total_propinas,
            COUNT(DISTINCT e.id) as total_meseros
        FROM ventas v
        LEFT JOIN empleados e ON v.mesero_id = e.id
        WHERE NOT v.suspendida 
        AND v.fecha BETWEEN ? AND ?
    ");
    $stmt->execute([$fecha_inicio_dt, $fecha_fin_dt]);
    $totales = $stmt->fetch(PDO::FETCH_ASSOC);

} elseif ($tipo_reporte === 'horario') {
    // Reporte por hora del día
    $stmt = $GLOBALS['pdo']->prepare("
        SELECT 
            HOUR(v.fecha) as hora,
            COUNT(*) as total_ventas,
            SUM(v.monto_pagado) as total_ingresos,
            AVG(v.monto_pagado) as ticket_promedio,
            SUM(v.propina) as propinas
        FROM ventas v
        WHERE NOT v.suspendida 
        AND v.fecha BETWEEN ? AND ?
        GROUP BY HOUR(v.fecha)
        ORDER BY hora ASC
    ");
    $stmt->execute([$fecha_inicio_dt, $fecha_fin_dt]);
    $datos_reporte = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Totales
    $stmt = $GLOBALS['pdo']->prepare("
        SELECT 
            COUNT(*) as total_ventas,
            SUM(v.monto_pagado) as total_ingresos,
            AVG(v.monto_pagado) as ticket_promedio
        FROM ventas v
        WHERE NOT v.suspendida 
        AND v.fecha BETWEEN ? AND ?
    ");
    $stmt->execute([$fecha_inicio_dt, $fecha_fin_dt]);
    $totales = $stmt->fetch(PDO::FETCH_ASSOC);
}
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reportes Avanzados - POS</title>
    <link rel="stylesheet" href="style.php">
    <style>
        .reportes-container {
            max-width: 1200px;
            margin: 20px auto;
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }

        .filtros {
            background: #f9f9f9;
            padding: 20px;
            border-radius: 6px;
            margin-bottom: 20px;
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
        }

        .filtros label {
            display: flex;
            flex-direction: column;
            gap: 5px;
            font-weight: bold;
        }

        .filtros input,
        .filtros select {
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 14px;
        }

        .btn-filtrar {
            padding: 10px 20px;
            background: #3498db;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-weight: bold;
            align-self: flex-end;
        }

        .btn-filtrar:hover {
            background: #2980b9;
        }

        .resumen-reportes {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 15px;
            margin-bottom: 30px;
        }

        .tarjeta-resumen {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px;
            border-radius: 8px;
            text-align: center;
        }

        .tarjeta-resumen h4 {
            margin: 0 0 10px 0;
            font-size: 12px;
            opacity: 0.9;
        }

        .tarjeta-resumen .valor {
            font-size: 24px;
            font-weight: bold;
        }

        .tabla-reporte {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }

        .tabla-reporte th {
            background: #34495e;
            color: white;
            padding: 12px;
            text-align: left;
            font-weight: bold;
        }

        .tabla-reporte td {
            padding: 10px 12px;
            border-bottom: 1px solid #ddd;
        }

        .tabla-reporte tr:hover {
            background: #f9f9f9;
        }

        .tabla-reporte tr:last-child {
            background: #ecf0f1;
            font-weight: bold;
            border-top: 2px solid #34495e;
        }

        .negativo {
            color: #e74c3c;
        }

        .positivo {
            color: #27ae60;
        }

        .titulo-reporte {
            color: #2c3e50;
            margin: 20px 0 10px 0;
        }

        .btn-volver {
            display: inline-block;
            padding: 10px 15px;
            background: #95a5a6;
            color: white;
            border-radius: 4px;
            text-decoration: none;
            margin-top: 20px;
        }

        .btn-volver:hover {
            background: #7f8c8d;
        }
    </style>
</head>

<body>
    <div class="reportes-container">
        <h1>Reportes Avanzados</h1>

        <form method="GET" class="filtros">
            <?php if (!empty($error_message)): ?>
                <div style="grid-column: 1 / -1; padding: 12px; background: #ffebeb; color: #922b21; border: 1px solid #f5c6cb; border-radius: 6px;">
                    <?php echo htmlspecialchars($error_message, ENT_QUOTES | ENT_HTML5, 'UTF-8'); ?>
                </div>
            <?php endif; ?>
            <label>
                Fecha Inicio:
                <input type="date" name="fecha_inicio" value="<?php echo htmlspecialchars($fecha_inicio, ENT_QUOTES | ENT_HTML5, 'UTF-8'); ?>">
            </label>
            <label>
                Fecha Fin:
                <input type="date" name="fecha_fin" value="<?php echo htmlspecialchars($fecha_fin, ENT_QUOTES | ENT_HTML5, 'UTF-8'); ?>">
            </label>
            <label>
                Tipo Reporte:
                <select name="tipo_reporte">
                    <option value="ventas" <?php echo $tipo_reporte === 'ventas' ? 'selected' : ''; ?>>Ventas por Día
                    </option>
                    <option value="meseros" <?php echo $tipo_reporte === 'meseros' ? 'selected' : ''; ?>>Ventas por Mesero
                    </option>
                    <option value="horario" <?php echo $tipo_reporte === 'horario' ? 'selected' : ''; ?>>Ventas por Hora
                    </option>
                </select>
            </label>
            <button type="submit" class="btn-filtrar">Filtrar</button>
        </form>

        <?php if (!empty($totales)): ?>
            <div class="resumen-reportes">
                <div class="tarjeta-resumen" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
                    <h4>Total Ventas</h4>
                    <div class="valor"><?php echo $totales['total_ventas'] ?? 0; ?></div>
                </div>
                <div class="tarjeta-resumen" style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);">
                    <h4>Ingresos Totales</h4>
                    <div class="valor">$<?php echo number_format($totales['total_ingresos'] ?? 0, 2); ?></div>
                </div>
                <div class="tarjeta-resumen" style="background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);">
                    <h4>Propinas</h4>
                    <div class="valor">$<?php echo number_format($totales['total_propinas'] ?? 0, 2); ?></div>
                </div>
                <div class="tarjeta-resumen" style="background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%);">
                    <h4>Ticket Promedio</h4>
                    <div class="valor">
                        $<?php echo number_format(($totales['total_ingresos'] ?? 0) / max(1, $totales['total_ventas'] ?? 1), 2); ?>
                    </div>
                </div>
                <?php if ($tipo_reporte === 'meseros'): ?>
                    <div class="tarjeta-resumen" style="background: linear-gradient(135deg, #fa709a 0%, #fee140 100%);">
                        <h4>Total Meseros</h4>
                        <div class="valor"><?php echo $totales['total_meseros'] ?? 0; ?></div>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <h3 class="titulo-reporte">
            <?php
            if ($tipo_reporte === 'ventas')
                echo 'Ventas por Día';
            elseif ($tipo_reporte === 'meseros')
                echo 'Ventas por Mesero';
            elseif ($tipo_reporte === 'horario')
                echo 'Ventas por Hora del Día';
            ?>
        </h3>

        <?php if (!empty($datos_reporte)): ?>
            <table class="tabla-reporte">
                <thead>
                    <tr>
                        <?php if ($tipo_reporte === 'ventas'): ?>
                            <th>Fecha</th>
                            <th>Total Ventas</th>
                            <th>Mesas</th>
                            <th>Efectivo</th>
                            <th>Tarjeta</th>
                            <th>Transferencia</th>
                            <th>Descuentos</th>
                            <th>Impuestos</th>
                            <th>Propinas</th>
                            <th>Total Ingresos</th>
                        <?php elseif ($tipo_reporte === 'meseros'): ?>
                            <th>Mesero</th>
                            <th>Total Ventas</th>
                            <th>Ticket Promedio</th>
                            <th>Total Ingresos</th>
                            <th>Propinas</th>
                            <th>Descuentos</th>
                        <?php elseif ($tipo_reporte === 'horario'): ?>
                            <th>Hora</th>
                            <th>Total Ventas</th>
                            <th>Total Ingresos</th>
                            <th>Ticket Promedio</th>
                            <th>Propinas</th>
                        <?php endif; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($datos_reporte as $row): ?>
                        <tr>
                            <?php if ($tipo_reporte === 'ventas'): ?>
                                <td><?php echo date('d/m/Y', strtotime($row['fecha'])); ?></td>
                                <td><?php echo $row['total_ventas']; ?></td>
                                <td><?php echo $row['total_mesas']; ?></td>
                                <td class="positivo">$<?php echo number_format($row['efectivo'] ?? 0, 2); ?></td>
                                <td class="positivo">$<?php echo number_format($row['tarjeta'] ?? 0, 2); ?></td>
                                <td class="positivo">$<?php echo number_format($row['transferencia'] ?? 0, 2); ?></td>
                                <td class="negativo">-$<?php echo number_format($row['descuentos'] ?? 0, 2); ?></td>
                                <td>+$<?php echo number_format($row['impuestos'] ?? 0, 2); ?></td>
                                <td>$<?php echo number_format($row['propinas'] ?? 0, 2); ?></td>
                                <td class="positivo" style="font-weight: bold;">
                                    $<?php echo number_format($row['total_ingresos'] ?? 0, 2); ?></td>
                            <?php elseif ($tipo_reporte === 'meseros'): ?>
                                <td><strong><?php echo htmlspecialchars($row['nombre'] ?? 'N/A'); ?></strong></td>
                                <td><?php echo $row['total_ventas']; ?></td>
                                <td>$<?php echo number_format($row['ticket_promedio'] ?? 0, 2); ?></td>
                                <td class="positivo" style="font-weight: bold;">
                                    $<?php echo number_format($row['total_ingresos'] ?? 0, 2); ?></td>
                                <td>$<?php echo number_format($row['total_propinas'] ?? 0, 2); ?></td>
                                <td class="negativo">-$<?php echo number_format($row['total_descuentos'] ?? 0, 2); ?></td>
                            <?php elseif ($tipo_reporte === 'horario'): ?>
                                <td><?php echo str_pad($row['hora'], 2, '0', STR_PAD_LEFT); ?>:00 -
                                    <?php echo str_pad($row['hora'] + 1, 2, '0', STR_PAD_LEFT); ?>:00</td>
                                <td><?php echo $row['total_ventas']; ?></td>
                                <td class="positivo">$<?php echo number_format($row['total_ingresos'] ?? 0, 2); ?></td>
                                <td>$<?php echo number_format($row['ticket_promedio'] ?? 0, 2); ?></td>
                                <td>$<?php echo number_format($row['propinas'] ?? 0, 2); ?></td>
                            <?php endif; ?>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <p style="text-align: center; color: #7f8c8d; margin-top: 20px;">No hay datos para el período seleccionado.</p>
        <?php endif; ?>

        <a href="dashboard.php" class="btn-volver">← Volver al Dashboard</a>
    </div>
</body>

</html>
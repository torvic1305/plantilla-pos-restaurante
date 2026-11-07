<?php
ini_set('display_errors', '0');

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
    if (!is_string($value) || $value === '') {
        return $default;
    }
    $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
    $dateErrors = DateTimeImmutable::getLastErrors();
    if (
        !$date
        || $date->format('Y-m-d') !== $value
        || ($dateErrors !== false && ($dateErrors['warning_count'] > 0 || $dateErrors['error_count'] > 0))
    ) {
        return $default;
    }
    return $date->format('Y-m-d');
}

// Parámetros de filtros
$fecha_inicio = validateDateParam($_GET['fecha_inicio'] ?? '', date('Y-m-d', strtotime('-30 days')));
$fecha_fin = validateDateParam($_GET['fecha_fin'] ?? '', date('Y-m-d'));
$tipoReporteSolicitado = $_GET['tipo_reporte'] ?? 'ventas';
$tipo_reporte = is_string($tipoReporteSolicitado)
    && in_array($tipoReporteSolicitado, ['ventas', 'meseros', 'horario'], true)
    ? $tipoReporteSolicitado
    : 'ventas';
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
$reportTitles = [
    'ventas' => 'Ventas por día',
    'meseros' => 'Ventas por mesero',
    'horario' => 'Ventas por hora del día',
];

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
        .report-header { min-height: 66px; padding: 10px clamp(16px, 3vw, 40px); border-bottom: 1px solid #30343a; background: #17191d; }
        .report-header h1 { margin: 0; color: #f1eee7; font-size: 1.1rem; }
        .report-header a { padding: 8px 12px; border: 1px solid #393d42; border-radius: 7px; color: #e3d3ae; font-size: .85rem; }
        .report-page { max-width: 1420px; }
        .reportes-container { max-width: none; margin: 0; padding: 24px; border: 1px solid #e3e5e8; border-radius: 12px; background: #fff; box-shadow: 0 8px 30px rgba(20,25,30,.08); }
        .report-title-row { display: flex; align-items: flex-end; justify-content: space-between; gap: 14px; margin-bottom: 20px; }
        .report-title-row h2 { margin: 0; color: #1d2329; font-size: 1.45rem; }
        .report-title-row p { margin: 5px 0 0; color: #66717d; }
        .report-range-badge { padding: 7px 10px; border: 1px solid #e8dfcb; border-radius: 18px; color: #705b32; background: #faf7f0; font-size: .78rem; white-space: nowrap; }
        .filtros { display: grid; grid-template-columns: repeat(3, minmax(140px, 1fr)) auto; align-items: end; gap: 13px; margin: 0 0 20px; padding: 16px; border: 1px solid #e5e8ec; border-radius: 10px; background: #f7f8fa; }
        .filtros label { display: flex; flex-direction: column; gap: 6px; margin: 0; color: #475361; font-size: .82rem; font-weight: 650; }
        .filtros input, .filtros select { width: 100%; }
        .btn-filtrar { min-width: 110px; color: #171717; background: #d3b16a; font-weight: 750; }
        .btn-filtrar:hover { background: #e0c17e; }
        .report-error { grid-column: 1 / -1; padding: 11px 13px; border: 1px solid #efc3c3; border-radius: 8px; color: #842d2d; background: #fff4f4; font-size: .85rem; }
        .resumen-reportes { display: grid; grid-template-columns: repeat(auto-fit, minmax(160px, 1fr)); gap: 12px; margin-bottom: 25px; }
        .tarjeta-resumen { min-width: 0; padding: 16px; border: 1px solid #e4e6e9; border-radius: 10px; color: #252b30; background: #fff; box-shadow: 0 2px 9px rgba(20,25,30,.04); text-align: left; }
        .tarjeta-resumen h4 { margin: 0 0 8px; color: #727d88; font-size: .75rem; font-weight: 650; }
        .tarjeta-resumen .valor { color: #262b30; font-size: clamp(1.15rem, 2vw, 1.55rem); font-weight: 750; }
        .tarjeta-resumen:nth-child(2) { border-top: 3px solid #c8a556; }
        .tarjeta-resumen:nth-child(3) { border-top: 3px solid #7b8f79; }
        .tarjeta-resumen:nth-child(4) { border-top: 3px solid #7d8ba0; }
        .titulo-reporte { margin: 0 0 12px; color: #272d33; font-size: 1.05rem; }
        .tabla-reporte-scroll { overflow-x: auto; border: 1px solid #e4e7eb; border-radius: 9px; }
        .tabla-reporte { min-width: 720px; margin: 0; border-collapse: separate; border-spacing: 0; }
        .tabla-reporte th { padding: 12px; border-bottom: 1px solid #dfe3e8; color: #515c68; background: #f4f6f8; font-size: .76rem; font-weight: 700; white-space: nowrap; }
        .tabla-reporte td { padding: 11px 12px; border-bottom: 1px solid #edf0f2; color: #39434d; font-size: .82rem; white-space: nowrap; }
        .tabla-reporte tbody tr:hover { background: #fbf8f0; }
        .negativo { color: #bd5b52; }
        .positivo { color: #527b58; }
        .report-empty-state { margin: 0; padding: 32px 16px; border: 1px dashed #d9dee4; border-radius: 9px; color: #687480; background: #fafbfc; text-align: center; }
        .btn-volver { display: inline-flex; align-items: center; margin-top: 18px; padding: 8px 11px; border: 1px solid #e1e4e7; border-radius: 7px; color: #4a5662; background: #f6f7f8; font-size: .82rem; text-decoration: none; }
        .btn-volver:hover { border-color: #c5ac74; color: #6b5425; background: #faf7f0; }
        @media (max-width: 760px) {
            .report-page { padding: 14px; }
            .reportes-container { padding: 16px; }
            .report-title-row { align-items: flex-start; flex-direction: column; }
            .filtros { grid-template-columns: repeat(2, minmax(0, 1fr)); }
            .btn-filtrar { width: 100%; }
        }
        @media (max-width: 480px) {
            .filtros { grid-template-columns: minmax(0, 1fr); }
        }
    </style>
</head>

<body>
    <header class="report-header">
        <h1>Restaurante POS <span aria-hidden="true">/</span> Reportes avanzados</h1>
        <a href="dashboard.php">‹ Volver al panel</a>
    </header>
    <main class="report-page">
    <div class="reportes-container">
        <div class="report-title-row">
            <div>
                <h2>Reportes avanzados</h2>
                <p>Analiza ventas, rendimiento del personal y horarios de mayor actividad.</p>
            </div>
            <span class="report-range-badge"><?= htmlspecialchars(date('d/m/Y', strtotime($fecha_inicio)), ENT_QUOTES | ENT_HTML5, 'UTF-8') ?> — <?= htmlspecialchars(date('d/m/Y', strtotime($fecha_fin)), ENT_QUOTES | ENT_HTML5, 'UTF-8') ?></span>
        </div>

        <form method="GET" class="filtros">
            <?php if (!empty($error_message)): ?>
                <div class="report-error" role="status">
                    <?php echo htmlspecialchars($error_message, ENT_QUOTES | ENT_HTML5, 'UTF-8'); ?>
                </div>
            <?php endif; ?>
            <label>
                Fecha de inicio
                <input type="date" name="fecha_inicio" value="<?php echo htmlspecialchars($fecha_inicio, ENT_QUOTES | ENT_HTML5, 'UTF-8'); ?>">
            </label>
            <label>
                Fecha de fin
                <input type="date" name="fecha_fin" value="<?php echo htmlspecialchars($fecha_fin, ENT_QUOTES | ENT_HTML5, 'UTF-8'); ?>">
            </label>
            <label>
                Tipo de reporte
                <select name="tipo_reporte">
                    <option value="ventas" <?php echo $tipo_reporte === 'ventas' ? 'selected' : ''; ?>>Ventas por Día
                    </option>
                    <option value="meseros" <?php echo $tipo_reporte === 'meseros' ? 'selected' : ''; ?>>Ventas por Mesero
                    </option>
                    <option value="horario" <?php echo $tipo_reporte === 'horario' ? 'selected' : ''; ?>>Ventas por Hora
                    </option>
                </select>
            </label>
            <button type="submit" class="btn-filtrar">Aplicar filtros</button>
        </form>

        <?php if (!empty($totales)): ?>
            <div class="resumen-reportes">
                <div class="tarjeta-resumen">
                    <h4>Ventas completadas</h4>
                    <div class="valor"><?php echo $totales['total_ventas'] ?? 0; ?></div>
                </div>
                <div class="tarjeta-resumen">
                    <h4>Ingresos totales</h4>
                    <div class="valor">$<?php echo number_format($totales['total_ingresos'] ?? 0, 2); ?></div>
                </div>
                <div class="tarjeta-resumen">
                    <h4>Propinas</h4>
                    <div class="valor">$<?php echo number_format($totales['total_propinas'] ?? 0, 2); ?></div>
                </div>
                <div class="tarjeta-resumen">
                    <h4>Ticket promedio</h4>
                    <div class="valor">
                        $<?php echo number_format(($totales['total_ingresos'] ?? 0) / max(1, $totales['total_ventas'] ?? 1), 2); ?>
                    </div>
                </div>
                <?php if ($tipo_reporte === 'meseros'): ?>
                    <div class="tarjeta-resumen">
                        <h4>Meseros con ventas</h4>
                        <div class="valor"><?php echo $totales['total_meseros'] ?? 0; ?></div>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <h3 class="titulo-reporte"><?= htmlspecialchars($reportTitles[$tipo_reporte], ENT_QUOTES | ENT_HTML5, 'UTF-8') ?></h3>

        <?php if (!empty($datos_reporte)): ?>
            <div class="tabla-reporte-scroll">
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
            </div>
        <?php else: ?>
            <p class="report-empty-state">No hay ventas completadas para este período y tipo de reporte.</p>
        <?php endif; ?>

        <a href="dashboard.php" class="btn-volver">‹ Volver al panel</a>
    </div>
    </main>
</body>

</html>
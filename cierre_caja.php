<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

include 'data.php';
include 'security.php';
if (!isset($_SESSION['user'])) {
    header('Location: index.php');
    exit;
}

if (!($_SESSION['user']['permisos']['pos'] ?? false)) {
    header('Location: dashboard.php');
    exit;
}

$user = $_SESSION['user'];
$cierre_actual = null;
$ventas_hoy = [];

// Obtener cierre actual si existe
if ($_POST['accion'] ?? null === 'crear_cierre') {
    if (!isset($_POST['csrf_token']) || !validateCsrfToken($_POST['csrf_token'])) {
        die('Token CSRF inválido.');
    }
    $fecha_inicio = date('Y-m-d 00:00:00');
    $fecha_fin = date('Y-m-d 23:59:59');

    $stmt = $GLOBALS['pdo']->prepare("
        SELECT 
            COUNT(*) as total_ventas,
            SUM(CASE WHEN tipo_pago = 'Efectivo' THEN 1 ELSE 0 END) as ventas_efectivo,
            SUM(CASE WHEN tipo_pago = 'Tarjeta' THEN 1 ELSE 0 END) as ventas_tarjeta,
            SUM(CASE WHEN tipo_pago = 'Transferencia' THEN 1 ELSE 0 END) as ventas_transferencia,
            SUM(CASE WHEN NOT suspendida THEN (SELECT COALESCE(SUM(JSON_EXTRACT(orden, CONCAT('$[', idx, '].precio'))), 0) FROM ventas v2 WHERE v2.id = ventas.id FOR EACH JSON ELEMENT idx) ELSE 0 END) as total_ventas_valor,
            SUM(descuento) as total_descuentos,
            SUM(impuesto) as total_impuestos,
            SUM(propina) as total_propinas
        FROM ventas 
        WHERE NOT suspendida AND fecha BETWEEN ? AND ?
    ");

    $stmt->execute([$fecha_inicio, $fecha_fin]);
    $cierre_actual = $stmt->fetch(PDO::FETCH_ASSOC);

    // Guardar cierre
    $stmt_save = $GLOBALS['pdo']->prepare("
        INSERT INTO cierres_caja (usuario_id, fecha, total_ventas, total_efectivo, total_tarjeta, total_descuentos, total_impuestos, total_propinas)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt_save->execute([
        $user['id'],
        date('Y-m-d'),
        $cierre_actual['total_ventas_valor'] ?? 0,
        $cierre_actual['total_ventas_valor'] ?? 0, // Efectivo
        0, // Tarjeta
        $cierre_actual['total_descuentos'] ?? 0,
        $cierre_actual['total_impuestos'] ?? 0,
        $cierre_actual['total_propinas'] ?? 0
    ]);
}

// Obtener ventas del día para cierre
$fecha_inicio = date('Y-m-d 00:00:00');
$fecha_fin = date('Y-m-d 23:59:59');

$stmt = $GLOBALS['pdo']->prepare("
    SELECT v.*, e.nombre as mesero_nombre 
    FROM ventas v 
    LEFT JOIN empleados e ON v.mesero_id = e.id
    WHERE NOT v.suspendida AND v.fecha BETWEEN ? AND ?
    ORDER BY v.fecha DESC
");
$stmt->execute([$fecha_inicio, $fecha_fin]);
$ventas_hoy = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Calcular totales
$total_efectivo = 0;
$total_tarjeta = 0;
$total_transferencia = 0;
$total_descuentos = 0;
$total_impuestos = 0;
$total_propinas = 0;

foreach ($ventas_hoy as $venta) {
    if ($venta['tipo_pago'] === 'Efectivo') {
        $total_efectivo += $venta['monto_pagado'];
    } elseif ($venta['tipo_pago'] === 'Tarjeta') {
        $total_tarjeta += $venta['monto_pagado'];
    } else {
        $total_transferencia += $venta['monto_pagado'];
    }
    $total_descuentos += $venta['descuento'];
    $total_impuestos += $venta['impuesto'];
    $total_propinas += $venta['propina'];
}

$total_general = $total_efectivo + $total_tarjeta + $total_transferencia;
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cierre de Caja - POS</title>
    <link rel="stylesheet" href="style.php">
    <style>
        .cierre-container {
            max-width: 900px;
            margin: 20px auto;
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }

        .cierre-header {
            background: #2c3e50;
            color: white;
            padding: 15px;
            border-radius: 4px;
            margin-bottom: 20px;
        }

        .cierre-resumen {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 30px;
        }

        .resumen-item {
            background: #ecf0f1;
            padding: 15px;
            border-radius: 4px;
            border-left: 4px solid #3498db;
        }

        .resumen-item h4 {
            margin: 0 0 10px 0;
            color: #2c3e50;
        }

        .resumen-item .valor {
            font-size: 24px;
            font-weight: bold;
            color: #27ae60;
        }

        .metodos-pago {
            background: #f9f9f9;
            padding: 15px;
            border-radius: 4px;
            margin-bottom: 20px;
        }

        .metodo-pago-row {
            display: flex;
            justify-content: space-between;
            padding: 10px 0;
            border-bottom: 1px solid #ddd;
        }

        .metodo-pago-row:last-child {
            border-bottom: none;
        }

        .metodo-pago-row strong {
            color: #2c3e50;
        }

        .cierre-acciones {
            display: flex;
            gap: 10px;
            justify-content: flex-end;
            margin-top: 20px;
        }

        .cierre-acciones button {
            padding: 10px 20px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-weight: bold;
        }

        .btn-cierre {
            background: #27ae60;
            color: white;
        }

        .btn-cierre:hover {
            background: #229954;
        }

        .btn-imprimir {
            background: #3498db;
            color: white;
        }

        .btn-imprimir:hover {
            background: #2980b9;
        }

        .ventas-detalle {
            margin-top: 30px;
        }

        .ventas-tabla {
            width: 100%;
            border-collapse: collapse;
            font-size: 12px;
        }

        .ventas-tabla th {
            background: #34495e;
            color: white;
            padding: 10px;
            text-align: left;
        }

        .ventas-tabla td {
            padding: 8px;
            border-bottom: 1px solid #ddd;
        }

        .ventas-tabla tr:hover {
            background: #f5f5f5;
        }
    </style>
</head>

<body>
    <div class="cierre-container">
        <div class="cierre-header">
            <h1>Cierre de Caja</h1>
            <p>Fecha: <?php echo date('d/m/Y H:i'); ?> | Usuario: <?php echo htmlspecialchars($user['nombre']); ?></p>
        </div>

        <div class="cierre-resumen">
            <div class="resumen-item">
                <h4>Efectivo</h4>
                <div class="valor">$<?php echo number_format($total_efectivo, 2); ?></div>
            </div>
            <div class="resumen-item">
                <h4>Tarjeta</h4>
                <div class="valor">$<?php echo number_format($total_tarjeta, 2); ?></div>
            </div>
            <div class="resumen-item">
                <h4>Transferencia</h4>
                <div class="valor">$<?php echo number_format($total_transferencia, 2); ?></div>
            </div>
            <div class="resumen-item" style="border-left-color: #e74c3c;">
                <h4>Total General</h4>
                <div class="valor">$<?php echo number_format($total_general, 2); ?></div>
            </div>
            <div class="resumen-item" style="border-left-color: #f39c12;">
                <h4>Descuentos</h4>
                <div class="valor">-$<?php echo number_format($total_descuentos, 2); ?></div>
            </div>
            <div class="resumen-item" style="border-left-color: #9b59b6;">
                <h4>Impuestos</h4>
                <div class="valor">+$<?php echo number_format($total_impuestos, 2); ?></div>
            </div>
            <div class="resumen-item" style="border-left-color: #1abc9c;">
                <h4>Propinas</h4>
                <div class="valor">$<?php echo number_format($total_propinas, 2); ?></div>
            </div>
            <div class="resumen-item" style="border-left-color: #16a085;">
                <h4>Total Ventas</h4>
                <div class="valor">
                    $<?php echo number_format($total_general + $total_impuestos - $total_descuentos, 2); ?></div>
            </div>
        </div>

        <div class="metodos-pago">
            <h3>Desglose por Método de Pago</h3>
            <div class="metodo-pago-row">
                <span>Ventas en Efectivo:</span>
                <strong>$<?php echo number_format($total_efectivo, 2); ?></strong>
            </div>
            <div class="metodo-pago-row">
                <span>Ventas con Tarjeta:</span>
                <strong>$<?php echo number_format($total_tarjeta, 2); ?></strong>
            </div>
            <div class="metodo-pago-row">
                <span>Ventas por Transferencia:</span>
                <strong>$<?php echo number_format($total_transferencia, 2); ?></strong>
            </div>
            <div class="metodo-pago-row" style="border-top: 2px solid #2c3e50; padding-top: 10px; margin-top: 10px;">
                <strong>Total Ingresos:</strong>
                <strong
                    style="color: #27ae60; font-size: 18px;">$<?php echo number_format($total_general, 2); ?></strong>
            </div>
        </div>

        <div class="cierre-acciones">
            <button class="btn-imprimir" onclick="imprimirCierre()">Imprimir Cierre</button>
            <form method="POST" style="display: inline;">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(getCsrfToken()) ?>">
                <input type="hidden" name="accion" value="crear_cierre">
                <button type="submit" class="btn-cierre"
                    onclick="return confirm('¿Finalizar cierre de caja? No podrá deshacer esta acción.');">Finalizar
                    Cierre</button>
            </form>
            <a href="dashboard.php"
                style="padding: 10px 20px; background: #95a5a6; color: white; border-radius: 4px; text-decoration: none; border: none; cursor: pointer;">Volver</a>
        </div>

        <div class="ventas-detalle">
            <h3>Detalle de Ventas del Día</h3>
            <table class="ventas-tabla">
                <thead>
                    <tr>
                        <th>ID Venta</th>
                        <th>Mesa</th>
                        <th>Mesero</th>
                        <th>Método Pago</th>
                        <th>Subtotal</th>
                        <th>Descuento</th>
                        <th>Impuesto</th>
                        <th>Propina</th>
                        <th>Total</th>
                        <th>Hora</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($ventas_hoy as $venta): ?>
                        <tr>
                            <td>#<?php echo $venta['id']; ?></td>
                            <td><?php echo $venta['mesa']; ?></td>
                            <td><?php echo htmlspecialchars($venta['mesero_nombre'] ?? 'N/A'); ?></td>
                            <td><?php echo htmlspecialchars($venta['tipo_pago']); ?></td>
                            <td>$<?php echo number_format($venta['monto_pagado'] - $venta['propina'], 2); ?></td>
                            <td>-$<?php echo number_format($venta['descuento'], 2); ?></td>
                            <td>+$<?php echo number_format($venta['impuesto'], 2); ?></td>
                            <td>$<?php echo number_format($venta['propina'], 2); ?></td>
                            <td>$<?php echo number_format($venta['monto_pagado'], 2); ?></td>
                            <td><?php echo date('H:i:s', strtotime($venta['fecha'])); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <script>
        function imprimirCierre() {
            const ventana = window.open('', '_blank', 'width=400,height=600');
            const contenido = document.querySelector('.cierre-container').innerHTML;
            ventana.document.write(`
                <!DOCTYPE html>
                <html>
                <head>
                    <title>Cierre de Caja</title>
                    <style>
                        body { font-family: Arial, sans-serif; font-size: 11px; margin: 0; padding: 10px; }
                        .cierre-header { text-align: center; margin-bottom: 15px; border-bottom: 2px dashed #000; padding-bottom: 10px; }
                        .resumen { margin: 10px 0; }
                        .resumen-row { display: flex; justify-content: space-between; margin: 5px 0; }
                        .total-row { font-weight: bold; border-top: 2px dashed #000; padding-top: 5px; }
                        @media print { body { margin: 0; padding: 0; } }
                    </style>
                </head>
                <body>
                    <div class="cierre-header">
                        <h3 style="margin: 0;">CIERRE DE CAJA</h3>
                        <p style="margin: 5px 0;">${new Date().toLocaleDateString()}</p>
                    </div>
                    <div class="resumen">
                        <div class="resumen-row">
                            <span>Efectivo:</span>
                            <span>$<?php echo number_format($total_efectivo, 2); ?></span>
                        </div>
                        <div class="resumen-row">
                            <span>Tarjeta:</span>
                            <span>$<?php echo number_format($total_tarjeta, 2); ?></span>
                        </div>
                        <div class="resumen-row">
                            <span>Transferencia:</span>
                            <span>$<?php echo number_format($total_transferencia, 2); ?></span>
                        </div>
                        <div class="resumen-row">
                            <span>Descuentos:</span>
                            <span>-$<?php echo number_format($total_descuentos, 2); ?></span>
                        </div>
                        <div class="resumen-row">
                            <span>Impuestos:</span>
                            <span>+$<?php echo number_format($total_impuestos, 2); ?></span>
                        </div>
                        <div class="resumen-row">
                            <span>Propinas:</span>
                            <span>$<?php echo number_format($total_propinas, 2); ?></span>
                        </div>
                        <div class="resumen-row total-row">
                            <span>TOTAL:</span>
                            <span>$<?php echo number_format($total_general, 2); ?></span>
                        </div>
                    </div>
                </body>
                </html>
            `);
            ventana.document.close();
            ventana.print();
        }
    </script>
</body>

</html>
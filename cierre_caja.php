<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

include 'data.php';
include 'security.php';
if (!isset($_SESSION['user'])) {
    header('Location: index.php');
    exit;
}

requirePermission('cierre_caja');

$user = $_SESSION['user'];
$cierre_actual = null;
$ventas_hoy = [];

// Obtener cierre actual si existe
if (($_POST['accion'] ?? null) === 'crear_cierre') {
    if (!is_string($_POST['csrf_token'] ?? null) || !validateCsrfToken($_POST['csrf_token'])) {
        die('Token CSRF inválido.');
    }
    $fecha_inicio = date('Y-m-d 00:00:00');
    $fecha_fin = date('Y-m-d 23:59:59');

    $fecha_siguiente = date('Y-m-d', strtotime('+1 day'));
    $stmt = $GLOBALS['pdo']->prepare(
        'SELECT * FROM ventas
         WHERE COALESCE(suspendida, 0) = 0 AND fecha >= ? AND fecha < ?'
    );
    $stmt->execute([$fecha_inicio, $fecha_siguiente . ' 00:00:00']);
    $ventasParaCierre = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $cierre_actual = [
        'total_ventas' => count($ventasParaCierre),
        'ventas_efectivo' => 0,
        'ventas_tarjeta' => 0,
        'ventas_transferencia' => 0,
        'ventas_otros' => 0,
        'total_ventas_valor' => 0,
        'total_descuentos' => 0,
        'total_impuestos' => 0,
        'total_propinas' => 0,
    ];
    foreach ($ventasParaCierre as $venta) {
        $totalCobrado = getTotalVentaCobrado($venta);
        $cierre_actual['total_ventas_valor'] += $totalCobrado;
        $cierre_actual['total_descuentos'] += (float) $venta['descuento'];
        $cierre_actual['total_impuestos'] += (float) $venta['impuesto'];
        $cierre_actual['total_propinas'] += (float) $venta['propina'];
        if ($venta['tipo_pago'] === 'Efectivo') {
            $cierre_actual['ventas_efectivo'] += $totalCobrado;
        } elseif ($venta['tipo_pago'] === 'Tarjeta') {
            $cierre_actual['ventas_tarjeta'] += $totalCobrado;
        } elseif ($venta['tipo_pago'] === 'Transferencia') {
            $cierre_actual['ventas_transferencia'] += $totalCobrado;
        } else {
            $cierre_actual['ventas_otros'] += $totalCobrado;
        }
    }

    // Guardar cierre
    $stmt_save = $GLOBALS['pdo']->prepare("
        INSERT INTO cierres_caja (usuario_id, fecha, total_ventas, total_efectivo, total_tarjeta, total_transferencia, total_otros, total_descuentos, total_impuestos, total_propinas)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt_save->execute([
        $user['id'],
        date('Y-m-d'),
        $cierre_actual['total_ventas_valor'] ?? 0,
        $cierre_actual['ventas_efectivo'] ?? 0,
        $cierre_actual['ventas_tarjeta'] ?? 0,
        $cierre_actual['ventas_transferencia'] ?? 0,
        $cierre_actual['ventas_otros'] ?? 0,
        $cierre_actual['total_descuentos'] ?? 0,
        $cierre_actual['total_impuestos'] ?? 0,
        $cierre_actual['total_propinas'] ?? 0
    ]);
}

// Obtener ventas del día para cierre
$fecha_inicio = date('Y-m-d 00:00:00');
$fecha_siguiente = date('Y-m-d', strtotime('+1 day'));

$stmt = $GLOBALS['pdo']->prepare("
    SELECT v.*, e.nombre as mesero_nombre 
    FROM ventas v 
    LEFT JOIN empleados e ON v.mesero_id = e.id
    WHERE COALESCE(v.suspendida, 0) = 0 AND v.fecha >= ? AND v.fecha < ?
    ORDER BY v.fecha DESC
");
$stmt->execute([$fecha_inicio, $fecha_siguiente . ' 00:00:00']);
$ventas_hoy = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Calcular totales
$total_efectivo = 0;
$total_tarjeta = 0;
$total_transferencia = 0;
$total_otros = 0;
$totales_por_metodo = [];
$total_descuentos = 0;
$total_impuestos = 0;
$total_propinas = 0;

foreach ($ventas_hoy as $venta) {
    $totalCobrado = getTotalVentaCobrado($venta);
    if ($venta['tipo_pago'] === 'Efectivo') {
        $total_efectivo += $totalCobrado;
    } elseif ($venta['tipo_pago'] === 'Tarjeta') {
        $total_tarjeta += $totalCobrado;
    } elseif ($venta['tipo_pago'] === 'Transferencia') {
        $total_transferencia += $totalCobrado;
    } else {
        $total_otros += $totalCobrado;
    }
    $metodo = $venta['tipo_pago'] ?: 'Sin método';
    $totales_por_metodo[$metodo] = ($totales_por_metodo[$metodo] ?? 0) + $totalCobrado;
    $total_descuentos += $venta['descuento'];
    $total_impuestos += $venta['impuesto'];
    $total_propinas += $venta['propina'];
}

$total_general = $total_efectivo + $total_tarjeta + $total_transferencia + $total_otros;
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

        .ventas-tabla-scroll {
            overflow-x: auto;
        }

        .ventas-tabla {
            width: 100%;
            min-width: 760px;
            border-collapse: collapse;
            font-size: 12px;
        }

        .ventas-vacia {
            padding: 24px !important;
            color: #64748b;
            text-align: center !important;
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
            <?php if ($total_otros > 0): ?>
                <div class="resumen-item">
                    <h4>Otros métodos</h4>
                    <div class="valor">$<?php echo number_format($total_otros, 2); ?></div>
                </div>
            <?php endif; ?>
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
                    $<?php echo number_format($total_general, 2); ?></div>
            </div>
        </div>

        <div class="metodos-pago">
            <h3>Desglose por Método de Pago</h3>
            <?php foreach ($totales_por_metodo as $metodo => $monto): ?>
                <div class="metodo-pago-row">
                    <span>Ventas con <?= htmlspecialchars($metodo) ?>:</span>
                    <strong>$<?php echo number_format($monto, 2); ?></strong>
                </div>
            <?php endforeach; ?>
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
            <div class="ventas-tabla-scroll">
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
                    <?php if ($ventas_hoy === []): ?>
                        <tr>
                            <td class="ventas-vacia" colspan="10">No hay ventas completadas registradas hoy.</td>
                        </tr>
                    <?php else: ?>
                      <?php foreach ($ventas_hoy as $venta): ?>
                        <?php
                        $ordenVenta = json_decode($venta['orden'] ?? '', true);
                        $subtotalVenta = is_array($ordenVenta)
                            ? array_sum(array_map(
                                static fn($item) => is_array($item)
                                    ? max(0, (float) ($item['precio'] ?? 0)) * max(1, (int) ($item['cantidad'] ?? 1))
                                    : 0,
                                $ordenVenta
                            ))
                            : 0;
                        ?>
                        <tr>
                            <td>#<?php echo $venta['id']; ?></td>
                            <td><?php echo (int) $venta['mesa'] === 0 ? 'Mostrador' : htmlspecialchars((string) $venta['mesa']); ?></td>
                            <td><?php echo htmlspecialchars($venta['mesero_nombre'] ?? 'N/A'); ?></td>
                            <td><?php echo htmlspecialchars($venta['tipo_pago'] ?? 'Sin método'); ?></td>
                            <td>$<?php echo number_format($subtotalVenta, 2); ?></td>
                            <td>-$<?php echo number_format($venta['descuento'], 2); ?></td>
                            <td>+$<?php echo number_format($venta['impuesto'], 2); ?></td>
                            <td>$<?php echo number_format($venta['propina'], 2); ?></td>
                            <td>$<?php echo number_format(getTotalVentaCobrado($venta), 2); ?></td>
                            <td><?php echo date('H:i:s', strtotime($venta['fecha'])); ?></td>
                        </tr>
                      <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
              </table>
            </div>
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
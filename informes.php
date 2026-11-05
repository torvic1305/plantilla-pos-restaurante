<?php
include 'data.php';
include 'security.php';
if (!isset($_SESSION['user'])) {
    header('Location: index.php');
    exit;
}
requirePermission('informes');
$desde = $_GET['desde'] ?? date('Y-m-d', strtotime('-30 days'));
$hasta = $_GET['hasta'] ?? date('Y-m-d');
$datePattern = '/^\d{4}-\d{2}-\d{2}$/';
$desdeDate = is_string($desde) && preg_match($datePattern, $desde) ? DateTimeImmutable::createFromFormat('!Y-m-d', $desde) : false;
$hastaDate = is_string($hasta) && preg_match($datePattern, $hasta) ? DateTimeImmutable::createFromFormat('!Y-m-d', $hasta) : false;
if (
    !$desdeDate || $desdeDate->format('Y-m-d') !== $desde ||
    !$hastaDate || $hastaDate->format('Y-m-d') !== $hasta ||
    $desdeDate > $hastaDate
) {
    http_response_code(400);
    exit('El rango de fechas no es válido.');
}

$hastaExclusivo = $hastaDate->modify('+1 day');
$stmtCompleted = $pdo->prepare("SELECT v.*, e.nombre AS mesero_nombre FROM ventas v LEFT JOIN empleados e ON v.mesero_id = e.id WHERE v.fecha >= ? AND v.fecha < ? AND v.suspendida = 0");
$stmtCompleted->execute([$desdeDate->format('Y-m-d 00:00:00'), $hastaExclusivo->format('Y-m-d 00:00:00')]);
$ventasCompletadas = $stmtCompleted->fetchAll(PDO::FETCH_ASSOC);
$totalCompletadas = 0;
foreach ($ventasCompletadas as $v) {
    $orden = json_decode($v['orden'], true);
    if (is_array($orden)) {
        $totalCompletadas += array_sum(array_column($orden, 'precio'));
    }
}

$stmtSuspendidas = $pdo->prepare("SELECT v.*, e.nombre AS mesero_nombre FROM ventas v LEFT JOIN empleados e ON v.mesero_id = e.id WHERE v.fecha >= ? AND v.fecha < ? AND v.suspendida = 1");
$stmtSuspendidas->execute([$desdeDate->format('Y-m-d 00:00:00'), $hastaExclusivo->format('Y-m-d 00:00:00')]);
$ventasSuspendidas = $stmtSuspendidas->fetchAll(PDO::FETCH_ASSOC);
$totalSuspendidas = 0;
foreach ($ventasSuspendidas as $v) {
    $orden = json_decode($v['orden'], true);
    if (is_array($orden)) {
        $totalSuspendidas += array_sum(array_column($orden, 'precio'));
    }
}
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Informes - Restaurante POS</title>
    <link rel="stylesheet" href="style.php">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
</head>

<body>
    <header>
        <h1>Informes</h1>
        <a href="dashboard.php">Volver</a>
    </header>
    <main>
        <h2>Rango de Fechas</h2>
        <form method="get">
            <label>Desde:</label>
            <input type="date" name="desde" value="<?php echo htmlspecialchars($desde, ENT_QUOTES | ENT_HTML5, 'UTF-8'); ?>">
            <label>Hasta:</label>
            <input type="date" name="hasta" value="<?php echo htmlspecialchars($hasta, ENT_QUOTES | ENT_HTML5, 'UTF-8'); ?>">
            <button type="submit">Filtrar</button>
        </form>
        <section>
            <h2>Ventas Completadas</h2>
            <p>Total ventas completadas: <?php echo count($ventasCompletadas); ?></p>
            <p>Monto total completado: $<?php echo number_format($totalCompletadas, 2); ?></p>
            <table border="1" style="width:100%; border-collapse:collapse; margin-top:10px;">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Mesa</th>
                        <th>Mesero</th>
                        <th>Pago</th>
                        <th>Total</th>
                        <th>Propina</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($ventasCompletadas as $v) {
                        $orden = json_decode($v['orden'], true);
                        $total = is_array($orden) ? array_sum(array_column($orden, 'precio')) : 0;
                        $propina = $v['propina'] ?? 0;
                        $acciones = $propina == 0 ? "<button onclick='agregarPropina(" . intval($v['id']) . ")'>Agregar Propina</button>" : '';
                        echo "<tr><td>" . intval($v['id']) . "</td><td>" . intval($v['mesa']) . "</td><td>" . htmlspecialchars($v['mesero_nombre']) . "</td><td>" . htmlspecialchars($v['tipo_pago']) . "</td><td>$" . number_format($total, 2) . "</td><td>$" . number_format($propina, 2) . "</td><td>" . $acciones . "</td></tr>";
                    } ?>
                </tbody>
            </table>
        </section>
        <section style="margin-top:24px;">
            <h2>Ventas Suspendidas</h2>
            <p>Total ventas suspendidas: <?php echo count($ventasSuspendidas); ?></p>
            <p>Monto total suspendido: $<?php echo number_format($totalSuspendidas, 2); ?></p>
            <table border="1" style="width:100%; border-collapse:collapse; margin-top:10px;">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Mesa</th>
                        <th>Mesero</th>
                        <th>Items</th>
                        <th>Total</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($ventasSuspendidas as $v) {
                        $orden = json_decode($v['orden'], true);
                        $total = is_array($orden) ? array_sum(array_column($orden, 'precio')) : 0;
                        $items = is_array($orden) ? count($orden) : 0;
                        $orden_json = htmlspecialchars(json_encode($orden), ENT_QUOTES, 'UTF-8');
                        $acciones = "<button class='print-pre-recibo' data-orden='" . $orden_json . "' data-mesa='" . intval($v['mesa']) . "'>Imprimir Pre-Recibo</button>";
                        echo "<tr><td>" . intval($v['id']) . "</td><td>" . intval($v['mesa']) . "</td><td>" . htmlspecialchars($v['mesero_nombre']) . "</td><td>" . intval($items) . "</td><td>$" . number_format($total, 2) . "</td><td>" . $acciones . "</td></tr>";
                    } ?>
                </tbody>
            </table>
        </section>
    </main>
    <!-- Modal para agregar propina -->
    <div id="propina-modal"
        style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.5); z-index:1000; justify-content:center; align-items:center;">
        <div style="background:white; padding:20px; border-radius:8px; max-width:400px; width:90%;">
            <h3>Agregar Propina</h3>
            <p>ID Venta: <span id="venta-propina-id"></span></p>
            <label>Propina: $<input type="number" id="propina-input" min="0" step="0.01" value="0"></label>
            <div style="margin-top:20px;">
                <button id="guardar-propina-modal">Guardar</button>
                <button id="cancelar-propina-modal">Cancelar</button>
            </div>
        </div>
    </div>
    <script>
        function agregarPropina(id) {
            document.getElementById('venta-propina-id').textContent = id;
            document.getElementById('propina-modal').style.display = 'flex';
        }

        const csrfToken = '<?= htmlspecialchars(getCsrfToken(), ENT_QUOTES | ENT_HTML5, 'UTF-8') ?>';

        document.getElementById('guardar-propina-modal').addEventListener('click', function () {
            const id = document.getElementById('venta-propina-id').textContent;
            const propina = parseFloat(document.getElementById('propina-input').value) || 0;
            fetch('actualizar_propina.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrfToken },
                body: JSON.stringify({ id: id, propina: propina, csrf_token: csrfToken })
            })
                .then(response => response.json())
                .then(data => {
                    if (data.status === 'ok') {
                        alert('Propina agregada correctamente.');
                        location.reload();
                    } else {
                        alert(data.message || 'Error al agregar propina');
                    }
                });
            document.getElementById('propina-modal').style.display = 'none';
        });

        document.getElementById('cancelar-propina-modal').addEventListener('click', function () {
            document.getElementById('propina-modal').style.display = 'none';
        });

        document.querySelectorAll('.print-pre-recibo').forEach(button => {
            button.addEventListener('click', function () {
                try {
                    const orden = JSON.parse(this.dataset.orden || '[]');
                    const mesa = parseInt(this.dataset.mesa, 10) || 0;
                    imprimirPreReciboSuspendida(orden, mesa);
                } catch (error) {
                    alert('No se pudo cargar la orden para imprimir.');
                }
            });
        });

        function imprimirPreReciboSuspendida(orden, mesa) {
            const { jsPDF } = window.jspdf;
            const doc = new jsPDF();
            doc.setFontSize(20);
            doc.text('Pre-Recibo - Restaurante', 20, 20);
            doc.setFontSize(12);
            doc.text(`Fecha: ${new Date().toLocaleString()}`, 20, 35);
            doc.text(`Mesa: ${mesa}`, 20, 45);
            let y = 60;
            doc.text('Items:', 20, y);
            y += 10;
            orden.forEach(item => {
                doc.text(`${item.nombre} - $${parseFloat(item.precio).toFixed(2)}`, 30, y);
                y += 10;
            });
            const subtotal = orden.reduce((sum, item) => sum + parseFloat(item.precio), 0);
            y += 10;
            doc.text(`Subtotal: $${subtotal.toFixed(2)}`, 20, y);
            // Nota: Para suspendidas, no aplicamos descuento aquí, pero si se quiere, se puede calcular
            y += 10;
            doc.text(`Total: $${subtotal.toFixed(2)}`, 20, y);
            doc.save('pre-recibo-suspendida.pdf');
        }
    </script>
    <script src="2.js"></script>
</body>

</html>
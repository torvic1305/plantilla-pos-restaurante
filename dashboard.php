<?php
error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');

include 'data.php';
include 'security.php';
if (!isset($_SESSION['user'])) {
    header('Location: index.php');
    exit;
}

$user = $_SESSION['user'];
$employeeState = getEstadoEmpleado((int) $user['id']);
if (
    !$employeeState
    || !empty($employeeState['suspendido'])
    || $employeeState['eliminado_en'] !== null
) {
    http_response_code(403);
    exit('La cuenta de este empleado está suspendida o archivada. Contacte a un gerente.');
}
$permisos = getPermisos((int) $user['id']);
$_SESSION['user']['permisos'] = $permisos;
$hoy = date('Y-m-d');
$appConfig = getAppConfig();
$cashAlertThreshold = (float) ($appConfig['cash_alert_threshold'] ?? 0);

$ventasHoy = ['ventas' => 0, 'ingresos' => 0, 'efectivo' => 0];
$canViewSales = !empty($permisos['informes']) || !empty($permisos['cierre_caja']);
if ($canViewSales) {
    $manana = date('Y-m-d', strtotime($hoy . ' +1 day'));
    $stmtVentas = $pdo->prepare(
        'SELECT tipo_pago, monto_pagado, orden, descuento, impuesto
         FROM ventas WHERE COALESCE(suspendida, 0) = 0 AND fecha >= ? AND fecha < ?'
    );
    $stmtVentas->execute([$hoy . ' 00:00:00', $manana . ' 00:00:00']);
    foreach ($stmtVentas->fetchAll(PDO::FETCH_ASSOC) as $venta) {
        $totalCobrado = getTotalVentaCobrado($venta);
        $ventasHoy['ventas']++;
        $ventasHoy['ingresos'] += $totalCobrado;
        if ($venta['tipo_pago'] === 'Efectivo') {
            $ventasHoy['efectivo'] += $totalCobrado;
        }
    }
}

$canViewDashboardChart = !empty($permisos['dashboard_grafica']);
$chartPeriodOptions = [
    'hoy' => 'Hoy',
    'ayer' => 'Ayer',
    'semana' => 'Esta semana',
    'mes' => 'Este mes',
    'este_anio' => 'Este año',
    'anio_pasado' => 'Año pasado',
    'rango' => 'Rango personalizado',
];
$chartPeriod = isset($_GET['grafica_periodo']) && is_string($_GET['grafica_periodo'])
    ? $_GET['grafica_periodo']
    : 'semana';
if (!isset($chartPeriodOptions[$chartPeriod])) {
    $chartPeriod = 'semana';
}
$chartRangeError = '';
$customStartInput = isset($_GET['desde']) && is_string($_GET['desde']) ? $_GET['desde'] : '';
$customEndInput = isset($_GET['hasta']) && is_string($_GET['hasta']) ? $_GET['hasta'] : '';
$parseDate = static function ($value) {
    if (!is_string($value) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
        return null;
    }
    $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
    $errors = DateTimeImmutable::getLastErrors();
    return $date && ($errors === false || ($errors['warning_count'] === 0 && $errors['error_count'] === 0))
        && $date->format('Y-m-d') === $value
        ? $date
        : null;
};
$todayDate = new DateTimeImmutable($hoy);
$chartStart = $todayDate->modify('monday this week');
$chartEnd = $todayDate;
if ($chartPeriod === 'hoy') {
    $chartStart = $todayDate;
} elseif ($chartPeriod === 'ayer') {
    $chartStart = $todayDate->modify('-1 day');
    $chartEnd = $chartStart;
} elseif ($chartPeriod === 'mes') {
    $chartStart = $todayDate->modify('first day of this month');
} elseif ($chartPeriod === 'este_anio') {
    $chartStart = $todayDate->setDate((int) $todayDate->format('Y'), 1, 1);
} elseif ($chartPeriod === 'anio_pasado') {
    $chartStart = $todayDate->modify('first day of january last year');
    $chartEnd = $todayDate->modify('last day of december last year');
} elseif ($chartPeriod === 'rango') {
    $requestedStart = $parseDate($customStartInput);
    $requestedEnd = $parseDate($customEndInput);
    if (!$requestedStart || !$requestedEnd || $requestedStart > $requestedEnd) {
        $chartRangeError = 'Indica fechas válidas y asegúrate de que la fecha inicial no sea posterior a la final.';
        $chartPeriod = 'semana';
        $chartStart = $todayDate->modify('monday this week');
        $chartEnd = $todayDate;
    } elseif ($requestedStart->diff($requestedEnd)->days > 3650) {
        $chartRangeError = 'El rango personalizado no puede superar diez años.';
        $chartPeriod = 'semana';
        $chartStart = $todayDate->modify('monday this week');
        $chartEnd = $todayDate;
    } else {
        $chartStart = $requestedStart;
        $chartEnd = $requestedEnd;
    }
}
$chartFrom = $chartStart->format('Y-m-d');
$chartUntil = $chartEnd->format('Y-m-d');
$chartPeriodLabel = $chartPeriodOptions[$chartPeriod] ?? $chartPeriodOptions['semana'];
$chartBuckets = [];
if ($canViewDashboardChart) {
    $useMonthlyBuckets = in_array($chartPeriod, ['este_anio', 'anio_pasado'], true)
        || ($chartPeriod === 'rango' && $chartStart->diff($chartEnd)->days > 31);
    if ($useMonthlyBuckets) {
        $bucketDate = $chartStart->modify('first day of this month');
        $lastBucketDate = $chartEnd->modify('first day of this month');
        while ($bucketDate <= $lastBucketDate) {
            $key = $bucketDate->format('Y-m');
            $chartBuckets[$key] = [
                'fecha' => $key,
                'etiqueta' => $bucketDate->format('M Y'),
                'ingresos' => 0.0,
            ];
            $bucketDate = $bucketDate->modify('+1 month');
        }
    } else {
        for ($bucketDate = $chartStart; $bucketDate <= $chartEnd; $bucketDate = $bucketDate->modify('+1 day')) {
            $key = $bucketDate->format('Y-m-d');
            $chartBuckets[$key] = [
                'fecha' => $key,
                'etiqueta' => $bucketDate->format('d/m'),
                'ingresos' => 0.0,
            ];
        }
    }

    $chartEndExclusive = $chartEnd->modify('+1 day')->format('Y-m-d');
    $chartSales = $pdo->prepare(
        'SELECT fecha, tipo_pago, monto_pagado, orden, descuento, impuesto
         FROM ventas
         WHERE COALESCE(suspendida, 0) = 0 AND fecha >= ? AND fecha < ?
         ORDER BY fecha'
    );
    $chartSales->execute([$chartFrom . ' 00:00:00', $chartEndExclusive . ' 00:00:00']);
    foreach ($chartSales->fetchAll(PDO::FETCH_ASSOC) as $sale) {
        $saleDate = new DateTimeImmutable($sale['fecha']);
        $key = $useMonthlyBuckets ? $saleDate->format('Y-m') : $saleDate->format('Y-m-d');
        if (isset($chartBuckets[$key])) {
            $chartBuckets[$key]['ingresos'] += getTotalVentaCobrado($sale);
        }
    }
}

$stmtMenu = $pdo->query('SELECT COUNT(*) FROM menu');
$totalProductos = (int) $stmtMenu->fetchColumn();
$maxChartIncome = max(array_merge([1], array_column($chartBuckets, 'ingresos')));
$lowStockItems = !empty($permisos['inventario_ver']) ? getInventarioBajoStock() : [];
$formatStockQuantity = static function ($quantity) {
    return rtrim(rtrim(number_format((float) $quantity, 3, '.', ''), '0'), '.');
};

$menuNavegacion = [
    ['Panel', 'dashboard.php', '⌂', true],
    ['Punto de Venta', 'pos.php', '▣', $permisos['pos'] ?? false],
    ['Menú', 'menu.php', '☷', $permisos['menu_ver'] ?? false],
    ['Clientes', 'clientes.php', '♙', $permisos['clientes_ver'] ?? false],
    ['Inventario', 'inventario.php', '▤', $permisos['inventario_ver'] ?? false],
    ['Compras', 'compras.php', '▧', $permisos['inventario_ver'] ?? false],
    ['Empleados', 'empleados.php', '♧', $permisos['empleados'] ?? false],
    ['Informes', 'informes.php', '▥', $permisos['informes'] ?? false],
    ['Reportes Avanzados', 'reportes_avanzados.php', '▧', $permisos['informes'] ?? false],
    ['Propinas', 'propinas.php', '$', $permisos['propinas'] ?? false],
    ['Merma', 'merma.php', '−', $permisos['mermas'] ?? false],
    ['Promociones', 'promociones.php', '★', $permisos['promociones'] ?? false],
    ['Cocina', 'cocina.php', '♨', $permisos['cocina'] ?? false],
    ['Cierre de Caja', 'cierre_caja.php', '▣', $permisos['cierre_caja'] ?? false],
    ['Configuración', 'config.php', '⚙', $permisos['config'] ?? false],
    ['Cerrar Sesión', 'logout.php', '↪', true],
];

$accionesRapidas = [
    ['Iniciar una venta nueva', 'pos.php', '🛒', $permisos['pos'] ?? false],
    ['Administrar menú', 'menu.php', '☷', $permisos['menu_ver'] ?? false],
    ['Revisar inventario', 'inventario.php', '▤', $permisos['inventario_ver'] ?? false],
    [!empty($permisos['inventario_editar']) ? 'Registrar compras' : 'Consultar compras', 'compras.php', '▧', $permisos['inventario_ver'] ?? false],
    ['Ver informe de ventas', 'informes.php', '▥', $permisos['informes'] ?? false],
    ['Gestionar empleados', 'empleados.php', '♧', $permisos['empleados'] ?? false],
    ['Registrar merma', 'merma.php', '−', $permisos['mermas'] ?? false],
];
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Panel - Restaurante POS</title>
    <link rel="stylesheet" href="style.php">
</head>

<body class="app-layout">
    <aside class="sidebar">
        <div class="sidebar-brand">RESTAURANTE POS</div>
        <nav aria-label="Navegación principal">
            <?php foreach ($menuNavegacion as [$etiqueta, $url, $icono, $visible]): ?>
                <?php if ($visible): ?>
                    <a href="<?= htmlspecialchars($url) ?>" class="<?= $url === 'dashboard.php' ? 'active' : '' ?>">
                        <span class="sidebar-icon" aria-hidden="true"><?= htmlspecialchars($icono) ?></span>
                        <span><?= htmlspecialchars($etiqueta) ?></span>
                    </a>
                <?php endif; ?>
            <?php endforeach; ?>
        </nav>
    </aside>

    <div class="app-main">
        <header class="topbar">
            <span class="breadcrumb"><?= htmlspecialchars($appConfig['nombre_restaurante']) ?> <span aria-hidden="true">/</span> Panel</span>
            <span class="user-name"><?= htmlspecialchars($user['nombre']) ?></span>
        </header>

        <main class="dashboard-content">
            <div class="page-heading">
                <div>
                    <h1>Panel principal</h1>
                    <p>Resumen de la actividad de hoy en tu restaurante.</p>
                </div>
                <?php if ($permisos['pos'] ?? false): ?>
                    <a class="dashboard-primary-action" href="pos.php">+ Nueva venta</a>
                <?php endif; ?>
            </div>

            <?php if (!empty($permisos['pos'])): ?>
                <section class="dashboard-panel sales-launch-panel">
                    <div>
                        <h2>Panel de venta</h2>
                        <p>Abre el punto de venta para seleccionar mesa, agregar productos y cobrar.</p>
                    </div>
                    <a class="dashboard-primary-action" href="pos.php">Comenzar una venta</a>
                </section>
            <?php endif; ?>

            <section class="stat-grid" aria-label="Resumen del sistema">
                <?php if ($canViewSales): ?>
                    <article class="stat-card">
                        <div class="stat-copy">
                            <span class="stat-value"><?= number_format((int) $ventasHoy['ventas']) ?></span>
                            <span class="stat-label">Ventas de hoy</span>
                        </div>
                        <div class="stat-icon green" aria-hidden="true">▣</div>
                    </article>
                    <article class="stat-card">
                        <div class="stat-copy">
                            <span class="stat-value">$<?= number_format((float) $ventasHoy['ingresos'], 2) ?></span>
                            <span class="stat-label">Ingresos de hoy</span>
                        </div>
                        <div class="stat-icon red" aria-hidden="true">$</div>
                    </article>
                <?php endif; ?>
                <article class="stat-card">
                    <div class="stat-copy">
                        <span class="stat-value"><?= number_format($totalProductos) ?></span>
                        <span class="stat-label">Productos del menú</span>
                    </div>
                    <div class="stat-icon orange" aria-hidden="true">☷</div>
                </article>
            </section>

            <p class="dashboard-welcome">¡Bienvenido, <?= htmlspecialchars($user['nombre']) ?>! Selecciona una opción para comenzar.</p>

            <nav class="quick-grid" aria-label="Accesos rápidos">
                <?php foreach ($accionesRapidas as [$etiqueta, $url, $icono, $visible]): ?>
                    <?php if ($visible): ?>
                        <a class="quick-link" href="<?= htmlspecialchars($url) ?>">
                            <span class="quick-icon" aria-hidden="true"><?= htmlspecialchars($icono) ?></span>
                            <span><?= htmlspecialchars($etiqueta) ?></span>
                        </a>
                    <?php endif; ?>
                <?php endforeach; ?>
            </nav>

            <?php if ($canViewDashboardChart): ?>
                <section class="dashboard-panel">
                    <div class="sales-chart-heading">
                        <div>
                            <h2>Gráfica de ventas</h2>
                            <p><?= htmlspecialchars($chartPeriodLabel, ENT_QUOTES | ENT_HTML5, 'UTF-8') ?> · <?= htmlspecialchars($chartFrom, ENT_QUOTES | ENT_HTML5, 'UTF-8') ?> al <?= htmlspecialchars($chartUntil, ENT_QUOTES | ENT_HTML5, 'UTF-8') ?></p>
                        </div>
                        <form method="get" action="dashboard.php" class="sales-chart-filters">
                            <label>Periodo
                                <select name="grafica_periodo" id="grafica-periodo">
                                    <?php foreach ($chartPeriodOptions as $value => $label): ?>
                                        <option value="<?= htmlspecialchars($value, ENT_QUOTES | ENT_HTML5, 'UTF-8') ?>" <?= $chartPeriod === $value ? 'selected' : '' ?>><?= htmlspecialchars($label, ENT_QUOTES | ENT_HTML5, 'UTF-8') ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </label>
                            <div id="rango-personalizado" class="sales-chart-date-range" <?= $chartPeriod !== 'rango' ? 'hidden' : '' ?>>
                                <label>Desde
                                    <input type="date" name="desde" value="<?= htmlspecialchars($customStartInput !== '' ? $customStartInput : $chartFrom, ENT_QUOTES | ENT_HTML5, 'UTF-8') ?>">
                                </label>
                                <label>Hasta
                                    <input type="date" name="hasta" value="<?= htmlspecialchars($customEndInput !== '' ? $customEndInput : $chartUntil, ENT_QUOTES | ENT_HTML5, 'UTF-8') ?>">
                                </label>
                            </div>
                            <button type="submit">Actualizar gráfica</button>
                        </form>
                    </div>
                    <?php if ($chartRangeError !== ''): ?>
                        <p class="settings-message error" role="alert"><?= htmlspecialchars($chartRangeError, ENT_QUOTES | ENT_HTML5, 'UTF-8') ?></p>
                    <?php endif; ?>
                    <?php if (array_sum(array_column($chartBuckets, 'ingresos')) <= 0): ?>
                        <p class="dashboard-no-data">No hay ventas completadas para el periodo seleccionado.</p>
                    <?php endif; ?>
                    <?php if ($chartBuckets): ?>
                        <div class="chart-scroll">
                            <div class="chart-bars" role="img" aria-label="Ingresos de ventas: <?= htmlspecialchars($chartPeriodLabel, ENT_QUOTES | ENT_HTML5, 'UTF-8') ?>">
                                <?php foreach ($chartBuckets as $bucket): ?>
                                    <?php $height = $bucket['ingresos'] > 0 ? max(5, ($bucket['ingresos'] / $maxChartIncome) * 100) : 2; ?>
                                    <div class="chart-day" title="<?= htmlspecialchars($bucket['fecha'], ENT_QUOTES | ENT_HTML5, 'UTF-8') ?>: $<?= number_format($bucket['ingresos'], 2) ?>">
                                        <span>$<?= number_format($bucket['ingresos'], 0) ?></span>
                                        <div class="chart-bar" style="height: <?= number_format($height, 2, '.', '') ?>%"></div>
                                        <span><?= htmlspecialchars($bucket['etiqueta'], ENT_QUOTES | ENT_HTML5, 'UTF-8') ?></span>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                </section>
                <script>
                    document.getElementById('grafica-periodo').addEventListener('change', function () {
                        document.getElementById('rango-personalizado').hidden = this.value !== 'rango';
                    });
                </script>
            <?php endif; ?>

            <?php if ($canViewSales && $cashAlertThreshold > 0 && (float) $ventasHoy['efectivo'] >= $cashAlertThreshold): ?>
                <section class="stock-alert cash-alert">
                    <h2>Umbral de efectivo alcanzado</h2>
                    <p>La caja registra $<?= number_format((float) $ventasHoy['efectivo'], 2) ?> en efectivo hoy; el umbral configurado es $<?= number_format($cashAlertThreshold, 2) ?>.</p>
                    <?php if (!empty($permisos['cierre_caja'])): ?><a href="cierre_caja.php">Revisar cierre de caja</a><?php endif; ?>
                </section>
            <?php endif; ?>

            <?php if (!empty($lowStockItems)): ?>
                <section class="stock-alert">
                    <h2>Alerta de inventario bajo</h2>
                    <p><?= count($lowStockItems) ?> producto(s) están en el mínimo o por debajo:</p>
                    <ul>
                        <?php foreach ($lowStockItems as $item): ?>
                            <li>
                                <?= htmlspecialchars($item['item'], ENT_QUOTES | ENT_HTML5, 'UTF-8') ?> — Existencia:
                                <?= $formatStockQuantity($item['cantidad']) ?> <?= htmlspecialchars($item['unidad'] ?? 'pieza', ENT_QUOTES | ENT_HTML5, 'UTF-8') ?>
                                / mínimo: <?= $formatStockQuantity($item['cantidad_minima']) ?>
                                <?php if (!empty($permisos['inventario_editar'])): ?>
                                    <a href="inventario.php#inventory-item-<?= (int) $item['id'] ?>">Reponer o ajustar</a>
                                <?php endif; ?>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                    <a href="inventario.php">Revisar inventario</a>
                </section>
            <?php endif; ?>
        </main>
    </div>
    <script src="utilidades.js"></script>
</body>

</html>

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
$permisos = getPermisos((int) $user['id']);
$_SESSION['user']['permisos'] = $permisos;
$hoy = date('Y-m-d');
$desdeSemana = date('Y-m-d', strtotime('-6 days'));
$appConfig = getAppConfig();
$cashAlertThreshold = (float) ($appConfig['cash_alert_threshold'] ?? 0);

$ventasHoy = ['ventas' => 0, 'ingresos' => 0, 'efectivo' => 0];
$ingresosSemana = [];
$canViewSales = !empty($permisos['informes']) || !empty($permisos['cierre_caja']);
if ($canViewSales) {
    $manana = date('Y-m-d', strtotime($hoy . ' +1 day'));
    $stmtVentas = $pdo->prepare(
        'SELECT * FROM ventas WHERE COALESCE(suspendida, 0) = 0 AND fecha >= ? AND fecha < ?'
    );
    $stmtVentas->execute([$desdeSemana . ' 00:00:00', $manana . ' 00:00:00']);
    foreach ($stmtVentas->fetchAll(PDO::FETCH_ASSOC) as $venta) {
        $totalCobrado = getTotalVentaCobrado($venta);
        $dia = date('Y-m-d', strtotime($venta['fecha']));
        $ingresosSemana[$dia] = ($ingresosSemana[$dia] ?? 0) + $totalCobrado;
        if ($dia === $hoy) {
            $ventasHoy['ventas']++;
            $ventasHoy['ingresos'] += $totalCobrado;
            if ($venta['tipo_pago'] === 'Efectivo') {
                $ventasHoy['efectivo'] += $totalCobrado;
            }
        }
    }
}

$stmtMenu = $pdo->query('SELECT COUNT(*) FROM menu');
$totalProductos = (int) $stmtMenu->fetchColumn();
$maxIngresoSemana = max(array_merge([1], array_values($ingresosSemana)));
$diasSemana = ['Dom', 'Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb'];
$graficaSemana = [];
for ($i = 6; $i >= 0; $i--) {
    $dia = date('Y-m-d', strtotime("-$i days"));
    $graficaSemana[] = [
        'fecha' => $dia,
        'etiqueta' => $diasSemana[(int) date('w', strtotime($dia))],
        'ingresos' => $ingresosSemana[$dia] ?? 0,
    ];
}
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

            <section class="dashboard-panel">
                <h2>Información de ventas · últimos 7 días</h2>
                <?php if (!$canViewSales): ?>
                    <p class="dashboard-no-data">Tu cuenta no tiene permiso para ver los importes de ventas.</p>
                <?php elseif (array_sum($ingresosSemana) <= 0): ?>
                    <p class="dashboard-no-data">No hay ventas completadas en los últimos 7 días.</p>
                <?php endif; ?>
                <?php if ($canViewSales): ?>
                    <div class="chart-bars" role="img" aria-label="Ingresos diarios de los últimos siete días">
                        <?php foreach ($graficaSemana as $dia): ?>
                            <?php $altura = $dia['ingresos'] > 0 ? max(5, ($dia['ingresos'] / $maxIngresoSemana) * 100) : 2; ?>
                            <div class="chart-day" title="<?= htmlspecialchars($dia['fecha']) ?>: $<?= number_format($dia['ingresos'], 2) ?>">
                                <span>$<?= number_format($dia['ingresos'], 0) ?></span>
                                <div class="chart-bar" style="height: <?= number_format($altura, 2, '.', '') ?>%"></div>
                                <span><?= htmlspecialchars($dia['etiqueta']) ?></span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </section>

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
    <script src="2.js"></script>
</body>

</html>

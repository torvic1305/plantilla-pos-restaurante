<?php
include 'data.php';
include 'security.php';
if (!isset($_SESSION['user'])) {
    header('Location: index.php');
    exit;
}
requirePermission('ventas_suspendidas_ver');

$searchInput = $_GET['search'] ?? '';
$search = is_string($searchInput) ? sanitizeString($searchInput, 50) : '';
$loadError = null;
try {
    $ventas = getVentasSuspendidas($search);
} catch (Throwable $e) {
    error_log('Unable to render suspended sales page: ' . $e->getMessage());
    $ventas = [];
    $loadError = 'No se pudieron cargar las ventas en espera. Verifica la conexión con la base de datos e inténtalo de nuevo.';
}
$escape = static fn($value) => htmlspecialchars((string) $value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
$actualizadaInput = $_GET['actualizada'] ?? '';
$actualizadaId = is_string($actualizadaInput)
    ? filter_var($actualizadaInput, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]])
    : false;
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ventas en espera - Restaurante POS</title>
    <link rel="stylesheet" href="style.php">
</head>
<body class="pos-page workflow-page">
    <header class="pos-header">
        <div class="pos-header-brand">
            <span class="pos-brand-mark" aria-hidden="true">R</span>
            <div>
                <span class="pos-eyebrow">RESTAURANTE POS</span>
                <h1>Ventas en espera</h1>
            </div>
        </div>
        <div class="pos-header-actions">
            <a class="pos-notification-link" href="pedidos_domicilio.php" aria-label="Notificaciones de reparto" title="Notificaciones de reparto">
                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M18 8a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9M10 21h4"/></svg>
            </a>
            <span class="pos-user-chip"><span class="pos-status-dot" aria-hidden="true"></span><?= $escape($_SESSION['user']['nombre'] ?? '') ?></span>
            <a href="pos.php"><span aria-hidden="true">‹</span> Volver al punto de venta</a>
        </div>
    </header>
    <main class="workflow-main">
        <div class="workflow-heading">
            <div>
                <span class="pos-section-kicker">PEDIDOS GUARDADOS</span>
                <h2>Ventas suspendidas</h2>
                <p>Consulta los productos y datos de cada venta. Selecciona una para editarla o completar el cobro.</p>
            </div>
            <a class="workflow-back-link" href="pos.php">+ Nueva venta</a>
        </div>
        <?php if ($actualizadaId): ?>
            <div class="workflow-card" role="status">La venta #<?= (int) $actualizadaId ?> se actualizó correctamente.</div>
        <?php endif; ?>
        <section class="workflow-card" aria-labelledby="pending-list-title">
            <div class="workflow-toolbar">
                <h3 id="pending-list-title">Lista de ventas en espera</h3>
                <span class="workflow-count"><?= $loadError ? 'No disponible' : count($ventas) . (count($ventas) === 1 ? ' venta' : ' ventas') ?></span>
            </div>
            <form class="workflow-search" method="get" action="ventas_suspendidas.php">
                <label class="visually-hidden" for="pending-search">Buscar por folio, mesa o mesero</label>
                <input id="pending-search" type="search" name="search" value="<?= $escape($search) ?>" placeholder="Buscar folio, mesa o mesero..." autocomplete="off">
                <button type="submit">Buscar</button>
            </form>
            <div class="pending-sale-list" style="margin-top:14px;">
                <?php if ($loadError): ?>
                    <div class="workflow-empty" role="alert"><strong>No se cargó la lista</strong><?= $escape($loadError) ?></div>
                <?php elseif (!$ventas): ?>
                    <div class="workflow-empty">
                        <strong><?= $search !== '' ? 'No se encontraron ventas' : 'No hay ventas en espera' ?></strong>
                        <?= $search !== '' ? 'Prueba con otro folio, mesa o mesero.' : 'Las ventas que suspendas desde el punto de venta aparecerán aquí.' ?>
                    </div>
                <?php else: ?>
                    <?php foreach ($ventas as $venta):
                        $decodedOrder = json_decode((string) ($venta['orden'] ?? ''), true);
                        $orderItems = is_array($decodedOrder) ? $decodedOrder : [];
                        $subtotal = 0.0;
                        $itemCount = 0;
                        foreach ($orderItems as $item) {
                            if (!is_array($item)) {
                                continue;
                            }
                            $quantity = max(1, (int) ($item['cantidad'] ?? 1));
                            $price = is_numeric($item['precio'] ?? null) ? max(0, (float) $item['precio']) : 0;
                            $subtotal += $price * $quantity;
                            $itemCount += $quantity;
                        }
                        $mesa = (int) ($venta['mesa'] ?? 0);
                        $fecha = strtotime((string) ($venta['fecha'] ?? ''));
                        $fechaLabel = $fecha === false ? 'Fecha no disponible' : date('d/m/Y H:i', $fecha);
                    ?>
                        <article class="pending-sale-card">
                            <div class="pending-sale-heading">
                                <div>
                                    <h3>Folio #<?= (int) ($venta['id'] ?? 0) ?></h3>
                                    <div class="pending-sale-meta">
                                        <span><?= $mesa === 0 ? 'Mostrador' : 'Mesa ' . $mesa ?></span>
                                        <span>Mesero: <?= $escape($venta['mesero_nombre'] ?? 'Sin mesero') ?></span>
                                        <span><?= $escape($fechaLabel) ?></span>
                                        <span><?= $itemCount ?> <?= $itemCount === 1 ? 'artículo' : 'artículos' ?></span>
                                    </div>
                                </div>
                                <strong class="pending-sale-subtotal">$<?= number_format($subtotal, 2) ?></strong>
                            </div>
                            <?php if ($orderItems): ?>
                                <ul class="pending-sale-items">
                                    <?php foreach ($orderItems as $item):
                                        if (!is_array($item)) {
                                            continue;
                                        }
                                        $quantity = max(1, (int) ($item['cantidad'] ?? 1));
                                        $price = is_numeric($item['precio'] ?? null) ? max(0, (float) $item['precio']) : 0;
                                    ?>
                                        <li>
                                            <span>
                                                <?= $quantity ?> × <?= $escape($item['nombre'] ?? 'Artículo') ?>
                                                <?php if (!empty($item['nota'])): ?><small>Nota: <?= $escape($item['nota']) ?></small><?php endif; ?>
                                            </span>
                                            <strong>$<?= number_format($price * $quantity, 2) ?></strong>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            <?php else: ?>
                                <div class="workflow-empty">Esta venta no contiene artículos válidos.</div>
                            <?php endif; ?>
                            <div class="pending-sale-footer">
                                <span>Subtotal de productos; impuestos y descuentos se calculan al cobrar.</span>
                                <a class="workflow-action-link" href="pos.php?suspendida=<?= (int) ($venta['id'] ?? 0) ?>">Editar y cobrar</a>
                            </div>
                        </article>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </section>
    </main>
</body>
</html>

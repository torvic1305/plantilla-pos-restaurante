<?php
include 'data.php';
include 'security.php';
if (!isset($_SESSION['user'])) {
    header('Location: index.php');
    exit;
}
requirePermission('pos');
$escape = static fn($value) => htmlspecialchars((string) $value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pedidos a domicilio - Restaurante POS</title>
    <link rel="stylesheet" href="style.php">
</head>
<body class="pos-page workflow-page">
    <header class="pos-header">
        <div class="pos-header-brand">
            <span class="pos-brand-mark" aria-hidden="true">R</span>
            <div>
                <span class="pos-eyebrow">RESTAURANTE POS</span>
                <h1>Pedidos a domicilio</h1>
            </div>
        </div>
        <div class="pos-header-actions">
            <span class="pos-user-chip"><span class="pos-status-dot" aria-hidden="true"></span><?= $escape($_SESSION['user']['nombre'] ?? '') ?></span>
            <a href="pos.php"><span aria-hidden="true">‹</span> Volver al punto de venta</a>
        </div>
    </header>
    <main class="workflow-main">
        <div class="workflow-heading">
            <div>
                <span class="pos-section-kicker">REPARTO</span>
                <h2>Notificaciones de pedidos</h2>
                <p>En esta página se consultará el detalle de las órdenes recibidas para entrega a domicilio.</p>
            </div>
            <span class="workflow-status">Sin conexión</span>
        </div>
        <section class="workflow-card" aria-labelledby="delivery-list-title">
            <div class="workflow-toolbar">
                <h3 id="delivery-list-title">Pedidos recibidos</h3>
                <span class="workflow-count">0 pedidos</span>
            </div>
            <div class="workflow-empty">
                <strong>No hay pedidos de reparto para mostrar</strong>
                El sistema todavía no tiene conexión con Uber Eats, DiDi Food ni Rappi y no guarda pedidos de esas plataformas. Por eso aún no hay folio, cliente, teléfono, dirección, productos o forma de pago que mostrar.
            </div>
            <div class="delivery-fields" aria-label="Datos que aparecerán al recibir un pedido">
                <div class="delivery-field"><span>Plataforma y folio</span><strong>Pendiente de integración</strong></div>
                <div class="delivery-field"><span>Cliente y teléfono</span><strong>Pendiente de integración</strong></div>
                <div class="delivery-field"><span>Dirección de entrega</span><strong>Pendiente de integración</strong></div>
                <div class="delivery-field"><span>Productos, pago y estado</span><strong>Pendiente de integración</strong></div>
            </div>
        </section>
        <section class="workflow-card">
            <span class="pos-section-kicker">REQUISITO DE CONEXIÓN</span>
            <p style="margin:8px 0 0;color:var(--pos-muted);line-height:1.55;">
                Para mostrar pedidos reales hace falta la aprobación del restaurante y acceso oficial a la API de cada plataforma. No se simulan avisos ni datos de pedidos; cuando haya acceso autorizado, esta pantalla podrá mostrar la información completa.
            </p>
        </section>
    </main>
</body>
</html>
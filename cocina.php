<?php
include 'data.php';
include 'security.php';
if (!isset($_SESSION['user'])) {
    header('Location: index.php');
    exit;
}
requirePermission('cocina');

$ventas = getPedidosParaCocina();
$escape = static fn($value) => htmlspecialchars((string) $value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
$estados = [
    'pendiente' => ['Pendiente', 'pending', 'Empezar preparación', 'preparando'],
    'preparando' => ['En preparación', 'preparing', 'Marcar lista', 'lista'],
    'lista' => ['Lista para entregar', 'ready', 'Marcar entregada', 'entregada'],
];
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Comandas - Restaurante POS</title>
    <link rel="stylesheet" href="style.php">
    <style>
        .kitchen-page { padding: 20px; }
        .kitchen-header, .kitchen-toolbar { display: flex; align-items: center; justify-content: space-between; gap: 16px; }
        .kitchen-toolbar { margin: 20px 0; }
        .kitchen-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(290px, 1fr)); gap: 16px; }
        .kitchen-ticket { background: var(--surface); border: 1px solid var(--border); border-radius: var(--radius); box-shadow: var(--shadow); padding: 16px; }
        .kitchen-ticket header { display: flex; justify-content: space-between; align-items: flex-start; gap: 12px; }
        .kitchen-ticket h2 { margin: 0 0 4px; }
        .ticket-meta, .ticket-age { color: var(--muted); font-size: .9rem; }
        .ticket-age.overdue { color: var(--danger); font-weight: 700; }
        .ticket-status { display: inline-block; border-radius: 999px; padding: 4px 10px; font-size: .8rem; font-weight: 700; }
        .ticket-status.pending { background: #fff3cd; color: #795800; }
        .ticket-status.preparing { background: #dceeff; color: #14528a; }
        .ticket-status.ready { background: #dcf4e3; color: #176534; }
        .ticket-items { padding-left: 20px; margin: 16px 0; }
        .ticket-items li { margin: 8px 0; }
        .ticket-note { display: block; color: var(--danger); font-size: .9rem; }
        .ticket-action { width: 100%; }
        .kitchen-empty { background: var(--surface); border-radius: var(--radius); padding: 24px; text-align: center; }
        .kitchen-message { min-height: 1.5em; color: var(--danger); }
        @media (max-width: 600px) {
            .kitchen-page { padding: 12px; }
            .kitchen-header, .kitchen-toolbar { align-items: flex-start; flex-direction: column; }
        }
    </style>
</head>

<body class="kitchen-page">
    <header class="kitchen-header">
        <div>
            <h1>Comandas de cocina</h1>
            <p>Las órdenes más antiguas aparecen primero. La pantalla se actualiza cada 15 segundos.</p>
        </div>
        <a href="dashboard.php">Volver al panel</a>
    </header>
    <main>
        <div class="kitchen-toolbar">
            <h2>Órdenes activas <span>(<?= count($ventas) ?>)</span></h2>
            <button type="button" id="refresh-kitchen">Actualizar ahora</button>
        </div>
        <p id="kitchen-message" class="kitchen-message" role="status" aria-live="polite"></p>
        <?php if (!$ventas): ?>
            <div class="kitchen-empty">No hay comandas pendientes de preparación.</div>
        <?php else: ?>
            <section class="kitchen-grid" aria-label="Comandas activas">
                <?php foreach ($ventas as $venta): ?>
                    <?php
                    $estado = $estados[$venta['estado_cocina']] ?? null;
                    $orden = json_decode($venta['orden'] ?? '', true);
                    if (!$estado || !is_array($orden)) {
                        continue;
                    }
                    $edadMinutos = max(0, (int) floor((time() - strtotime($venta['fecha'])) / 60));
                    $mesa = (int) ($venta['mesa'] ?? 0);
                    ?>
                    <article class="kitchen-ticket">
                        <header>
                            <div>
                                <h2><?= $mesa > 0 ? 'Mesa ' . $mesa : 'Mostrador' ?></h2>
                                <div class="ticket-meta">
                                    Orden #<?= (int) $venta['id'] ?> · <?= $escape($venta['mesero_nombre'] ?? 'Sin mesero') ?>
                                </div>
                            </div>
                            <span class="ticket-status <?= $escape($estado[1]) ?>"><?= $escape($estado[0]) ?></span>
                        </header>
                        <p class="ticket-age <?= $edadMinutos >= 15 ? 'overdue' : '' ?>">
                            Recibida <?= $escape(date('H:i', strtotime($venta['fecha']))) ?> · <?= $edadMinutos ?> min
                        </p>
                        <ul class="ticket-items">
                            <?php foreach ($orden as $item): ?>
                                <?php if (!is_array($item) || trim((string) ($item['nombre'] ?? '')) === '') continue; ?>
                                <li>
                                    <strong><?= max(1, (int) ($item['cantidad'] ?? 1)) ?> × <?= $escape($item['nombre']) ?></strong>
                                    <?php if (!empty($item['nota'])): ?>
                                        <span class="ticket-note">Nota: <?= $escape($item['nota']) ?></span>
                                    <?php endif; ?>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                        <button
                            type="button"
                            class="ticket-action"
                            data-order-id="<?= (int) $venta['id'] ?>"
                            data-next-state="<?= $escape($estado[3]) ?>"
                        ><?= $escape($estado[2]) ?></button>
                    </article>
                <?php endforeach; ?>
            </section>
        <?php endif; ?>
    </main>
    <script>
        const kitchenCsrfToken = <?= json_encode(getCsrfToken(), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>;
        const kitchenMessage = document.getElementById('kitchen-message');

        document.getElementById('refresh-kitchen').addEventListener('click', () => window.location.reload());
        document.querySelectorAll('.ticket-action').forEach(button => {
            button.addEventListener('click', async () => {
                button.disabled = true;
                kitchenMessage.textContent = '';
                try {
                    const response = await fetch('actualizar_estado_comanda.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': kitchenCsrfToken },
                        body: JSON.stringify({
                            id: button.dataset.orderId,
                            estado: button.dataset.nextState,
                            csrf_token: kitchenCsrfToken
                        })
                    });
                    const result = await response.json();
                    if (!response.ok || result.status !== 'ok') {
                        throw new Error(result.message || 'No se pudo actualizar la comanda.');
                    }
                    window.location.reload();
                } catch (error) {
                    kitchenMessage.textContent = error.message || 'Error al actualizar la comanda.';
                    button.disabled = false;
                }
            });
        });

        window.setInterval(() => {
            if (!document.hidden) {
                window.location.reload();
            }
        }, 15000);
    </script>
</body>

</html>

<?php
include 'data.php';
include 'security.php';
if (!isset($_SESSION['user'])) {
    header('Location: index.php');
    exit;
}

requirePermission('inventario_ver');
$canEditPurchases = !empty($_SESSION['user']['permisos']['inventario_editar']);
$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requirePermission('inventario_editar');
    try {
        $csrfToken = $_POST['csrf_token'] ?? null;
        if (!is_string($csrfToken) || !validateCsrfToken($csrfToken)) {
            throw new InvalidArgumentException('La solicitud venció. Recarga la página e inténtalo de nuevo.');
        }
        if (($_POST['action'] ?? '') !== 'register') {
            throw new InvalidArgumentException('La acción de compra no es válida.');
        }

        $supplierInput = $_POST['proveedor'] ?? '';
        $folioInput = $_POST['folio'] ?? '';
        $purchaseDate = $_POST['fecha_compra'] ?? '';
        $notesInput = $_POST['observaciones'] ?? '';
        if (!is_string($supplierInput) || !is_string($folioInput) || !is_string($notesInput)) {
            throw new InvalidArgumentException('Los datos de proveedor, folio u observaciones no son válidos.');
        }
        $supplier = sanitizeString($supplierInput, 255);
        $folio = sanitizeString($folioInput, 100);
        $notes = sanitizeString($notesInput, 1000);
        $date = is_string($purchaseDate) ? DateTimeImmutable::createFromFormat('!Y-m-d', $purchaseDate) : false;
        $dateErrors = DateTimeImmutable::getLastErrors();
        if ($supplier === '') {
            throw new InvalidArgumentException('Indica el proveedor de la compra.');
        }
        if (
            !$date
            || $date->format('Y-m-d') !== $purchaseDate
            || ($dateErrors !== false && ($dateErrors['warning_count'] > 0 || $dateErrors['error_count'] > 0))
        ) {
            throw new InvalidArgumentException('Indica una fecha válida para la compra.');
        }

        $submittedItems = $_POST['items'] ?? null;
        if (!is_array($submittedItems) || $submittedItems === [] || count($submittedItems) > 100) {
            throw new InvalidArgumentException('Agrega entre 1 y 100 artículos a la compra.');
        }
        $items = [];
        foreach ($submittedItems as $submittedItem) {
            if (!is_array($submittedItem)) {
                throw new InvalidArgumentException('Uno de los artículos de la compra no es válido.');
            }
            $inventoryId = validateInteger($submittedItem['inventario_id'] ?? null, 1, PHP_INT_MAX);
            $quantity = validateQuantity($submittedItem['cantidad'] ?? null, 0.001, 100000000);
            $unitCost = validateDecimal($submittedItem['costo_unitario'] ?? null, 0, 1000000);
            if ($inventoryId === null || $quantity === null || $unitCost === null) {
                throw new InvalidArgumentException('Selecciona cada artículo e indica cantidades y costos válidos.');
            }
            $items[] = [
                'inventario_id' => $inventoryId,
                'cantidad' => $quantity,
                'costo_unitario' => $unitCost,
            ];
        }

        $purchaseId = registrarCompra(
            $supplier,
            $folio,
            $date->format('Y-m-d'),
            $notes,
            $items,
            (int) $_SESSION['user']['id']
        );
        registrarAuditoria(
            (int) $_SESSION['user']['id'],
            'REGISTRAR_COMPRA',
            'COMPRAS',
            $purchaseId,
            'Compra #' . $purchaseId . ' a ' . $supplier
        );
        header('Location: compras.php?success=1');
        exit;
    } catch (Exception $e) {
        $message = $e->getMessage();
    }
}

$inventoryItems = getInventario();
$recentPurchases = getComprasRecientes();
$purchaseDetails = getDetallesCompras(array_column($recentPurchases, 'id'));
$success = ($_GET['success'] ?? '') === '1';
$escape = static fn($value) => htmlspecialchars((string) $value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
$formatQuantity = static fn($value) => rtrim(rtrim(number_format((float) $value, 3, '.', ''), '0'), '.') ?: '0';
$permissions = $_SESSION['user']['permisos'] ?? [];
$navigation = [
    ['Panel', 'dashboard.php', '⌂', true],
    ['Punto de Venta', 'pos.php', '▣', $permissions['pos'] ?? false],
    ['Menú', 'menu.php', '☷', $permissions['menu_ver'] ?? false],
    ['Clientes', 'clientes.php', '♙', $permissions['clientes_ver'] ?? false],
    ['Inventario', 'inventario.php', '▤', $permissions['inventario_ver'] ?? false],
    ['Compras', 'compras.php', '▧', $permissions['inventario_ver'] ?? false],
    ['Empleados', 'empleados.php', '♧', $permissions['empleados'] ?? false],
    ['Informes', 'informes.php', '▥', $permissions['informes'] ?? false],
    ['Propinas', 'propinas.php', '$', $permissions['propinas'] ?? false],
    ['Merma', 'merma.php', '−', $permissions['mermas'] ?? false],
    ['Cocina', 'cocina.php', '♨', $permissions['cocina'] ?? false],
    ['Cierre de Caja', 'cierre_caja.php', '▣', $permissions['cierre_caja'] ?? false],
    ['Configuración', 'config.php', '⚙', $permissions['config'] ?? false],
    ['Cerrar Sesión', 'logout.php', '↪', true],
];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Compras - Restaurante POS</title>
    <link rel="stylesheet" href="style.php">
</head>
<body class="app-layout purchases-page">
    <aside class="sidebar">
        <div class="sidebar-brand">RESTAURANTE POS</div>
        <nav aria-label="Navegación principal">
            <?php foreach ($navigation as [$label, $url, $icon, $visible]): ?>
                <?php if ($visible): ?>
                    <a href="<?= $escape($url) ?>" class="<?= $url === 'compras.php' ? 'active' : '' ?>">
                        <span class="sidebar-icon" aria-hidden="true"><?= $escape($icon) ?></span>
                        <span><?= $escape($label) ?></span>
                    </a>
                <?php endif; ?>
            <?php endforeach; ?>
        </nav>
    </aside>
    <div class="app-main">
        <header class="topbar">
            <span class="breadcrumb">Restaurante POS <span aria-hidden="true">/</span> Compras</span>
            <span class="user-name"><?= $escape($_SESSION['user']['nombre'] ?? '') ?></span>
        </header>
        <main class="purchase-content">
            <section class="purchase-heading">
                <div>
                    <h1>Compras e inventario</h1>
                    <p>Registra las entradas de mercancía y consulta el historial de compras.</p>
                </div>
                <a class="purchase-secondary-action" href="inventario.php">Ver inventario</a>
            </section>

            <?php if ($success): ?>
                <div class="settings-message success" role="status">Compra registrada y existencias actualizadas correctamente.</div>
            <?php endif; ?>
            <?php if ($message !== ''): ?>
                <div class="settings-message error" role="alert"><?= $escape($message) ?></div>
            <?php endif; ?>

            <?php if ($canEditPurchases): ?>
                <section class="purchase-card">
                    <div class="purchase-card-heading">
                        <div>
                            <h2>Registrar compra</h2>
                            <p>Al guardar, la cantidad comprada se suma al inventario y se conserva el costo de compra.</p>
                        </div>
                    </div>
                    <?php if ($inventoryItems === []): ?>
                        <p class="purchase-empty">Primero agrega artículos en inventario para poder registrar una compra.</p>
                    <?php else: ?>
                        <form method="post" class="purchase-form">
                            <input type="hidden" name="csrf_token" value="<?= $escape(getCsrfToken()) ?>">
                            <input type="hidden" name="action" value="register">
                            <div class="purchase-header-fields">
                                <label>Proveedor
                                    <input type="text" name="proveedor" maxlength="255" required>
                                </label>
                                <label>Folio o número de factura
                                    <input type="text" name="folio" maxlength="100">
                                </label>
                                <label>Fecha de compra
                                    <input type="date" name="fecha_compra" value="<?= $escape(date('Y-m-d')) ?>" required>
                                </label>
                            </div>
                            <div class="purchase-items-heading">
                                <h3>Artículos</h3>
                                <button type="button" class="purchase-secondary-action" id="add-purchase-item">+ Agregar artículo</button>
                            </div>
                            <div class="purchase-lines-scroll">
                                <table class="purchase-lines">
                                    <thead>
                                        <tr>
                                            <th scope="col">Artículo</th>
                                            <th scope="col">Cantidad</th>
                                            <th scope="col">Costo unitario</th>
                                            <th scope="col">Importe</th>
                                            <th scope="col"><span class="visually-hidden">Acciones</span></th>
                                        </tr>
                                    </thead>
                                    <tbody id="purchase-items">
                                        <tr class="purchase-item-row">
                                            <td>
                                                <select name="items[0][inventario_id]" aria-label="Artículo" required>
                                                    <option value="">Selecciona un artículo</option>
                                                    <?php foreach ($inventoryItems as $item): ?>
                                                        <option value="<?= (int) $item['id'] ?>" data-cost="<?= $escape(number_format((float) ($item['precio_costo'] ?? 0), 2, '.', '')) ?>">
                                                            <?= $escape($item['item']) ?> (<?= $escape($item['unidad'] ?? 'pieza') ?>)
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </td>
                                            <td><input type="number" name="items[0][cantidad]" min="0.001" step="0.001" value="1" required aria-label="Cantidad"></td>
                                            <td><input type="number" name="items[0][costo_unitario]" min="0" max="1000000" step="0.01" value="0" required aria-label="Costo unitario"></td>
                                            <td class="purchase-line-total">$0.00</td>
                                            <td><button type="button" class="purchase-remove-item" aria-label="Quitar artículo" disabled>Quitar</button></td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                            <p class="purchase-total">Total de la compra: <strong id="purchase-total">$0.00</strong></p>
                            <label class="purchase-notes">Observaciones
                                <textarea name="observaciones" maxlength="1000" rows="3"></textarea>
                            </label>
                            <div class="purchase-form-actions">
                                <button type="submit" class="purchase-primary-action">Guardar compra</button>
                            </div>
                        </form>
                    <?php endif; ?>
                </section>
            <?php endif; ?>

            <section class="purchase-card">
                <div class="purchase-card-heading">
                    <div>
                        <h2>Historial de compras</h2>
                        <p>Se muestran las últimas <?= count($recentPurchases) ?> compras registradas.</p>
                    </div>
                </div>
                <?php if ($recentPurchases === []): ?>
                    <p class="purchase-empty">Todavía no hay compras registradas.</p>
                <?php else: ?>
                    <div class="purchase-history-scroll">
                        <table class="purchase-history">
                            <thead>
                                <tr>
                                    <th scope="col">Fecha</th>
                                    <th scope="col">Proveedor</th>
                                    <th scope="col">Folio</th>
                                    <th scope="col">Artículos</th>
                                    <th scope="col">Total</th>
                                    <th scope="col">Registró</th>
                                    <th scope="col">Detalle</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recentPurchases as $purchase): ?>
                                    <?php $purchaseId = (int) $purchase['id']; ?>
                                    <tr>
                                        <td><?= $escape($purchase['fecha_compra']) ?></td>
                                        <td><strong><?= $escape($purchase['proveedor']) ?></strong></td>
                                        <td><?= $escape($purchase['folio'] ?? '') ?: '—' ?></td>
                                        <td><?= (int) $purchase['cantidad_articulos'] ?></td>
                                        <td class="numeric-cell">$<?= number_format((float) $purchase['total'], 2) ?></td>
                                        <td><?= $escape($purchase['usuario_nombre'] ?? 'Cuenta archivada') ?></td>
                                        <td>
                                            <details class="purchase-detail">
                                                <summary>Ver</summary>
                                                <?php if (!empty($purchase['observaciones'])): ?>
                                                    <p><?= $escape($purchase['observaciones']) ?></p>
                                                <?php endif; ?>
                                                <ul>
                                                    <?php foreach ($purchaseDetails[$purchaseId] ?? [] as $detail): ?>
                                                        <li>
                                                            <?= $escape($detail['item_nombre']) ?>:
                                                            <?= $formatQuantity($detail['cantidad']) ?> ×
                                                            $<?= number_format((float) $detail['costo_unitario'], 2) ?> =
                                                            $<?= number_format((float) $detail['subtotal'], 2) ?>
                                                        </li>
                                                    <?php endforeach; ?>
                                                </ul>
                                            </details>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </section>
        </main>
    </div>

    <?php if ($canEditPurchases && $inventoryItems !== []): ?>
        <template id="purchase-item-template">
            <tr class="purchase-item-row">
                <td>
                    <select name="items[__index__][inventario_id]" aria-label="Artículo" required>
                        <option value="">Selecciona un artículo</option>
                        <?php foreach ($inventoryItems as $item): ?>
                            <option value="<?= (int) $item['id'] ?>" data-cost="<?= $escape(number_format((float) ($item['precio_costo'] ?? 0), 2, '.', '')) ?>">
                                <?= $escape($item['item']) ?> (<?= $escape($item['unidad'] ?? 'pieza') ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </td>
                <td><input type="number" name="items[__index__][cantidad]" min="0.001" step="0.001" value="1" required aria-label="Cantidad"></td>
                <td><input type="number" name="items[__index__][costo_unitario]" min="0" max="1000000" step="0.01" value="0" required aria-label="Costo unitario"></td>
                <td class="purchase-line-total">$0.00</td>
                <td><button type="button" class="purchase-remove-item" aria-label="Quitar artículo">Quitar</button></td>
            </tr>
        </template>
        <script>
            const purchaseItems = document.getElementById('purchase-items');
            const purchaseTemplate = document.getElementById('purchase-item-template');
            const currencyFormatter = new Intl.NumberFormat('es-MX', { style: 'currency', currency: 'MXN' });
            let nextPurchaseItemIndex = 1;

            function updatePurchaseTotal() {
                let total = 0;
                purchaseItems.querySelectorAll('.purchase-item-row').forEach((row) => {
                    const quantity = Number(row.querySelector('[name$="[cantidad]"]').value) || 0;
                    const cost = Number(row.querySelector('[name$="[costo_unitario]"]').value) || 0;
                    const lineTotal = Math.round(quantity * cost * 100) / 100;
                    total += lineTotal;
                    row.querySelector('.purchase-line-total').textContent = currencyFormatter.format(lineTotal);
                });
                document.getElementById('purchase-total').textContent = currencyFormatter.format(total);
                const removeButtons = purchaseItems.querySelectorAll('.purchase-remove-item');
                removeButtons.forEach((button) => {
                    button.disabled = removeButtons.length === 1;
                });
            }

            document.getElementById('add-purchase-item').addEventListener('click', () => {
                const rowMarkup = purchaseTemplate.innerHTML.replace(/__index__/g, String(nextPurchaseItemIndex++));
                purchaseItems.insertAdjacentHTML('beforeend', rowMarkup);
                updatePurchaseTotal();
            });

            purchaseItems.addEventListener('input', updatePurchaseTotal);
            purchaseItems.addEventListener('change', (event) => {
                if (event.target.matches('select[name$="[inventario_id]"]')) {
                    const selected = event.target.selectedOptions[0];
                    const costInput = event.target.closest('tr').querySelector('[name$="[costo_unitario]"]');
                    costInput.value = selected.dataset.cost || '0';
                }
                updatePurchaseTotal();
            });
            purchaseItems.addEventListener('click', (event) => {
                const removeButton = event.target.closest('.purchase-remove-item');
                if (removeButton && purchaseItems.querySelectorAll('.purchase-item-row').length > 1) {
                    removeButton.closest('.purchase-item-row').remove();
                    updatePurchaseTotal();
                }
            });
            updatePurchaseTotal();
        </script>
    <?php endif; ?>
</body>
</html>
<?php
include 'data.php';
include 'security.php';
if (!isset($_SESSION['user'])) {
    header('Location: index.php');
    exit;
}

requirePermission('inventario_ver');
$canEditInventory = !empty($_SESSION['user']['permisos']['inventario_editar']);

$message = '';
$editItem = null;
$search = isset($_GET['q']) && is_string($_GET['q']) ? sanitizeString($_GET['q'], 100) : '';
$searchField = isset($_GET['campo']) && is_string($_GET['campo']) ? $_GET['campo'] : 'all';
$allowedSearchFields = ['all', 'item', 'codigo_barras', 'categoria', 'proveedor'];
if (!in_array($searchField, $allowedSearchFields, true)) {
    $searchField = 'all';
}
$categoryFilter = isset($_GET['categoria']) && is_string($_GET['categoria'])
    ? sanitizeString($_GET['categoria'], 120)
    : '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requirePermission('inventario_editar');
    try {
        if (!is_string($_POST['csrf_token'] ?? null) || !validateCsrfToken($_POST['csrf_token'])) {
            throw new Exception('Token de seguridad inválido');
        }

        $action = $_POST['action'] ?? 'add';
        if ($action === 'restock') {
            $restockId = validateInteger($_POST['id'] ?? null, 1, PHP_INT_MAX);
            $restockQuantity = validateQuantity($_POST['cantidad_reponer'] ?? null, 0.001, 100000000);
            if ($restockId === null || $restockQuantity === null) {
                throw new Exception('Indica un artículo y una cantidad válida para reponer.');
            }
            adjustInventoryStock($restockId, $restockQuantity, 'Reposición desde inventario');
            registrarAuditoria($_SESSION['user']['id'], 'REPONER_INVENTARIO', 'INVENTARIO', $restockId, 'Cantidad repuesta: ' . $restockQuantity);
            header('Location: inventario.php?success=1');
            exit;
        }

        $item = sanitizeString($_POST['item'] ?? '', 100);
        $codigo_barras = sanitizeString($_POST['codigo_barras'] ?? '', 100);
        $categoria = sanitizeString($_POST['categoria'] ?? 'General', 120);
        $cantidad = validateQuantity($_POST['cantidad'] ?? 0, 0, 100000000);
        $cantidad_minima = validateQuantity($_POST['cantidad_minima'] ?? 0, 0, 100000000);
        $cantidad_maxima = validateQuantity($_POST['cantidad_maxima'] ?? 0, 0, 100000000);
        $precio_costo = validateDecimal($_POST['precio_costo'] ?? 0, 0, 1000000);
        $precio_venta = validateDecimal($_POST['precio_venta'] ?? 0, 0, 1000000);
        $proveedor = sanitizeString($_POST['proveedor'] ?? '', 100);
        $unidad = sanitizeString($_POST['unidad'] ?? 'pieza', 20);
        $allowedUnits = ['pieza', 'kg', 'g', 'L', 'ml', 'porción', 'paquete'];
        $id = validateInteger($_POST['id'] ?? 0, 0, PHP_INT_MAX);

        if ($action === 'save') {
            if ($item === '' || $categoria === '') {
                throw new Exception('El nombre y la categoría del artículo son obligatorios.');
            }
            if ($cantidad === null || $cantidad_minima === null || $cantidad_maxima === null ||
                $cantidad_maxima > 0 && $cantidad_maxima < $cantidad_minima) {
                throw new Exception('Revisa las cantidades y confirma que el máximo sea mayor o igual al mínimo.');
            }
            if ($precio_costo === null || $precio_venta === null) {
                throw new Exception('Revisa los precios de costo y venta.');
            }
            if (!in_array($unidad, $allowedUnits, true)) {
                throw new Exception('Selecciona una unidad de medida válida.');
            }
            if ($id > 0) {
                updateInventarioItem($id, $item, $cantidad, $cantidad_minima, $cantidad_maxima, $precio_costo, $proveedor, $codigo_barras, $categoria, $precio_venta, $unidad);
                $message = 'Item actualizado correctamente.';
            } else {
                addInventarioItem($item, $cantidad, $cantidad_minima, $cantidad_maxima, $precio_costo, $proveedor, $codigo_barras, $categoria, $precio_venta, $unidad);
                $message = 'Item agregado correctamente.';
            }
            registrarAuditoria($_SESSION['user']['id'], 'GUARDAR_INVENTARIO', 'INVENTARIO', $id > 0 ? $id : null, $item);
        } elseif ($action === 'delete' && $id > 0) {
            deleteInventarioItem($id);
            $message = 'Item eliminado correctamente.';
            registrarAuditoria($_SESSION['user']['id'], 'ELIMINAR_INVENTARIO', 'INVENTARIO', $id, 'Item eliminado');
        } else {
            throw new Exception('La acción de inventario no es válida.');
        }

        header('Location: inventario.php?success=1');
        exit;
    } catch (Exception $e) {
        $message = $e->getMessage();
    }
}

if (isset($_GET['edit'])) {
    $editId = validateInteger($_GET['edit'], 1, PHP_INT_MAX);
    if ($editId !== null) {
        $editItem = getInventarioItemById($editId);
    }
}

$inventario = getInventario($search, $searchField, $categoryFilter);
$categories = getCategoriasInventario();
$lowStockItems = getInventarioBajoStock();
$showForm = $editItem !== null || isset($_GET['new']) || $message !== '';
$success = ($_GET['success'] ?? '') === '1';
$escape = static fn($value) => htmlspecialchars((string) $value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
$permissions = $_SESSION['user']['permisos'] ?? [];
$navigation = [
    ['Panel', 'dashboard.php', '⌂', true],
    ['Punto de Venta', 'pos.php', '▣', $permissions['pos'] ?? false],
    ['Menú', 'menu.php', '☷', $permissions['menu_ver'] ?? false],
    ['Clientes', 'clientes.php', '♙', $permissions['clientes_ver'] ?? false],
    ['Inventario', 'inventario.php', '▤', true],
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
$formatQuantity = static fn($value) => rtrim(rtrim(number_format((float) $value, 3, '.', ''), '0'), '.') ?: '0';
$searchLabels = [
    'all' => 'Todo',
    'item' => 'Nombre',
    'codigo_barras' => 'Código de barras',
    'categoria' => 'Categoría',
    'proveedor' => 'Proveedor',
];
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inventario - Restaurante POS</title>
    <link rel="stylesheet" href="style.php">
</head>

<body class="app-layout inventory-page">
    <aside class="sidebar">
        <div class="sidebar-brand">RESTAURANTE POS</div>
        <nav aria-label="Navegación principal">
            <?php foreach ($navigation as [$label, $url, $icon, $visible]): ?>
                <?php if ($visible): ?>
                    <a href="<?= $escape($url) ?>" class="<?= $url === 'inventario.php' ? 'active' : '' ?>">
                        <span class="sidebar-icon" aria-hidden="true"><?= $escape($icon) ?></span>
                        <span><?= $escape($label) ?></span>
                    </a>
                <?php endif; ?>
            <?php endforeach; ?>
        </nav>
    </aside>
    <div class="app-main">
        <header class="topbar">
            <span class="breadcrumb">Restaurante POS <span aria-hidden="true">/</span> Inventario</span>
            <span class="user-name"><?= $escape($_SESSION['user']['nombre'] ?? '') ?></span>
        </header>
        <main class="inventory-content">
        <div class="inventory-toolbar">
            <form method="get" class="inventory-search">
                <label class="visually-hidden" for="inventory-search">Buscar inventario</label>
                <input id="inventory-search" type="search" name="q" value="<?= $escape($search) ?>" placeholder="Buscar inventario">
                <label>Campo:
                    <select name="campo">
                        <?php foreach ($searchLabels as $value => $label): ?>
                            <option value="<?= $escape($value) ?>" <?= $searchField === $value ? 'selected' : '' ?>><?= $escape($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label>Categoría:
                    <select name="categoria">
                        <option value="">Todas</option>
                        <?php foreach ($categories as $category): ?>
                            <option value="<?= $escape($category) ?>" <?= $categoryFilter === $category ? 'selected' : '' ?>><?= $escape($category) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <button type="submit">Buscar</button>
            </form>
            <div class="inventory-toolbar-actions">
                <?php if ($canEditInventory): ?>
                    <a class="inventory-new-button" href="inventario.php?new=1#inventory-form">+ Nuevo artículo</a>
                <?php endif; ?>
                <a class="inventory-new-button" href="compras.php">Compras</a>
                <a class="button-secondary inventory-dashboard-link" href="dashboard.php" aria-label="Volver al panel">···</a>
            </div>
        </div>

        <?php if ($success): ?>
            <div class="settings-message success" role="status">Cambios de inventario guardados correctamente.</div>
        <?php endif; ?>
        <?php if ($message): ?>
            <div class="settings-message error" role="alert"><?= $escape($message) ?></div>
        <?php endif; ?>

        <section class="inventory-list-card">
            <div class="inventory-list-heading">
                <h2>Artículos <span class="inventory-count"><?= count($inventario) ?></span></h2>
                <div class="inventory-heading-meta">
                    <?php if (!empty($lowStockItems)): ?>
                        <span class="inventory-low-stock"><?= count($lowStockItems) ?> con stock bajo</span>
                    <?php endif; ?>
                    <span class="inventory-settings-icon" aria-hidden="true">⚙</span>
                </div>
            </div>
            <?php if (empty($inventario)): ?>
                <p class="inventory-empty">
                    <?= $search !== '' || $categoryFilter !== '' ? 'No se encontraron artículos con esos filtros.' : 'No hay artículos registrados en el inventario.' ?>
                </p>
            <?php else: ?>
                <div class="inventory-table-scroll">
                    <table class="inventory-table">
                        <thead>
                            <tr>
                                <th scope="col">ID</th>
                                <th scope="col">Código de barras</th>
                                <th scope="col">Nombre</th>
                                <th scope="col">Categoría</th>
                                <th scope="col" class="numeric-cell">Costo</th>
                                <th scope="col" class="numeric-cell">Precio de venta</th>
                                <th scope="col" class="numeric-cell">Cantidad</th>
                                <th scope="col">Unidad</th>
                                <?php if ($canEditInventory): ?><th scope="col">Acciones</th><?php endif; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($inventario as $item): ?>
                                <?php $isLow = (float) $item['cantidad'] <= (float) $item['cantidad_minima']; ?>
                                <tr id="inventory-item-<?= (int) $item['id'] ?>">
                                    <td><?= (int) $item['id'] ?></td>
                                    <td><?= $escape($item['codigo_barras'] ?? '') ?: '—' ?></td>
                                    <td><strong><?= $escape($item['item']) ?></strong></td>
                                    <td><?= $escape($item['categoria'] ?? 'General') ?></td>
                                    <td class="numeric-cell">$<?= number_format((float) ($item['precio_costo'] ?? 0), 2) ?></td>
                                    <td class="numeric-cell">$<?= number_format((float) ($item['precio_venta'] ?? 0), 2) ?></td>
                                    <td class="numeric-cell <?= $isLow ? 'quantity-low' : '' ?>">
                                        <?= $formatQuantity($item['cantidad']) ?>
                                        <?php if ($isLow): ?><span class="stock-status">Bajo</span><?php endif; ?>
                                    </td>
                                    <td><?= $escape($item['unidad'] ?? 'pieza') ?></td>
                                    <?php if ($canEditInventory): ?>
                                        <td class="inventory-actions">
                                            <a href="inventario.php?edit=<?= (int) $item['id'] ?>#inventory-form">Editar</a>
                                            <?php if ($isLow): ?>
                                                <form method="post" class="inventory-restock-form">
                                                    <input type="hidden" name="csrf_token" value="<?= $escape(getCsrfToken()) ?>">
                                                    <input type="hidden" name="action" value="restock">
                                                    <input type="hidden" name="id" value="<?= (int) $item['id'] ?>">
                                                    <label>Reponer
                                                        <input type="number" name="cantidad_reponer" min="0.001" step="0.001" required aria-label="Cantidad a reponer de <?= $escape($item['item']) ?>">
                                                    </label>
                                                    <button type="submit">Registrar</button>
                                                </form>
                                            <?php endif; ?>
                                            <form method="post" onsubmit="return confirm('¿Eliminar este artículo de inventario?');">
                                                <input type="hidden" name="csrf_token" value="<?= $escape(getCsrfToken()) ?>">
                                                <input type="hidden" name="action" value="delete">
                                                <input type="hidden" name="id" value="<?= (int) $item['id'] ?>">
                                                <button type="submit" class="inventory-delete">Eliminar</button>
                                            </form>
                                        </td>
                                    <?php endif; ?>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
            <?php if (!empty($lowStockItems) && $canEditInventory): ?>
                <details class="inventory-alert-details">
                    <summary>Ver artículos que requieren reposición</summary>
                    <ul>
                        <?php foreach ($lowStockItems as $lowItem): ?>
                            <li>
                                <?= $escape($lowItem['item']) ?> — <?= $formatQuantity($lowItem['cantidad']) ?> <?= $escape($lowItem['unidad'] ?? 'pieza') ?> disponibles; mínimo <?= $formatQuantity($lowItem['cantidad_minima']) ?>.
                                <?php if ($canEditInventory): ?>
                                    <form method="post" class="inventory-restock-form">
                                        <input type="hidden" name="csrf_token" value="<?= $escape(getCsrfToken()) ?>">
                                        <input type="hidden" name="action" value="restock">
                                        <input type="hidden" name="id" value="<?= (int) $lowItem['id'] ?>">
                                        <label>Reponer
                                            <input type="number" name="cantidad_reponer" min="0.001" step="0.001" required aria-label="Cantidad a reponer de <?= $escape($lowItem['item']) ?>">
                                        </label>
                                        <button type="submit">Registrar reposición</button>
                                    </form>
                                <?php endif; ?>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </details>
            <?php endif; ?>
        </section>

        <?php if ($showForm && $canEditInventory): ?>
        <section class="inventory-form-card" id="inventory-form">
            <h2><?= $editItem ? 'Editar artículo' : 'Nuevo artículo' ?></h2>
            <form method="post" class="inventory-item-form">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(getCsrfToken()) ?>">
                <input type="hidden" name="action" value="save">
                <input type="hidden" name="id" value="<?= (int) ($editItem['id'] ?? 0) ?>">
                <label>Nombre del artículo
                    <input type="text" name="item" maxlength="100" required value="<?= $escape($editItem['item'] ?? '') ?>">
                </label>
                <label>Código de barras
                    <input type="text" name="codigo_barras" maxlength="100" value="<?= $escape($editItem['codigo_barras'] ?? '') ?>">
                </label>
                <label>Categoría
                    <input type="text" name="categoria" list="inventory-categories" maxlength="120" required value="<?= $escape($editItem['categoria'] ?? 'General') ?>">
                    <datalist id="inventory-categories">
                        <?php foreach ($categories as $category): ?><option value="<?= $escape($category) ?>"><?php endforeach; ?>
                    </datalist>
                </label>
                <label>Cantidad disponible
                    <input type="number" name="cantidad" min="0" step="0.001" required value="<?= $escape($editItem['cantidad'] ?? '0') ?>">
                </label>
                <label>Cantidad mínima
                    <input type="number" name="cantidad_minima" min="0" step="0.001" required value="<?= $escape($editItem['cantidad_minima'] ?? '0') ?>">
                </label>
                <label>Cantidad máxima sugerida
                    <input type="number" name="cantidad_maxima" min="0" step="0.001" required value="<?= $escape($editItem['cantidad_maxima'] ?? '0') ?>">
                </label>
                <label>Unidad de medida (usada también en las recetas)
                    <select name="unidad" required>
                        <?php foreach (['pieza', 'kg', 'g', 'L', 'ml', 'porción', 'paquete'] as $unit): ?>
                            <option value="<?= $escape($unit) ?>" <?= ($editItem['unidad'] ?? 'pieza') === $unit ? 'selected' : '' ?>><?= $escape($unit) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label>Precio de costo
                    <input type="number" name="precio_costo" min="0" max="1000000" step="0.01" required value="<?= $escape($editItem['precio_costo'] ?? '0') ?>">
                </label>
                <label>Precio de venta
                    <input type="number" name="precio_venta" min="0" max="1000000" step="0.01" required value="<?= $escape($editItem['precio_venta'] ?? '0') ?>">
                </label>
                <label>Proveedor
                    <input type="text" name="proveedor" maxlength="100" value="<?= $escape($editItem['proveedor'] ?? '') ?>">
                </label>
                <div class="inventory-form-actions">
                    <button type="submit"><?= $editItem ? 'Guardar artículo' : 'Crear artículo' ?></button>
                    <a href="inventario.php">Cancelar</a>
                </div>
            </form>
        </section>
        <?php endif; ?>
        </main>
    </div>
    <script src="utilidades.js"></script>
</body>

</html>
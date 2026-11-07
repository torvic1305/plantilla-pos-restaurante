<?php
include 'data.php';
include 'security.php';
if (!isset($_SESSION['user'])) {
    header('Location: index.php');
    exit;
}
requirePermission('pos');
$user = $_SESSION['user'];
$config = getAppConfig();
$mesas = range(1, max(1, intval($config['mesas'] ?? 20)));
$formas_pago = is_array($config['formas_pago']) ? $config['formas_pago'] : ['Efectivo', 'Tarjeta', 'Transferencia'];
$metodo_pago_default = in_array($config['metodo_pago_default'] ?? '', $formas_pago, true)
    ? $config['metodo_pago_default']
    : $formas_pago[0];
$requiere_mesa = !empty($config['pos_require_table']);
$impuesto = floatval($config['impuesto'] ?? 0);
$meseros = getMeseros();
$menu = getMenu();
$inventarioVenta = getInventarioParaVenta();
$promociones_activas = getPromocionesActivas();
$promociones_pos = array_map(static function ($promo) {
    return [
        'nombre' => (string) ($promo['nombre'] ?? ''),
        'descuento' => max(0, min(100, (float) ($promo['descuento'] ?? 0)))
    ];
}, $promociones_activas);
$suspendidaInicialId = isset($_GET['suspendida']) && is_string($_GET['suspendida'])
    ? filter_var($_GET['suspendida'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]])
    : false;
$puedeConfigurar = !empty($user['permisos']['config']);
$meseros_pos = $puedeConfigurar ? ($meseros ?: [$user]) : [$user];
$config_recibos = [
    'nombre' => (string) ($config['nombre_restaurante'] ?? 'Restaurante POS'),
    'titulo' => (string) ($config['recibo_titulo'] ?? 'Restaurante POS'),
    'pie' => (string) ($config['recibo_pie'] ?? 'Gracias por su compra'),
    'anchoPapel' => (int) ($config['recibo_ancho_papel'] ?? '80'),
    'altoPapel' => (int) ($config['recibo_alto_papel'] ?? 200),
    'margenSuperior' => (int) ($config['recibo_margen_superior'] ?? 2),
    'margenInferior' => (int) ($config['recibo_margen_inferior'] ?? 4),
    'tamanoFuente' => (string) ($config['recibo_tamano_fuente'] ?? 'normal'),
    'mostrarFecha' => !empty($config['recibo_mostrar_fecha']),
    'mostrarPrecios' => !empty($config['recibo_mostrar_precios']),
    'mostrarDescuentos' => !empty($config['recibo_mostrar_descuentos']),
    'mostrarImpuestos' => !empty($config['recibo_mostrar_impuestos']),
    'mostrarMesa' => !empty($config['recibo_mostrar_mesa']),
    'mostrarMesero' => !empty($config['recibo_mostrar_mesero']),
    'mostrarFolio' => !empty($config['recibo_mostrar_folio']),
    'mostrarNotas' => !empty($config['recibo_mostrar_notas']),
    'mostrarSubtotal' => !empty($config['recibo_mostrar_subtotal']),
    'mostrarPago' => !empty($config['recibo_mostrar_pago']),
];
$config_comandas = [
    'anchoPapel' => (int) ($config['comanda_ancho_papel'] ?? '80'),
    'tamanoFuente' => (string) ($config['comanda_tamano_fuente'] ?? 'normal'),
    'margenSuperior' => (int) ($config['comanda_margen_superior'] ?? 2),
    'margenInferior' => (int) ($config['comanda_margen_inferior'] ?? 4),
];
$config_impresoras = [
    'recibo' => [
        'ip' => (string) ($config['impresora_recibo_ip'] ?? ''),
        'puerto' => (int) ($config['impresora_recibo_puerto'] ?? 9100),
    ],
    'cocina' => [
        'ip' => (string) ($config['impresora_cocina_ip'] ?? ''),
        'puerto' => (int) ($config['impresora_cocina_puerto'] ?? 9100),
    ],
    'barra' => [
        'ip' => (string) ($config['impresora_barra_ip'] ?? ''),
        'puerto' => (int) ($config['impresora_barra_puerto'] ?? 9100),
    ],
    'categoriasBarra' => is_array($config['impresora_categorias_barra'] ?? null)
        ? $config['impresora_categorias_barra']
        : [],
];
$escape = static fn($value) => htmlspecialchars((string) $value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
$categorias_menu = [];
foreach ($menu as $item) {
    $categoria = trim($item['categoria'] ?: 'General');
    if (!in_array($categoria, $categorias_menu)) {
        $categorias_menu[] = $categoria;
    }
}
foreach ($inventarioVenta as $item) {
    $categoria = trim($item['categoria'] ?: 'Inventario');
    if (!in_array($categoria, $categorias_menu, true)) {
        $categorias_menu[] = $categoria;
    }
}
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Punto de Venta - Restaurante POS</title>
    <link rel="stylesheet" href="style.php">
</head>

<body class="pos-page">
    <header class="pos-header">
        <div class="pos-header-brand">
            <span class="pos-brand-mark" aria-hidden="true">R</span>
            <div>
                <span class="pos-eyebrow">RESTAURANTE POS</span>
                <h1>Punto de Venta</h1>
            </div>
        </div>
        <div class="pos-header-actions">
            <?php if (!empty($user['permisos']['ventas_suspendidas_ver'])): ?>
                <a class="pos-pending-sales-link" href="ventas_suspendidas.php"><span aria-hidden="true">◷</span> Ventas en espera</a>
            <?php endif; ?>
            <a class="pos-notification-link" href="pedidos_domicilio.php" aria-label="Notificaciones de reparto" title="Notificaciones de reparto">
                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M18 8a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9M10 21h4"/></svg>
                <span class="visually-hidden">Notificaciones de reparto</span>
            </a>
            <span class="pos-user-chip"><span class="pos-status-dot" aria-hidden="true"></span><?= $escape($user['nombre'] ?? '') ?></span>
            <a href="dashboard.php"><span aria-hidden="true">‹</span> Volver al panel</a>
        </div>
    </header>
    <main class="pos-main">
        <div class="pos-container">
            <div class="menu">
                <div class="pos-menu-heading">
                    <div>
                        <span class="pos-section-kicker">MENÚ</span>
                        <h2>¿Qué vamos a servir?</h2>
                    </div>
                    <span class="pos-result-count"><span id="menu-result-count"><?= count($menu) + count($inventarioVenta) ?></span> productos</span>
                </div>
                <div class="menu-filtros">
                    <label class="pos-menu-search">
                        <span aria-hidden="true">⌕</span>
                        <span class="visually-hidden">Buscar producto</span>
                        <input id="menu-search" type="search" placeholder="Buscar platillo o producto..." autocomplete="off">
                    </label>
                </div>
                <div class="pos-category-bar" role="group" aria-label="Filtrar productos por categoría">
                    <button type="button" class="pos-category-chip active" data-category="" aria-pressed="true">Todos</button>
                    <?php foreach ($categorias_menu as $categoria): ?>
                        <button type="button" class="pos-category-chip" data-category="<?= $escape($categoria) ?>" aria-pressed="false"><?= $escape($categoria) ?></button>
                    <?php endforeach; ?>
                </div>
                <select id="menu-categoria" class="visually-hidden" tabindex="-1" aria-hidden="true">
                    <option value="">Todas</option>
                    <?php foreach ($categorias_menu as $categoria): ?>
                        <option value="<?= $escape($categoria) ?>"><?= $escape($categoria) ?></option>
                    <?php endforeach; ?>
                </select>
                <div class="menu-grid" id="menu-grid">
                    <?php foreach ($menu as $item):
                        $itemName = (string) ($item['nombre'] ?? '');
                        $itemCategory = trim((string) ($item['categoria'] ?? '')) ?: 'General';
                        $itemPrice = (float) ($item['precio'] ?? 0);
                    ?>
                        <button type="button" class="menu-item" data-tipo="menu" data-id="<?= (int) $item['id'] ?>" data-nombre="<?= $escape($itemName) ?>" data-precio="<?= $escape(number_format($itemPrice, 2, '.', '')) ?>" data-categoria="<?= $escape($itemCategory) ?>" aria-label="Agregar <?= $escape($itemName) ?> al pedido, $<?= $escape(number_format($itemPrice, 2)) ?>">
                            <span class="menu-item-visual" aria-hidden="true"><span class="menu-item-plate"></span><span class="menu-item-plus">+</span></span>
                            <span class="menu-item-details">
                                <span class="menu-item-name"><?= $escape($itemName) ?></span>
                                <span class="menu-item-category"><?= $escape($itemCategory) ?></span>
                                <span class="menu-item-price">$<?= $escape(number_format($itemPrice, 2)) ?></span>
                            </span>
                        </button>
                    <?php endforeach; ?>
                    <?php if ($inventarioVenta): ?><h3 class="pos-inventory-heading">Artículos de inventario</h3><?php endif; ?>
                    <?php foreach ($inventarioVenta as $item):
                        $itemName = (string) ($item['item'] ?? '');
                        $itemCategory = trim((string) ($item['categoria'] ?? '')) ?: 'Inventario';
                        $itemPrice = (float) ($item['precio_venta'] ?? 0);
                        $itemUnit = (string) ($item['unidad'] ?? 'pieza');
                    ?>
                        <button type="button" class="menu-item inventory-sale-item" data-tipo="inventario" data-id="<?= (int) $item['id'] ?>" data-nombre="<?= $escape($itemName) ?>" data-precio="<?= $escape(number_format($itemPrice, 2, '.', '')) ?>" data-categoria="<?= $escape($itemCategory) ?>" data-unidad="<?= $escape($itemUnit) ?>" aria-label="Agregar <?= $escape($itemName) ?> al pedido, $<?= $escape(number_format($itemPrice, 2)) ?>">
                            <span class="menu-item-visual" aria-hidden="true"><span class="menu-item-plate"></span><span class="menu-item-plus">+</span></span>
                            <span class="menu-item-details">
                                <span class="menu-item-name"><?= $escape($itemName) ?></span>
                                <span class="menu-item-category"><?= $escape($itemUnit) ?> · <?= $escape($itemCategory) ?></span>
                                <span class="menu-item-price">$<?= $escape(number_format($itemPrice, 2)) ?></span>
                            </span>
                        </button>
                    <?php endforeach; ?>
                </div>
            </div>
            <div class="orden">
                <div class="pos-order-heading">
                    <div>
                        <span class="pos-section-kicker">PEDIDO</span>
                        <h2>Orden actual</h2>
                    </div>
                    <span class="pos-order-count"><span id="item-count">0</span></span>
                </div>
                <p id="estado-main" class="estado-info">Selecciona productos para comenzar.</p>
                <ul id="orden-list"></ul>
                <div class="venta-resumen">
                    <p>Subtotal: $<span id="subtotal-amount">0.00</span></p>
                    <p>Descuento: $<span id="discount-amount">0.00</span></p>
                    <p>Impuesto (<?php echo $impuesto; ?>%): $<span id="tax-amount">0.00</span></p>
                    <p>Total final: $<span id="total-amount">0.00</span></p>
                </div>
                <div class="venta-acciones">
                    <label>Descuento manual: $<input type="number" id="descuento-manual" min="0" step="0.01"
                            value="0"></label>
                    <label>Forma de pago:
                        <select id="tipo-pago">
                            <?php foreach ($formas_pago as $forma) {
                                echo "<option value='" . htmlspecialchars($forma) . "'" . ($forma === $metodo_pago_default ? ' selected' : '') . ">" . htmlspecialchars($forma) . "</option>";
                            } ?>
                        </select>
                    </label>
                    <label>Cantidad pagada: <input type="number" id="monto-pagado" min="0" step="0.01"
                            value="0"></label>
                    <label>Cambio: $<span id="cambio-amount">0.00</span></label>
                </div>
                <div class="venta-acciones">
                    <button id="imprimir-comandas-rapido" type="button">Imprimir comandas (cocina y barra)</button>
                    <button id="imprimir-pre-recibo">Imprimir Pre-Recibo</button>
                    <button id="cobrar">Cobrar</button>
                    <button id="suspender" <?= empty($user['permisos']['ventas_suspendidas_ver']) ? 'hidden' : '' ?>>Suspender Venta</button>
                    <button id="limpiar-orden">Limpiar Orden</button>
                </div>
                <p id="estado-impresion-comandas" role="status" aria-live="polite"></p>
                <div id="promo-banner" class="promo-banner"
                    style="display:none; margin-top: 12px; padding: 10px; border-radius: 6px; background:#fff3cd; color:#856404;">
                    <strong>Promoción activa:</strong> <span id="promocion-text"></span>
                </div>
                <div id="tip-panel" class="tip-panel"
                    style="display:none; margin-top: 12px; border-top: 1px solid #ddd; padding-top: 12px;">
                    <h4>Propina</h4>
                    <p>La venta ya fue cobrada. Subtotal: $<span id="subtotal-propina">0.00</span></p>
                    <div class="tip-options">
                        <button class="tip-btn" data-percent="0">Sin propina</button>
                        <button class="tip-btn" data-percent="10">10%</button>
                        <button class="tip-btn" data-percent="15">15%</button>
                        <button class="tip-btn" data-percent="20">20%</button>
                    </div>
                    <div style="margin: 10px 0; display:flex; gap:10px; align-items:center; flex-wrap:wrap;">
                        <label>Tipo de propina:
                            <select id="propina-tipo" style="padding:4px;">
                                <option value="percent">Porcentaje</option>
                                <option value="amount">Monto fijo</option>
                            </select>
                        </label>
                        <label id="propina-input-label">Propina personalizada: %<input type="number" id="propina-venta"
                                min="0" step="0.01" value="0" style="width:100px;"></label>
                    </div>
                    <div style="margin-bottom: 10px; color: #444; font-size: 0.95em;">
                        <span id="propina-valor-text">Valor actual de propina: $0.00</span>
                    </div>
                    <div class="venta-acciones">
                        <button id="guardar-propina">Guardar propina</button>
                        <button id="finalizar-propina">Cerrar panel</button>
                    </div>
                    <p>ID venta: <span id="venta-id-finalizada">N/D</span></p>
                </div>
                <button id="imprimir">Imprimir</button>
                <button id="imprimir-cocina">Imprimir Cocina</button>
                <button id="imprimir-barra">Imprimir Barra</button>
            </div>
            <section class="mesas">
                <div class="pos-service-heading">
                    <div>
                        <span class="pos-section-kicker">SERVICIO</span>
                        <h2>Mesa y mesero</h2>
                    </div>
                    <label class="pos-waiter-select">Atiende
                        <select id="mesero-select">
                            <?php foreach ($meseros_pos as $m): ?>
                                <option value="<?= (int) $m['id'] ?>"><?= $escape($m['nombre']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <?php if ($puedeConfigurar): ?>
                        <details class="mesas-config">
                            <summary>Configurar mesas</summary>
                            <label>Mesas configuradas
                                <input id="mesas-count" type="number" min="1" value="<?= count($mesas) ?>">
                            </label>
                            <button id="guardar-mesas" type="button">Guardar</button>
                        </details>
                    <?php endif; ?>
                </div>
                <div class="mesas-grid" aria-label="Selecciona una mesa">
                    <?php foreach ($mesas as $m) {
                        echo "<button type='button' class='mesa-btn' data-mesa='$m' aria-pressed='false'>Mesa $m</button>";
                    } ?>
                    <?php if (!$requiere_mesa): ?>
                        <button type="button" class="mesa-btn mesa-mostrad-btn" data-mesa="0" aria-pressed="false">Mostrador</button>
                    <?php endif; ?>
                </div>
                <p class="pos-service-selection">
                    <span>Servicio actual</span>
                    <strong><span id="current-mesa-text">Selecciona una mesa</span><span class="pos-selection-divider">·</span><span id="current-mesero-text"><?= $escape($meseros_pos[0]['nombre'] ?? '') ?></span></strong>
                </p>
            </section>
        </div>
        <div id="suspendida-selected" class="suspendida-checkout" role="dialog" aria-modal="true" aria-labelledby="suspendida-modal-title" style="display:none;">
            <section class="suspendida-checkout-content">
                <div class="pos-suspended-heading">
                    <div>
                        <span class="pos-section-kicker">DETALLE Y COBRO</span>
                        <h2 id="suspendida-modal-title">Venta suspendida <span id="selected-venta-id"></span></h2>
                    </div>
                    <button type="button" id="cancelar-suspendida" class="pos-refresh-button" aria-label="Cerrar venta suspendida">×</button>
                </div>
                <div class="suspendida-sale-meta">
                    <p>Mesero <strong id="selected-venta-mesero"></strong></p>
                    <p>Mesa <strong id="selected-venta-mesa"></strong></p>
                </div>
                <div class="suspendida-add-products">
                    <label for="suspendida-product-select">Agregar producto a la venta</label>
                    <div>
                        <select id="suspendida-product-select">
                            <option value="">Selecciona un producto</option>
                            <?php foreach ($menu as $item): ?>
                                <option value="menu:<?= (int) $item['id'] ?>" data-id="<?= (int) $item['id'] ?>" data-tipo="menu" data-nombre="<?= $escape($item['nombre'] ?? '') ?>" data-precio="<?= $escape(number_format((float) ($item['precio'] ?? 0), 2, '.', '')) ?>" data-categoria="<?= $escape($item['categoria'] ?? '') ?>">
                                    <?= $escape($item['nombre'] ?? '') ?> · $<?= $escape(number_format((float) ($item['precio'] ?? 0), 2)) ?>
                                </option>
                            <?php endforeach; ?>
                            <?php foreach ($inventarioVenta as $item): ?>
                                <option value="inventario:<?= (int) $item['id'] ?>" data-id="<?= (int) $item['id'] ?>" data-tipo="inventario" data-nombre="<?= $escape($item['item'] ?? '') ?>" data-precio="<?= $escape(number_format((float) ($item['precio_venta'] ?? 0), 2, '.', '')) ?>" data-categoria="<?= $escape($item['categoria'] ?? '') ?>" data-unidad="<?= $escape($item['unidad'] ?? 'pieza') ?>">
                                    <?= $escape($item['item'] ?? '') ?> · $<?= $escape(number_format((float) ($item['precio_venta'] ?? 0), 2)) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <button id="suspendida-add-product" type="button">Agregar</button>
                    </div>
                </div>
                <div class="suspendida-sale-items">
                    <h3>Productos de la venta</h3>
                    <ul id="suspendida-orden-list"></ul>
                    <h3>Productos nuevos</h3>
                    <ul id="suspendida-added-list"></ul>
                </div>
                <div class="venta-resumen">
                    <p>Subtotal: $<span id="suspendida-subtotal">0.00</span></p>
                    <p>Descuento: $<span id="suspendida-discount">0.00</span></p>
                    <p>Total final: $<span id="suspendida-total">0.00</span></p>
                    <p>Items totales: <span id="suspendida-item-count">0</span></p>
                </div>
                <div class="venta-acciones">
                    <label>Forma de pago:
                        <select id="suspendida-tipo-pago">
                            <?php foreach ($formas_pago as $forma): ?>
                                <option value="<?= $escape($forma) ?>" <?= $forma === $metodo_pago_default ? 'selected' : '' ?>><?= $escape($forma) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <label>Monto pagado: <input type="number" id="suspendida-monto-pagado" min="0" step="0.01" value="0"></label>
                    <label>Cambio: $<span id="suspendida-cambio">0.00</span></label>
                    <label>Propina: $<input type="number" id="suspendida-propina" min="0" step="0.01" value="0"></label>
                </div>
                <div class="venta-acciones">
                    <button id="imprimir-pre-recibo-suspendida" type="button">Imprimir pre-recibo</button>
                    <button id="imprimir-cocina-suspendida" type="button">Imprimir cocina</button>
                    <button id="imprimir-barra-suspendida" type="button">Imprimir barra</button>
                </div>
                <div class="venta-acciones">
                    <button id="guardar-suspendida" type="button">Guardar cambios</button>
                    <button id="completar-suspendida" type="button" class="suspended-checkout-primary">Completar venta y cobrar</button>
                </div>
            </section>
        </div>
        <!-- Modal de confirmación de venta -->
        <div id="confirm-modal" class="modal"
            style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.5); z-index:1000; justify-content:center; align-items:center;">
            <div class="modal-content"
                style="background:white; padding:20px; border-radius:8px; max-width:500px; width:90%;">
                <h3>Confirmar Venta</h3>
                <div id="confirm-details"></div>
                <div class="venta-acciones" style="margin-top:20px;">
                    <button id="confirm-cobrar">Confirmar y Cobrar</button>
                    <button id="cancel-confirm">Cancelar</button>
                </div>
            </div>
        </div>
        <!-- Modal para notas de item -->
        <div id="nota-modal" class="modal"
            style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.5); z-index:1000; justify-content:center; align-items:center;">
            <div class="modal-content"
                style="background:white; padding:20px; border-radius:8px; max-width:400px; width:90%;">
                <h3>Agregar Nota al Item</h3>
                <p id="nota-item-nombre" style="margin:10px 0;"></p>
                <textarea id="nota-texto" placeholder="Ej: Sin cebolla, bien cocido, extra queso"
                    style="width:100%; height:100px; padding:8px; border:1px solid #ccc; border-radius:4px; font-family:Arial, sans-serif;"></textarea>
                <div class="venta-acciones" style="margin-top:10px;">
                    <button id="guardar-nota">Guardar</button>
                    <button id="cancelar-nota">Cancelar</button>
                </div>
            </div>
        </div>
    </main>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <script src="utilidades.js"></script>
    <script>
        let orden = [];
        let mesaSeleccionada = null;
        let meseroSeleccionado = document.getElementById('mesero-select').value;
        let editingSuspendidaId = null;
        let suspendidaOriginalOrder = [];
        let suspendidaAddedItems = [];
        let ventaFinalizadaId = null;
        let subtotalVenta = 0;
        let impuesto = parseFloat('<?php echo $impuesto; ?>') || 0;
        let itemEditandoIndex = -1;
        const csrfToken = '<?= htmlspecialchars(getCsrfToken(), ENT_QUOTES | ENT_HTML5, 'UTF-8') ?>';
        const promocionesActivas = <?php echo json_encode($promociones_pos, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT); ?>;
        const configuracionPOS = <?php echo json_encode([
            'requiereMesa' => $requiere_mesa,
            'confirmarVenta' => !empty($config['pos_confirmar_venta']),
            'enfocarBusqueda' => !empty($config['pos_focus_search']),
            'ventasSuspendidas' => !empty($user['permisos']['ventas_suspendidas_ver']),
            'suspendidaInicialId' => $suspendidaInicialId ?: null,
            'recibos' => $config_recibos,
            'comandas' => $config_comandas,
            'impresoras' => $config_impresoras,
        ], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT); ?>;

        const currentMeseroText = document.getElementById('current-mesero-text');
        const currentMesaText = document.getElementById('current-mesa-text');
        const selectedVentaId = document.getElementById('selected-venta-id');
        const selectedVentaMesero = document.getElementById('selected-venta-mesero');
        const selectedVentaMesa = document.getElementById('selected-venta-mesa');
        const suspendidaOrdenList = document.getElementById('suspendida-orden-list');
        const suspendidaAddedList = document.getElementById('suspendida-added-list');
        const suspendidaSelectedSection = document.getElementById('suspendida-selected');
        const propinaPanel = document.getElementById('tip-panel');
        const ventaIdFinalizada = document.getElementById('venta-id-finalizada');
        const montoPagadoInput = document.getElementById('monto-pagado');
        const cambioAmount = document.getElementById('cambio-amount');
        const tipoPagoSelect = document.getElementById('tipo-pago');
        let propinaVentaInput = document.getElementById('propina-venta');
        const propinaTipoSelect = document.getElementById('propina-tipo');
        const propinaInputLabel = document.getElementById('propina-input-label');
        const propinaValorText = document.getElementById('propina-valor-text');
        const suspendidaMontoPagadoInput = document.getElementById('suspendida-monto-pagado');
        const suspendidaCambioDisplay = document.getElementById('suspendida-cambio');
        const estadoMain = document.getElementById('estado-main');
        const suspendidaTotalDisplay = document.getElementById('suspendida-total');
        const suspendidaItemCount = document.getElementById('suspendida-item-count');
        const btnLimpiarOrden = document.getElementById('limpiar-orden');
        const btnCancelarSuspendida = document.getElementById('cancelar-suspendida');

        function actualizarMeseroInfo() {
            const sel = document.querySelector('#mesero-select');
            currentMeseroText.textContent = sel.options[sel.selectedIndex].text;
        }

        function calcularTotal(order = orden) {
            return order.reduce((sum, item) => {
                const quantity = Math.max(1, parseInt(item.cantidad || 1, 10));
                return sum + (parseFloat(item.precio) || 0) * quantity;
            }, 0);
        }

        function obtenerDescuentoPromocion(subtotal) {
            const promocion = promocionesActivas.reduce((mejor, actual) =>
                actual.descuento > mejor.descuento ? actual : mejor,
                { nombre: '', descuento: 0 }
            );
            return {
                nombre: promocion.nombre,
                monto: subtotal * (promocion.descuento / 100)
            };
        }

        function calcularTotalFinal(order = orden) {
            const subtotal = calcularTotal(order);
            const descuentoPromo = obtenerDescuentoPromocion(subtotal).monto;
            const descuentoManual = Math.max(0, parseFloat(document.getElementById('descuento-manual').value) || 0);
            const totalConDescuentos = Math.max(0, subtotal - descuentoPromo - descuentoManual);
            const impuestoCalculado = totalConDescuentos * (impuesto / 100);
            return totalConDescuentos + impuestoCalculado;
        }

        function actualizarCambio() {
            const total = calcularTotalFinal();
            const monto = parseFloat(montoPagadoInput.value) || 0;
            const cambio = tipoPagoSelect.value === 'Efectivo' ? Math.max(0, monto - total) : 0;
            cambioAmount.textContent = cambio.toFixed(2);
        }

        function actualizarSuspendidaCambio() {
            const total = Number(suspendidaTotalDisplay.textContent) || 0;
            const monto = parseFloat(suspendidaMontoPagadoInput.value) || 0;
            const cambio = document.getElementById('suspendida-tipo-pago').value === 'Efectivo' ? Math.max(0, monto - total) : 0;
            suspendidaCambioDisplay.textContent = cambio.toFixed(2);
        }

        function actualizarEstadoMain() {
            if (!orden.length) {
                estadoMain.textContent = 'No hay productos en la orden. Agregue artículos para comenzar.';
                return;
            }
            if (!mesaSeleccionada && configuracionPOS.requiereMesa) {
                estadoMain.textContent = 'Seleccione una mesa antes de continuar.';
                return;
            }
            estadoMain.textContent = 'Orden lista para cobrar o suspender. Confirme la mesa y el mesero.';
        }

        function actualizarOrden() {
            const list = document.getElementById('orden-list');
            const subtotalAmount = document.getElementById('subtotal-amount');
            const discountAmount = document.getElementById('discount-amount');
            const taxAmount = document.getElementById('tax-amount');
            const totalAmount = document.getElementById('total-amount');
            const itemCount = document.getElementById('item-count');
            const promoBanner = document.getElementById('promo-banner');
            const promocionText = document.getElementById('promocion-text');

            list.innerHTML = '';
            orden.forEach((item, index) => {
                const li = document.createElement('li');
                li.className = 'order-line';
                const quantity = Math.max(1, parseInt(item.cantidad || 1, 10));
                const itemInfo = document.createElement('div');
                itemInfo.className = 'order-line-info';
                const itemName = document.createElement('strong');
                itemName.className = 'order-line-name';
                itemName.textContent = item.nombre;
                const itemMeta = document.createElement('span');
                itemMeta.className = 'order-line-meta';
                itemMeta.textContent = quantity + ' × $' + item.precio.toFixed(2) +
                    (item.tipo === 'inventario' ? ' / ' + (item.unidad || 'pieza') : '');
                itemInfo.appendChild(itemName);
                itemInfo.appendChild(itemMeta);
                const lineTotal = document.createElement('strong');
                lineTotal.className = 'order-line-total';
                lineTotal.textContent = '$' + (quantity * item.precio).toFixed(2);
                itemInfo.appendChild(lineTotal);
                if (item.nota) {
                    const itemNote = document.createElement('span');
                    itemNote.className = 'order-line-note';
                    itemNote.textContent = item.nota;
                    itemInfo.appendChild(itemNote);
                }

                const quantityInput = document.createElement('input');
                quantityInput.className = 'order-line-quantity';
                quantityInput.type = 'number';
                quantityInput.min = '1';
                quantityInput.max = '10000';
                quantityInput.step = '1';
                quantityInput.value = String(quantity);
                quantityInput.setAttribute('aria-label', 'Cantidad de ' + item.nombre);
                quantityInput.addEventListener('change', function () {
                    const newQuantity = Number.parseInt(this.value, 10);
                    if (!Number.isInteger(newQuantity) || newQuantity < 1 || newQuantity > 10000) {
                        this.value = String(quantity);
                        return;
                    }
                    orden[index].cantidad = newQuantity;
                    actualizarOrden();
                });

                const decreaseQuantity = document.createElement('button');
                decreaseQuantity.type = 'button';
                decreaseQuantity.className = 'order-line-stepper';
                decreaseQuantity.textContent = '−';
                decreaseQuantity.setAttribute('aria-label', 'Disminuir cantidad de ' + item.nombre);
                decreaseQuantity.disabled = quantity <= 1;
                decreaseQuantity.addEventListener('click', function () {
                    if (quantity > 1) {
                        orden[index].cantidad = quantity - 1;
                        actualizarOrden();
                    }
                });
                const increaseQuantity = document.createElement('button');
                increaseQuantity.type = 'button';
                increaseQuantity.className = 'order-line-stepper';
                increaseQuantity.textContent = '+';
                increaseQuantity.setAttribute('aria-label', 'Aumentar cantidad de ' + item.nombre);
                increaseQuantity.disabled = quantity >= 10000;
                increaseQuantity.addEventListener('click', function () {
                    if (quantity < 10000) {
                        orden[index].cantidad = quantity + 1;
                        actualizarOrden();
                    }
                });

                const editNote = document.createElement('button');
                editNote.type = 'button';
                editNote.textContent = 'Nota';
                editNote.className = 'order-line-note-button';
                editNote.setAttribute('aria-label', 'Agregar nota a ' + item.nombre);
                editNote.addEventListener('click', function () {
                    itemEditandoIndex = index;
                    document.getElementById('nota-item-nombre').textContent = item.nombre;
                    document.getElementById('nota-texto').value = item.nota || '';
                    document.getElementById('nota-modal').style.display = 'flex';
                });

                const remove = document.createElement('button');
                remove.type = 'button';
                remove.textContent = '×';
                remove.className = 'order-line-remove';
                remove.setAttribute('aria-label', 'Quitar ' + item.nombre + ' del pedido');
                remove.addEventListener('click', function () {
                    orden.splice(index, 1);
                    actualizarOrden();
                });
                const controls = document.createElement('div');
                controls.className = 'order-line-controls';
                controls.appendChild(decreaseQuantity);
                controls.appendChild(quantityInput);
                controls.appendChild(increaseQuantity);
                controls.appendChild(editNote);
                controls.appendChild(remove);
                li.appendChild(itemInfo);
                li.appendChild(controls);
                list.appendChild(li);
            });

            const subtotal = calcularTotal();
            const promocion = obtenerDescuentoPromocion(subtotal);
            const descuento = promocion.monto;
            const promocionActiva = promocion.monto > 0 ? promocion.nombre : '';

            const descuentoManual = Math.max(0, parseFloat(document.getElementById('descuento-manual').value) || 0);
            const descuentoTotal = Math.min(subtotal, descuento + descuentoManual);
            const totalConDescuentos = Math.max(0, subtotal - descuentoTotal);
            const impuestoCalculado = totalConDescuentos * (impuesto / 100);
            const totalConImpuesto = totalConDescuentos + impuestoCalculado;

            subtotalVenta = subtotal;

            subtotalAmount.textContent = subtotal.toFixed(2);
            discountAmount.textContent = descuentoTotal.toFixed(2);
            taxAmount.textContent = impuestoCalculado.toFixed(2);
            totalAmount.textContent = totalConImpuesto.toFixed(2);
            itemCount.textContent = orden.reduce((count, item) => count + Math.max(1, parseInt(item.cantidad || 1, 10)), 0);

            if (tipoPagoSelect.value !== 'Efectivo') {
                montoPagadoInput.value = totalConImpuesto.toFixed(2);
                montoPagadoInput.disabled = true;
            } else {
                montoPagadoInput.disabled = false;
            }

            if (promocionActiva) {
                promocionText.textContent = promocionActiva;
                promoBanner.style.display = 'block';
            } else {
                promoBanner.style.display = 'none';
            }

            actualizarCambio();
            actualizarEstadoMain();
        }

        function crearArticuloEditable(item, index, esNuevo) {
            const li = document.createElement('li');
            li.className = 'suspendida-edit-item';
            const name = document.createElement('span');
            name.className = 'suspendida-edit-item-name';
            const unit = item.tipo === 'inventario' ? ' / ' + (item.unidad || 'pieza') : '';
            name.textContent = (item.nombre || 'Artículo') + ' · $' + (Number(item.precio) || 0).toFixed(2) + unit;

            const controls = document.createElement('div');
            controls.className = 'suspendida-edit-controls';
            const quantity = document.createElement('input');
            quantity.type = 'number';
            quantity.min = '1';
            quantity.max = '10000';
            quantity.step = '1';
            quantity.value = String(Math.max(1, Number.parseInt(item.cantidad || 1, 10)));
            quantity.setAttribute('aria-label', 'Cantidad de ' + (item.nombre || 'artículo'));
            quantity.addEventListener('change', function () {
                const value = Number.parseInt(this.value, 10);
                if (!Number.isInteger(value) || value < 1 || value > 10000) {
                    this.value = String(Math.max(1, Number.parseInt(item.cantidad || 1, 10)));
                    return;
                }
                item.cantidad = value;
                actualizarSuspendidaOrden();
            });

            const note = document.createElement('input');
            note.type = 'text';
            note.maxLength = 500;
            note.placeholder = 'Nota opcional';
            note.value = item.nota || '';
            note.setAttribute('aria-label', 'Nota de ' + (item.nombre || 'artículo'));
            note.addEventListener('change', function () {
                item.nota = this.value.trim();
            });

            const remove = document.createElement('button');
            remove.type = 'button';
            remove.textContent = 'Quitar';
            remove.addEventListener('click', function () {
                if (esNuevo) {
                    suspendidaAddedItems.splice(index, 1);
                } else {
                    suspendidaOriginalOrder.splice(index, 1);
                }
                actualizarSuspendidaOrden();
            });
            controls.append(quantity, note, remove);
            li.append(name, controls);
            return li;
        }

        function obtenerEdicionesArticulosExistentes() {
            return suspendidaOriginalOrder.map(item => ({
                indice: item.indiceOriginal,
                cantidad: Math.max(1, Number.parseInt(item.cantidad || 1, 10)),
                nota: item.nota || ''
            }));
        }

        function actualizarSuspendidaOrden() {
            suspendidaOrdenList.innerHTML = '';
            suspendidaAddedList.innerHTML = '';
            if (!suspendidaOriginalOrder.length) {
                const empty = document.createElement('li');
                empty.textContent = 'No hay productos originales; agrega productos para continuar.';
                suspendidaOrdenList.appendChild(empty);
            }
            suspendidaOriginalOrder.forEach((item, index) => {
                suspendidaOrdenList.appendChild(crearArticuloEditable(item, index, false));
            });
            suspendidaAddedItems.forEach((item, index) => {
                suspendidaAddedList.appendChild(crearArticuloEditable(item, index, true));
            });

            const ordenCompleta = suspendidaOriginalOrder.concat(suspendidaAddedItems);
            const subtotal = calcularTotal(ordenCompleta);
            const descuento = obtenerDescuentoPromocion(subtotal).monto;

            const totalFinal = Math.max(0, subtotal - descuento);
            const totalConImpuesto = Math.round((totalFinal + (totalFinal * (impuesto / 100))) * 100) / 100;

            suspendidaMontoPagadoInput.value = totalConImpuesto.toFixed(2);
            document.getElementById('suspendida-subtotal').textContent = subtotal.toFixed(2);
            document.getElementById('suspendida-discount').textContent = descuento.toFixed(2);
            suspendidaTotalDisplay.textContent = totalConImpuesto.toFixed(2);
            suspendidaItemCount.textContent = ordenCompleta.reduce((count, item) => count + Math.max(1, parseInt(item.cantidad || 1, 10)), 0).toString();

            if (document.getElementById('suspendida-tipo-pago').value !== 'Efectivo') {
                suspendidaMontoPagadoInput.value = totalConImpuesto.toFixed(2);
                suspendidaMontoPagadoInput.disabled = true;
            } else {
                suspendidaMontoPagadoInput.disabled = false;
            }

            actualizarSuspendidaCambio();
        }

        function resetVentaFinalizadaPanel() {
            propinaPanel.style.display = 'none';
            ventaFinalizadaId = null;
            propinaVentaInput.value = '0';
            propinaTipoSelect.value = 'percent';
            actualizarPropinaTipo();
            ventaIdFinalizada.textContent = 'N/D';
        }

        function calcularPropinaActual() {
            const subtotal = parseFloat(document.getElementById('subtotal-propina').textContent) || 0;
            const tipo = propinaTipoSelect.value;
            const valor = parseFloat(propinaVentaInput.value) || 0;
            if (tipo === 'percent') {
                return subtotal * (valor / 100);
            }
            return valor;
        }

        function actualizarPropinaTipo() {
            if (propinaTipoSelect.value === 'percent') {
                propinaInputLabel.innerHTML = 'Propina personalizada: %<input type="number" id="propina-venta" min="0" step="0.01" value="' + (parseFloat(propinaVentaInput.value) || 0) + '" style="width:100px;">';
            } else {
                propinaInputLabel.innerHTML = 'Propina personalizada: $<input type="number" id="propina-venta" min="0" step="0.01" value="' + (parseFloat(propinaVentaInput.value) || 0) + '" style="width:100px;">';
            }
            propinaVentaInput = document.getElementById('propina-venta');
            propinaVentaInput.addEventListener('input', function () {
                const propinaActual = calcularPropinaActual();
                propinaValorText.textContent = 'Valor actual de propina: $' + propinaActual.toFixed(2);
            });
            const propinaActual = calcularPropinaActual();
            propinaValorText.textContent = 'Valor actual de propina: $' + propinaActual.toFixed(2);
        }

        function resetOrdenActual() {
            orden = [];
            mesaSeleccionada = null;
            currentMesaText.textContent = 'Ninguna';
            actualizarOrden();
        }

        function resetSuspendidaSelection() {
            editingSuspendidaId = null;
            suspendidaOriginalOrder = [];
            suspendidaAddedItems = [];
            suspendidaSelectedSection.style.display = 'none';
            selectedVentaId.textContent = '';
            selectedVentaMesero.textContent = '';
            selectedVentaMesa.textContent = '';
            document.getElementById('suspendida-propina').value = '0';
            document.getElementById('suspendida-monto-pagado').value = '0';
            suspendidaCambioDisplay.textContent = '0.00';
            suspendidaTotalDisplay.textContent = '0.00';
            suspendidaItemCount.textContent = '0';
        }

        function obtenerContextoImpresion() {
            if (editingSuspendidaId) {
                const mesaTexto = selectedVentaMesa.textContent.trim();
                return {
                    mesa: mesaTexto === 'Mostrador' ? '0' : mesaTexto,
                    mesero: selectedVentaMesero.textContent.trim()
                };
            }

            const meseroSelect = document.getElementById('mesero-select');
            return {
                mesa: mesaSeleccionada,
                mesero: meseroSelect.options[meseroSelect.selectedIndex]?.text || ''
            };
        }

        actualizarMeseroInfo();

        document.getElementById('mesero-select').addEventListener('change', function () {
            meseroSeleccionado = this.value;
            actualizarMeseroInfo();
        });

        document.querySelectorAll('.mesa-btn').forEach(btn => {
            btn.addEventListener('click', function () {
                mesaSeleccionada = this.dataset.mesa;
                currentMesaText.textContent = mesaSeleccionada === '0' ? 'Mostrador' : 'Mesa ' + mesaSeleccionada;
                document.querySelectorAll('.mesa-btn').forEach(mesa => {
                    mesa.classList.remove('selected');
                    mesa.setAttribute('aria-pressed', 'false');
                });
                this.classList.add('selected');
                this.setAttribute('aria-pressed', 'true');
                actualizarEstadoMain();
            });
        });

        document.querySelectorAll('.menu-item').forEach(btn => {
            btn.addEventListener('click', function () {
                const item = {
                    id: this.dataset.id,
                    tipo: this.dataset.tipo || 'menu',
                    nombre: this.dataset.nombre,
                    precio: parseFloat(this.dataset.precio),
                    categoria: this.dataset.categoria || '',
                    cantidad: 1
                };
                if (this.dataset.tipo === 'inventario') {
                    item.unidad = this.dataset.unidad || 'pieza';
                }
                if (editingSuspendidaId) {
                    suspendidaAddedItems.push(item);
                    actualizarSuspendidaOrden();
                } else {
                    orden.push(item);
                    actualizarOrden();
                }
            });
        });

        // Menu filtering functionality
        function filtrarMenu() {
            const searchTerm = document.getElementById('menu-search').value.toLowerCase();
            const categoriaSeleccionada = document.getElementById('menu-categoria').value;
            const menuItems = document.querySelectorAll('.menu-item');
            let visibleCount = 0;
            let visibleInventoryCount = 0;

            menuItems.forEach(item => {
                const nombre = item.dataset.nombre.toLowerCase();
                const categoria = item.dataset.categoria || '';
                const matchesSearch = nombre.includes(searchTerm);
                const matchesCategoria = !categoriaSeleccionada || categoria === categoriaSeleccionada;
                const visible = matchesSearch && matchesCategoria;
                item.hidden = !visible;
                if (visible) {
                    visibleCount++;
                    if (item.classList.contains('inventory-sale-item')) {
                        visibleInventoryCount++;
                    }
                }
            });
            document.getElementById('menu-result-count').textContent = String(visibleCount);
            const inventoryHeading = document.querySelector('.pos-inventory-heading');
            if (inventoryHeading) {
                inventoryHeading.hidden = visibleInventoryCount === 0;
            }
            document.querySelectorAll('.pos-category-chip').forEach(chip => {
                const selected = chip.dataset.category === categoriaSeleccionada;
                chip.classList.toggle('active', selected);
                chip.setAttribute('aria-pressed', selected ? 'true' : 'false');
            });
        }

        document.getElementById('menu-search').addEventListener('input', filtrarMenu);
        document.getElementById('menu-categoria').addEventListener('change', filtrarMenu);
        document.querySelectorAll('.pos-category-chip').forEach(chip => {
            chip.addEventListener('click', function () {
                document.getElementById('menu-categoria').value = this.dataset.category;
                filtrarMenu();
            });
        });

        tipoPagoSelect.addEventListener('change', function () {
            const total = calcularTotalFinal();
            if (this.value !== 'Efectivo') {
                montoPagadoInput.value = total.toFixed(2);
                montoPagadoInput.disabled = true;
            } else {
                montoPagadoInput.disabled = false;
            }
            actualizarCambio();
        });

        montoPagadoInput.addEventListener('input', actualizarCambio);
        suspendidaMontoPagadoInput.addEventListener('input', actualizarSuspendidaCambio);

        // Descuento manual listener
        document.getElementById('descuento-manual').addEventListener('input', function () {
            actualizarOrden();
            actualizarCambio();
        });

        // Modal de notas listeners
        document.getElementById('guardar-nota').addEventListener('click', function () {
            if (itemEditandoIndex >= 0 && itemEditandoIndex < orden.length) {
                orden[itemEditandoIndex].nota = document.getElementById('nota-texto').value.trim();
                document.getElementById('nota-modal').style.display = 'none';
                itemEditandoIndex = -1;
                actualizarOrden();
            }
        });

        document.getElementById('cancelar-nota').addEventListener('click', function () {
            document.getElementById('nota-modal').style.display = 'none';
            itemEditandoIndex = -1;
        });
        document.getElementById('suspendida-tipo-pago').addEventListener('change', function () {
            const ordenCompleta = suspendidaOriginalOrder.concat(suspendidaAddedItems);
            const subtotal = calcularTotal(ordenCompleta);
            const descuento = obtenerDescuentoPromocion(subtotal).monto;

            const totalFinal = Math.max(0, subtotal - descuento);
            const totalConImpuesto = Math.round((totalFinal + (totalFinal * (impuesto / 100))) * 100) / 100;
            suspendidaMontoPagadoInput.value = totalConImpuesto.toFixed(2);
            suspendidaMontoPagadoInput.disabled = this.value !== 'Efectivo';
            actualizarSuspendidaCambio();
        });
        suspendidaMontoPagadoInput.addEventListener('input', actualizarSuspendidaCambio);

        btnLimpiarOrden.addEventListener('click', function () {
            resetOrdenActual();
            resetVentaFinalizadaPanel();
            estadoMain.textContent = 'Orden limpia. Seleccione mesa y productos para comenzar de nuevo.';
        });

        btnCancelarSuspendida.addEventListener('click', function () {
            resetSuspendidaSelection();
            if (configuracionPOS.suspendidaInicialId) {
                window.history.replaceState(null, '', 'pos.php');
            }
        });
        suspendidaSelectedSection.addEventListener('click', function (event) {
            if (event.target === suspendidaSelectedSection) {
                resetSuspendidaSelection();
                if (configuracionPOS.suspendidaInicialId) {
                    window.history.replaceState(null, '', 'pos.php');
                }
            }
        });

        document.getElementById('suspender').addEventListener('click', function () {
            if (!mesaSeleccionada && !configuracionPOS.requiereMesa) {
                mesaSeleccionada = '0';
                currentMesaText.textContent = 'Mostrador';
            }
            if (mesaSeleccionada === null) {
                alert('Seleccione una mesa antes de suspender la venta.');
                return;
            }
            if (!meseroSeleccionado) {
                alert('Seleccione un mesero antes de suspender la venta.');
                return;
            }
            if (!orden.length) {
                alert('Agregue productos antes de suspender la venta.');
                return;
            }
            const ordenParaImprimir = orden.map(item => ({ ...item }));
            const contextoImpresion = obtenerContextoImpresion();
            const subtotal = calcularTotal();
            const descuento = subtotal - calcularTotalFinal();
            const total = calcularTotalFinal();
            fetch('guardar_venta.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrfToken },
                body: JSON.stringify({ orden: ordenParaImprimir, mesa: mesaSeleccionada, mesero: meseroSeleccionado, suspendida: true, csrf_token: csrfToken })
            })
                .then(response => response.json())
                .then(data => {
                    if (data.status === 'ok') {
                        resetOrdenActual();
                        resetVentaFinalizadaPanel();
                        estadoMain.textContent = 'Venta suspendida. Puede iniciar una nueva orden.';
                        alert('Venta suspendida correctamente.');
                        imprimirPreRecibo(ordenParaImprimir, subtotal, descuento, total, contextoImpresion);
                        imprimirComandaCocina(ordenParaImprimir, contextoImpresion);
                        imprimirComandaBarra(ordenParaImprimir, contextoImpresion);
                    } else {
                        alert(data.message || 'Error al suspender la venta');
                    }
                });
        });

        document.getElementById('cobrar').addEventListener('click', function () {
            if (!mesaSeleccionada && !configuracionPOS.requiereMesa) {
                mesaSeleccionada = '0';
                currentMesaText.textContent = 'Mostrador';
            }
            if (mesaSeleccionada === null) {
                alert('Seleccione una mesa antes de completar la venta.');
                return;
            }
            if (!meseroSeleccionado) {
                alert('Seleccione un mesero antes de completar la venta.');
                return;
            }
            if (!orden.length) {
                alert('Agregue productos antes de completar la venta.');
                return;
            }
            // Mostrar modal de confirmación
            const subtotal = calcularTotal();
            const descuento = subtotal - calcularTotalFinal();
            const total = calcularTotalFinal();
            const tipoPago = tipoPagoSelect.value;
            const montoPagado = parseFloat(montoPagadoInput.value) || 0;

            let details = `<p><strong>Mesa:</strong> ${mesaSeleccionada === '0' ? 'Mostrador' : mesaSeleccionada}</p>`;
            details += `<p><strong>Mesero:</strong> ${document.getElementById('mesero-select').options[document.getElementById('mesero-select').selectedIndex].text}</p>`;
            details += `<p><strong>Items:</strong></p><ul>`;
            orden.forEach(item => {
                details += `<li>${Math.max(1, parseInt(item.cantidad || 1, 10))} x ${item.nombre} - $${item.precio.toFixed(2)}</li>`;
            });
            details += `</ul>`;
            details += `<p><strong>Subtotal:</strong> $${subtotal.toFixed(2)}</p>`;
            if (descuento > 0) details += `<p><strong>Descuento:</strong> $${descuento.toFixed(2)}</p>`;
            details += `<p><strong>Total:</strong> $${total.toFixed(2)}</p>`;
            details += `<p><strong>Forma de pago:</strong> ${tipoPago}</p>`;
            if (tipoPago === 'Efectivo') {
                details += `<p><strong>Monto pagado:</strong> $${montoPagado.toFixed(2)}</p>`;
                details += `<p><strong>Cambio:</strong> $${Math.max(0, montoPagado - total).toFixed(2)}</p>`;
            }

            document.getElementById('confirm-details').innerHTML = details;
            if (configuracionPOS.confirmarVenta) {
                document.getElementById('confirm-modal').style.display = 'flex';
            } else {
                document.getElementById('confirm-cobrar').click();
            }
        });

        document.getElementById('confirm-cobrar').addEventListener('click', function () {
            // Proceder con el cobro
            const tipoPago = tipoPagoSelect.value;
            const subtotal = calcularTotal();
            const promocion = obtenerDescuentoPromocion(subtotal);
            const descuentoManual = Math.max(0, parseFloat(document.getElementById('descuento-manual').value) || 0);
            const descuentoTotal = Math.min(subtotal, promocion.monto + descuentoManual);
            const subtotalConDescuento = Math.max(0, subtotal - descuentoTotal);
            const impuestoCalculado = subtotalConDescuento * (impuesto / 100);
            const total = subtotalConDescuento + impuestoCalculado;
            const montoPagado = parseFloat(montoPagadoInput.value) || 0;
            const ordenParaImprimir = orden.map(item => ({ ...item }));
            const contextoImpresion = obtenerContextoImpresion();
            const esEfectivo = tipoPago === 'Efectivo';
            if (esEfectivo && montoPagado < total) {
                alert('El monto pagado debe ser mayor o igual al total para efectivo.');
                return;
            }
            const cambio = esEfectivo ? Math.max(0, montoPagado - total) : 0;
            document.getElementById('confirm-modal').style.display = 'none';
            fetch('guardar_venta.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrfToken },
                body: JSON.stringify({
                    orden: ordenParaImprimir,
                    mesa: mesaSeleccionada,
                    mesero: meseroSeleccionado,
                    suspendida: false,
                    tipo_pago: tipoPago,
                    monto_pagado: montoPagado,
                    descuento: descuentoTotal,
                    impuesto: impuestoCalculado,
                    propina: 0,
                    csrf_token: csrfToken
                })
            })
                .then(response => response.json())
                .then(data => {
                    if (data.status === 'ok') {
                        ventaFinalizadaId = data.id;
                        ventaIdFinalizada.textContent = data.id;
                        document.getElementById('subtotal-propina').textContent = subtotalVenta.toFixed(2);
                        propinaPanel.style.display = 'block';
                        resetOrdenActual();
                        estadoMain.textContent = 'Venta completada. Ingrese propina o cierre el panel.';
                        alert('Venta cobrada correctamente. Cambio: $' + cambio.toFixed(2));
                        // Imprimir recibo final
                        imprimirRecibo(data.id, ordenParaImprimir, subtotal, descuentoTotal, total, tipoPago, montoPagado, cambio, contextoImpresion);
                    } else {
                        alert(data.message || 'Error al completar la venta');
                    }
                });
        });

        document.getElementById('cancel-confirm').addEventListener('click', function () {
            document.getElementById('confirm-modal').style.display = 'none';
        });

        document.getElementById('imprimir-pre-recibo').addEventListener('click', function () {
            if (!orden.length) {
                alert('Agregue productos para imprimir el pre-recibo.');
                return;
            }
            const subtotal = calcularTotal();
            const descuento = subtotal - calcularTotalFinal();
            const total = calcularTotalFinal();
            imprimirPreRecibo(orden, subtotal, descuento, total);
        });

        document.getElementById('imprimir-comandas-rapido').addEventListener('click', async function () {
            if (!orden.length) {
                alert('Agregue productos antes de imprimir las comandas.');
                return;
            }

            const button = this;
            const status = document.getElementById('estado-impresion-comandas');
            button.disabled = true;
            status.textContent = 'Enviando comandas a las estaciones…';
            try {
                const results = await Promise.all([
                    imprimirComandaCocina(orden),
                    imprimirComandaBarra(orden)
                ]);
                const sentCount = results.filter(result => result === true).length;
                const failedCount = results.filter(result => result === false).length;
                const attemptedCount = results.filter(result => result !== null).length;
                if (failedCount > 0) {
                    status.textContent = sentCount > 0
                        ? 'Impresión parcial: una estación no confirmó la comanda. Revise las alertas y la impresora.'
                        : 'No se confirmó la impresión. Revise las alertas y la impresora.';
                } else if (sentCount > 0) {
                    status.textContent = sentCount === 2
                        ? 'Comandas enviadas a cocina y barra.'
                        : 'Comanda enviada a la estación que tiene productos asignados.';
                } else {
                    status.textContent = attemptedCount === 0
                        ? 'No hay productos asignados a cocina o barra en esta orden.'
                        : 'No se pudo confirmar la impresión.';
                }
            } catch (error) {
                status.textContent = error.message || 'No se pudieron enviar las comandas.';
            } finally {
                button.disabled = false;
            }
        });

        document.getElementById('imprimir').addEventListener('click', function () {
            if (!orden.length) {
                alert('Agregue productos para imprimir.');
                return;
            }
            const subtotal = calcularTotal();
            const descuento = subtotal - calcularTotalFinal();
            const total = calcularTotalFinal();
            imprimirPreRecibo(orden, subtotal, descuento, total);
        });

        document.getElementById('imprimir-cocina').addEventListener('click', function () {
            if (!orden.length) {
                alert('Agregue productos para imprimir la comanda de cocina.');
                return;
            }
            imprimirComandaCocina(orden);
        });

        document.getElementById('imprimir-barra').addEventListener('click', function () {
            if (!orden.length) {
                alert('Agregue productos para imprimir la comanda de barra.');
                return;
            }
            imprimirComandaBarra(orden);
        });

        document.getElementById('guardar-propina').addEventListener('click', function () {
            if (!ventaFinalizadaId) {
                alert('No hay venta finalizada para asignar propina.');
                return;
            }
            const propina = calcularPropinaActual();
            fetch('actualizar_propina.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrfToken },
                body: JSON.stringify({ id: ventaFinalizadaId, propina, csrf_token: csrfToken })
            })
                .then(response => response.json())
                .then(data => {
                    if (data.status === 'ok') {
                        alert('Propina registrada correctamente.');
                        resetVentaFinalizadaPanel();
                        ventaIdFinalizada.textContent = 'N/D';
                    } else {
                        alert(data.message || 'Error al guardar la propina');
                    }
                });
        });


        document.getElementById('finalizar-propina').addEventListener('click', function () {
            resetVentaFinalizadaPanel();
        });

        propinaTipoSelect.addEventListener('change', function () {
            actualizarPropinaTipo();
        });

        function abrirSuspendidaInicial() {
            const saleId = Number(configuracionPOS.suspendidaInicialId);
            if (!saleId || !configuracionPOS.ventasSuspendidas) return;
            fetch('buscar_suspendidas.php')
                .then(response => {
                    if (!response.ok) throw new Error('No se pudieron consultar las ventas en espera.');
                    return response.json();
                })
                .then(data => {
                    if (data.status !== 'ok' || !Array.isArray(data.ventas)) {
                        throw new Error(data.message || 'La respuesta del servidor no contiene ventas.');
                    }
                    const sale = data.ventas.find(venta => Number(venta.id) === saleId);
                    if (!sale) {
                        alert('La venta en espera ya no existe o fue completada.');
                        return;
                    }
                    cargarSuspendida(sale);
                })
                .catch(error => alert(error.message || 'No se pudo abrir la venta en espera.'));
        }

        function cargarSuspendida(venta) {
            editingSuspendidaId = venta.id;
            suspendidaOriginalOrder = Array.isArray(venta.orden)
                ? venta.orden
                    .map((item, index) => item && typeof item === 'object' && !Array.isArray(item)
                        ? { ...item, indiceOriginal: index }
                        : null)
                    .filter(item => item !== null)
                : [];
            suspendidaAddedItems = [];
            selectedVentaId.textContent = venta.id;
            selectedVentaMesero.textContent = venta.mesero_nombre || 'N/A';
            selectedVentaMesa.textContent = Number(venta.mesa) === 0 ? 'Mostrador' : venta.mesa;
            suspendidaSelectedSection.style.display = 'flex';
            actualizarSuspendidaOrden();
            document.getElementById('suspendida-tipo-pago').focus();
        }

        document.getElementById('suspendida-add-product').addEventListener('click', function () {
            const select = document.getElementById('suspendida-product-select');
            const option = select.selectedOptions[0];
            if (!option || !option.value) {
                alert('Selecciona un producto para agregar a la venta.');
                return;
            }
            const item = {
                id: option.dataset.id,
                tipo: option.dataset.tipo || 'menu',
                nombre: option.dataset.nombre || '',
                precio: Number(option.dataset.precio) || 0,
                categoria: option.dataset.categoria || '',
                cantidad: 1
            };
            if (item.tipo === 'inventario') {
                item.unidad = option.dataset.unidad || 'pieza';
            }
            suspendidaAddedItems.push(item);
            select.value = '';
            actualizarSuspendidaOrden();
        });

        document.getElementById('guardar-suspendida').addEventListener('click', function () {
            if (!editingSuspendidaId) {
                alert('Seleccione una venta suspendida primero.');
                return;
            }
            const nuevos = suspendidaAddedItems.slice();
            const contextoImpresion = obtenerContextoImpresion();
            fetch('editar_suspendida.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrfToken },
                body: JSON.stringify({
                    id: editingSuspendidaId,
                    items_existentes: obtenerEdicionesArticulosExistentes(),
                    nuevos_items: suspendidaAddedItems,
                    csrf_token: csrfToken
                })
            })
                .then(response => response.json())
                .then(data => {
                    if (data.status === 'ok') {
                        const saleId = editingSuspendidaId;
                        if (nuevos.length) {
                            imprimirComandaCocina(nuevos, contextoImpresion);
                            imprimirComandaBarra(nuevos, contextoImpresion);
                        }
                        suspendidaOriginalOrder = [];
                        suspendidaAddedItems = [];
                        editingSuspendidaId = null;
                        suspendidaSelectedSection.style.display = 'none';
                        selectedVentaId.textContent = '';
                        selectedVentaMesero.textContent = '';
                        selectedVentaMesa.textContent = '';
                        actualizarSuspendidaOrden();
                        window.location.href = 'ventas_suspendidas.php?actualizada=' + encodeURIComponent(saleId);
                    } else {
                        alert(data.message || 'Error al actualizar suspensión.');
                    }
                });
        });

        document.getElementById('completar-suspendida').addEventListener('click', function () {
            if (!editingSuspendidaId) {
                alert('Seleccione una venta suspendida primero.');
                return;
            }
            const tipoPago = document.getElementById('suspendida-tipo-pago').value;
            const propina = parseFloat(document.getElementById('suspendida-propina').value) || 0;
            const montoPagadoInput = document.getElementById('suspendida-monto-pagado');
            const ordenCompleta = suspendidaOriginalOrder.concat(suspendidaAddedItems);
            const contextoImpresion = obtenerContextoImpresion();
            const subtotal = calcularTotal(ordenCompleta);
            const promocion = obtenerDescuentoPromocion(subtotal);
            const descuento = Math.min(subtotal, promocion.monto);
            const subtotalConDescuento = Math.max(0, subtotal - descuento);
            const impuestoCalculado = subtotalConDescuento * (impuesto / 100);
            const total = Math.round((subtotalConDescuento + impuestoCalculado) * 100) / 100;
            if (tipoPago === 'Efectivo' && !montoPagadoInput.value) {
                montoPagadoInput.value = total.toFixed(2);
            }
            const montoPagado = Number(montoPagadoInput.value) || 0;
            if (!tipoPago) {
                alert('Seleccione una forma de pago para completar la venta.');
                return;
            }
            if (tipoPago === 'Efectivo' && montoPagado < total) {
                alert('El monto pagado debe ser mayor o igual al total de la venta para efectivo.');
                return;
            }
            fetch('completar_suspendida.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrfToken },
                body: JSON.stringify({
                    id: editingSuspendidaId,
                    tipo_pago: tipoPago,
                    propina: propina,
                    monto_pagado: montoPagado,
                    descuento: descuento,
                    items_existentes: obtenerEdicionesArticulosExistentes(),
                    nuevos_items: suspendidaAddedItems,
                    csrf_token: csrfToken
                })
            })
                .then(response => response.json())
                .then(data => {
                    if (data.status === 'ok') {
                        resetSuspendidaSelection();
                        resetOrdenActual();
                        resetVentaFinalizadaPanel();
                        estadoMain.textContent = 'Venta suspendida completada correctamente. Inicie una nueva orden.';
                        imprimirRecibo(
                            data.venta_id,
                            ordenCompleta,
                            subtotal,
                            descuento,
                            total,
                            tipoPago,
                            montoPagado,
                            tipoPago === 'Efectivo' ? Math.max(0, montoPagado - total) : 0,
                            contextoImpresion,
                            false
                        );
                        alert('Venta suspendida completada correctamente.');
                        window.history.replaceState(null, '', 'pos.php');
                    } else {
                        alert(data.message || 'Error al completar venta suspendida.');
                    }
                });
        });

        document.getElementById('imprimir-pre-recibo-suspendida').addEventListener('click', function () {
            if (!editingSuspendidaId) {
                alert('Seleccione una venta suspendida primero.');
                return;
            }
            const ordenCompleta = suspendidaOriginalOrder.concat(suspendidaAddedItems);
            const subtotal = calcularTotal(ordenCompleta);
            const descuento = subtotal - calcularTotalFinal(ordenCompleta);
            const total = calcularTotalFinal(ordenCompleta);
            imprimirPreRecibo(ordenCompleta, subtotal, descuento, total);
        });

        document.getElementById('imprimir-cocina-suspendida').addEventListener('click', function () {
            if (!editingSuspendidaId) {
                alert('Seleccione una venta suspendida primero.');
                return;
            }
            const ordenCompleta = suspendidaOriginalOrder.concat(suspendidaAddedItems);
            imprimirComandaCocina(ordenCompleta);
        });

        document.getElementById('imprimir-barra-suspendida').addEventListener('click', function () {
            if (!editingSuspendidaId) {
                alert('Seleccione una venta suspendida primero.');
                return;
            }
            const ordenCompleta = suspendidaOriginalOrder.concat(suspendidaAddedItems);
            imprimirComandaBarra(ordenCompleta);
        });

        const guardarMesasButton = document.getElementById('guardar-mesas');
        if (guardarMesasButton) {
            guardarMesasButton.addEventListener('click', function () {
                const form = document.createElement('form');
                form.method = 'POST';
                form.action = 'guardar_config.php';
                const csrf = document.createElement('input');
                csrf.type = 'hidden';
                csrf.name = 'csrf_token';
                csrf.value = csrfToken;
                const mesas = document.createElement('input');
                mesas.type = 'hidden';
                mesas.name = 'mesas';
                mesas.value = document.getElementById('mesas-count').value;
                form.append(csrf, mesas);
                document.body.appendChild(form);
                form.submit();
            });
        }

        abrirSuspendidaInicial();

        // Propina buttons
        document.querySelectorAll('.tip-btn').forEach(btn => {
            btn.addEventListener('click', function () {
                const percent = parseFloat(this.dataset.percent);
                const subtotal = parseFloat(document.getElementById('subtotal-propina').textContent);
                propinaVentaInput.value = percent;
                propinaTipoSelect.value = 'percent';
                actualizarPropinaTipo();
            });
        });

        // Funciones de impresión
        function crearVentanaImpresion(titulo, contenido, esRecibo = false) {
            const win = window.open('', '_blank');
            if (!win) {
                alert('No se pudo abrir la ventana de impresión. Verifique el bloqueador de ventanas emergentes.');
                return false;
            }
            const opciones = configuracionPOS.recibos;
            const formato = esRecibo ? opciones : configuracionPOS.comandas;
            const anchoPapel = formato.anchoPapel === 58 ? 58 : 80;
            const tamanoFuente = { pequena: '10px', normal: '12px', grande: '14px' }[formato.tamanoFuente] || '12px';
            const alturaMinima = esRecibo ? Math.max(100, Math.min(500, opciones.altoPapel || 200)) : 200;
            const margenSuperior = Math.max(0, Math.min(20, formato.margenSuperior || 0));
            const margenInferior = Math.max(0, Math.min(20, formato.margenInferior || 0));
            win.document.write(`<!DOCTYPE html><html><head><title>${escaparHtmlImpresion(titulo)}</title><style id="print-page"></style><style>
                * { box-sizing: border-box; }
                html, body { margin: 0; padding: 0; width: ${anchoPapel}mm; overflow: visible; }
                body { font-family: Arial, sans-serif; font-size: ${tamanoFuente}; line-height: 1.35; overflow-wrap: anywhere; }
                #receipt-print-content { min-height: ${alturaMinima}mm; padding: ${margenSuperior}mm 2mm ${margenInferior}mm; }
                .center { text-align: center; }
                .separator { border-top: 1px dashed #000; margin: 8px 0; }
                .small { font-size: .9em; }
                .items { margin: 8px 0; }
                .items div { margin-bottom: 4px; }
                .footer { margin-top: 10px; }
            </style></head><body><div id="receipt-print-content">${contenido}</div></body></html>`);
            win.document.close();
            win.focus();
            const receiptContent = win.document.getElementById('receipt-print-content');
            const contentHeightMm = receiptContent.getBoundingClientRect().height * 25.4 / 96;
            const pageHeightMm = Math.max(alturaMinima, Math.ceil(contentHeightMm + 8));
            win.document.getElementById('print-page').textContent =
                `@page { size: ${anchoPapel}mm ${pageHeightMm}mm; margin: 0; }`;
            win.print();
            return true;
        }

        function escaparHtmlImpresion(valor) {
            return String(valor).replace(/[&<>"']/g, caracter => ({
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#039;'
            })[caracter]);
        }

        function generarItemsHtml(orden, esRecibo = false) {
            return orden.map(item => {
                const nombre = escaparHtmlImpresion(item.nombre);
                const precio = (!esRecibo || configuracionPOS.recibos.mostrarPrecios)
                    ? ' - $' + parseFloat(item.precio).toFixed(2)
                    : '';
                const nota = (!esRecibo || configuracionPOS.recibos.mostrarNotas) && item.nota
                    ? `<small>Nota: ${escaparHtmlImpresion(item.nota)}</small>`
                    : '';
                return `<div>${parseInt(item.cantidad || 1, 10)} x ${nombre}${precio}${nota}</div>`;
            }).join('');
        }

        function enviarTrabajoImpresion(estacion, orden, contexto, datos, impresionLocal) {
            const impresora = configuracionPOS.impresoras[estacion];
            if (!impresora || !impresora.ip) {
                return Promise.resolve(impresionLocal() !== false);
            }

            return fetch('imprimir_comanda.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrfToken },
                body: JSON.stringify({
                    estacion,
                    orden,
                    mesa: contexto.mesa,
                    mesero: contexto.mesero,
                    csrf_token: csrfToken,
                    ...datos
                })
            })
                .then(async response => {
                    const result = await response.json();
                    if (!response.ok || result.status !== 'ok') {
                        throw new Error(result.message || 'No se pudo enviar el trabajo de impresión.');
                    }
                    return result;
                })
                .then(result => {
                    if (!result.message.includes('no contiene productos')) {
                        console.info(result.message);
                    }
                    return !result.message.includes('no contiene productos');
                })
                .catch(error => {
                    const stationName = estacion === 'cocina' ? 'cocina' : 'barra';
                    alert('No se pudo imprimir en ' + stationName + ': ' + (error.message || 'error de conexión con la impresora.'));
                    return false;
                });
        }

        function imprimirPreRecibo(orden, subtotal, descuento, total, contexto = obtenerContextoImpresion()) {
            const opciones = configuracionPOS.recibos;
            const titulo = escaparHtmlImpresion(opciones.titulo);
            const pie = escaparHtmlImpresion(opciones.pie);
            const impuestoCalculado = Math.max(0, subtotal - descuento) * (impuesto / 100);
            const contenido = `
                <div class="center"><strong>${titulo} · PRE-RECIBO</strong></div>
                <div class="separator"></div>
                ${opciones.mostrarFecha ? `<div>Fecha: ${new Date().toLocaleString()}</div>` : ''}
                ${opciones.mostrarMesa ? `<div>Mesa: ${contexto.mesa === '0' ? 'Mostrador' : escaparHtmlImpresion(contexto.mesa)}</div>` : ''}
                ${opciones.mostrarMesero ? `<div>Mesero: ${escaparHtmlImpresion(contexto.mesero)}</div>` : ''}
                <div class="separator"></div>
                <div class="items">${generarItemsHtml(orden, true)}</div>
                <div class="separator"></div>
                ${opciones.mostrarSubtotal ? `<div>Subtotal: $${subtotal.toFixed(2)}</div>` : ''}
                ${opciones.mostrarDescuentos && descuento > 0 ? `<div>Descuento: $${descuento.toFixed(2)}</div>` : ''}
                ${opciones.mostrarImpuestos && impuestoCalculado > 0 ? `<div>Impuesto: $${impuestoCalculado.toFixed(2)}</div>` : ''}
                <div><strong>Total: $${total.toFixed(2)}</strong></div>
                <div class="footer center small">${pie}</div>
            `;
            enviarTrabajoImpresion('recibo', orden, contexto, {
                pre_recibo: true,
                descuento,
                impuesto: impuestoCalculado
            }, () => crearVentanaImpresion(opciones.titulo + ' - Pre-Recibo', contenido, true));
        }

        function imprimirRecibo(id, orden, subtotal, descuento, total, tipoPago, montoPagado, cambio, contexto = obtenerContextoImpresion(), imprimirComandas = true) {
            const opciones = configuracionPOS.recibos;
            const titulo = escaparHtmlImpresion(opciones.titulo);
            const pie = escaparHtmlImpresion(opciones.pie);
            const impuestoCalculado = Math.max(0, subtotal - descuento) * (impuesto / 100);
            const contenido = `
                <div class="center"><strong>${titulo}</strong></div>
                <div class="center"><strong>RECIBO</strong></div>
                <div class="separator"></div>
                ${opciones.mostrarFolio ? `<div>ID Venta: ${id}</div>` : ''}
                ${opciones.mostrarFecha ? `<div>Fecha: ${new Date().toLocaleString()}</div>` : ''}
                ${opciones.mostrarMesa ? `<div>Mesa: ${contexto.mesa === '0' ? 'Mostrador' : escaparHtmlImpresion(contexto.mesa)}</div>` : ''}
                ${opciones.mostrarMesero ? `<div>Mesero: ${escaparHtmlImpresion(contexto.mesero)}</div>` : ''}
                <div class="separator"></div>
                <div class="items">${generarItemsHtml(orden, true)}</div>
                <div class="separator"></div>
                ${opciones.mostrarSubtotal ? `<div>Subtotal: $${subtotal.toFixed(2)}</div>` : ''}
                ${opciones.mostrarDescuentos && descuento > 0 ? `<div>Descuento: $${descuento.toFixed(2)}</div>` : ''}
                ${opciones.mostrarImpuestos && impuestoCalculado > 0 ? `<div>Impuesto: $${impuestoCalculado.toFixed(2)}</div>` : ''}
                <div><strong>Total: $${total.toFixed(2)}</strong></div>
                ${opciones.mostrarPago ? `<div>Forma de Pago: ${escaparHtmlImpresion(tipoPago)}</div>
                ${tipoPago === 'Efectivo' ? `<div>Monto Pagado: $${montoPagado.toFixed(2)}</div><div>Cambio: $${cambio.toFixed(2)}</div>` : ''}` : ''}
                <div class="footer center small">${pie}</div>
            `;
            enviarTrabajoImpresion('recibo', orden, contexto, {
                venta_id: id,
                descuento,
                impuesto: impuestoCalculado,
                tipo_pago: tipoPago,
                monto_pagado: montoPagado
            }, () => crearVentanaImpresion(opciones.titulo + ' - Recibo', contenido, true));
            if (imprimirComandas) {
                imprimirComandaCocina(orden, contexto);
                imprimirComandaBarra(orden, contexto);
            }
        }

        function esProductoDeBarra(item) {
            if (item.tipo === 'inventario') {
                return false;
            }
            const categoriasConfiguradas = configuracionPOS.impresoras.categoriasBarra
                .map(categoria => String(categoria).trim().toLocaleLowerCase())
                .filter(Boolean);
            const categoria = String(item.categoria || '').trim().toLocaleLowerCase();
            if (categoriasConfiguradas.length) {
                return categoriasConfiguradas.includes(categoria);
            }
            return /bebida|cerveza|vino/i.test(`${item.categoria || ''} ${item.nombre || ''}`);
        }

        function imprimirComandaCocina(orden, contexto = obtenerContextoImpresion()) {
            const opciones = configuracionPOS.recibos;
            const comida = orden.filter(item => item.tipo !== 'inventario' && !esProductoDeBarra(item));
            if (comida.length === 0) return Promise.resolve(null);
            const contenido = `
                <div class="center"><strong>${opciones.titulo}</strong></div>
                <div class="center"><strong>COMANDA COCINA</strong></div>
                <div class="separator"></div>
                <div>Mesa: ${contexto.mesa === '0' ? 'Mostrador' : escaparHtmlImpresion(contexto.mesa)}</div>
                <div>Mesero: ${escaparHtmlImpresion(contexto.mesero)}</div>
                ${opciones.mostrarFecha ? `<div>Hora: ${new Date().toLocaleTimeString()}</div>` : ''}
                <div class="separator"></div>
                <div class="items">${generarItemsHtml(comida)}</div>
                <div class="footer center small">${opciones.pie}</div>
            `;
            return enviarTrabajoImpresion('cocina', comida, contexto, {}, () => crearVentanaImpresion(opciones.titulo + ' - Comanda Cocina', contenido));
        }

        function imprimirComandaBarra(orden, contexto = obtenerContextoImpresion()) {
            const opciones = configuracionPOS.recibos;
            const bebidas = orden.filter(item => item.tipo !== 'inventario' && esProductoDeBarra(item));
            if (bebidas.length === 0) return Promise.resolve(null);
            const contenido = `
                <div class="center"><strong>${opciones.titulo}</strong></div>
                <div class="center"><strong>COMANDA BARRA</strong></div>
                <div class="separator"></div>
                <div>Mesa: ${contexto.mesa === '0' ? 'Mostrador' : escaparHtmlImpresion(contexto.mesa)}</div>
                <div>Mesero: ${escaparHtmlImpresion(contexto.mesero)}</div>
                ${opciones.mostrarFecha ? `<div>Hora: ${new Date().toLocaleTimeString()}</div>` : ''}
                <div class="separator"></div>
                <div class="items">${generarItemsHtml(bebidas)}</div>
                <div class="footer center small">${opciones.pie}</div>
            `;
            return enviarTrabajoImpresion('barra', bebidas, contexto, {}, () => crearVentanaImpresion(opciones.titulo + ' - Comanda Barra', contenido));
        }

        if (configuracionPOS.enfocarBusqueda) {
            document.getElementById('menu-search').focus();
        }
    </script>
</body>

</html>
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
$promociones_activas = getPromocionesActivas();
$promociones_pos = array_map(static function ($promo) {
    return [
        'nombre' => (string) ($promo['nombre'] ?? ''),
        'descuento' => max(0, min(100, (float) ($promo['descuento'] ?? 0)))
    ];
}, $promociones_activas);
$config_recibos = [
    'nombre' => (string) ($config['nombre_restaurante'] ?? 'Restaurante POS'),
    'titulo' => (string) ($config['recibo_titulo'] ?? 'Restaurante POS'),
    'pie' => (string) ($config['recibo_pie'] ?? 'Gracias por su compra'),
    'mostrarFecha' => !empty($config['recibo_mostrar_fecha']),
    'mostrarPrecios' => !empty($config['recibo_mostrar_precios']),
    'mostrarDescuentos' => !empty($config['recibo_mostrar_descuentos']),
    'mostrarImpuestos' => !empty($config['recibo_mostrar_impuestos']),
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
$puedeConfigurar = !empty($user['permisos']['config']);
$categorias_menu = [];
foreach ($menu as $item) {
    $categoria = trim($item['categoria'] ?: 'General');
    if (!in_array($categoria, $categorias_menu)) {
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

<body>
    <header>
        <h1>Punto de Venta</h1>
        <a href="dashboard.php">Volver</a>
    </header>
    <main>
        <div class="pos-container">
            <div class="mesas">
                <h3>Mesas</h3>
                <?php if ($puedeConfigurar): ?>
                <div class="mesas-config">
                    <label>Mesas configuradas: <input id="mesas-count" type="number" min="1"
                        value="<?php echo count($mesas); ?>"></label>
                    <button id="guardar-mesas">Guardar mesas</button>
                </div>
                <h4>Asignar mesa</h4>
                <select id="mesero-select">
                    <?php foreach ($meseros as $m) {
                        echo "<option value='{$m['id']}'>{$m['nombre']}</option>";
                    } ?>
                </select>
                <div class="mesas-grid">
                    <?php foreach ($mesas as $m) {
                        echo "<button class='mesa-btn' data-mesa='$m'>Mesa $m</button>";
                    } ?>
                    <?php if (!$requiere_mesa): ?>
                        <button class="mesa-btn mesa-mostrad-btn" data-mesa="0">Mostrador</button>
                    <?php endif; ?>
                </div>
                <p>Mesero asignado: <span
                        id="current-mesero-text"><?php echo htmlspecialchars($meseros[0]['nombre'] ?? ''); ?></span></p>
                <p>Mesa seleccionada: <span id="current-mesa-text">Ninguna</span></p>
            </div>
            <div class="menu">
                <h3>Menú</h3>
                <div class="menu-filtros">
                    <label>Buscar:<br><input id="menu-search" type="text" placeholder="Buscar artículo"></label>
                    <label>Categoría:<br>
                        <select id="menu-categoria">
                            <option value="">Todas</option>
                            <?php foreach ($categorias_menu as $categoria) {
                                echo "<option value='" . htmlspecialchars($categoria) . "'>" . htmlspecialchars($categoria) . "</option>";
                            } ?>
                        </select>
                    </label>
                </div>
                <div class="menu-grid" id="menu-grid">
                    <?php foreach ($menu as $item) {
                        echo "<button class='menu-item' data-id='" . htmlspecialchars($item['id']) . "' data-nombre='" . htmlspecialchars($item['nombre']) . "' data-precio='" . htmlspecialchars($item['precio']) . "' data-categoria='" . htmlspecialchars($item['categoria']) . "'>" . htmlspecialchars($item['nombre']) . " - $" . htmlspecialchars($item['precio']) . "</button>";
                    } ?>
                </div>
            </div>
            <div class="orden">
                <h3>Orden Actual</h3>
                <p id="estado-main" class="estado-info">Seleccione productos para comenzar la venta.</p>
                <ul id="orden-list"></ul>
                <div class="venta-resumen">
                    <p>Subtotal: $<span id="subtotal-amount">0.00</span></p>
                    <p>Descuento: $<span id="discount-amount">0.00</span></p>
                    <p>Impuesto (<?php echo $impuesto; ?>%): $<span id="tax-amount">0.00</span></p>
                    <p>Total final: $<span id="total-amount">0.00</span></p>
                    <p>Items: <span id="item-count">0</span></p>
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
                    <button id="imprimir-pre-recibo">Imprimir Pre-Recibo</button>
                    <button id="cobrar">Cobrar</button>
                    <button id="suspender" <?= empty($user['permisos']['ventas_suspendidas_ver']) ? 'hidden' : '' ?>>Suspender Venta</button>
                    <button id="limpiar-orden">Limpiar Orden</button>
                </div>
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
            <div class="suspendidas" <?= empty($user['permisos']['ventas_suspendidas_ver']) ? 'hidden' : '' ?>>
                <h3>Ventas Suspendidas</h3>
                <div class="suspendidas-search">
                    <input type="text" id="search-suspendidas" placeholder="Buscar ID, mesa, mesero">
                    <button id="buscar-suspendidas">Buscar</button>
                </div>
                <div id="suspendidas-results"></div>
                <div id="suspendida-selected" style="display: none; margin-top: 16px;">
                    <h4>Venta suspendida seleccionada</h4>
                    <p>ID: <span id="selected-venta-id"></span></p>
                    <p>Mesero: <span id="selected-venta-mesero"></span></p>
                    <p>Mesa: <span id="selected-venta-mesa"></span></p>
                    <h5>Items existentes</h5>
                    <ul id="suspendida-orden-list"></ul>
                    <h5>Productos agregados</h5>
                    <ul id="suspendida-added-list"></ul>
                    <div class="venta-resumen">
                        <p>Subtotal: $<span id="suspendida-subtotal">0.00</span></p>
                        <p>Descuento: $<span id="suspendida-discount">0.00</span></p>
                        <p>Total final: $<span id="suspendida-total">0.00</span></p>
                        <p>Items totales: <span id="suspendida-item-count">0</span></p>
                    </div>
                    <div class="venta-acciones">
                        <label>Forma de pago:
                            <select id="suspendida-tipo-pago">
                                <?php foreach ($formas_pago as $forma) {
                                    echo "<option value='" . htmlspecialchars($forma) . "'" . ($forma === $metodo_pago_default ? ' selected' : '') . ">" . htmlspecialchars($forma) . "</option>";
                                } ?>
                            </select>
                        </label>
                        <label>Monto pagado: <input type="number" id="suspendida-monto-pagado" min="0" step="0.01"
                                value="0"></label>
                        <label>Cambio: $<span id="suspendida-cambio">0.00</span></label>
                        <label>Propina: $<input type="number" id="suspendida-propina" min="0" step="0.01"
                                value="0"></label>
                    </div>
                    <?php endif; ?>
                    <div class="venta-acciones">
                        <button id="imprimir-pre-recibo-suspendida">Imprimir Pre-Recibo</button>
                        <button id="imprimir-cocina-suspendida">Imprimir Cocina</button>
                        <button id="imprimir-barra-suspendida">Imprimir Barra</button>
                    </div>
                    <div class="venta-acciones">
                        <button id="guardar-suspendida">Guardar cambios</button>
                        <button id="completar-suspendida">Completar venta suspendida</button>
                        <button id="cancelar-suspendida">Cerrar</button>
                    </div>
                </div>
            </div>
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
    <script src="2.js"></script>
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
            'recibos' => $config_recibos,
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
        const propinaVentaInput = document.getElementById('propina-venta');
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
            return order.reduce((sum, item) => sum + (parseFloat(item.precio) || 0), 0);
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
            const ordenCompleta = suspendidaOriginalOrder.concat(suspendidaAddedItems);
            const total = calcularTotalFinal(ordenCompleta);
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
                let itemText = item.nombre + ' - $' + item.precio.toFixed(2);
                if (item.nota) {
                    itemText += ' [Nota: ' + item.nota + ']';
                }
                li.textContent = itemText;

                const editNote = document.createElement('button');
                editNote.type = 'button';
                editNote.textContent = 'Nota';
                editNote.style.marginLeft = '5px';
                editNote.addEventListener('click', function () {
                    itemEditandoIndex = index;
                    document.getElementById('nota-item-nombre').textContent = item.nombre;
                    document.getElementById('nota-texto').value = item.nota || '';
                    document.getElementById('nota-modal').style.display = 'flex';
                });

                const remove = document.createElement('button');
                remove.type = 'button';
                remove.textContent = 'Quitar';
                remove.style.marginLeft = '5px';
                remove.addEventListener('click', function () {
                    orden.splice(index, 1);
                    actualizarOrden();
                });
                li.appendChild(document.createTextNode(' '));
                li.appendChild(editNote);
                li.appendChild(document.createTextNode(' '));
                li.appendChild(remove);
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
            itemCount.textContent = orden.length;

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

        function actualizarSuspendidaOrden() {
            suspendidaOrdenList.innerHTML = '';
            suspendidaAddedList.innerHTML = '';
            suspendidaOriginalOrder.forEach(item => {
                const li = document.createElement('li');
                li.textContent = item.nombre + ' - $' + parseFloat(item.precio).toFixed(2);
                suspendidaOrdenList.appendChild(li);
            });
            suspendidaAddedItems.forEach((item, index) => {
                const li = document.createElement('li');
                li.textContent = item.nombre + ' - $' + item.precio.toFixed(2);
                const remove = document.createElement('button');
                remove.type = 'button';
                remove.textContent = 'Quitar';
                remove.addEventListener('click', function () {
                    suspendidaAddedItems.splice(index, 1);
                    actualizarSuspendidaOrden();
                    actualizarSuspendidaCambio();
                });
                li.appendChild(document.createTextNode(' '));
                li.appendChild(remove);
                suspendidaAddedList.appendChild(li);
            });

            const ordenCompleta = suspendidaOriginalOrder.concat(suspendidaAddedItems);
            const subtotal = calcularTotal(ordenCompleta);
            const descuento = obtenerDescuentoPromocion(subtotal).monto;

            const totalFinal = Math.max(0, subtotal - descuento);
            const totalConImpuesto = totalFinal + (totalFinal * (impuesto / 100));

            document.getElementById('suspendida-subtotal').textContent = subtotal.toFixed(2);
            document.getElementById('suspendida-discount').textContent = descuento.toFixed(2);
            suspendidaTotalDisplay.textContent = totalConImpuesto.toFixed(2);
            suspendidaItemCount.textContent = ordenCompleta.length.toString();

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
                document.querySelectorAll('.mesa-btn').forEach(mesa => mesa.classList.remove('selected'));
                this.classList.add('selected');
                actualizarEstadoMain();
            });
        });

        document.querySelectorAll('.menu-item').forEach(btn => {
            btn.addEventListener('click', function () {
                const item = {
                    id: this.dataset.id,
                    nombre: this.dataset.nombre,
                    precio: parseFloat(this.dataset.precio),
                    categoria: this.dataset.categoria || ''
                };
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

            menuItems.forEach(item => {
                const nombre = item.dataset.nombre.toLowerCase();
                const categoria = item.dataset.categoria || '';
                const matchesSearch = nombre.includes(searchTerm);
                const matchesCategoria = !categoriaSeleccionada || categoria === categoriaSeleccionada;
                item.style.display = matchesSearch && matchesCategoria ? 'block' : 'none';
            });
        }

        document.getElementById('menu-search').addEventListener('input', filtrarMenu);
        document.getElementById('menu-categoria').addEventListener('change', filtrarMenu);

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
            const totalConImpuesto = totalFinal + (totalFinal * (impuesto / 100));

            if (this.value !== 'Efectivo') {
                suspendidaMontoPagadoInput.value = totalConImpuesto.toFixed(2);
                suspendidaMontoPagadoInput.disabled = true;
            } else {
                suspendidaMontoPagadoInput.disabled = false;
            }
            actualizarSuspendidaCambio();
        });

        btnLimpiarOrden.addEventListener('click', function () {
            resetOrdenActual();
            resetVentaFinalizadaPanel();
            estadoMain.textContent = 'Orden limpia. Seleccione mesa y productos para comenzar de nuevo.';
        });

        btnCancelarSuspendida.addEventListener('click', function () {
            resetSuspendidaSelection();
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
                        buscarSuspendidas();
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
                details += `<li>${item.nombre} - $${item.precio.toFixed(2)}</li>`;
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

        document.getElementById('buscar-suspendidas').addEventListener('click', buscarSuspendidas);

        function buscarSuspendidas() {
            if (!configuracionPOS.ventasSuspendidas) return;
            const search = document.getElementById('search-suspendidas').value;
            fetch('buscar_suspendidas.php?search=' + encodeURIComponent(search))
                .then(response => response.json())
                .then(data => {
                    const container = document.getElementById('suspendidas-results');
                    container.innerHTML = '';
                    if (data.status !== 'ok') {
                        container.textContent = 'Error en la búsqueda.';
                        return;
                    }
                    if (!data.ventas.length) {
                        container.textContent = 'No se encontraron ventas suspendidas.';
                        return;
                    }
                    data.ventas.forEach(venta => {
                        const div = document.createElement('div');
                        div.className = 'suspendida-item';
                        const ubicacion = Number(venta.mesa) === 0 ? 'Mostrador' : `Mesa ${venta.mesa}`;
                        div.innerHTML = `<strong>ID ${venta.id}</strong> - ${ubicacion} - Mesero ${venta.mesero_nombre || 'N/A'} - Items: ${venta.orden.length}`;
                        const editar = document.createElement('button');
                        editar.type = 'button';
                        editar.textContent = 'Abrir';
                        editar.addEventListener('click', function () {
                            cargarSuspendida(venta);
                        });
                        div.appendChild(editar);
                        container.appendChild(div);
                    });
                });
        }

        function cargarSuspendida(venta) {
            editingSuspendidaId = venta.id;
            suspendidaOriginalOrder = Array.isArray(venta.orden) ? venta.orden : [];
            suspendidaAddedItems = [];
            selectedVentaId.textContent = venta.id;
            selectedVentaMesero.textContent = venta.mesero_nombre || 'N/A';
            selectedVentaMesa.textContent = Number(venta.mesa) === 0 ? 'Mostrador' : venta.mesa;
            suspendidaSelectedSection.style.display = 'block';
            actualizarSuspendidaOrden();
        }

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
                body: JSON.stringify({ id: editingSuspendidaId, nuevos_items: suspendidaAddedItems, csrf_token: csrfToken })
            })
                .then(response => response.json())
                .then(data => {
                    if (data.status === 'ok') {
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
                        alert('Venta suspendida actualizada. Ahora puede iniciar una nueva venta.');
                        buscarSuspendidas();
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
            const montoPagado = parseFloat(document.getElementById('suspendida-monto-pagado').value) || 0;
            const ordenCompleta = suspendidaOriginalOrder.concat(suspendidaAddedItems);
            const contextoImpresion = obtenerContextoImpresion();
            const subtotal = calcularTotal(ordenCompleta);
            const promocion = obtenerDescuentoPromocion(subtotal);
            const descuento = Math.min(subtotal, promocion.monto);
            const subtotalConDescuento = Math.max(0, subtotal - descuento);
            const impuestoCalculado = subtotalConDescuento * (impuesto / 100);
            const total = subtotalConDescuento + impuestoCalculado;
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
                body: JSON.stringify({ id: editingSuspendidaId, tipo_pago: tipoPago, propina: propina, monto_pagado: montoPagado, descuento: descuento, nuevos_items: suspendidaAddedItems, csrf_token: csrfToken })
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
                        buscarSuspendidas();
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

        buscarSuspendidas();

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
        function crearVentanaImpresion(titulo, contenido) {
            const win = window.open('', '_blank');
            if (!win) {
                alert('No se pudo abrir la ventana de impresión. Verifique el bloqueador de ventanas emergentes.');
                return;
            }
            win.document.write(`<!DOCTYPE html><html><head><title>${titulo}</title><style>
                body { font-family: Arial, sans-serif; font-size: 12px; margin: 0; padding: 10px; width: 80mm; }
                .center { text-align: center; }
                .separator { border-top: 1px dashed #000; margin: 8px 0; }
                .small { font-size: 11px; }
                .items { margin: 8px 0; }
                .items div { margin-bottom: 4px; }
                .footer { margin-top: 10px; }
            </style></head><body>${contenido}</body></html>`);
            win.document.close();
            win.focus();
            win.print();
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

        function generarItemsHtml(orden) {
            return orden.map(item => {
                const nombre = escaparHtmlImpresion(item.nombre);
                const precio = configuracionPOS.recibos.mostrarPrecios ? ' - $' + parseFloat(item.precio).toFixed(2) : '';
                const nota = item.nota ? `<small>Nota: ${escaparHtmlImpresion(item.nota)}</small>` : '';
                return `<div>${parseInt(item.cantidad || 1, 10)} x ${nombre}${precio}${nota}</div>`;
            }).join('');
        }

        function enviarTrabajoImpresion(estacion, orden, contexto, datos, impresionLocal) {
            const impresora = configuracionPOS.impresoras[estacion];
            if (!impresora || !impresora.ip) {
                impresionLocal();
                return;
            }

            fetch('imprimir_comanda.php', {
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
                })
                .catch(error => {
                    alert(error.message || 'Error de conexión con la impresora.');
                });
        }

        function imprimirPreRecibo(orden, subtotal, descuento, total, contexto = obtenerContextoImpresion()) {
            const opciones = configuracionPOS.recibos;
            const impuestoCalculado = Math.max(0, subtotal - descuento) * (impuesto / 100);
            const contenido = `
                <div class="center"><strong>${opciones.titulo} · PRE-RECIBO</strong></div>
                <div class="separator"></div>
                ${opciones.mostrarFecha ? `<div>Fecha: ${new Date().toLocaleString()}</div>` : ''}
                <div>Mesa: ${contexto.mesa === '0' ? 'Mostrador' : escaparHtmlImpresion(contexto.mesa)}</div>
                <div>Mesero: ${escaparHtmlImpresion(contexto.mesero)}</div>
                <div class="separator"></div>
                <div class="items">${generarItemsHtml(orden)}</div>
                <div class="separator"></div>
                <div>Subtotal: $${subtotal.toFixed(2)}</div>
                ${opciones.mostrarDescuentos && descuento > 0 ? `<div>Descuento: $${descuento.toFixed(2)}</div>` : ''}
                ${opciones.mostrarImpuestos && impuestoCalculado > 0 ? `<div>Impuesto: $${impuestoCalculado.toFixed(2)}</div>` : ''}
                <div><strong>Total: $${total.toFixed(2)}</strong></div>
                <div class="footer center small">${opciones.pie}</div>
            `;
            enviarTrabajoImpresion('recibo', orden, contexto, {
                pre_recibo: true,
                descuento,
                impuesto: impuestoCalculado
            }, () => crearVentanaImpresion(opciones.titulo + ' - Pre-Recibo', contenido));
        }

        function imprimirRecibo(id, orden, subtotal, descuento, total, tipoPago, montoPagado, cambio, contexto = obtenerContextoImpresion(), imprimirComandas = true) {
            const opciones = configuracionPOS.recibos;
            const impuestoCalculado = Math.max(0, subtotal - descuento) * (impuesto / 100);
            const contenido = `
                <div class="center"><strong>${opciones.titulo}</strong></div>
                <div class="center"><strong>RECIBO</strong></div>
                <div class="separator"></div>
                <div>ID Venta: ${id}</div>
                ${opciones.mostrarFecha ? `<div>Fecha: ${new Date().toLocaleString()}</div>` : ''}
                <div>Mesa: ${contexto.mesa === '0' ? 'Mostrador' : escaparHtmlImpresion(contexto.mesa)}</div>
                <div>Mesero: ${escaparHtmlImpresion(contexto.mesero)}</div>
                <div class="separator"></div>
                <div class="items">${generarItemsHtml(orden)}</div>
                <div class="separator"></div>
                <div>Subtotal: $${subtotal.toFixed(2)}</div>
                ${opciones.mostrarDescuentos && descuento > 0 ? `<div>Descuento: $${descuento.toFixed(2)}</div>` : ''}
                ${opciones.mostrarImpuestos && impuestoCalculado > 0 ? `<div>Impuesto: $${impuestoCalculado.toFixed(2)}</div>` : ''}
                <div><strong>Total: $${total.toFixed(2)}</strong></div>
                <div>Forma de Pago: ${tipoPago}</div>
                ${tipoPago === 'Efectivo' ? `<div>Monto Pagado: $${montoPagado.toFixed(2)}</div><div>Cambio: $${cambio.toFixed(2)}</div>` : ''}
                <div class="footer center small">${opciones.pie}</div>
            `;
            enviarTrabajoImpresion('recibo', orden, contexto, {
                venta_id: id,
                descuento,
                impuesto: impuestoCalculado,
                tipo_pago: tipoPago,
                monto_pagado: montoPagado
            }, () => crearVentanaImpresion(opciones.titulo + ' - Recibo', contenido));
            if (imprimirComandas) {
                imprimirComandaCocina(orden, contexto);
                imprimirComandaBarra(orden, contexto);
            }
        }

        function esProductoDeBarra(item) {
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
            const comida = orden.filter(item => !esProductoDeBarra(item));
            if (comida.length === 0) return;
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
            enviarTrabajoImpresion('cocina', orden, contexto, {}, () => crearVentanaImpresion(opciones.titulo + ' - Comanda Cocina', contenido));
        }

        function imprimirComandaBarra(orden, contexto = obtenerContextoImpresion()) {
            const opciones = configuracionPOS.recibos;
            const bebidas = orden.filter(esProductoDeBarra);
            if (bebidas.length === 0) return;
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
            enviarTrabajoImpresion('barra', orden, contexto, {}, () => crearVentanaImpresion(opciones.titulo + ' - Comanda Barra', contenido));
        }

        if (configuracionPOS.enfocarBusqueda) {
            document.getElementById('menu-search').focus();
        }
    </script>
</body>

</html>
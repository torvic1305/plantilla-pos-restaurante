<?php
include 'data.php';
include 'security.php';

requirePermission('config');
$config = getAppConfig();
$canManageEmployees = !empty($_SESSION['user']['permisos']['empleados']);
$managedEmployees = $canManageEmployees
    ? array_values(array_filter(getEmpleados(), static fn($employee) => $employee['usuario'] !== 'super'))
    : [];
$error_msg = '';
$success_msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        requireMethod('POST');
        if (!is_string($_POST['csrf_token'] ?? null) || !validateCsrfToken($_POST['csrf_token'])) {
            throw new Exception('Token de seguridad inválido.');
        }

        $nombre = sanitizeString($_POST['nombre_restaurante'] ?? '', 100);
        $impuesto = validateDecimal($_POST['impuesto'] ?? null, 0, 100);
        $mesas = validateInteger($_POST['mesas'] ?? null, 1, 1000);
        $cashThreshold = validateDecimal($_POST['cash_alert_threshold'] ?? 0, 0, 99999999);
        if ($nombre === '' || $impuesto === null || $mesas === null || $cashThreshold === null) {
            throw new Exception('Verifica el nombre, el impuesto, el número de mesas y el umbral de efectivo.');
        }

        $paymentText = $_POST['formas_pago'] ?? '';
        if (!is_string($paymentText)) {
            throw new Exception('La lista de formas de pago no es válida.');
        }
        $paymentLines = preg_split('/[\r\n,]+/', $paymentText);
        $payments = array_values(array_unique(array_filter(array_map(
            static fn($payment) => sanitizeString($payment, 40),
            $paymentLines
        ))));
        if ($payments === [] || count($payments) > 12) {
            throw new Exception('Configura entre 1 y 12 métodos de pago.');
        }

        $defaultPayment = sanitizeString($_POST['metodo_pago_default'] ?? '', 40);
        if (!in_array($defaultPayment, $payments, true)) {
            throw new Exception('El método de pago predeterminado debe estar habilitado.');
        }

        $background = $_POST['color_fondo'] ?? '';
        $textColor = $_POST['color_letra'] ?? '';
        $buttonColor = $_POST['color_boton'] ?? '';
        $buttonHoverColor = $_POST['color_boton_hover'] ?? '';
        foreach ([$background, $textColor, $buttonColor, $buttonHoverColor] as $color) {
            if (!is_string($color) || !preg_match('/^#[a-fA-F0-9]{6}$/', $color)) {
                throw new Exception('Los colores deben ser valores hexadecimales válidos.');
            }
        }

        $fontInput = $_POST['fuente'] ?? '';
        if (!is_string($fontInput)) {
            throw new Exception('La fuente seleccionada no es válida.');
        }
        $fontFamily = sanitizeString($fontInput, 80);
        if ($fontFamily === '' || !preg_match('/^[a-zA-Z0-9 ,\'"-]+$/', $fontFamily)) {
            throw new Exception('La fuente contiene caracteres no permitidos.');
        }

        $receiptTitle = sanitizeString($_POST['recibo_titulo'] ?? '', 100);
        $receiptFooter = sanitizeString($_POST['recibo_pie'] ?? '', 160);
        if ($receiptTitle === '') {
            throw new Exception('El título del recibo no puede quedar vacío.');
        }

        $receiptPaperWidth = $_POST['recibo_ancho_papel'] ?? '';
        $receiptFontSize = $_POST['recibo_tamano_fuente'] ?? '';
        if (!is_string($receiptPaperWidth) || !in_array($receiptPaperWidth, ['58', '80'], true)) {
            throw new Exception('Selecciona un ancho de papel válido para el recibo.');
        }
        if (!is_string($receiptFontSize) || !in_array($receiptFontSize, ['pequena', 'normal', 'grande'], true)) {
            throw new Exception('Selecciona un tamaño de letra válido para el recibo.');
        }
        $receiptPageHeight = validateInteger($_POST['recibo_alto_papel'] ?? null, 100, 500);
        $receiptTopMargin = validateInteger($_POST['recibo_margen_superior'] ?? null, 0, 20);
        $receiptBottomMargin = validateInteger($_POST['recibo_margen_inferior'] ?? null, 0, 20);
        if ($receiptPageHeight === null || $receiptTopMargin === null || $receiptBottomMargin === null) {
            throw new Exception('Configura una altura entre 100 y 500 mm y márgenes entre 0 y 20 mm.');
        }
        $commandPaperWidth = $_POST['comanda_ancho_papel'] ?? '';
        $commandFontSize = $_POST['comanda_tamano_fuente'] ?? '';
        $commandTopMargin = validateInteger($_POST['comanda_margen_superior'] ?? null, 0, 20);
        $commandBottomMargin = validateInteger($_POST['comanda_margen_inferior'] ?? null, 0, 20);
        if (
            !is_string($commandPaperWidth) || !in_array($commandPaperWidth, ['58', '80'], true)
            || !is_string($commandFontSize) || !in_array($commandFontSize, ['pequena', 'normal', 'grande'], true)
            || $commandTopMargin === null || $commandBottomMargin === null
        ) {
            throw new Exception('Configura un tamaño de papel, letra y márgenes válidos para las comandas.');
        }

        $printers = [];
        foreach (['recibo', 'cocina', 'barra'] as $station) {
            $ipInput = $_POST['impresora_' . $station . '_ip'] ?? '';
            if (!is_string($ipInput)) {
                throw new Exception('La IP configurada para cada impresora debe ser texto.');
            }
            $ip = trim($ipInput);
            $port = validateInteger($_POST['impresora_' . $station . '_puerto'] ?? 9100, 1, 65535);
            if ($port === null || ($ip !== '' && !validatePrinterIPv4($ip))) {
                throw new Exception('Usa una dirección IPv4 privada válida y un puerto entre 1 y 65535 para cada impresora.');
            }
            $printers[$station] = ['ip' => $ip, 'puerto' => $port];
        }

        $barCategoriesInput = $_POST['impresora_categorias_barra'] ?? '';
        if (!is_string($barCategoriesInput)) {
            throw new Exception('La lista de categorías de barra no es válida.');
        }
        $barCategories = array_values(array_unique(array_filter(array_map(
            static fn($category) => sanitizeString($category, 80),
            preg_split('/[\r\n,]+/', $barCategoriesInput)
        ))));

        $config = array_merge($config, [
            'nombre_restaurante' => $nombre,
            'impuesto' => $impuesto,
            'mesas' => $mesas,
            'formas_pago' => $payments,
            'metodo_pago_default' => $defaultPayment,
            'cash_alert_threshold' => $cashThreshold,
            'pos_focus_search' => isset($_POST['pos_focus_search']),
            'pos_confirmar_venta' => isset($_POST['pos_confirmar_venta']),
            'pos_require_table' => isset($_POST['pos_require_table']),
            'recibo_titulo' => $receiptTitle,
            'recibo_pie' => $receiptFooter,
            'recibo_ancho_papel' => $receiptPaperWidth,
            'recibo_alto_papel' => $receiptPageHeight,
            'recibo_margen_superior' => $receiptTopMargin,
            'recibo_margen_inferior' => $receiptBottomMargin,
            'recibo_tamano_fuente' => $receiptFontSize,
            'recibo_mostrar_fecha' => isset($_POST['recibo_mostrar_fecha']),
            'recibo_mostrar_precios' => isset($_POST['recibo_mostrar_precios']),
            'recibo_mostrar_impuestos' => isset($_POST['recibo_mostrar_impuestos']),
            'recibo_mostrar_descuentos' => isset($_POST['recibo_mostrar_descuentos']),
            'recibo_mostrar_mesa' => isset($_POST['recibo_mostrar_mesa']),
            'recibo_mostrar_mesero' => isset($_POST['recibo_mostrar_mesero']),
            'recibo_mostrar_folio' => isset($_POST['recibo_mostrar_folio']),
            'recibo_mostrar_notas' => isset($_POST['recibo_mostrar_notas']),
            'recibo_mostrar_subtotal' => isset($_POST['recibo_mostrar_subtotal']),
            'recibo_mostrar_pago' => isset($_POST['recibo_mostrar_pago']),
            'comanda_ancho_papel' => $commandPaperWidth,
            'comanda_tamano_fuente' => $commandFontSize,
            'comanda_margen_superior' => $commandTopMargin,
            'comanda_margen_inferior' => $commandBottomMargin,
            'impresora_recibo_ip' => $printers['recibo']['ip'],
            'impresora_recibo_puerto' => $printers['recibo']['puerto'],
            'impresora_cocina_ip' => $printers['cocina']['ip'],
            'impresora_cocina_puerto' => $printers['cocina']['puerto'],
            'impresora_barra_ip' => $printers['barra']['ip'],
            'impresora_barra_puerto' => $printers['barra']['puerto'],
            'impresora_categorias_barra' => $barCategories,
            'color_fondo' => $background,
            'color_letra' => $textColor,
            'color_boton' => $buttonColor,
            'color_boton_hover' => $buttonHoverColor,
            'fuente' => $fontFamily,
        ]);

        saveAppConfig($config);
        registrarAuditoria($_SESSION['user']['id'], 'UPDATE_CONFIG', 'CONFIG', 1, 'Configuración del sistema POS actualizada');
        $success_msg = 'Configuración guardada correctamente.';
    } catch (Exception $e) {
        $error_msg = $e->getMessage();
    }
}

$escape = static fn($value) => htmlspecialchars((string) $value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
$paymentsText = implode("\n", $config['formas_pago']);
$barCategoriesText = implode("\n", is_array($config['impresora_categorias_barra'] ?? null) ? $config['impresora_categorias_barra'] : []);
$fontChoices = ['Arial, sans-serif', 'Verdana, sans-serif', 'Georgia, serif', 'Tahoma, sans-serif'];
if (!in_array($config['fuente'], $fontChoices, true)) {
    array_unshift($fontChoices, $config['fuente']);
}
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Configuración - Restaurante POS</title>
    <link rel="stylesheet" href="style.php">
</head>

<body>
    <header>
        <h1>Configuración del sistema</h1>
        <a href="dashboard.php">Volver al panel</a>
    </header>
    <main class="settings-page">
        <div class="page-heading">
            <div>
                <h2>Preferencias del restaurante</h2>
                <p>Los cambios se guardan y se aplican al punto de venta, los recibos y el panel.</p>
            </div>
        </div>

        <?php if ($error_msg !== ''): ?>
            <div class="settings-message error" role="alert"><?= $escape($error_msg) ?></div>
        <?php endif; ?>
        <?php if ($success_msg !== ''): ?>
            <div class="settings-message success" role="status"><?= $escape($success_msg) ?></div>
        <?php endif; ?>

        <?php if ($canManageEmployees): ?>
            <section class="settings-section settings-employee-panel">
                <div class="settings-section-heading">
                    <span class="settings-section-icon" aria-hidden="true">♧</span>
                    <div>
                        <h3>Empleados y permisos</h3>
                        <p>Administra las cuentas del personal, sus roles y los accesos por área desde el panel de empleados.</p>
                    </div>
                </div>
                <div class="employee-panel-summary">
                    <strong><?= count($managedEmployees) ?></strong>
                    <span>empleados configurables</span>
                    <a class="dashboard-primary-action" href="empleados.php">Administrar empleados y permisos</a>
                </div>
            </section>
        <?php endif; ?>

        <form method="post" class="settings-form">
            <input type="hidden" name="csrf_token" value="<?= $escape(getCsrfToken()) ?>">

            <section class="settings-section">
                <div class="settings-section-heading">
                    <span class="settings-section-icon" aria-hidden="true">▤</span>
                    <div><h3>Restaurante e impuestos</h3><p>Datos generales usados por la aplicación.</p></div>
                </div>
                <div class="settings-fields">
                    <label>Nombre del restaurante
                        <input type="text" name="nombre_restaurante" maxlength="100" required value="<?= $escape($config['nombre_restaurante']) ?>">
                    </label>
                    <label>Impuesto sobre ventas (%)
                        <input type="number" name="impuesto" min="0" max="100" step="0.01" required value="<?= $escape($config['impuesto']) ?>">
                    </label>
                    <label>Número de mesas
                        <input type="number" name="mesas" min="1" max="1000" required value="<?= $escape($config['mesas']) ?>">
                    </label>
                </div>
            </section>

            <section class="settings-section">
                <div class="settings-section-heading">
                    <span class="settings-section-icon" aria-hidden="true">$</span>
                    <div><h3>Formas de pago y caja</h3><p>Define las opciones disponibles y el aviso de efectivo.</p></div>
                </div>
                <div class="settings-fields">
                    <label class="field-wide">Métodos de pago disponibles
                        <textarea name="formas_pago" rows="4" required><?= $escape($paymentsText) ?></textarea>
                        <small>Escribe un método por línea. Se aceptan comas como separador.</small>
                    </label>
                    <label>Método predeterminado
                        <select name="metodo_pago_default" id="metodo-pago-default">
                            <?php foreach ($config['formas_pago'] as $payment): ?>
                                <option value="<?= $escape($payment) ?>" <?= $payment === $config['metodo_pago_default'] ? 'selected' : '' ?>><?= $escape($payment) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <small>Se seleccionará al iniciar cada venta.</small>
                    </label>
                    <label>Umbral de efectivo para alertar
                        <input type="number" name="cash_alert_threshold" min="0" step="0.01" value="<?= $escape($config['cash_alert_threshold']) ?>">
                        <small>Al llegar a este total en efectivo hoy, aparece una alerta en el panel. 0 desactiva el aviso.</small>
                    </label>
                </div>
            </section>

            <section class="settings-section">
                <div class="settings-section-heading">
                    <span class="settings-section-icon" aria-hidden="true">🛒</span>
                    <div><h3>Operación de ventas</h3><p>Controles de la pantalla de cobro.</p></div>
                </div>
                <div class="settings-toggles">
                    <label class="setting-toggle">
                        <input type="checkbox" name="pos_require_table" value="1" <?= !empty($config['pos_require_table']) ? 'checked' : '' ?>>
                        <span><strong>Solicitar mesa para cobrar</strong><small>Si se desactiva, las ventas se registran como mostrador.</small></span>
                    </label>
                    <label class="setting-toggle">
                        <input type="checkbox" name="pos_confirmar_venta" value="1" <?= !empty($config['pos_confirmar_venta']) ? 'checked' : '' ?>>
                        <span><strong>Confirmar antes de cobrar</strong><small>Muestra el resumen de la venta para una última revisión.</small></span>
                    </label>
                    <label class="setting-toggle">
                        <input type="checkbox" name="pos_focus_search" value="1" <?= !empty($config['pos_focus_search']) ? 'checked' : '' ?>>
                        <span><strong>Enfocar búsqueda del menú al abrir el POS</strong><small>Facilita buscar productos con teclado o lector.</small></span>
                    </label>
                </div>
            </section>

            <section class="settings-section">
                <div class="settings-section-heading">
                    <span class="settings-section-icon" aria-hidden="true">▧</span>
                    <div><h3>Recibos</h3><p>Personaliza el encabezado, pie y datos impresos.</p></div>
                </div>
                <div class="settings-fields">
                    <label>Título del recibo
                        <input type="text" name="recibo_titulo" maxlength="100" required value="<?= $escape($config['recibo_titulo']) ?>">
                    </label>
                    <label class="field-wide">Mensaje al pie
                        <input type="text" name="recibo_pie" maxlength="160" value="<?= $escape($config['recibo_pie']) ?>">
                    </label>
                    <label>Ancho del papel
                        <select name="recibo_ancho_papel">
                            <option value="58" <?= ($config['recibo_ancho_papel'] ?? '80') === '58' ? 'selected' : '' ?>>58 mm (papel angosto)</option>
                            <option value="80" <?= ($config['recibo_ancho_papel'] ?? '80') === '80' ? 'selected' : '' ?>>80 mm (papel ancho)</option>
                        </select>
                        <small>Ajusta el ancho de línea para evitar que el texto salga del área imprimible.</small>
                    </label>
                    <label>Altura mínima del área de impresión (mm)
                        <input type="number" name="recibo_alto_papel" min="100" max="500" step="1" required value="<?= $escape($config['recibo_alto_papel'] ?? 200) ?>">
                        <small>El área se alarga automáticamente cuando el contenido del recibo lo necesita.</small>
                    </label>
                    <label>Margen superior (mm)
                        <input type="number" name="recibo_margen_superior" min="0" max="20" step="1" required value="<?= $escape($config['recibo_margen_superior'] ?? 2) ?>">
                        <small>Espacio libre antes del encabezado.</small>
                    </label>
                    <label>Margen inferior (mm)
                        <input type="number" name="recibo_margen_inferior" min="0" max="20" step="1" required value="<?= $escape($config['recibo_margen_inferior'] ?? 4) ?>">
                        <small>Espacio para que el final del texto salga completo antes del corte.</small>
                    </label>
                    <label>Tamaño de letra del recibo
                        <select name="recibo_tamano_fuente">
                            <option value="pequena" <?= ($config['recibo_tamano_fuente'] ?? 'normal') === 'pequena' ? 'selected' : '' ?>>Pequeña</option>
                            <option value="normal" <?= ($config['recibo_tamano_fuente'] ?? 'normal') === 'normal' ? 'selected' : '' ?>>Normal</option>
                            <option value="grande" <?= ($config['recibo_tamano_fuente'] ?? 'normal') === 'grande' ? 'selected' : '' ?>>Grande</option>
                        </select>
                        <small>El ancho de línea se recalcula según el tamaño de letra seleccionado.</small>
                    </label>
                </div>
                <div class="settings-toggles">
                    <label class="setting-toggle"><input type="checkbox" name="recibo_mostrar_fecha" value="1" <?= !empty($config['recibo_mostrar_fecha']) ? 'checked' : '' ?>><span><strong>Mostrar fecha y hora</strong><small>En pre-recibos, recibos y comandas.</small></span></label>
                    <label class="setting-toggle"><input type="checkbox" name="recibo_mostrar_precios" value="1" <?= !empty($config['recibo_mostrar_precios']) ? 'checked' : '' ?>><span><strong>Mostrar precio por producto</strong><small>Controla los precios en la lista de artículos.</small></span></label>
                    <label class="setting-toggle"><input type="checkbox" name="recibo_mostrar_descuentos" value="1" <?= !empty($config['recibo_mostrar_descuentos']) ? 'checked' : '' ?>><span><strong>Mostrar descuentos</strong><small>Incluye el total de descuentos aplicados.</small></span></label>
                    <label class="setting-toggle"><input type="checkbox" name="recibo_mostrar_impuestos" value="1" <?= !empty($config['recibo_mostrar_impuestos']) ? 'checked' : '' ?>><span><strong>Desglosar impuestos</strong><small>Muestra el importe del impuesto en el recibo.</small></span></label>
                    <label class="setting-toggle"><input type="checkbox" name="recibo_mostrar_mesa" value="1" <?= !empty($config['recibo_mostrar_mesa']) ? 'checked' : '' ?>><span><strong>Mostrar mesa o mostrador</strong><small>Incluye dónde se realizó la venta.</small></span></label>
                    <label class="setting-toggle"><input type="checkbox" name="recibo_mostrar_mesero" value="1" <?= !empty($config['recibo_mostrar_mesero']) ? 'checked' : '' ?>><span><strong>Mostrar mesero</strong><small>Incluye el empleado que atendió la venta.</small></span></label>
                    <label class="setting-toggle"><input type="checkbox" name="recibo_mostrar_folio" value="1" <?= !empty($config['recibo_mostrar_folio']) ? 'checked' : '' ?>><span><strong>Mostrar folio de venta</strong><small>Identifica el recibo con su número de venta.</small></span></label>
                    <label class="setting-toggle"><input type="checkbox" name="recibo_mostrar_notas" value="1" <?= !empty($config['recibo_mostrar_notas']) ? 'checked' : '' ?>><span><strong>Mostrar notas de productos</strong><small>Imprime las instrucciones o notas capturadas.</small></span></label>
                    <label class="setting-toggle"><input type="checkbox" name="recibo_mostrar_subtotal" value="1" <?= !empty($config['recibo_mostrar_subtotal']) ? 'checked' : '' ?>><span><strong>Mostrar subtotal</strong><small>Incluye el subtotal antes de descuentos e impuestos.</small></span></label>
                    <label class="setting-toggle"><input type="checkbox" name="recibo_mostrar_pago" value="1" <?= !empty($config['recibo_mostrar_pago']) ? 'checked' : '' ?>><span><strong>Mostrar datos del pago</strong><small>Incluye forma de pago, monto recibido y cambio.</small></span></label>
                </div>
                <div class="receipt-preview-panel">
                    <div class="receipt-preview-heading">
                        <div>
                            <h4>Vista previa del área de impresión</h4>
                            <p>La vista se actualiza al cambiar el papel, la letra, los márgenes o los datos del recibo.</p>
                        </div>
                        <output id="receipt-preview-size" aria-live="polite"></output>
                    </div>
                    <div class="receipt-preview-scroll">
                        <div id="receipt-preview-paper" class="receipt-preview-paper">
                            <div id="receipt-preview-top-margin" class="receipt-preview-margin" data-preview-margin="recibo_margen_superior" title="Arrastra para ajustar margen superior">
                                <span id="receipt-preview-top-label"></span>
                            </div>
                            <div id="receipt-preview-content" class="receipt-preview-content"></div>
                            <div id="receipt-preview-bottom-margin" class="receipt-preview-margin receipt-preview-margin-bottom" data-preview-margin="recibo_margen_inferior" title="Arrastra para ajustar margen inferior">
                                <span id="receipt-preview-bottom-label"></span>
                            </div>
                            <div class="receipt-preview-cut-feed">Avance para corte · 8 mm</div>
                        </div>
                    </div>
                    <p class="receipt-preview-note">Arrastra las franjas amarillas para ajustar los márgenes; también puedes cambiar los valores desde los campos. El papel puede avanzar más si hay muchos productos. Para impresión desde el navegador, selecciona rollo de <?= $escape($config['recibo_ancho_papel'] ?? '80') ?> mm, márgenes «Ninguno» y escala 100 % en las preferencias de la impresora.</p>
                </div>
            </section>

            <section class="settings-section">
                <div class="settings-section-heading">
                    <span class="settings-section-icon" aria-hidden="true">▤</span>
                    <div><h3>Impresoras y comandas</h3><p>Para imprimir comandas más rápido y sin el diálogo del navegador, configura la IP privada y el puerto TCP de cada impresora ESC/POS. Deja una IP vacía para imprimir manualmente desde el navegador. Guarda los cambios antes de probar.</p></div>
                </div>
                <div class="settings-fields">
                    <?php foreach (['recibo' => 'Recibos de cliente', 'cocina' => 'Comandas de cocina', 'barra' => 'Comandas de barra'] as $station => $label): ?>
                        <label><?= $escape($label) ?> · IP privada
                            <input type="text" name="impresora_<?= $station ?>_ip" inputmode="decimal" placeholder="192.168.1.50" value="<?= $escape($config['impresora_' . $station . '_ip'] ?? '') ?>">
                        </label>
                        <label>Puerto · <?= $escape($label) ?>
                            <input type="number" name="impresora_<?= $station ?>_puerto" min="1" max="65535" value="<?= $escape($config['impresora_' . $station . '_puerto'] ?? 9100) ?>">
                        </label>
                        <div class="printer-test-row">
                            <button type="button" class="button-secondary" data-printer-test="<?= $station ?>">Probar impresora de <?= $escape($station) ?></button>
                        </div>
                    <?php endforeach; ?>
                    <label>Ancho del papel para comandas
                        <select name="comanda_ancho_papel">
                            <option value="58" <?= ($config['comanda_ancho_papel'] ?? '80') === '58' ? 'selected' : '' ?>>58 mm</option>
                            <option value="80" <?= ($config['comanda_ancho_papel'] ?? '80') === '80' ? 'selected' : '' ?>>80 mm</option>
                        </select>
                    </label>
                    <label>Tamaño de letra para comandas
                        <select name="comanda_tamano_fuente">
                            <option value="pequena" <?= ($config['comanda_tamano_fuente'] ?? 'normal') === 'pequena' ? 'selected' : '' ?>>Pequeña</option>
                            <option value="normal" <?= ($config['comanda_tamano_fuente'] ?? 'normal') === 'normal' ? 'selected' : '' ?>>Normal</option>
                            <option value="grande" <?= ($config['comanda_tamano_fuente'] ?? 'normal') === 'grande' ? 'selected' : '' ?>>Grande</option>
                        </select>
                    </label>
                    <label>Margen superior de comandas (mm)
                        <input type="number" name="comanda_margen_superior" min="0" max="20" required value="<?= $escape($config['comanda_margen_superior'] ?? 2) ?>">
                    </label>
                    <label>Margen inferior de comandas (mm)
                        <input type="number" name="comanda_margen_inferior" min="0" max="20" required value="<?= $escape($config['comanda_margen_inferior'] ?? 4) ?>">
                    </label>
                    <label class="field-wide">Categorías que se envían a barra
                        <textarea name="impresora_categorias_barra" rows="3" placeholder="Bebidas&#10;Cervezas"><?= $escape($barCategoriesText) ?></textarea>
                        <small>Una categoría por línea. Los demás productos se envían a cocina. Si queda vacío, se usa la detección tradicional por nombre de bebidas.</small>
                    </label>
                </div>
                <p id="printer-test-result" class="settings-message" role="status" hidden></p>
                <div class="receipt-preview-panel">
                    <div class="receipt-preview-heading">
                        <div>
                            <h4>Vista previa editable de comandas</h4>
                            <p>Selecciona cocina o barra y arrastra las franjas amarillas para ajustar los márgenes.</p>
                        </div>
                        <div class="receipt-preview-tabs" role="group" aria-label="Tipo de comanda">
                            <button type="button" class="button-secondary" data-command-preview="cocina" aria-pressed="true">Cocina</button>
                            <button type="button" class="button-secondary" data-command-preview="barra" aria-pressed="false">Barra</button>
                        </div>
                    </div>
                    <output id="command-preview-size" class="receipt-preview-dimensions" aria-live="polite"></output>
                    <div class="receipt-preview-scroll">
                        <div id="command-preview-paper" class="receipt-preview-paper">
                            <div id="command-preview-top-margin" class="receipt-preview-margin" data-preview-margin="comanda_margen_superior" title="Arrastra para ajustar margen superior"><span></span></div>
                            <div id="command-preview-content" class="receipt-preview-content"></div>
                            <div id="command-preview-bottom-margin" class="receipt-preview-margin receipt-preview-margin-bottom" data-preview-margin="comanda_margen_inferior" title="Arrastra para ajustar margen inferior"><span></span></div>
                            <div class="receipt-preview-cut-feed">Avance para corte · 8 mm</div>
                        </div>
                    </div>
                    <p class="receipt-preview-note">Los márgenes se pueden modificar también arrastrando las franjas del papel. Guarda los cambios antes de probar la impresora.</p>
                </div>
            </section>

            <section class="settings-section">
                <div class="settings-section-heading">
                    <span class="settings-section-icon" aria-hidden="true">⚙</span>
                    <div><h3>Apariencia</h3><p>Personaliza los colores y tipografía del sistema.</p></div>
                </div>
                <div class="theme-preset-row">
                    <span><strong>Tema predeterminado</strong><small>Aplica una paleta de colores negros y dorados.</small></span>
                    <button type="button" class="button-secondary" id="black-gold-preset">Volver a predeterminado</button>
                </div>
                <div class="settings-fields settings-colors">
                    <label>Fondo de páginas
                        <input type="color" name="color_fondo" value="<?= $escape($config['color_fondo']) ?>">
                    </label>
                    <label>Color de texto
                        <input type="color" name="color_letra" value="<?= $escape($config['color_letra']) ?>">
                    </label>
                    <label>Botones y enlaces
                        <input type="color" name="color_boton" value="<?= $escape($config['color_boton']) ?>">
                    </label>
                    <label>Botones al pasar el cursor
                        <input type="color" name="color_boton_hover" value="<?= $escape($config['color_boton_hover']) ?>">
                    </label>
                    <label>Fuente del sistema
                        <select name="fuente">
                            <?php foreach ($fontChoices as $font): ?>
                                <option value="<?= $escape($font) ?>" <?= $font === $config['fuente'] ? 'selected' : '' ?>><?= $escape(explode(',', $font)[0]) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                </div>
            </section>

            <div class="settings-actions">
                <button type="submit">Guardar configuración</button>
                <a href="dashboard.php">Cancelar</a>
            </div>
        </form>
    </main>
    <script>
        const paymentInput = document.querySelector('[name="formas_pago"]');
        const defaultPayment = document.getElementById('metodo-pago-default');
        const printerResult = document.getElementById('printer-test-result');
        const csrfToken = document.querySelector('[name="csrf_token"]').value;
        const receiptForm = document.querySelector('.settings-form');
        document.getElementById('black-gold-preset').addEventListener('click', () => {
            const preset = {
                color_fondo: '#f4f1e9',
                color_letra: '#211f1a',
                color_boton: '#171717',
                color_boton_hover: '#7a5e16'
            };
            Object.entries(preset).forEach(([name, color]) => {
                receiptForm.elements[name].value = color;
            });
        });
        paymentInput.addEventListener('input', () => {
            const payments = [...new Set(paymentInput.value.split(/[\n,]+/).map(value => value.trim()).filter(Boolean))];
            const previous = defaultPayment.value;
            defaultPayment.replaceChildren(...payments.map(payment => new Option(payment, payment)));
            if (payments.includes(previous)) defaultPayment.value = previous;
        });
        document.querySelectorAll('[data-printer-test]').forEach(button => {
            button.addEventListener('click', async () => {
                printerResult.hidden = false;
                printerResult.className = 'settings-message';
                printerResult.textContent = 'Enviando prueba de impresión…';
                try {
                    const response = await fetch('imprimir_comanda.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrfToken },
                        body: JSON.stringify({ estacion: button.dataset.printerTest, prueba: true, csrf_token: csrfToken })
                    });
                    const result = await response.json();
                    if (!response.ok || result.status !== 'ok') {
                        throw new Error(result.message || 'No se pudo imprimir la prueba.');
                    }
                    printerResult.className = 'settings-message success';
                    printerResult.textContent = result.message;
                } catch (error) {
                    printerResult.className = 'settings-message error';
                    printerResult.textContent = error.message || 'Error al probar la impresora.';
                }
            });
        });

        const receiptPreviewPaper = document.getElementById('receipt-preview-paper');
        const receiptPreviewContent = document.getElementById('receipt-preview-content');
        const receiptPreviewTopMargin = document.getElementById('receipt-preview-top-margin');
        const receiptPreviewBottomMargin = document.getElementById('receipt-preview-bottom-margin');
        const receiptPreviewSize = document.getElementById('receipt-preview-size');
        const commandPreviewPaper = document.getElementById('command-preview-paper');
        const commandPreviewContent = document.getElementById('command-preview-content');
        const commandPreviewSize = document.getElementById('command-preview-size');
        let commandPreviewStation = 'cocina';

        function receiptPreviewLine(text, className = '') {
            const line = document.createElement('div');
            line.className = className;
            line.textContent = text;
            receiptPreviewContent.appendChild(line);
        }

        function updateReceiptPreview() {
            const value = name => receiptForm.elements[name].value;
            const checked = name => receiptForm.elements[name].checked;
            const width = Number(value('recibo_ancho_papel')) || 80;
            const minHeight = Math.max(100, Math.min(500, Number(value('recibo_alto_papel')) || 200));
            const topMargin = Math.max(0, Math.min(20, Number(value('recibo_margen_superior')) || 0));
            const bottomMargin = Math.max(0, Math.min(20, Number(value('recibo_margen_inferior')) || 0));
            const fontSize = { pequena: '10px', normal: '12px', grande: '14px' }[value('recibo_tamano_fuente')] || '12px';

            receiptPreviewPaper.style.width = `${width}mm`;
            receiptPreviewPaper.style.minHeight = `${minHeight + 8}mm`;
            receiptPreviewPaper.style.setProperty('--receipt-preview-font-size', fontSize);
            receiptPreviewTopMargin.style.height = `${topMargin}mm`;
            receiptPreviewBottomMargin.style.height = `${bottomMargin}mm`;
            document.getElementById('receipt-preview-top-label').textContent = topMargin ? `Margen superior ${topMargin} mm` : '';
            document.getElementById('receipt-preview-bottom-label').textContent = bottomMargin ? `Margen inferior ${bottomMargin} mm` : '';
            receiptPreviewContent.replaceChildren();

            receiptPreviewLine(value('recibo_titulo') || 'Nombre del restaurante', 'receipt-preview-center receipt-preview-strong');
            receiptPreviewLine('RECIBO', 'receipt-preview-center receipt-preview-strong');
            receiptPreviewLine('--------------------------------', 'receipt-preview-separator');
            if (checked('recibo_mostrar_folio')) receiptPreviewLine('Folio: #000123');
            if (checked('recibo_mostrar_fecha')) receiptPreviewLine(`Fecha: ${new Date().toLocaleString()}`);
            if (checked('recibo_mostrar_mesa')) receiptPreviewLine('Mesa: 4');
            if (checked('recibo_mostrar_mesero')) receiptPreviewLine('Mesero: Nombre del mesero');
            receiptPreviewLine('--------------------------------', 'receipt-preview-separator');

            const sampleItems = [
                ['2 x Hamburguesa especial', '$240.00'],
                ['1 x Papas a la francesa', '$85.00'],
                ['2 x Refresco de sabor', '$70.00'],
                ['1 x Ensalada de la casa', '$110.00'],
                ['1 x Orden de alitas BBQ', '$165.00'],
                ['2 x Agua embotellada', '$50.00']
            ];
            sampleItems.forEach(([name, price]) => {
                receiptPreviewLine(name);
                if (checked('recibo_mostrar_precios')) receiptPreviewLine(`   ${price}`, 'receipt-preview-item-price');
                if (checked('recibo_mostrar_notas') && name.includes('Hamburguesa')) {
                    receiptPreviewLine('   Nota: sin cebolla', 'receipt-preview-item-price');
                }
            });

            receiptPreviewLine('--------------------------------', 'receipt-preview-separator');
            if (checked('recibo_mostrar_subtotal')) receiptPreviewLine('Subtotal: $720.00');
            if (checked('recibo_mostrar_descuentos')) receiptPreviewLine('Descuento: -$20.00');
            if (checked('recibo_mostrar_impuestos')) receiptPreviewLine('Impuesto: $112.00');
            receiptPreviewLine('TOTAL: $812.00', 'receipt-preview-strong');
            if (checked('recibo_mostrar_pago')) {
                receiptPreviewLine('Pago: Efectivo');
                receiptPreviewLine('Pagado: $1,000.00');
                receiptPreviewLine('Cambio: $188.00');
            }
            const footer = value('recibo_pie').trim();
            if (footer) {
                receiptPreviewLine('--------------------------------', 'receipt-preview-separator');
                receiptPreviewLine(footer, 'receipt-preview-center');
            }

            const estimatedHeight = receiptPreviewPaper.scrollHeight / 96 * 25.4;
            receiptPreviewSize.textContent = `Papel ${width} mm · área mínima ${minHeight} mm · altura estimada con corte ${Math.max(minHeight + 8, Math.ceil(estimatedHeight))} mm`;
        }

        function updateCommandPreview() {
            const value = name => receiptForm.elements[name].value;
            const width = Number(value('comanda_ancho_papel')) || 80;
            const topMargin = Math.max(0, Math.min(20, Number(value('comanda_margen_superior')) || 0));
            const bottomMargin = Math.max(0, Math.min(20, Number(value('comanda_margen_inferior')) || 0));
            const fontSize = { pequena: '10px', normal: '12px', grande: '14px' }[value('comanda_tamano_fuente')] || '12px';
            const title = commandPreviewStation === 'cocina' ? 'COMANDA COCINA' : 'COMANDA BARRA';
            const items = commandPreviewStation === 'cocina'
                ? ['2 x Hamburguesa especial', '1 x Papas a la francesa', '1 x Ensalada de la casa']
                : ['2 x Refresco de sabor', '1 x Agua embotellada', '1 x Limonada natural'];

            commandPreviewPaper.style.width = `${width}mm`;
            commandPreviewPaper.style.minHeight = '100mm';
            commandPreviewPaper.style.setProperty('--receipt-preview-font-size', fontSize);
            document.getElementById('command-preview-top-margin').style.height = `${topMargin}mm`;
            document.getElementById('command-preview-bottom-margin').style.height = `${bottomMargin}mm`;
            document.querySelector('#command-preview-top-margin span').textContent = topMargin ? `Margen superior ${topMargin} mm · arrastra para ajustar` : 'Arrastra para ajustar margen superior';
            document.querySelector('#command-preview-bottom-margin span').textContent = bottomMargin ? `Margen inferior ${bottomMargin} mm · arrastra para ajustar` : 'Arrastra para ajustar margen inferior';
            commandPreviewContent.replaceChildren();

            const line = (text, className = '') => {
                const element = document.createElement('div');
                element.className = className;
                element.textContent = text;
                commandPreviewContent.appendChild(element);
            };
            line(value('recibo_titulo') || 'Nombre del restaurante', 'receipt-preview-center receipt-preview-strong');
            line(title, 'receipt-preview-center receipt-preview-strong');
            line('--------------------------------', 'receipt-preview-separator');
            line('Mesa: 4');
            line('Mesero: Nombre del mesero');
            line(`Hora: ${new Date().toLocaleTimeString()}`);
            line('--------------------------------', 'receipt-preview-separator');
            items.forEach(item => {
                line(item);
                if (item.includes('Hamburguesa')) line('   Nota: sin cebolla', 'receipt-preview-item-price');
            });
            line('--------------------------------', 'receipt-preview-separator');
            line(commandPreviewStation === 'cocina' ? 'Preparar alimentos' : 'Preparar bebidas', 'receipt-preview-strong');

            const estimatedHeight = commandPreviewPaper.scrollHeight / 96 * 25.4;
            commandPreviewSize.textContent = `Comanda de ${commandPreviewStation} · papel ${width} mm · altura estimada ${Math.ceil(estimatedHeight)} mm`;
        }

        function bindDraggableMargin(previewPaper, updatePreview) {
            previewPaper.querySelectorAll('[data-preview-margin]').forEach(margin => {
                margin.addEventListener('pointerdown', event => {
                    if (event.button !== 0) return;
                    event.preventDefault();
                    const input = receiptForm.elements[margin.dataset.previewMargin];
                    const startY = event.clientY;
                    const startValue = Number(input.value) || 0;
                    margin.setPointerCapture(event.pointerId);
                    const onMove = moveEvent => {
                        const deltaMm = (moveEvent.clientY - startY) * 25.4 / 96;
                        input.value = Math.max(0, Math.min(20, Math.round(startValue + deltaMm)));
                        updatePreview();
                    };
                    const onUp = () => {
                        margin.removeEventListener('pointermove', onMove);
                        margin.removeEventListener('pointerup', onUp);
                        margin.removeEventListener('pointercancel', onUp);
                    };
                    margin.addEventListener('pointermove', onMove);
                    margin.addEventListener('pointerup', onUp);
                    margin.addEventListener('pointercancel', onUp);
                });
            });
        }

        receiptForm.querySelectorAll('[name^="recibo_"]').forEach(input => {
            input.addEventListener('input', updateReceiptPreview);
            input.addEventListener('change', updateReceiptPreview);
        });
        receiptForm.querySelectorAll('[name^="comanda_"]').forEach(input => {
            input.addEventListener('input', updateCommandPreview);
            input.addEventListener('change', updateCommandPreview);
        });
        document.querySelectorAll('[data-command-preview]').forEach(button => {
            button.addEventListener('click', () => {
                commandPreviewStation = button.dataset.commandPreview;
                document.querySelectorAll('[data-command-preview]').forEach(tab => {
                    tab.setAttribute('aria-pressed', String(tab === button));
                });
                updateCommandPreview();
            });
        });
        bindDraggableMargin(receiptPreviewPaper, updateReceiptPreview);
        bindDraggableMargin(commandPreviewPaper, updateCommandPreview);
        updateReceiptPreview();
        updateCommandPreview();
    </script>
</body>

</html>

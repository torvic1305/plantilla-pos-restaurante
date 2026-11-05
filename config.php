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
            'recibo_mostrar_fecha' => isset($_POST['recibo_mostrar_fecha']),
            'recibo_mostrar_precios' => isset($_POST['recibo_mostrar_precios']),
            'recibo_mostrar_impuestos' => isset($_POST['recibo_mostrar_impuestos']),
            'recibo_mostrar_descuentos' => isset($_POST['recibo_mostrar_descuentos']),
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
                </div>
                <div class="settings-toggles">
                    <label class="setting-toggle"><input type="checkbox" name="recibo_mostrar_fecha" value="1" <?= !empty($config['recibo_mostrar_fecha']) ? 'checked' : '' ?>><span><strong>Mostrar fecha y hora</strong><small>En pre-recibos, recibos y comandas.</small></span></label>
                    <label class="setting-toggle"><input type="checkbox" name="recibo_mostrar_precios" value="1" <?= !empty($config['recibo_mostrar_precios']) ? 'checked' : '' ?>><span><strong>Mostrar precio por producto</strong><small>Controla los precios en la lista de artículos.</small></span></label>
                    <label class="setting-toggle"><input type="checkbox" name="recibo_mostrar_descuentos" value="1" <?= !empty($config['recibo_mostrar_descuentos']) ? 'checked' : '' ?>><span><strong>Mostrar descuentos</strong><small>Incluye el total de descuentos aplicados.</small></span></label>
                    <label class="setting-toggle"><input type="checkbox" name="recibo_mostrar_impuestos" value="1" <?= !empty($config['recibo_mostrar_impuestos']) ? 'checked' : '' ?>><span><strong>Desglosar impuestos</strong><small>Muestra el importe del impuesto en el recibo.</small></span></label>
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
                    <label class="field-wide">Categorías que se envían a barra
                        <textarea name="impresora_categorias_barra" rows="3" placeholder="Bebidas&#10;Cervezas"><?= $escape($barCategoriesText) ?></textarea>
                        <small>Una categoría por línea. Los demás productos se envían a cocina. Si queda vacío, se usa la detección tradicional por nombre de bebidas.</small>
                    </label>
                </div>
                <p id="printer-test-result" class="settings-message" role="status" hidden></p>
            </section>

            <section class="settings-section">
                <div class="settings-section-heading">
                    <span class="settings-section-icon" aria-hidden="true">⚙</span>
                    <div><h3>Apariencia</h3><p>Personaliza los colores y tipografía del sistema.</p></div>
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
    </script>
</body>

</html>

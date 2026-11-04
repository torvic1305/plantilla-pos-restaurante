<?php
include 'data.php';
include 'security.php';

// Verificar permiso de gerente
if (!isset($_SESSION['user']) || $_SESSION['user']['rol'] !== 'gerente') {
    http_response_code(403);
    header('Location: dashboard.php');
    exit;
}

$config = [
    'color_fondo' => '#f58610db',
    'color_letra' => '#000',
    'tamano_letra' => '16px',
    'fuente' => 'Arial, sans-serif',
    'formas_pago' => ['Efectivo', 'Tarjeta', 'Transferencia'],
    'mesas' => 20,
    'impuesto' => 0
];

if (file_exists('config.json')) {
    $loaded = json_decode(file_get_contents('config.json'), true);
    if (is_array($loaded)) {
        $config = array_merge($config, $loaded);
    }
}

$error_msg = '';
$success_msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Validar CSRF token obligatorio
        if (!isset($_POST['csrf_token']) || !validateCsrfToken($_POST['csrf_token'])) {
            throw new Exception('Token de seguridad inválido');
        }

        // Validar y sanitizar entrada
        $impuesto = validateDecimal($_POST['impuesto'] ?? 0, 0, 100);
        if ($impuesto === null) {
            throw new Exception('Impuesto debe ser entre 0 y 100');
        }

        $mesas = validateInteger($_POST['mesas'] ?? 20, 1, 1000);
        if ($mesas === null) {
            throw new Exception('Número de mesas inválido');
        }

        $color_fondo = strip_tags(trim($_POST['color_fondo'] ?? '#000000'));
        $color_letra = strip_tags(trim($_POST['color_letra'] ?? '#fff'));
        $tamano_letra = sanitizeString($_POST['tamano_letra'] ?? '16px', 20);
        $fuente = sanitizeString($_POST['fuente'] ?? 'Arial', 100);

        // Validar formas de pago
        $pagos = array_filter(array_map('trim', explode(',', $_POST['formas_pago'] ?? '')));
        if (empty($pagos)) {
            throw new Exception('Debe haber al menos una forma de pago');
        }

        $formas_pago_validas = [];
        foreach ($pagos as $pago) {
            $pago_sanitizado = sanitizeString($pago, 50);
            if (!empty($pago_sanitizado)) {
                $formas_pago_validas[] = $pago_sanitizado;
            }
        }

        if (empty($formas_pago_validas)) {
            throw new Exception('Formas de pago inválidas');
        }

        // Actualizar configuración
        $config['color_fondo'] = $color_fondo;
        $config['color_letra'] = $color_letra;
        $config['tamano_letra'] = $tamano_letra;
        $config['fuente'] = $fuente;
        $config['mesas'] = $mesas;
        $config['impuesto'] = $impuesto;
        $config['formas_pago'] = array_values($formas_pago_validas);

        file_put_contents('config.json', json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        registrarAuditoria($_SESSION['user']['id'], 'UPDATE_CONFIG', 'CONFIG', 1, 'Configuración del sistema actualizada');
        $success_msg = '✅ Configuración guardada correctamente';
    } catch (Exception $e) {
        $error_msg = '❌ Error: ' . $e->getMessage();
    }
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
        <h1>Configuración</h1>
        <a href="dashboard.php">Volver</a>
    </header>
    <main>
        <h2>⚙️ Configuración del Sistema POS</h2>

        <?php if (!empty($error_msg)) { ?>
            <div style="color: red; padding: 10px; background: #ffe0e0; border-radius: 4px; margin-bottom: 20px;">
                <?= htmlspecialchars($error_msg) ?>
            </div>
        <?php } ?>

        <?php if (!empty($success_msg)) { ?>
            <div style="color: green; padding: 10px; background: #e0ffe0; border-radius: 4px; margin-bottom: 20px;">
                <?= htmlspecialchars($success_msg) ?>
            </div>
        <?php } ?>

        <h3>💰 Impuestos y Finanzas</h3>
        <form method="post" style="margin-bottom: 30px;">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(getCsrfToken()) ?>">

            <label>Porcentaje de Impuesto (%):
                <input type="number" name="impuesto" min="0" max="100" step="0.01"
                    value="<?php echo htmlspecialchars($config['impuesto'] ?? 0); ?>" required>
            </label><br>
            <small style="display: block; color: #666; margin: 5px 0;">
                El porcentaje de impuesto se aplicará automáticamente a todas las ventas.<br>
                Se mostrará desglosado en recibos y reportes. Dejar en 0 si no aplica.
            </small>

            <h3>Mesas</h3>
            <label>Número de mesas:
                <input type="number" name="mesas" min="1" max="1000"
                    value="<?php echo htmlspecialchars($config['mesas']); ?>" required>
            </label><br>

            <h3>Formas de Pago</h3>
            <p>Escribe las formas de pago separadas por comas:</p>
            <input type="text" name="formas_pago" style="width:100%;" maxlength="500"
                value="<?php echo htmlspecialchars(implode(', ', $config['formas_pago'])); ?>" required><br>

            <h3>Personalizar Estilos</h3>
            <label>Color de Fondo:
                <input type="color" name="color_fondo" value="<?php echo htmlspecialchars($config['color_fondo']); ?>"
                    required>
            </label><br>
            <label>Color de Letra:
                <input type="color" name="color_letra" value="<?php echo htmlspecialchars($config['color_letra']); ?>"
                    required>
            </label><br>
            <label>Tamaño de Letra:
                <input type="text" name="tamano_letra" maxlength="20"
                    value="<?php echo htmlspecialchars($config['tamano_letra']); ?>" required>
            </label><br>
            <label>Fuente:
                <input type="text" name="fuente" maxlength="100"
                    value="<?php echo htmlspecialchars($config['fuente']); ?>" required>
            </label><br>

            <button type="submit"
                style="margin-top: 20px; padding: 10px 20px; background: #27ae60; color: white; border: none; border-radius: 4px; cursor: pointer;">💾
                Guardar Configuración</button>
        </form>
    </main>
    <script src="2.js"></script>
</body>

</html>
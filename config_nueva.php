<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

include 'data.php';
include 'security.php';
if (!isset($_SESSION['user'])) {
    header('Location: index.php');
    exit;
}

$user = $_SESSION['user'];
requirePermission('config');

$config = getAppConfig();
$mensaje = '';
$error = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['guardar'])) {
    if (!isset($_POST['csrf_token']) || !validateCsrfToken($_POST['csrf_token'])) {
        $mensaje = 'Token de seguridad inválido.';
        $error = true;
    } else {
        $nuevaConfig = [
            'color_fondo' => sanitizeString($_POST['color_fondo'] ?? '#f58610db', 7),
            'color_letra' => sanitizeString($_POST['color_letra'] ?? '#000', 7),
            'tamano_letra' => sanitizeString($_POST['tamano_letra'] ?? '16px', 10),
            'fuente' => sanitizeString($_POST['fuente'] ?? 'Arial, sans-serif', 100),
            'formas_pago' => array_filter(array_map('trim', explode(',', sanitizeString($_POST['formas_pago'] ?? 'Efectivo,Tarjeta,Transferencia', 255)))),
            'mesas' => validateInteger($_POST['mesas'] ?? 20, 1, 100) ?? 20,
            'impuesto' => validateDecimal($_POST['impuesto'] ?? 0, 0, 100) ?? 0
        ];

        if (file_put_contents('config.json', json_encode($nuevaConfig, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE))) {
            $mensaje = 'Configuración guardada correctamente.';
            $config = $nuevaConfig;
        } else {
            $mensaje = 'Error al guardar la configuración.';
            $error = true;
        }
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
    <style>
        .config-container {
            max-width: 600px;
            margin: 40px auto;
            background: white;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }

        .config-container h1 {
            color: #2c3e50;
            margin-top: 0;
        }

        .config-section {
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 1px solid #ddd;
        }

        .config-section h2 {
            color: #34495e;
            font-size: 18px;
            margin-top: 0;
        }

        .config-section:last-child {
            border-bottom: none;
        }

        .form-group {
            margin-bottom: 15px;
        }

        .form-group label {
            display: block;
            margin-bottom: 5px;
            color: #2c3e50;
            font-weight: bold;
        }

        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 14px;
            box-sizing: border-box;
        }

        .form-group input[type="color"] {
            height: 40px;
            padding: 2px;
        }

        .form-group textarea {
            resize: none;
            height: 60px;
        }

        .form-group small {
            display: block;
            color: #7f8c8d;
            margin-top: 5px;
        }

        .color-preview {
            display: inline-block;
            width: 50px;
            height: 50px;
            border-radius: 4px;
            margin-left: 10px;
            vertical-align: middle;
            border: 1px solid #ddd;
        }

        .botones {
            display: flex;
            gap: 10px;
            justify-content: flex-end;
            margin-top: 20px;
        }

        .btn {
            padding: 12px 24px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-weight: bold;
            font-size: 14px;
        }

        .btn-guardar {
            background: #27ae60;
            color: white;
        }

        .btn-guardar:hover {
            background: #229954;
        }

        .btn-cancelar {
            background: #95a5a6;
            color: white;
            text-decoration: none;
            display: inline-block;
        }

        .btn-cancelar:hover {
            background: #7f8c8d;
        }

        .mensaje {
            padding: 15px;
            border-radius: 4px;
            margin-bottom: 20px;
        }

        .mensaje.exito {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .mensaje.error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        .alerta-impuesto {
            background: #fff3cd;
            border-left: 4px solid #ffc107;
            padding: 15px;
            border-radius: 4px;
            margin-bottom: 15px;
        }

        .alerta-impuesto strong {
            color: #856404;
        }
    </style>
</head>

<body>
    <div class="config-container">
        <h1>⚙️ Configuración del Sistema POS</h1>

        <?php if ($mensaje): ?>
            <div class="mensaje <?php echo $error ? 'error' : 'exito'; ?>">
                <?php echo htmlspecialchars($mensaje); ?>
            </div>
        <?php endif; ?>

        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(getCsrfToken()) ?>">
            <!-- Sección Impuestos -->
            <div class="config-section">
                <h2>💰 Impuestos y Finanzas</h2>

                <div class="alerta-impuesto">
                    <strong>ℹ️ Importante:</strong> El porcentaje de impuesto se aplicará automáticamente a todas las
                    ventas. Se mostrará desglosado en recibos y reportes.
                </div>

                <div class="form-group">
                    <label for="impuesto">Porcentaje de Impuesto (%)</label>
                    <input type="number" id="impuesto" name="impuesto" min="0" max="100" step="0.01"
                        value="<?php echo $config['impuesto'] ?? 0; ?>">
                    <small>Ej: 16 para IVA del 16% (dejar en 0 si no aplica)</small>
                </div>

                <div class="form-group">
                    <label for="formas_pago">Formas de Pago</label>
                    <textarea id="formas_pago" name="formas_pago"
                        placeholder="Efectivo, Tarjeta, Transferencia"><?php echo implode(', ', $config['formas_pago'] ?? ['Efectivo', 'Tarjeta', 'Transferencia']); ?></textarea>
                    <small>Separa con comas. Estas opciones aparecerán en el POS</small>
                </div>

                <div class="form-group">
                    <label for="mesas">Número de Mesas</label>
                    <input type="number" id="mesas" name="mesas" min="1" max="100"
                        value="<?php echo htmlspecialchars($config['mesas'] ?? 20); ?>">
                    <small>Cantidad total de mesas en el restaurante</small>
                </div>
            </div>

            <!-- Sección Apariencia -->
            <div class="config-section">
                <h2>🎨 Apariencia</h2>

                <div class="form-group">
                    <label for="color_fondo">Color de Fondo</label>
                    <div>
                        <input type="color" id="color_fondo" name="color_fondo"
                            value="<?php echo htmlspecialchars(substr($config['color_fondo'] ?? '#f58610', 0, 7)); ?>"
                            onchange="document.body.style.backgroundColor = this.value">
                        <span class="color-preview" id="preview-fondo"></span>
                    </div>
                    <small>Color de fondo general de la aplicación</small>
                </div>

                <div class="form-group">
                    <label for="color_letra">Color de Texto</label>
                    <div>
                        <input type="color" id="color_letra" name="color_letra"
                            value="<?php echo htmlspecialchars(substr($config['color_letra'] ?? '#000', 0, 7)); ?>"
                            onchange="document.body.style.color = this.value">
                        <span class="color-preview" id="preview-letra"></span>
                    </div>
                    <small>Color del texto en toda la aplicación</small>
                </div>

                <div class="form-group">
                    <label for="tamano_letra">Tamaño de Letra</label>
                    <input type="text" id="tamano_letra" name="tamano_letra"
                        value="<?php echo htmlspecialchars($config['tamano_letra'] ?? '16px'); ?>" placeholder="16px">
                    <small>Ej: 14px, 16px, 18px</small>
                </div>

                <div class="form-group">
                    <label for="fuente">Tipo de Fuente</label>
                    <select id="fuente" name="fuente">
                        <option value="Arial, sans-serif" <?php echo ($config['fuente'] ?? '') === 'Arial, sans-serif' ? 'selected' : ''; ?>>Arial</option>
                        <option value="Georgia, serif" <?php echo ($config['fuente'] ?? '') === 'Georgia, serif' ? 'selected' : ''; ?>>Georgia</option>
                        <option value="Courier New, monospace" <?php echo ($config['fuente'] ?? '') === 'Courier New, monospace' ? 'selected' : ''; ?>>Courier</option>
                        <option value="Verdana, sans-serif" <?php echo ($config['fuente'] ?? '') === 'Verdana, sans-serif' ? 'selected' : ''; ?>>Verdana</option>
                        <option value="Times New Roman, serif" <?php echo ($config['fuente'] ?? '') === 'Times New Roman, serif' ? 'selected' : ''; ?>>Times New Roman</option>
                    </select>
                    <small>Fuente de texto en toda la interfaz</small>
                </div>
            </div>

            <!-- Información del Sistema -->
            <div class="config-section">
                <h2>ℹ️ Información del Sistema</h2>
                <p><strong>PHP:</strong> <?php echo phpversion(); ?></p>
                <p><strong>MySQL:</strong> PDO</p>
                <p><strong>Usuario Actual:</strong> <?php echo htmlspecialchars($user['nombre']); ?>
                    (<?php echo htmlspecialchars($user['rol']); ?>)</p>
                <p><strong>Archivo Config:</strong> config.json</p>
            </div>

            <!-- Botones -->
            <div class="botones">
                <a href="dashboard.php" class="btn btn-cancelar">Cancelar</a>
                <button type="submit" name="guardar" class="btn btn-guardar">Guardar Cambios</button>
            </div>
        </form>
    </div>

    <script>
        // Actualizar previsualizaciones de color
        document.getElementById('color_fondo').addEventListener('change', function () {
            document.getElementById('preview-fondo').style.backgroundColor = this.value;
        });
        document.getElementById('color_letra').addEventListener('change', function () {
            document.getElementById('preview-letra').style.backgroundColor = this.value;
        });

        // Inicializar previsualizaciones
        document.getElementById('preview-fondo').style.backgroundColor = document.getElementById('color_fondo').value;
        document.getElementById('preview-letra').style.backgroundColor = document.getElementById('color_letra').value;
    </script>
</body>

</html>
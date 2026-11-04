<?php
include 'data.php';
include 'security.php';

// Generar CSRF token
$csrf_token = getCsrfToken();
$loginRestaurantName = getAppConfig()['nombre_restaurante'] ?? 'Restaurante POS';
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inicio de Sesión - Restaurante POS</title>
    <link rel="stylesheet" href="style.php">
</head>

<body class="login-screen">
    <?php
    $error_msg = '';
    if (isset($_GET['error'])) {
        if ($_GET['error'] == 1) {
            $error_msg = $_SESSION['login_error'] ?? 'Usuario o contraseña incorrectos.';
            unset($_SESSION['login_error']);
        } elseif ($_GET['error'] == 2) {
            $error_msg = 'Usuario y contraseña son requeridos.';
        }
    }

    $dbMessage = '';
    if (!($pdo instanceof PDO)) {
        if (!empty($dbError) && strpos($dbError, '[1045]') !== false) {
            $dbMessage = 'MySQL rechazó las credenciales. Configure DB_USER y DB_PASS en Apache y reinicie Apache.';
        } elseif (!empty($dbError) && (strpos($dbError, '[1049]') !== false || strpos($dbError, '[42S02]') !== false)) {
            $dbMessage = 'La base de datos o sus tablas todavía no están instaladas. Ejecute setup.php y vuelva a cargar esta página.';
        } else {
            $dbMessage = 'No se pudo conectar a MySQL. Verifique que MySQL esté iniciado y configure DB_HOST, DB_NAME, DB_USER y DB_PASS.';
        }
    }
    ?>
    <div class="login-container">
        <div class="login-brand">
            <span class="login-brand-icon" aria-hidden="true">♨</span>
            <span class="login-brand-name"><?= htmlspecialchars($loginRestaurantName, ENT_QUOTES | ENT_HTML5, 'UTF-8') ?></span>
        </div>
        <div class="login-content">
            <h1>Inicio de sesión</h1>
            <p class="login-intro">Bienvenido al sistema. Para continuar, ingresa tu usuario y contraseña.</p>

            <?php if ($dbMessage !== ''): ?>
                <div class="login-alert" role="alert">
                    <strong>No se puede iniciar sesión.</strong>
                    <span><?= htmlspecialchars($dbMessage, ENT_QUOTES | ENT_HTML5, 'UTF-8') ?></span>
                    <?php if (strpos($dbError ?? '', '[1049]') !== false || strpos($dbError ?? '', '[42S02]') !== false): ?>
                        <a href="setup.php">Ir a la instalación</a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <?php if ($error_msg !== ''): ?>
                <div class="login-alert" role="alert"><?= htmlspecialchars($error_msg, ENT_QUOTES | ENT_HTML5, 'UTF-8') ?></div>
            <?php endif; ?>

        <form action="login.php" method="POST">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
            <label class="visually-hidden" for="username">Usuario</label>
            <input type="text" id="username" name="username" placeholder="Usuario" required autocomplete="username" autofocus <?= !($pdo instanceof PDO) ? 'disabled' : '' ?>>
            <label class="visually-hidden" for="password">Contraseña</label>
            <input type="password" id="password" name="password" placeholder="Contraseña" required autocomplete="current-password" <?= !($pdo instanceof PDO) ? 'disabled' : '' ?>>
            <button type="submit" <?= !($pdo instanceof PDO) ? 'disabled' : '' ?>>Iniciar sesión</button>
        </form>
        <?php if (!($pdo instanceof PDO) && $dbMessage === ''): ?>
            <p class="login-help"><a href="setup.php">Configurar la base de datos</a></p>
        <?php endif; ?>
        <p class="login-version"><span>POS</span> Sistema de punto de venta para restaurante</p>
        </div>
    </div>
</body>

</html>
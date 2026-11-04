<?php
include 'data.php';
include 'security.php';

// Generar CSRF token
$csrf_token = getCsrfToken();
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inicio de Sesión - Restaurante POS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="style.php">

</head>

<body>
    <?php if (!$pdo) {
        $msg = isset($dbError) ? htmlspecialchars($dbError) : 'No se puede conectar a la base de datos.';
        echo "<p style='color:red; text-align:center; margin-top:20px;'><strong>Error:</strong> $msg Asegúrate de que MySQL esté corriendo y ejecuta <a href='setup.php'>setup.php</a>.</p>";
    } ?>
    <div class="login-container">
        <h2>Inicio de Sesión</h2>
        <?php
        $error_msg = '';
        if (isset($_GET['error'])) {
            $error = $_GET['error'];
            if ($error == 1) {
                $error_msg = $_SESSION['login_error'] ?? 'Usuario o contraseña incorrectos.';
                unset($_SESSION['login_error']);
            } elseif ($error == 2) {
                $error_msg = 'Usuario y contraseña son requeridos.';
            }
        }
        if (!empty($error_msg)) {
            echo "<p style='color:red;'><strong>Error:</strong> " . htmlspecialchars($error_msg) . "</p>";
        }
        ?>
        <form action="login.php" method="POST">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
            <label for="username">Usuario:</label>
            <input type="text" id="username" name="username" required autocomplete="username">
            <label for="password">Contraseña:</label>
            <input type="password" id="password" name="password" required autocomplete="current-password">
            <button type="submit">Iniciar Sesión</button>
        </form>
        <p style="text-align: center; margin-top: 20px; font-size: 12px; color: #666;">
            Si es la primera vez, ejecuta <a href="setup.php">setup.php</a> para crear la base de datos.
        </p>
    </div>
    <script src="2.js"></script>
</body>

</html>
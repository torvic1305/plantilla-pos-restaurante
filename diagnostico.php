<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

include 'data.php';
include 'security.php';

if (!isset($_SESSION['user'])) {
    header('Location: index.php');
    exit;
}

if ($_SESSION['user']['rol'] !== 'gerente') {
    header('Location: dashboard.php');
    exit;
}

echo "<h2>Diagnóstico del Sistema</h2>";

// 1. Verificar MySQL
echo "<h3>1. Estado de MySQL:</h3>";
if ($pdo instanceof PDO) {
    echo "✓ Conexión a MySQL correcta<br>";
    echo "✓ Base de datos configurada y tabla de usuarios accesible<br>";

    // 3. Verificar tabla empleados
    $result = $pdo->query("SHOW TABLES LIKE 'empleados'");
    if ($result->rowCount() > 0) {
        echo "✓ Tabla 'empleados' existe<br>";

        // Contar usuarios
        $result = $pdo->query("SELECT COUNT(*) FROM empleados");
        $count = $result->fetchColumn();
        echo "  - Total de usuarios: " . $count . "<br>";

        // Listar usuarios
        $result = $pdo->query("SELECT usuario, rol FROM empleados");
        echo "  - Usuarios: <br>";
        foreach ($result->fetchAll(PDO::FETCH_ASSOC) as $user) {
            echo "    - " . htmlspecialchars($user['usuario']) . " (" . htmlspecialchars($user['rol']) . ")<br>";
        }
    } else {
        echo "✗ Tabla 'empleados' NO existe. <a href='setup.php'>Ejecuta setup.php</a><br>";
    }

    // 4. Verificar tabla menu
    $result = $pdo->query("SHOW TABLES LIKE 'menu'");
    if ($result->rowCount() > 0) {
        echo "✓ Tabla 'menu' existe<br>";
    } else {
        echo "✗ Tabla 'menu' NO existe<br>";
    }
} else {
    echo "✗ Error de conexión o esquema: " . htmlspecialchars($dbError ?? 'Base de datos no disponible') . "<br>";
    echo "Verifica MySQL y las variables DB_HOST, DB_NAME, DB_USER y DB_PASS; después ejecuta <a href='setup.php'>setup.php</a>.<br>";
}

// 5. Verificar files
echo "<h3>2. Archivos del sistema:</h3>";
$files = ['login.php', 'index.php', 'dashboard.php', 'data.php', 'style.php', 'config.json'];
foreach ($files as $file) {
    if (file_exists($file)) {
        echo "✓ " . $file . "<br>";
    } else {
        echo "✗ " . $file . " (FALTA)<br>";
    }
}

// 6. Prueba de login
echo "<h3>3. Prueba de login:</h3>";
if (isset($_POST['test_user'])) {
    $csrf_token = $_POST['csrf_token'] ?? '';
    if (!validateCsrfToken($csrf_token)) {
        echo "<p>Error: Token de seguridad inválido.</p>";
    } else {
        $usuario = sanitizeString($_POST['test_user'] ?? '', 100);
        $password = $_POST['test_pass'] ?? '';
        echo "Probando login con usuario: " . htmlspecialchars($usuario) . "<br>";
        $user = verificarLogin($usuario, $password);
        if ($user) {
            echo "✓ Login exitoso<br>";
            echo "  - Nombre: " . htmlspecialchars($user['nombre']) . "<br>";
            echo "  - Rol: " . htmlspecialchars($user['rol']) . "<br>";
        } else {
            echo "✗ Login fallido<br>";
        }
    }
} else {
    ?>
    <form method="POST">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(getCsrfToken()) ?>">
        Usuario: <input type="text" name="test_user" value="admin"><br>
        Contraseña: <input type="password" name="test_pass" value="pointofsale"><br>
        <button type="submit">Probar Login</button>
    </form>
    <?php
}

echo "<hr>";
echo "<a href='index.php'>Volver a login</a> | <a href='setup.php'>Ejecutar setup.php</a>";
?>
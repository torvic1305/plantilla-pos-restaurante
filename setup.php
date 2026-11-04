<?php
// Setup de base de datos MySQL
$host = getenv('DB_HOST') ?: 'localhost';
$db = getenv('DB_NAME') ?: 'restaurante_pos';
$user = getenv('DB_USER') ?: 'root';
$pass = getenv('DB_PASS') ?: '';

$message = '';
$error = false;

function ensureColumn(PDO $pdo, string $table, string $column, string $definition): void
{
    $stmt = $pdo->prepare(
        'SELECT COUNT(*) FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?'
    );
    $stmt->execute([$table, $column]);

    if ((int) $stmt->fetchColumn() === 0) {
        $pdo->exec("ALTER TABLE `$table` ADD COLUMN `$column` $definition");
    }
}

try {
    $pdo = new PDO("mysql:host=$host", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Crear base de datos
    $pdo->exec("CREATE DATABASE IF NOT EXISTS `$db` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    $pdo->exec("USE `$db`");

    // Tabla empleados
    $pdo->exec("CREATE TABLE IF NOT EXISTS empleados (
        id INT AUTO_INCREMENT PRIMARY KEY,
        nombre VARCHAR(255) NOT NULL,
        usuario VARCHAR(255) UNIQUE NOT NULL,
        password VARCHAR(255) NOT NULL,
        rol ENUM('mesero', 'cocinero', 'gerente') NOT NULL,
        permisos JSON NULL,
        configuraciones JSON NULL
    )");

    // Agregar columnas si no existen (para actualizaciones)
    ensureColumn($pdo, 'empleados', 'permisos', 'JSON NULL');
    ensureColumn($pdo, 'empleados', 'configuraciones', 'JSON NULL');

    // Tabla menu
    $pdo->exec("CREATE TABLE IF NOT EXISTS menu (
        id INT AUTO_INCREMENT PRIMARY KEY,
        nombre VARCHAR(255) NOT NULL,
        precio DECIMAL(10,2) NOT NULL,
        categoria VARCHAR(255)
    )");

    // Tabla ventas
    $pdo->exec("CREATE TABLE IF NOT EXISTS ventas (
        id INT AUTO_INCREMENT PRIMARY KEY,
        mesero_id INT,
        mesa INT,
        orden TEXT,
        suspendida BOOLEAN DEFAULT FALSE,
        tipo_pago VARCHAR(100) NULL,
        propina DECIMAL(10,2) DEFAULT 0,
        fecha TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (mesero_id) REFERENCES empleados(id)
    )");
    ensureColumn($pdo, 'ventas', 'tipo_pago', 'VARCHAR(100) NULL');
    ensureColumn($pdo, 'ventas', 'propina', 'DECIMAL(10,2) DEFAULT 0');
    ensureColumn($pdo, 'ventas', 'monto_pagado', 'DECIMAL(10,2) DEFAULT 0');
    ensureColumn($pdo, 'ventas', 'descuento', 'DECIMAL(10,2) DEFAULT 0');
    ensureColumn($pdo, 'ventas', 'impuesto', 'DECIMAL(10,2) DEFAULT 0');

    // Tabla cierres de caja
    $pdo->exec("CREATE TABLE IF NOT EXISTS cierres_caja (
        id INT AUTO_INCREMENT PRIMARY KEY,
        usuario_id INT,
        fecha DATE,
        total_ventas DECIMAL(10,2) DEFAULT 0,
        total_efectivo DECIMAL(10,2) DEFAULT 0,
        total_tarjeta DECIMAL(10,2) DEFAULT 0,
        total_transferencia DECIMAL(10,2) DEFAULT 0,
        total_otros DECIMAL(10,2) DEFAULT 0,
        total_descuentos DECIMAL(10,2) DEFAULT 0,
        total_impuestos DECIMAL(10,2) DEFAULT 0,
        total_propinas DECIMAL(10,2) DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (usuario_id) REFERENCES empleados(id)
    )");
    ensureColumn($pdo, 'cierres_caja', 'total_otros', 'DECIMAL(10,2) DEFAULT 0');

    // Tabla auditoría
    $pdo->exec("CREATE TABLE IF NOT EXISTS audit_log (
        id INT AUTO_INCREMENT PRIMARY KEY,
        usuario_id INT,
        accion VARCHAR(255),
        entidad VARCHAR(100),
        entidad_id INT,
        descripcion TEXT,
        fecha DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (usuario_id) REFERENCES empleados(id)
    )");

    // Tabla inventario básico
    $pdo->exec("CREATE TABLE IF NOT EXISTS inventario (
        id INT AUTO_INCREMENT PRIMARY KEY,
        item VARCHAR(255),
        cantidad INT DEFAULT 0,
        cantidad_minima INT DEFAULT 0,
        cantidad_maxima INT DEFAULT 0,
        precio_costo DECIMAL(10,2),
        proveedor VARCHAR(255),
        ultima_actualizacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )");

    // Tabla clientes
    $pdo->exec("CREATE TABLE IF NOT EXISTS clientes (
        id INT AUTO_INCREMENT PRIMARY KEY,
        nombre VARCHAR(255) NOT NULL,
        telefono VARCHAR(50),
        email VARCHAR(255),
        direccion TEXT,
        creado TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");

    // Tabla mermas
    $pdo->exec("CREATE TABLE IF NOT EXISTS mermas (
        id INT AUTO_INCREMENT PRIMARY KEY,
        item VARCHAR(255),
        cantidad INT,
        razon TEXT,
        fecha TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");

    // Tabla promociones
    $pdo->exec("CREATE TABLE IF NOT EXISTS promociones (
        id INT AUTO_INCREMENT PRIMARY KEY,
        nombre VARCHAR(255),
        descuento DECIMAL(5,2),
        desde DATETIME,
        hasta DATETIME
    )");

    // Tabla de logs de operaciones
    $pdo->exec("CREATE TABLE IF NOT EXISTS logs (
        id INT AUTO_INCREMENT PRIMARY KEY,
        action TEXT,
        user_id INT,
        timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");

    // Insertar datos iniciales
    $stmt = $pdo->prepare("INSERT IGNORE INTO empleados (nombre, usuario, password, rol) VALUES (?, ?, ?, ?)");
    $stmt->execute(['Administrador', 'admin', password_hash('pointofsale', PASSWORD_DEFAULT), 'gerente']);
    $stmt->execute(['Super Usuario', 'super', password_hash('VicSuper$1', PASSWORD_DEFAULT), 'gerente']);
    $stmt->execute(['Mesero 1', 'mesero1', password_hash('mesero123', PASSWORD_DEFAULT), 'mesero']);
    $stmt->execute(['Cocinero 1', 'cocinero1', password_hash('cocinero123', PASSWORD_DEFAULT), 'cocinero']);

    $stmt = $pdo->prepare("INSERT IGNORE INTO menu (nombre, precio, categoria) VALUES (?, ?, ?)");
    $stmt->execute(['Pizza', 10.00, 'Platos principales']);
    $stmt->execute(['Ensalada', 5.00, 'Entradas']);

    $message = 'Base de datos configurada correctamente.';
} catch (PDOException $e) {
    $message = 'Error: ' . $e->getMessage();
    $error = true;
}
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Setup - Restaurante POS</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background: #f4f4f4;
            color: #333;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            margin: 0;
        }

        .box {
            background: #fff;
            padding: 24px;
            border-radius: 8px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, .1);
            max-width: 420px;
            width: 100%;
            text-align: center;
        }

        .box h1 {
            margin-top: 0;
        }

        .success {
            color: #0a6;
        }

        .error {
            color: #c00;
        }

        .button {
            display: inline-block;
            margin-top: 18px;
            padding: 10px 18px;
            background: #007bff;
            color: #fff;
            text-decoration: none;
            border-radius: 5px;
        }
    </style>
</head>

<body>
    <div class="box">
        <h1>Setup</h1>
        <p class="<?php echo $error ? 'error' : 'success'; ?>"><?php echo htmlspecialchars($message); ?></p>
        <?php if (!$error): ?>
            <p>La base de datos está lista. Ahora puedes iniciar sesión.</p>
            <a class="button" href="index.php">Ir al login</a>
        <?php else: ?>
            <p>Verifica que MySQL esté activo y que el usuario/contraseña sean correctos.</p>
        <?php endif; ?>
    </div>
</body>

</html>
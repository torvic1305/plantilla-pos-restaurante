<?php
// Setup de base de datos MySQL
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$host = getenv('DB_HOST') ?: 'localhost';
$db = getenv('DB_NAME') ?: 'restaurante_pos';
$user = getenv('DB_USER') ?: 'root';
$pass = getenv('DB_PASS') ?: '';
if (!preg_match('/^[A-Za-z0-9_]{1,64}$/', $db)) {
    http_response_code(500);
    exit('El nombre de la base de datos configurado no es válido.');
}

$message = '';
$error = false;
$remoteAddress = $_SERVER['REMOTE_ADDR'] ?? '';
$isLocalRequest = in_array($remoteAddress, ['127.0.0.1', '::1'], true);
$requestMethod = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$setupCsrf = $_SESSION['csrf_token'] ?? '';
if (!is_string($setupCsrf) || $setupCsrf === '') {
    $setupCsrf = bin2hex(random_bytes(32));
    $_SESSION['csrf_token'] = $setupCsrf;
}

function renderSetupGate(string $title, string $message, bool $isError = true, string $action = ''): void
{
    global $setupCsrf;
    ?>
    <!DOCTYPE html>
    <html lang="es">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Setup - Restaurante POS</title>
        <style>
            body{font-family:Arial,sans-serif;background:#f4f4f4;color:#333;display:grid;place-items:center;min-height:100vh;margin:0}
            .box{background:#fff;padding:24px;border-radius:8px;box-shadow:0 10px 30px #0002;max-width:480px;width:calc(100% - 32px)}
            label{display:block;text-align:left;margin:12px 0}input{box-sizing:border-box;width:100%;padding:10px}
            button,a.button{display:inline-block;margin-top:12px;padding:10px 18px;background:#1768ad;color:#fff;border:0;border-radius:5px;text-decoration:none}
            .error{color:#c00}.success{color:#087a46}
        </style>
    </head>
    <body><main class="box">
        <h1><?= htmlspecialchars($title, ENT_QUOTES | ENT_HTML5, 'UTF-8') ?></h1>
        <p class="<?= $isError ? 'error' : 'success' ?>" role="<?= $isError ? 'alert' : 'status' ?>"><?= htmlspecialchars($message, ENT_QUOTES | ENT_HTML5, 'UTF-8') ?></p>
        <?php if ($action === 'bootstrap'): ?>
            <p>Crea la primera cuenta gerente. La instalación inicial solo se permite desde el propio servidor.</p>
            <form method="post">
                <input type="hidden" name="action" value="bootstrap_admin">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($setupCsrf, ENT_QUOTES | ENT_HTML5, 'UTF-8') ?>">
                <label>Nombre<input name="nombre" maxlength="100" required></label>
                <label>Usuario (3–100 caracteres)<input name="usuario" minlength="3" maxlength="100" required autocomplete="username"></label>
                <label>Contraseña (mínimo 12 caracteres)<input type="password" name="password" minlength="12" required autocomplete="new-password"></label>
                <label>Confirmar contraseña<input type="password" name="password_confirm" minlength="12" required autocomplete="new-password"></label>
                <button type="submit">Crear cuenta gerente</button>
            </form>
        <?php elseif ($action === 'upgrade'): ?>
            <p>La base de datos ya tiene usuarios. Inicia sesión como gerente y confirma para aplicar actualizaciones de esquema.</p>
            <form method="post">
                <input type="hidden" name="action" value="upgrade">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($setupCsrf, ENT_QUOTES | ENT_HTML5, 'UTF-8') ?>">
                <button type="submit">Actualizar base de datos</button>
            </form>
        <?php else: ?>
            <a class="button" href="index.php">Ir al inicio de sesión</a>
        <?php endif; ?>
    </main></body></html>
    <?php
    exit;
}

try {
    $preflight = new PDO("mysql:host=$host", $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
    $schemaCheck = $preflight->prepare('SELECT COUNT(*) FROM information_schema.SCHEMATA WHERE SCHEMA_NAME = ?');
    $schemaCheck->execute([$db]);
    $schemaExists = (int) $schemaCheck->fetchColumn() > 0;
    $hasEmployees = false;
    $employeeCount = 0;
    if ($schemaExists) {
        $preflight->exec("USE `$db`");
        $tableCheck = $preflight->prepare(
            'SELECT COUNT(*) FROM information_schema.TABLES
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?'
        );
        $tableCheck->execute(['empleados']);
        $hasEmployees = (int) $tableCheck->fetchColumn() > 0;
        if ($hasEmployees) {
            $employeeCount = (int) $preflight->query('SELECT COUNT(*) FROM empleados')->fetchColumn();
        }
    }

    $bootstrapMode = !$hasEmployees || $employeeCount === 0;
    if ($bootstrapMode && !$isLocalRequest) {
        http_response_code(403);
        renderSetupGate('Instalación local requerida', 'La instalación inicial solo se permite desde el propio servidor.');
    }
    if (!$bootstrapMode) {
        $currentUserId = filter_var($_SESSION['user']['id'] ?? null, FILTER_VALIDATE_INT);
        $managerQuery = $preflight->prepare('SELECT rol FROM empleados WHERE id = ?');
        $managerQuery->execute([$currentUserId]);
        if ($managerQuery->fetchColumn() !== 'gerente') {
            http_response_code(403);
            renderSetupGate('Acceso restringido', 'Para actualizar la base de datos debes iniciar sesión con una cuenta gerente.');
        }
        if ($requestMethod !== 'POST' || ($_POST['action'] ?? '') !== 'upgrade') {
            renderSetupGate('Actualizar base de datos', 'Confirma la actualización del esquema con tu sesión gerente.', false, 'upgrade');
        }
        $submittedToken = $_POST['csrf_token'] ?? '';
        if (!is_string($submittedToken) || !hash_equals($setupCsrf, $submittedToken)) {
            http_response_code(403);
            renderSetupGate('Solicitud inválida', 'El token de seguridad no es válido. Recarga la página e inténtalo de nuevo.');
        }
    } elseif ($requestMethod === 'POST' && ($_POST['action'] ?? '') === 'bootstrap_admin') {
        $submittedToken = $_POST['csrf_token'] ?? '';
        if (!is_string($submittedToken) || !hash_equals($setupCsrf, $submittedToken)) {
            http_response_code(403);
            renderSetupGate('Solicitud inválida', 'El token de seguridad no es válido. Recarga la página e inténtalo de nuevo.', true, 'bootstrap');
        }
        $adminName = trim((string) ($_POST['nombre'] ?? ''));
        $adminUsername = trim((string) ($_POST['usuario'] ?? ''));
        $adminPassword = (string) ($_POST['password'] ?? '');
        $adminPasswordConfirm = (string) ($_POST['password_confirm'] ?? '');
        if ($adminName === '' || mb_strlen($adminName) > 100 ||
            !preg_match('/^[\p{L}\p{N}_.-]{3,100}$/u', $adminUsername) ||
            strlen($adminPassword) < 12 || $adminPassword !== $adminPasswordConfirm) {
            renderSetupGate('Datos inválidos', 'Verifica el nombre, el formato de usuario y que las contraseñas coincidan (mínimo 12 caracteres).', true, 'bootstrap');
        }
    }
} catch (PDOException $e) {
    error_log('Setup preflight failed: ' . $e->getMessage());
    http_response_code(503);
    renderSetupGate('No se pudo verificar la instalación', 'Verifica que MySQL esté iniciado y que sus credenciales sean correctas.');
}

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
    ensureColumn($pdo, 'empleados', 'suspendido', 'TINYINT(1) NOT NULL DEFAULT 0');
    ensureColumn($pdo, 'empleados', 'motivo_suspension', 'VARCHAR(255) NULL');
    ensureColumn($pdo, 'empleados', 'eliminado_en', 'DATETIME NULL');

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
        estado_cocina VARCHAR(20) NOT NULL DEFAULT 'entregada',
        cocina_actualizada DATETIME NULL,
        fecha TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (mesero_id) REFERENCES empleados(id)
    )");
    ensureColumn($pdo, 'ventas', 'tipo_pago', 'VARCHAR(100) NULL');
    ensureColumn($pdo, 'ventas', 'propina', 'DECIMAL(10,2) DEFAULT 0');
    ensureColumn($pdo, 'ventas', 'monto_pagado', 'DECIMAL(10,2) DEFAULT 0');
    ensureColumn($pdo, 'ventas', 'descuento', 'DECIMAL(10,2) DEFAULT 0');
    ensureColumn($pdo, 'ventas', 'impuesto', 'DECIMAL(10,2) DEFAULT 0');
    ensureColumn($pdo, 'ventas', 'estado_cocina', "VARCHAR(20) NOT NULL DEFAULT 'entregada'");
    ensureColumn($pdo, 'ventas', 'cocina_actualizada', 'DATETIME NULL');
    $stmt = $pdo->prepare(
        'SELECT COUNT(*) FROM information_schema.STATISTICS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND INDEX_NAME = ?'
    );
    $stmt->execute(['ventas', 'idx_ventas_cocina_queue']);
    if ((int) $stmt->fetchColumn() === 0) {
        $pdo->exec('CREATE INDEX idx_ventas_cocina_queue ON ventas (suspendida, estado_cocina, fecha)');
    }

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

    $pdo->exec("CREATE TABLE IF NOT EXISTS gastos (
        id BIGINT AUTO_INCREMENT PRIMARY KEY,
        concepto VARCHAR(160) NOT NULL,
        categoria VARCHAR(80) NOT NULL,
        monto DECIMAL(12,2) NOT NULL,
        fecha DATE NOT NULL,
        metodo_pago VARCHAR(40) NOT NULL,
        observaciones VARCHAR(500) NULL,
        usuario_id INT NULL,
        creado TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        KEY idx_gastos_fecha (fecha, id),
        CONSTRAINT fk_gastos_usuario FOREIGN KEY (usuario_id) REFERENCES empleados(id) ON DELETE SET NULL
    ) ENGINE=InnoDB");

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
        codigo_barras VARCHAR(100) NULL,
        categoria VARCHAR(120) NOT NULL DEFAULT 'General',
        cantidad DECIMAL(12,3) DEFAULT 0,
        cantidad_minima DECIMAL(12,3) DEFAULT 0,
        cantidad_maxima DECIMAL(12,3) DEFAULT 0,
        precio_costo DECIMAL(10,2),
        precio_venta DECIMAL(10,2) DEFAULT 0,
        proveedor VARCHAR(255),
        unidad VARCHAR(20) NOT NULL DEFAULT 'pieza',
        ultima_actualizacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )");
    ensureColumn($pdo, 'inventario', 'codigo_barras', 'VARCHAR(100) NULL');
    ensureColumn($pdo, 'inventario', 'categoria', "VARCHAR(120) NOT NULL DEFAULT 'General'");
    ensureColumn($pdo, 'inventario', 'precio_venta', 'DECIMAL(10,2) DEFAULT 0');
    ensureColumn($pdo, 'inventario', 'unidad', "VARCHAR(20) NOT NULL DEFAULT 'pieza'");
    $pdo->exec("ALTER TABLE inventario
        MODIFY cantidad DECIMAL(12,3) DEFAULT 0,
        MODIFY cantidad_minima DECIMAL(12,3) DEFAULT 0,
        MODIFY cantidad_maxima DECIMAL(12,3) DEFAULT 0");

    $pdo->exec("CREATE TABLE IF NOT EXISTS menu_recetas (
        id INT AUTO_INCREMENT PRIMARY KEY,
        menu_id INT NOT NULL,
        inventario_id INT NOT NULL,
        cantidad DECIMAL(12,3) NOT NULL,
        UNIQUE KEY uq_menu_receta_ingrediente (menu_id, inventario_id),
        CONSTRAINT fk_menu_recetas_menu FOREIGN KEY (menu_id) REFERENCES menu(id) ON DELETE CASCADE,
        CONSTRAINT fk_menu_recetas_inventario FOREIGN KEY (inventario_id) REFERENCES inventario(id) ON DELETE RESTRICT
    )");
    $pdo->exec("CREATE TABLE IF NOT EXISTS movimientos_inventario (
        id BIGINT AUTO_INCREMENT PRIMARY KEY,
        inventario_id INT NOT NULL,
        tipo VARCHAR(30) NOT NULL,
        cantidad DECIMAL(12,3) NOT NULL,
        motivo VARCHAR(255) NOT NULL,
        venta_id INT NULL,
        usuario_id INT NULL,
        creado TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        KEY idx_movimientos_inventario_fecha (inventario_id, creado),
        CONSTRAINT fk_movimientos_inventario_item FOREIGN KEY (inventario_id) REFERENCES inventario(id) ON DELETE RESTRICT,
        CONSTRAINT fk_movimientos_inventario_venta FOREIGN KEY (venta_id) REFERENCES ventas(id) ON DELETE SET NULL,
        CONSTRAINT fk_movimientos_inventario_usuario FOREIGN KEY (usuario_id) REFERENCES empleados(id) ON DELETE SET NULL
    )");
    $pdo->exec("CREATE TABLE IF NOT EXISTS compras (
        id BIGINT AUTO_INCREMENT PRIMARY KEY,
        proveedor VARCHAR(255) NOT NULL,
        folio VARCHAR(100) NULL,
        fecha_compra DATE NOT NULL,
        total DECIMAL(12,2) NOT NULL DEFAULT 0,
        observaciones TEXT NULL,
        usuario_id INT NULL,
        creado TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        KEY idx_compras_fecha (fecha_compra, id),
        CONSTRAINT fk_compras_usuario FOREIGN KEY (usuario_id) REFERENCES empleados(id) ON DELETE SET NULL
    ) ENGINE=InnoDB");
    $pdo->exec("CREATE TABLE IF NOT EXISTS compra_detalles (
        id BIGINT AUTO_INCREMENT PRIMARY KEY,
        compra_id BIGINT NOT NULL,
        inventario_id INT NOT NULL,
        item_nombre VARCHAR(255) NOT NULL,
        cantidad DECIMAL(12,3) NOT NULL,
        costo_unitario DECIMAL(10,2) NOT NULL,
        subtotal DECIMAL(12,2) NOT NULL,
        KEY idx_compra_detalles_compra (compra_id),
        KEY idx_compra_detalles_inventario (inventario_id),
        CONSTRAINT fk_compra_detalles_compra FOREIGN KEY (compra_id) REFERENCES compras(id) ON DELETE RESTRICT,
        CONSTRAINT fk_compra_detalles_inventario FOREIGN KEY (inventario_id) REFERENCES inventario(id) ON DELETE RESTRICT
    ) ENGINE=InnoDB");

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
    $message = 'Base de datos configurada correctamente.';
} catch (Throwable $e) {
    error_log('Database setup failed: ' . $e->getMessage());
    $message = 'No se pudo configurar la base de datos. Revisa el registro del servidor y los permisos de MySQL.';
    $error = true;
}

if (!$error && $bootstrapMode && $requestMethod === 'POST' && ($_POST['action'] ?? '') === 'bootstrap_admin') {
    try {
        $lockName = 'restaurante_pos_bootstrap_admin';
        $lock = $pdo->prepare('SELECT GET_LOCK(?, 5)');
        $lock->execute([$lockName]);
        if ((int) $lock->fetchColumn() !== 1) {
            throw new RuntimeException('No se pudo bloquear la creación inicial de gerente.');
        }

        $pdo->beginTransaction();
        $existingEmployees = (int) $pdo->query('SELECT COUNT(*) FROM empleados')->fetchColumn();
        if ($existingEmployees !== 0) {
            throw new RuntimeException('Ya existe una cuenta; la instalación inicial está cerrada.');
        }
        $createManager = $pdo->prepare(
            'INSERT INTO empleados (nombre, usuario, password, rol) VALUES (?, ?, ?, ?)'
        );
        $createManager->execute([
            $adminName,
            $adminUsername,
            password_hash($adminPassword, PASSWORD_DEFAULT),
            'gerente',
        ]);
        $pdo->commit();
        $release = $pdo->prepare('SELECT RELEASE_LOCK(?)');
        $release->execute([$lockName]);
        header('Location: index.php');
        exit;
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        if (isset($lockName)) {
            $release = $pdo->prepare('SELECT RELEASE_LOCK(?)');
            $release->execute([$lockName]);
        }
        error_log('Initial manager creation failed: ' . $e->getMessage());
        $message = 'No se pudo crear el gerente. Verifica que el usuario no esté ocupado e inténtalo de nuevo.';
        $error = true;
    }
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
        <p class="<?php echo $error ? 'error' : 'success'; ?>"><?php echo htmlspecialchars($message, ENT_QUOTES | ENT_HTML5, 'UTF-8'); ?></p>
        <?php if ($bootstrapMode): ?>
            <p>Crea la cuenta gerente inicial con una contraseña propia. No se crean cuentas ni contraseñas predeterminadas.</p>
            <form method="post">
                <input type="hidden" name="action" value="bootstrap_admin">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($setupCsrf, ENT_QUOTES | ENT_HTML5, 'UTF-8') ?>">
                <label>Nombre
                    <input type="text" name="nombre" maxlength="100" required>
                </label>
                <label>Usuario (3–100 caracteres)
                    <input type="text" name="usuario" minlength="3" maxlength="100" required autocomplete="username">
                </label>
                <label>Contraseña (mínimo 12 caracteres)
                    <input type="password" name="password" minlength="12" required autocomplete="new-password">
                </label>
                <label>Confirmar contraseña
                    <input type="password" name="password_confirm" minlength="12" required autocomplete="new-password">
                </label>
                <button class="button" type="submit">Crear cuenta gerente</button>
            </form>
        <?php elseif (!$error): ?>
            <p>La base de datos está lista. Ahora puedes iniciar sesión.</p>
            <a class="button" href="index.php">Ir al login</a>
        <?php else: ?>
            <p>Verifica que MySQL esté activo y que el usuario/contraseña sean correctos.</p>
        <?php endif; ?>
    </div>
</body>

</html>
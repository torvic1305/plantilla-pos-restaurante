<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$dbError = null;

// Conexión a DB
$host = getenv('DB_HOST') ?: 'localhost';
$db = getenv('DB_NAME') ?: 'restaurante_pos';
$user = getenv('DB_USER') ?: 'root';
$pass = getenv('DB_PASS') ?: '';
$pdo = null;
try {
    $pdo = new PDO("mysql:host=$host", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->exec("CREATE DATABASE IF NOT EXISTS `$db` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    $pdo->exec("USE `$db`");
    try {
        $pdo->exec("ALTER TABLE ventas ADD COLUMN tipo_pago VARCHAR(100) NULL");
    } catch (PDOException $inner) {
        // La columna ya existe o no hay tabla aún
    }
    try {
        $pdo->exec("ALTER TABLE ventas ADD COLUMN monto_pagado DECIMAL(10,2) DEFAULT 0");
    } catch (PDOException $inner) {
        // La columna ya existe o no hay tabla aún
    }
    try {
        $pdo->exec("ALTER TABLE ventas ADD COLUMN descuento DECIMAL(10,2) DEFAULT 0");
    } catch (PDOException $inner) {
        // La columna ya existe o no hay tabla aún
    }
    try {
        $pdo->exec("ALTER TABLE ventas ADD COLUMN impuesto DECIMAL(10,2) DEFAULT 0");
    } catch (PDOException $inner) {
        // La columna ya existe o no hay tabla aún
    }
    // Crear tabla de auditoría si no existe
    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS audit_log (
            id INT AUTO_INCREMENT PRIMARY KEY,
            usuario_id INT,
            accion VARCHAR(255),
            entidad VARCHAR(100),
            entidad_id INT,
            descripcion TEXT,
            fecha DATETIME DEFAULT CURRENT_TIMESTAMP
        )");
    } catch (PDOException $inner) {
        // La tabla ya existe
    }
    // Tabla cierres de caja
    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS cierres_caja (
            id INT AUTO_INCREMENT PRIMARY KEY,
            usuario_id INT,
            fecha DATE,
            total_ventas DECIMAL(10,2) DEFAULT 0,
            total_efectivo DECIMAL(10,2) DEFAULT 0,
            total_tarjeta DECIMAL(10,2) DEFAULT 0,
            total_transferencia DECIMAL(10,2) DEFAULT 0,
            total_descuentos DECIMAL(10,2) DEFAULT 0,
            total_impuestos DECIMAL(10,2) DEFAULT 0,
            total_propinas DECIMAL(10,2) DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )");
    } catch (PDOException $inner) {
        // La tabla ya existe
    }
    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS clientes (
            id INT AUTO_INCREMENT PRIMARY KEY,
            nombre VARCHAR(255) NOT NULL,
            telefono VARCHAR(50),
            email VARCHAR(255),
            direccion TEXT,
            creado TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )");
    } catch (PDOException $inner) {
        // La tabla ya existe
    }
    try {
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
    } catch (PDOException $inner) {
        // La tabla ya existe
    }
} catch (PDOException $e) {
    $dbError = $e->getMessage();
    $pdo = null;
}

function getAppConfig()
{
    $default = [
        'color_fondo' => '#f58610db',
        'color_letra' => '#000',
        'tamano_letra' => '16px',
        'fuente' => 'Arial, sans-serif',
        'formas_pago' => ['Efectivo', 'Tarjeta', 'Transferencia'],
        'mesas' => 20
    ];
    if (file_exists('config.json')) {
        $saved = json_decode(file_get_contents('config.json'), true);
        if (is_array($saved)) {
            return array_merge($default, $saved);
        }
    }
    return $default;
}

function saveAppConfig($config)
{
    file_put_contents('config.json', json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
}

// Funciones para empleados
function getEmpleados()
{
    global $pdo;
    if (!$pdo)
        return [];
    try {
        $stmt = $pdo->query("SELECT * FROM empleados");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        return [];
    }
}

function addEmpleado($nombre, $usuario, $password, $rol)
{
    global $pdo;
    $hashed = password_hash($password, PASSWORD_DEFAULT);

    // Permisos por defecto según rol
    $permisos_default = [];
    switch ($rol) {
        case 'gerente':
            $permisos_default = [
                'pos' => true,
                'menu_ver' => true,
                'menu_editar' => true,
                'empleados' => true,
                'informes' => true,
                'mermas' => true,
                'promociones' => true,
                'config' => true,
                'cocina' => true,
                'eliminar_suspendidas' => true
            ];
            break;
        case 'cocinero':
            $permisos_default = [
                'pos' => false,
                'menu_ver' => true,
                'menu_editar' => false,
                'empleados' => false,
                'informes' => false,
                'mermas' => true,
                'promociones' => false,
                'config' => false,
                'cocina' => true,
                'eliminar_suspendidas' => false
            ];
            break;
        case 'mesero':
            $permisos_default = [
                'pos' => true,
                'menu_ver' => true,
                'menu_editar' => false,
                'empleados' => false,
                'informes' => false,
                'mermas' => false,
                'promociones' => false,
                'config' => false,
                'cocina' => false,
                'eliminar_suspendidas' => false
            ];
            break;
    }

    // Configuraciones por defecto
    $config_default = [
        'idioma' => 'es',
        'tema' => 'claro',
        'notificaciones' => true,
        'horario' => '08:00-18:00',
        'salario' => 0.00
    ];

    $permisos_json = json_encode($permisos_default);
    $config_json = json_encode($config_default);

    $stmt = $pdo->prepare("INSERT INTO empleados (nombre, usuario, password, rol, permisos, configuraciones) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->execute([$nombre, $usuario, $hashed, $rol, $permisos_json, $config_json]);
}

function getPermisos($user_id)
{
    global $pdo;
    $stmt = $pdo->prepare("SELECT permisos FROM empleados WHERE id = ?");
    $stmt->execute([$user_id]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    return $result ? json_decode($result['permisos'], true) : [];
}

function setPermisos($user_id, $permisos)
{
    global $pdo;
    $permisos_json = json_encode($permisos);
    $stmt = $pdo->prepare("UPDATE empleados SET permisos = ? WHERE id = ?");
    $stmt->execute([$permisos_json, $user_id]);
}

function getConfiguraciones($user_id)
{
    global $pdo;
    $stmt = $pdo->prepare("SELECT configuraciones FROM empleados WHERE id = ?");
    $stmt->execute([$user_id]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    return $result ? json_decode($result['configuraciones'], true) : [];
}

function setConfiguraciones($user_id, $configuraciones)
{
    global $pdo;
    $config_json = json_encode($configuraciones);
    $stmt = $pdo->prepare("UPDATE empleados SET configuraciones = ? WHERE id = ?");
    $stmt->execute([$config_json, $user_id]);
}

function verificarLogin($usuario, $password)
{
    global $pdo;
    if (!$pdo)
        return false;
    try {
        $stmt = $pdo->prepare("SELECT * FROM empleados WHERE usuario = ?");
        $stmt->execute([$usuario]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($user && password_verify($password, $user['password'])) {
            return $user;
        }
        return false;
    } catch (PDOException $e) {
        return false;
    }
}

// Funciones para menú
function getMenu()
{
    global $pdo;
    $stmt = $pdo->query("SELECT * FROM menu");
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function addMenuItem($nombre, $precio, $categoria)
{
    global $pdo;

    $stmt = $pdo->prepare("INSERT INTO menu (nombre, precio, categoria) VALUES (?, ?, ?)");
    $stmt->execute([$nombre, $precio, $categoria]);
}

// Funciones para inventario
function getInventario()
{
    global $pdo;
    $stmt = $pdo->query("SELECT * FROM inventario ORDER BY item ASC");
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getInventarioBajoStock()
{
    global $pdo;
    $stmt = $pdo->query("SELECT * FROM inventario WHERE cantidad <= cantidad_minima ORDER BY cantidad ASC");
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getInventarioItemById($id)
{
    global $pdo;
    $stmt = $pdo->prepare("SELECT * FROM inventario WHERE id = ?");
    $stmt->execute([$id]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

function addInventarioItem($item, $cantidad, $cantidad_minima, $cantidad_maxima, $precio_costo, $proveedor)
{
    global $pdo;
    $stmt = $pdo->prepare("INSERT INTO inventario (item, cantidad, cantidad_minima, cantidad_maxima, precio_costo, proveedor) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->execute([$item, $cantidad, $cantidad_minima, $cantidad_maxima, $precio_costo, $proveedor]);
    registrarAuditoria($_SESSION['user']['id'] ?? null, 'CREATE', 'inventario', $pdo->lastInsertId(), "Item de inventario agregado: $item");
}

function updateInventarioItem($id, $item, $cantidad, $cantidad_minima, $cantidad_maxima, $precio_costo, $proveedor)
{
    global $pdo;
    $stmt = $pdo->prepare("UPDATE inventario SET item = ?, cantidad = ?, cantidad_minima = ?, cantidad_maxima = ?, precio_costo = ?, proveedor = ? WHERE id = ?");
    $stmt->execute([$item, $cantidad, $cantidad_minima, $cantidad_maxima, $precio_costo, $proveedor, $id]);
    registrarAuditoria($_SESSION['user']['id'] ?? null, 'UPDATE', 'inventario', $id, "Item de inventario actualizado: $item");
}

function deleteInventarioItem($id)
{
    global $pdo;
    $stmt = $pdo->prepare("DELETE FROM inventario WHERE id = ?");
    $stmt->execute([$id]);
    registrarAuditoria($_SESSION['user']['id'] ?? null, 'DELETE', 'inventario', $id, "Item de inventario eliminado");
}

// Funciones para clientes
function getClientes()
{
    global $pdo;
    $stmt = $pdo->query("SELECT * FROM clientes ORDER BY nombre ASC");
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getClienteById($id)
{
    global $pdo;
    $stmt = $pdo->prepare("SELECT * FROM clientes WHERE id = ?");
    $stmt->execute([$id]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

function addCliente($nombre, $telefono, $email, $direccion)
{
    global $pdo;
    $stmt = $pdo->prepare("INSERT INTO clientes (nombre, telefono, email, direccion) VALUES (?, ?, ?, ?)");
    $stmt->execute([$nombre, $telefono, $email, $direccion]);
    registrarAuditoria($_SESSION['user']['id'] ?? null, 'CREATE', 'cliente', $pdo->lastInsertId(), "Cliente agregado: $nombre");
}

function updateCliente($id, $nombre, $telefono, $email, $direccion)
{
    global $pdo;
    $stmt = $pdo->prepare("UPDATE clientes SET nombre = ?, telefono = ?, email = ?, direccion = ? WHERE id = ?");
    $stmt->execute([$nombre, $telefono, $email, $direccion, $id]);
    registrarAuditoria($_SESSION['user']['id'] ?? null, 'UPDATE', 'cliente', $id, "Cliente actualizado: $nombre");
}

function deleteCliente($id)
{
    global $pdo;
    $stmt = $pdo->prepare("DELETE FROM clientes WHERE id = ?");
    $stmt->execute([$id]);
    registrarAuditoria($_SESSION['user']['id'] ?? null, 'DELETE', 'cliente', $id, "Cliente eliminado");
}

function getVentas()
{
    global $pdo;
    $stmt = $pdo->query("SELECT * FROM ventas");
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getPedidosParaCocina()
{
    global $pdo;
    $stmt = $pdo->prepare("SELECT v.*, e.nombre AS mesero_nombre FROM ventas v LEFT JOIN empleados e ON v.mesero_id = e.id WHERE v.suspendida = 0 ORDER BY v.fecha DESC");
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function addVenta($mesero_id, $mesa, $orden, $suspendida = false, $tipo_pago = null, $propina = 0, $monto_pagado = 0, $descuento = 0, $impuesto = 0)
{
    global $pdo;
    $orden_json = json_encode($orden);
    $stmt = $pdo->prepare("INSERT INTO ventas (mesero_id, mesa, orden, suspendida, tipo_pago, propina, monto_pagado, descuento, impuesto) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute([$mesero_id, $mesa, $orden_json, $suspendida, $tipo_pago, $propina, $monto_pagado, $descuento, $impuesto]);
    $ventaId = $pdo->lastInsertId();
    // Auditoría
    registrarAuditoria($_SESSION['user']['id'] ?? null, 'CREATE', 'venta', $ventaId, "Venta " . ($suspendida ? "suspendida" : "completada"));
    return $ventaId;
}

function getMeseros()
{
    global $pdo;
    $stmt = $pdo->prepare("SELECT * FROM empleados WHERE rol = 'mesero'");
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getVentasSuspendidas($search = '')
{
    global $pdo;
    // Validar y sanitizar búsqueda
    $search = trim(strval($search));
    $search = strip_tags($search);
    if (strlen($search) > 50) {
        $search = substr($search, 0, 50);
    }

    $sql = "SELECT v.*, e.nombre AS mesero_nombre FROM ventas v LEFT JOIN empleados e ON v.mesero_id = e.id WHERE v.suspendida = 1";
    $params = [];
    if ($search !== '') {
        $sql .= " AND (v.id LIKE ? OR v.mesa LIKE ? OR e.nombre LIKE ? OR e.usuario LIKE ? )";
        $search_wild = "%$search%";
        $params = [$search_wild, $search_wild, $search_wild, $search_wild];
    }
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("DB Error in getVentasSuspendidas: " . $e->getMessage());
        return [];
    }
}

function getVentaById($id)
{
    global $pdo;
    $stmt = $pdo->prepare("SELECT * FROM ventas WHERE id = ?");
    $stmt->execute([$id]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

function updateVentaOrden($id, $orden)
{
    global $pdo;
    $orden_json = json_encode($orden);
    $stmt = $pdo->prepare("UPDATE ventas SET orden = ? WHERE id = ?");
    $stmt->execute([$orden_json, $id]);
}

function completarVenta($id, $tipo_pago, $propina = 0, $monto_pagado = 0, $descuento = 0)
{
    global $pdo;
    $propina = max(0, floatval($propina));
    $monto_pagado = max(0, floatval($monto_pagado));
    $descuento = max(0, floatval($descuento));
    $stmt = $pdo->prepare("UPDATE ventas SET suspendida = 0, tipo_pago = ?, propina = ?, monto_pagado = ?, descuento = ? WHERE id = ?");
    $stmt->execute([$tipo_pago, $propina, $monto_pagado, $descuento, $id]);
}

function updateVentaPropina($id, $propina)
{
    global $pdo;
    $propina = max(0, floatval($propina));
    $stmt = $pdo->prepare("UPDATE ventas SET propina = ? WHERE id = ?");
    $stmt->execute([$propina, $id]);
}

function getPropinasDelDia()
{
    global $pdo;
    $hoy = date('Y-m-d');
    $stmt = $pdo->prepare("SELECT v.*, e.nombre AS mesero_nombre FROM ventas v LEFT JOIN empleados e ON v.mesero_id = e.id WHERE DATE(v.fecha) = ? AND v.suspendida = 0 AND v.propina > 0 ORDER BY v.fecha DESC");
    $stmt->execute([$hoy]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getTotalPropinasPorMesero($desde = null, $hasta = null)
{
    global $pdo;
    $desde = $desde ?? date('Y-m-d');
    $hasta = $hasta ?? date('Y-m-d');
    $desde_dt = $desde . ' 00:00:00';
    $hasta_dt = $hasta . ' 23:59:59';
    $stmt = $pdo->prepare("SELECT e.id, e.nombre, SUM(v.propina) as total_propinas, COUNT(*) as ventas_con_propina FROM ventas v LEFT JOIN empleados e ON v.mesero_id = e.id WHERE v.fecha BETWEEN ? AND ? AND v.suspendida = 0 AND v.propina > 0 GROUP BY e.id, e.nombre ORDER BY total_propinas DESC");
    $stmt->execute([$desde_dt, $hasta_dt]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function deleteVentaSuspendida($id, $user_rol)
{
    global $pdo;
    if ($user_rol != 'gerente')
        return false;
    $stmt = $pdo->prepare("SELECT suspendida FROM ventas WHERE id = ?");
    $stmt->execute([$id]);
    $venta = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($venta && $venta['suspendida']) {
        $stmt = $pdo->prepare("DELETE FROM ventas WHERE id = ?");
        $stmt->execute([$id]);
        return true;
    }
    return false;
}

// Funciones para mermas
function getMermas()
{
    global $pdo;
    $stmt = $pdo->query("SELECT * FROM mermas");
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function addMerma($item, $cantidad, $razon)
{
    global $pdo;
    $stmt = $pdo->prepare("INSERT INTO mermas (item, cantidad, razon) VALUES (?, ?, ?)");
    $stmt->execute([$item, $cantidad, $razon]);
}

// Funciones para promociones
function getPromociones()
{
    global $pdo;
    if (!$pdo)
        return [];
    $stmt = $pdo->query("SELECT * FROM promociones");
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getPromocionesActivas()
{
    global $pdo;
    if (!$pdo)
        return [];
    $ahora = date('Y-m-d H:i:s');
    $stmt = $pdo->prepare("SELECT * FROM promociones WHERE desde <= ? AND hasta >= ?");
    $stmt->execute([$ahora, $ahora]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function addPromocion($nombre, $descuento, $desde, $hasta)
{
    global $pdo;
    $stmt = $pdo->prepare("INSERT INTO promociones (nombre, descuento, desde, hasta) VALUES (?, ?, ?, ?)");
    $stmt->execute([$nombre, $descuento, $desde, $hasta]);
}

// Auditoría
function registrarAuditoria($usuario_id, $accion, $entidad, $entidad_id, $descripcion = '')
{
    global $pdo;
    if (!$pdo)
        return false;
    try {
        // Validar entrada
        $accion = strtoupper(trim($accion));
        $entidad = strtoupper(trim($entidad));
        $stmt = $pdo->prepare("INSERT INTO audit_log (usuario_id, accion, entidad, entidad_id, descripcion) VALUES (?, ?, ?, ?, ?)");
        return $stmt->execute([$usuario_id, $accion, $entidad, $entidad_id, $descripcion]);
    } catch (PDOException $e) {
        error_log("Audit log failed: " . $e->getMessage());
        return false;
    }
}

// Logs
function logAction($action, $user_id)
{
    global $pdo;
    if (!$pdo)
        return false;
    try {
        $stmt = $pdo->prepare("INSERT INTO logs (action, user_id, timestamp) VALUES (?, ?, NOW())");
        return $stmt->execute([$action, $user_id]);
    } catch (PDOException $e) {
        error_log("Log failed for action: $action - " . $e->getMessage());
        return false;
    }
}

// Crear tabla logs si no existe
$pdo->exec("CREATE TABLE IF NOT EXISTS logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    action TEXT,
    user_id INT,
    fecha TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");
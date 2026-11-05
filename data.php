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
    $pdo = new PDO(
        "mysql:host=$host;dbname=$db;charset=utf8mb4",
        $user,
        $pass,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]
    );
    $pdo->query('SELECT 1 FROM empleados LIMIT 0');
} catch (PDOException $e) {
    $dbError = $e->getMessage();
    $pdo = null;
}

function getAppConfig()
{
    $default = [
        'nombre_restaurante' => 'Restaurante POS',
        'color_fondo' => '#edf0f4',
        'color_letra' => '#303642',
        'fuente' => 'Arial, sans-serif',
        'formas_pago' => ['Efectivo', 'Tarjeta', 'Transferencia'],
        'metodo_pago_default' => 'Efectivo',
        'mesas' => 20,
        'impuesto' => 0,
        'cash_alert_threshold' => 0,
        'pos_focus_search' => true,
        'pos_confirmar_venta' => true,
        'pos_require_table' => true,
        'recibo_titulo' => 'Restaurante POS',
        'recibo_pie' => 'Gracias por su compra',
        'recibo_mostrar_fecha' => true,
        'recibo_mostrar_precios' => true,
        'recibo_mostrar_impuestos' => true,
        'recibo_mostrar_descuentos' => true,
        'impresora_recibo_ip' => '',
        'impresora_recibo_puerto' => 9100,
        'impresora_cocina_ip' => '',
        'impresora_cocina_puerto' => 9100,
        'impresora_barra_ip' => '',
        'impresora_barra_puerto' => 9100,
        'impresora_categorias_barra' => [],
        'color_boton' => '#368fdb',
        'color_boton_hover' => '#1768ad',
    ];
    $configPath = __DIR__ . '/config.json';
    if (is_file($configPath)) {
        $saved = json_decode(file_get_contents($configPath), true);
        if (is_array($saved)) {
            $config = array_merge($default, $saved);
            if (!is_array($config['formas_pago'])) {
                $config['formas_pago'] = $default['formas_pago'];
            }
            $config['formas_pago'] = array_values(array_unique(array_filter(
                $config['formas_pago'],
                static fn($payment) => is_string($payment) && trim($payment) !== ''
            )));
            if ($config['formas_pago'] === []) {
                $config['formas_pago'] = $default['formas_pago'];
            }
            if (!in_array($config['metodo_pago_default'], $config['formas_pago'], true)) {
                $config['metodo_pago_default'] = $config['formas_pago'][0];
            }
            foreach (['recibo', 'cocina', 'barra'] as $station) {
                $ipKey = 'impresora_' . $station . '_ip';
                $portKey = 'impresora_' . $station . '_puerto';
                if (!is_string($config[$ipKey])) {
                    $config[$ipKey] = '';
                }
                $port = filter_var($config[$portKey], FILTER_VALIDATE_INT);
                $config[$portKey] = $port !== false && $port >= 1 && $port <= 65535 ? $port : 9100;
            }
            if (!is_array($config['impresora_categorias_barra'])) {
                $config['impresora_categorias_barra'] = [];
            } else {
                $config['impresora_categorias_barra'] = array_values(array_filter(
                    $config['impresora_categorias_barra'],
                    static fn($category) => is_string($category) && trim($category) !== ''
                ));
            }
            return $config;
        }
    }
    return $default;
}

function validatePrinterIPv4($ip)
{
    if (!is_string($ip) || filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) === false) {
        return false;
    }

    $octets = array_map('intval', explode('.', $ip));
    return $octets[0] === 10
        || ($octets[0] === 172 && $octets[1] >= 16 && $octets[1] <= 31)
        || ($octets[0] === 192 && $octets[1] === 168);
}

function saveAppConfig($config)
{
    $path = __DIR__ . '/config.json';
    $temporaryPath = $path . '.tmp';
    $json = json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    if ($json === false || file_put_contents($temporaryPath, $json, LOCK_EX) === false) {
        throw new RuntimeException('No se pudo guardar la configuración. Verifique los permisos de escritura.');
    }

    if (!rename($temporaryPath, $path)) {
        unlink($temporaryPath);
        throw new RuntimeException('No se pudo aplicar la configuración guardada.');
    }
}

function getTotalVentaCobrado($venta)
{
    $montoPagado = max(0, (float) ($venta['monto_pagado'] ?? 0));
    if (($venta['tipo_pago'] ?? null) !== 'Efectivo') {
        return $montoPagado;
    }

    $orden = json_decode($venta['orden'] ?? '', true);
    if (!is_array($orden) || $orden === []) {
        return $montoPagado;
    }

    $subtotal = 0;
    foreach ($orden as $item) {
        if (!is_array($item) || !is_numeric($item['precio'] ?? null)) {
            return $montoPagado;
        }
        $subtotal += max(0, (float) $item['precio']) * max(1, (int) ($item['cantidad'] ?? 1));
    }

    return round(max(
        0,
        $subtotal - max(0, (float) ($venta['descuento'] ?? 0)) + max(0, (float) ($venta['impuesto'] ?? 0))
    ), 2);
}

function getDefaultPermissions($role)
{
    $permissions = [
        'pos' => false,
        'menu_ver' => false,
        'menu_editar' => false,
        'clientes_ver' => false,
        'clientes_editar' => false,
        'inventario_ver' => false,
        'inventario_editar' => false,
        'empleados' => false,
        'informes' => false,
        'mermas' => false,
        'promociones' => false,
        'config' => false,
        'cocina' => false,
        'cierre_caja' => false,
        'propinas' => false,
        'ventas_suspendidas_ver' => false,
        'eliminar_suspendidas' => false,
    ];

    if ($role === 'gerente') {
        return array_fill_keys(array_keys($permissions), true);
    }

    if ($role === 'mesero') {
        $permissions['pos'] = true;
        $permissions['menu_ver'] = true;
        $permissions['clientes_ver'] = true;
        $permissions['ventas_suspendidas_ver'] = true;
    } elseif ($role === 'cocinero') {
        $permissions['menu_ver'] = true;
        $permissions['cocina'] = true;
        $permissions['mermas'] = true;
    }

    return $permissions;
}

function getPermissionDefinitions()
{
    return [
        'Ventas' => [
            'pos' => 'Acceder al punto de venta y cobrar',
            'ventas_suspendidas_ver' => 'Consultar y editar ventas suspendidas',
            'cierre_caja' => 'Consultar y registrar cierres de caja',
            'propinas' => 'Consultar y actualizar propinas',
        ],
        'Catálogo e inventario' => [
            'menu_ver' => 'Consultar el menú',
            'menu_editar' => 'Crear, modificar y eliminar productos del menú',
            'inventario_ver' => 'Consultar existencias',
            'inventario_editar' => 'Registrar y modificar existencias',
            'mermas' => 'Registrar y consultar mermas',
        ],
        'Administración' => [
            'clientes_ver' => 'Consultar clientes',
            'clientes_editar' => 'Crear, modificar y eliminar clientes',
            'informes' => 'Consultar informes y reportes',
            'promociones' => 'Administrar promociones',
            'cocina' => 'Acceder a la pantalla de cocina',
            'empleados' => 'Administrar empleados y sus permisos',
            'config' => 'Cambiar la configuración general del POS',
            'eliminar_suspendidas' => 'Eliminar todas las ventas suspendidas',
        ],
    ];
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
    $permisos_default = getDefaultPermissions($rol);
    $config_default = [];
    $permisos_json = json_encode($permisos_default);
    $config_json = json_encode($config_default);

    $stmt = $pdo->prepare("INSERT INTO empleados (nombre, usuario, password, rol, permisos, configuraciones) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->execute([$nombre, $usuario, $hashed, $rol, $permisos_json, $config_json]);
}

function getPermisos($user_id)
{
    global $pdo;
    $stmt = $pdo->prepare("SELECT permisos, rol FROM empleados WHERE id = ?");
    $stmt->execute([$user_id]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$result) {
        return [];
    }

    $savedPermissions = json_decode($result['permisos'] ?? '', true);
    return array_merge(
        getDefaultPermissions($result['rol']),
        is_array($savedPermissions) ? array_intersect_key($savedPermissions, getDefaultPermissions($result['rol'])) : []
    );
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
            $savedPermissions = json_decode($user['permisos'] ?? '', true);
            $user['permisos'] = array_merge(
                getDefaultPermissions($user['rol']),
                is_array($savedPermissions) ? array_intersect_key($savedPermissions, getDefaultPermissions($user['rol'])) : []
            );
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

function normalizeMenuOrderItems($items)
{
    global $pdo;

    if (!is_array($items) || $items === []) {
        return [];
    }

    $normalizedItems = [];
    $menuIds = [];
    foreach ($items as $item) {
        if (!is_array($item)) {
            throw new InvalidArgumentException('Estructura de orden inválida.');
        }

        $id = filter_var($item['id'] ?? null, FILTER_VALIDATE_INT);
        $price = validateDecimal($item['precio'] ?? null, 0, 999999);
        $quantity = validateInteger($item['cantidad'] ?? 1, 1, 10000);
        if ($id === false || $id < 1 || $price === null || $quantity === null) {
            throw new InvalidArgumentException('Producto, precio o cantidad inválida.');
        }

        $menuIds[$id] = $id;
        $normalizedItems[] = [
            'id' => $id,
            'precio_enviado' => $price,
            'cantidad' => $quantity,
            'nota' => isset($item['nota']) && is_string($item['nota'])
                ? sanitizeString($item['nota'], 500)
                : '',
        ];
    }

    $placeholders = implode(',', array_fill(0, count($menuIds), '?'));
    $stmt = $pdo->prepare("SELECT id, nombre, precio, categoria FROM menu WHERE id IN ($placeholders)");
    $stmt->execute(array_values($menuIds));
    $menuById = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $menuItem) {
        $menuById[(int) $menuItem['id']] = $menuItem;
    }

    $order = [];
    foreach ($normalizedItems as $item) {
        $menuItem = $menuById[$item['id']] ?? null;
        if (!$menuItem) {
            throw new InvalidArgumentException('Uno o más productos ya no están disponibles. Actualice el POS.');
        }

        $currentPrice = round((float) $menuItem['precio'], 2);
        if (abs($item['precio_enviado'] - $currentPrice) > 0.001) {
            throw new InvalidArgumentException(
                'El precio de ' . $menuItem['nombre'] . ' cambió. Actualice el POS antes de continuar.'
            );
        }

        $orderItem = [
            'id' => $item['id'],
            'nombre' => $menuItem['nombre'],
            'precio' => $currentPrice,
            'categoria' => (string) ($menuItem['categoria'] ?? ''),
            'cantidad' => $item['cantidad'],
        ];
        if ($item['nota'] !== '') {
            $orderItem['nota'] = $item['nota'];
        }
        $order[] = $orderItem;
    }

    return $order;
}

function addMenuItem($nombre, $precio, $categoria)
{
    global $pdo;

    $stmt = $pdo->prepare("INSERT INTO menu (nombre, precio, categoria) VALUES (?, ?, ?)");
    $stmt->execute([$nombre, $precio, $categoria]);
}

// Funciones para inventario
function getInventario($search = '', $field = 'all', $category = '')
{
    global $pdo;
    $sql = 'SELECT * FROM inventario WHERE 1=1';
    $params = [];
    $fields = [
        'item' => 'item',
        'codigo_barras' => 'codigo_barras',
        'categoria' => 'categoria',
        'proveedor' => 'proveedor',
    ];
    if ($search !== '') {
        if ($field !== 'all' && isset($fields[$field])) {
            $sql .= ' AND ' . $fields[$field] . ' LIKE ?';
            $params[] = '%' . $search . '%';
        } else {
            $sql .= ' AND (item LIKE ? OR codigo_barras LIKE ? OR categoria LIKE ? OR proveedor LIKE ?)';
            $searchTerm = '%' . $search . '%';
            array_push($params, $searchTerm, $searchTerm, $searchTerm, $searchTerm);
        }
    }
    if ($category !== '') {
        $sql .= ' AND categoria = ?';
        $params[] = $category;
    }
    $sql .= ' ORDER BY item ASC';
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getCategoriasInventario()
{
    global $pdo;
    $stmt = $pdo->query("SELECT DISTINCT categoria FROM inventario WHERE categoria IS NOT NULL AND TRIM(categoria) <> '' ORDER BY categoria ASC");
    return $stmt->fetchAll(PDO::FETCH_COLUMN);
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

function addInventarioItem($item, $cantidad, $cantidad_minima, $cantidad_maxima, $precio_costo, $proveedor, $codigo_barras = '', $categoria = 'General', $precio_venta = 0)
{
    global $pdo;
    $stmt = $pdo->prepare("INSERT INTO inventario (item, codigo_barras, categoria, cantidad, cantidad_minima, cantidad_maxima, precio_costo, precio_venta, proveedor) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute([$item, $codigo_barras ?: null, $categoria, $cantidad, $cantidad_minima, $cantidad_maxima, $precio_costo, $precio_venta, $proveedor]);
    registrarAuditoria($_SESSION['user']['id'] ?? null, 'CREATE', 'inventario', $pdo->lastInsertId(), "Item de inventario agregado: $item");
}

function updateInventarioItem($id, $item, $cantidad, $cantidad_minima, $cantidad_maxima, $precio_costo, $proveedor, $codigo_barras = '', $categoria = 'General', $precio_venta = 0)
{
    global $pdo;
    $stmt = $pdo->prepare("UPDATE inventario SET item = ?, codigo_barras = ?, categoria = ?, cantidad = ?, cantidad_minima = ?, cantidad_maxima = ?, precio_costo = ?, precio_venta = ?, proveedor = ? WHERE id = ?");
    $stmt->execute([$item, $codigo_barras ?: null, $categoria, $cantidad, $cantidad_minima, $cantidad_maxima, $precio_costo, $precio_venta, $proveedor, $id]);
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
    $stmt = $pdo->prepare("SELECT v.*, e.nombre AS mesero_nombre FROM ventas v LEFT JOIN empleados e ON v.mesero_id = e.id WHERE v.suspendida = 0 AND v.estado_cocina IN ('pendiente', 'preparando', 'lista') ORDER BY v.fecha ASC");
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function addVenta($mesero_id, $mesa, $orden, $suspendida = false, $tipo_pago = null, $propina = 0, $monto_pagado = 0, $descuento = 0, $impuesto = 0)
{
    global $pdo;
    $orden_json = json_encode($orden);
    $estadoCocina = $suspendida ? 'no_enviada' : 'pendiente';
    $stmt = $pdo->prepare("INSERT INTO ventas (mesero_id, mesa, orden, suspendida, tipo_pago, propina, monto_pagado, descuento, impuesto, estado_cocina, cocina_actualizada) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");
    $stmt->execute([$mesero_id, $mesa, $orden_json, $suspendida, $tipo_pago, $propina, $monto_pagado, $descuento, $impuesto, $estadoCocina]);
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

function completarVenta($id, $tipo_pago, $propina = 0, $monto_pagado = 0, $descuento = 0, $impuesto = 0)
{
    global $pdo;
    $propina = max(0, floatval($propina));
    $monto_pagado = max(0, floatval($monto_pagado));
    $descuento = max(0, floatval($descuento));
    $impuesto = max(0, floatval($impuesto));
    $stmt = $pdo->prepare("UPDATE ventas SET suspendida = 0, tipo_pago = ?, propina = ?, monto_pagado = ?, descuento = ?, impuesto = ?, estado_cocina = 'pendiente', cocina_actualizada = NOW() WHERE id = ?");
    $stmt->execute([$tipo_pago, $propina, $monto_pagado, $descuento, $impuesto, $id]);
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

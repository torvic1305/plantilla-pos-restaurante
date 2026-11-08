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
    $employeeColumns = $pdo->query(
        "SELECT COLUMN_NAME FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'empleados'
         AND COLUMN_NAME IN ('suspendido', 'motivo_suspension', 'eliminado_en')"
    )->fetchAll(PDO::FETCH_COLUMN);
    $employeeColumnDefinitions = [
        'suspendido' => 'TINYINT(1) NOT NULL DEFAULT 0',
        'motivo_suspension' => 'VARCHAR(255) NULL',
        'eliminado_en' => 'DATETIME NULL',
    ];
    foreach ($employeeColumnDefinitions as $column => $definition) {
        if (!in_array($column, $employeeColumns, true)) {
            $pdo->exec("ALTER TABLE empleados ADD COLUMN `$column` $definition");
        }
    }
    $appTables = $pdo->query(
        "SELECT TABLE_NAME FROM information_schema.TABLES
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME IN ('inventario', 'compras', 'compra_detalles', 'gastos')"
    )->fetchAll(PDO::FETCH_COLUMN);
    if (!in_array('gastos', $appTables, true)) {
        $pdo->exec("CREATE TABLE gastos (
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
    }
    if (in_array('inventario', $appTables, true)) {
        if (!in_array('compras', $appTables, true)) {
            $pdo->exec("CREATE TABLE compras (
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
        }
        if (!in_array('compra_detalles', $appTables, true)) {
            $pdo->exec("CREATE TABLE compra_detalles (
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
        }
    }
} catch (PDOException $e) {
    $dbError = $e->getMessage();
    $pdo = null;
}

function getAppConfig()
{
    $default = [
        'nombre_restaurante' => 'Restaurante POS',
        'color_fondo' => '#f4f1e9',
        'color_letra' => '#211f1a',
        'fuente' => 'Arial, sans-serif',
        'formas_pago' => ['Efectivo', 'Tarjeta', 'Transferencia'],
        'metodo_pago_default' => 'Efectivo',
        'mesas' => 20,
        'impuesto' => 0,
        'cash_alert_threshold' => 0,
        'pos_focus_search' => true,
        'pos_confirmar_venta' => true,
        'pos_require_table' => true,
        'color_boton' => '#171717',
        'color_boton_hover' => '#7a5e16',
        'recibo_titulo' => 'Restaurante POS',
        'recibo_pie' => 'Gracias por su compra',
        'recibo_ancho_papel' => '80',
        'recibo_alto_papel' => 200,
        'recibo_margen_superior' => 2,
        'recibo_margen_inferior' => 4,
        'recibo_tamano_fuente' => 'normal',
        'comanda_ancho_papel' => '80',
        'comanda_tamano_fuente' => 'normal',
        'comanda_margen_superior' => 2,
        'comanda_margen_inferior' => 4,
        'recibo_mostrar_fecha' => true,
        'recibo_mostrar_precios' => true,
        'recibo_mostrar_impuestos' => true,
        'recibo_mostrar_descuentos' => true,
        'recibo_mostrar_mesa' => true,
        'recibo_mostrar_mesero' => true,
        'recibo_mostrar_folio' => true,
        'recibo_mostrar_notas' => true,
        'recibo_mostrar_subtotal' => true,
        'recibo_mostrar_pago' => true,
        'recibo_mostrar_propina' => true,
        'recibo_logo_imagen' => null,
        'recibo_logo_posicion' => 'encabezado',
        'recibo_qr_imagen' => null,
        'recibo_qr_posicion' => 'pie',
        'impresora_recibo_ip' => '',
        'impresora_recibo_puerto' => 9100,
        'impresora_cocina_ip' => '',
        'impresora_cocina_puerto' => 9100,
        'impresora_barra_ip' => '',
        'impresora_barra_puerto' => 9100,
        'impresora_categorias_barra' => [],
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
            if (!in_array($config['recibo_ancho_papel'], ['58', '80'], true)) {
                $config['recibo_ancho_papel'] = $default['recibo_ancho_papel'];
            }
            foreach (['recibo_alto_papel', 'recibo_margen_superior', 'recibo_margen_inferior'] as $printAreaKey) {
                $minimum = $printAreaKey === 'recibo_alto_papel' ? 100 : 0;
                $maximum = $printAreaKey === 'recibo_alto_papel' ? 500 : 20;
                $value = filter_var($config[$printAreaKey], FILTER_VALIDATE_INT);
                $config[$printAreaKey] = $value !== false && $value >= $minimum && $value <= $maximum
                    ? $value
                    : $default[$printAreaKey];
            }
            if (!in_array($config['recibo_tamano_fuente'], ['pequena', 'normal', 'grande'], true)) {
                $config['recibo_tamano_fuente'] = $default['recibo_tamano_fuente'];
            }
            foreach (['recibo_logo_imagen' => 'logo', 'recibo_qr_imagen' => 'qr'] as $imageKey => $imageType) {
                $imageName = $config[$imageKey];
                $pattern = '/^recibo-' . $imageType . '-[a-f0-9]{32}\.(?:png|jpe?g|webp)$/';
                $config[$imageKey] = is_string($imageName) && preg_match($pattern, $imageName) ? $imageName : null;
            }
            foreach (['recibo_logo_posicion', 'recibo_qr_posicion'] as $positionKey) {
                if (!in_array($config[$positionKey], ['encabezado', 'pie'], true)) {
                    $config[$positionKey] = $default[$positionKey];
                }
            }
            if (!in_array($config['comanda_ancho_papel'], ['58', '80'], true)) {
                $config['comanda_ancho_papel'] = $default['comanda_ancho_papel'];
            }
            if (!in_array($config['comanda_tamano_fuente'], ['pequena', 'normal', 'grande'], true)) {
                $config['comanda_tamano_fuente'] = $default['comanda_tamano_fuente'];
            }
            foreach (['comanda_margen_superior', 'comanda_margen_inferior'] as $printAreaKey) {
                $value = filter_var($config[$printAreaKey], FILTER_VALIDATE_INT);
                $config[$printAreaKey] = $value !== false && $value >= 0 && $value <= 20
                    ? $value
                    : $default[$printAreaKey];
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

function registrarGasto($concepto, $categoria, $monto, $fecha, $metodoPago, $observaciones, $usuarioId)
{
    global $pdo;
    if (!$pdo instanceof PDO) {
        throw new RuntimeException('No hay conexión con la base de datos.');
    }

    $stmt = $pdo->prepare(
        'INSERT INTO gastos (concepto, categoria, monto, fecha, metodo_pago, observaciones, usuario_id)
         VALUES (?, ?, ?, ?, ?, ?, ?)'
    );
    $stmt->execute([$concepto, $categoria, $monto, $fecha, $metodoPago, $observaciones, $usuarioId]);
    $expenseId = (int) $pdo->lastInsertId();
    registrarAuditoria($usuarioId, 'CREAR_GASTO', 'GASTO', $expenseId, $concepto . ' · $' . number_format($monto, 2, '.', ''));
    return $expenseId;
}

function getGastosRecientes($limit = 50, $fecha = null)
{
    global $pdo;
    if (!$pdo instanceof PDO) {
        throw new RuntimeException('No hay conexión con la base de datos.');
    }

    $limit = max(1, min(200, (int) $limit));
    $sql = 'SELECT g.*, e.nombre AS usuario_nombre
            FROM gastos g LEFT JOIN empleados e ON e.id = g.usuario_id';
    $params = [];
    if (is_string($fecha) && $fecha !== '') {
        $sql .= ' WHERE g.fecha = ?';
        $params[] = $fecha;
    }
    $sql .= ' ORDER BY g.fecha DESC, g.id DESC LIMIT ?';
    $stmt = $pdo->prepare($sql);
    foreach ($params as $index => $value) {
        $stmt->bindValue($index + 1, $value, PDO::PARAM_STR);
    }
    $stmt->bindValue(count($params) + 1, $limit, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getTotalGastosPorFecha($fecha)
{
    global $pdo;
    if (!$pdo instanceof PDO) {
        throw new RuntimeException('No hay conexión con la base de datos.');
    }
    $stmt = $pdo->prepare('SELECT COALESCE(SUM(monto), 0) FROM gastos WHERE fecha = ?');
    $stmt->execute([$fecha]);
    return (float) $stmt->fetchColumn();
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
        'dashboard_grafica' => false,
        'ventas_suspendidas_ver' => false,
        'eliminar_suspendidas' => false,
        'gastos' => false,
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
            'dashboard_grafica' => 'Ver la gráfica de ventas en el panel y elegir su rango de fechas',
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
            'gastos' => 'Registrar y consultar gastos del restaurante',
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
        $stmt = $pdo->query("SELECT * FROM empleados WHERE eliminado_en IS NULL ORDER BY nombre");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log('Could not load active employees: ' . $e->getMessage());
        return [];
    }
}

function getEmpleadosEliminados()
{
    global $pdo;
    if (!$pdo) {
        throw new RuntimeException('No hay conexión con la base de datos.');
    }
    $stmt = $pdo->query("SELECT * FROM empleados WHERE eliminado_en IS NOT NULL ORDER BY eliminado_en DESC, nombre");
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getEstadoEmpleado($employeeId)
{
    global $pdo;
    $stmt = $pdo->prepare('SELECT suspendido, eliminado_en FROM empleados WHERE id = ?');
    $stmt->execute([$employeeId]);
    $employee = $stmt->fetch(PDO::FETCH_ASSOC);
    return $employee ?: null;
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
        $stmt = $pdo->prepare(
            'SELECT * FROM empleados
             WHERE usuario = ? AND suspendido = 0 AND eliminado_en IS NULL'
        );
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
    $inventoryIds = [];
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

        $type = ($item['tipo'] ?? 'menu') === 'inventario' ? 'inventario' : 'menu';
        if ($type === 'inventario') {
            $inventoryIds[$id] = $id;
        } else {
            $menuIds[$id] = $id;
        }
        $normalizedItems[] = [
            'id' => $id,
            'tipo' => $type,
            'precio_enviado' => $price,
            'cantidad' => $quantity,
            'nota' => isset($item['nota']) && is_string($item['nota'])
                ? sanitizeString($item['nota'], 500)
                : '',
        ];
    }

    $menuById = [];
    if ($menuIds !== []) {
        $placeholders = implode(',', array_fill(0, count($menuIds), '?'));
        $stmt = $pdo->prepare("SELECT id, nombre, precio, categoria FROM menu WHERE id IN ($placeholders)");
        $stmt->execute(array_values($menuIds));
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $menuItem) {
            $menuById['menu:' . (int) $menuItem['id']] = $menuItem;
        }
    }
    if ($inventoryIds !== []) {
        $placeholders = implode(',', array_fill(0, count($inventoryIds), '?'));
        $stmt = $pdo->prepare(
            "SELECT id, item AS nombre, precio_venta AS precio, categoria, unidad
             FROM inventario WHERE id IN ($placeholders) AND precio_venta > 0"
        );
        $stmt->execute(array_values($inventoryIds));
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $inventoryItem) {
            $menuById['inventario:' . (int) $inventoryItem['id']] = $inventoryItem;
        }
    }

    $order = [];
    foreach ($normalizedItems as $item) {
        $menuItem = $menuById[$item['tipo'] . ':' . $item['id']] ?? null;
        if (!$menuItem) {
            throw new InvalidArgumentException('Uno o más productos ya no están disponibles para la venta. Actualice el POS.');
        }

        $currentPrice = round((float) $menuItem['precio'], 2);
        if (abs($item['precio_enviado'] - $currentPrice) > 0.001) {
            throw new InvalidArgumentException(
                'El precio de ' . $menuItem['nombre'] . ' cambió. Actualice el POS antes de continuar.'
            );
        }

        $orderItem = [
            'id' => $item['id'],
            'tipo' => $item['tipo'],
            'nombre' => $menuItem['nombre'],
            'precio' => $currentPrice,
            'categoria' => (string) ($menuItem['categoria'] ?? ''),
            'cantidad' => $item['cantidad'],
        ];
        if ($item['tipo'] === 'inventario') {
            $orderItem['unidad'] = (string) ($menuItem['unidad'] ?? 'pieza');
        }
        if ($item['nota'] !== '') {
            $orderItem['nota'] = $item['nota'];
        }
        $order[] = $orderItem;
    }

    return $order;
}

function updateSuspendedOrderItems($originalOrder, $existingEdits, $newItems)
{
    if (!is_array($originalOrder) || !is_array($existingEdits) || !is_array($newItems)) {
        throw new InvalidArgumentException('Estructura de orden inválida.');
    }

    $updatedOrder = [];
    $usedIndexes = [];
    foreach ($existingEdits as $edit) {
        if (!is_array($edit)) {
            throw new InvalidArgumentException('Detalle de producto inválido.');
        }
        $index = validateInteger($edit['indice'] ?? null, 0, max(0, count($originalOrder) - 1));
        $quantity = validateInteger($edit['cantidad'] ?? null, 1, 10000);
        if ($index === null || $quantity === null || isset($usedIndexes[$index]) || !isset($originalOrder[$index]) || !is_array($originalOrder[$index])) {
            throw new InvalidArgumentException('No se pudo validar la edición de los productos.');
        }

        $usedIndexes[$index] = true;
        $item = $originalOrder[$index];
        $item['cantidad'] = $quantity;
        $note = is_string($edit['nota'] ?? null) ? sanitizeString($edit['nota'], 500) : '';
        if ($note === '') {
            unset($item['nota']);
        } else {
            $item['nota'] = $note;
        }
        $updatedOrder[] = $item;
    }

    $updatedOrder = array_merge($updatedOrder, normalizeMenuOrderItems($newItems));
    if ($updatedOrder === []) {
        throw new InvalidArgumentException('La venta debe conservar al menos un producto.');
    }

    return $updatedOrder;
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

function getInventarioParaVenta()
{
    global $pdo;
    $stmt = $pdo->query(
        'SELECT id, item, categoria, precio_venta, cantidad, unidad
         FROM inventario WHERE precio_venta > 0 ORDER BY item ASC'
    );
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getRecetasMenu()
{
    global $pdo;
    $stmt = $pdo->query(
        'SELECT r.menu_id, r.inventario_id, r.cantidad, i.item, i.unidad
         FROM menu_recetas r
         INNER JOIN inventario i ON i.id = r.inventario_id
         ORDER BY r.menu_id, i.item'
    );
    $recipes = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $recipes[(int) $row['menu_id']][] = $row;
    }
    return $recipes;
}

function saveMenuRecipe($menuId, $ingredients)
{
    global $pdo;
    if (!is_array($ingredients) || count($ingredients) > 100) {
        throw new InvalidArgumentException('La receta no es válida.');
    }

    $menuCheck = $pdo->prepare('SELECT id FROM menu WHERE id = ?');
    $menuCheck->execute([$menuId]);
    if (!$menuCheck->fetchColumn()) {
        throw new InvalidArgumentException('El platillo seleccionado ya no existe.');
    }

    $normalized = [];
    foreach ($ingredients as $ingredient) {
        if (!is_array($ingredient)) {
            throw new InvalidArgumentException('Ingrediente inválido.');
        }
        $inventoryId = validateInteger($ingredient['inventario_id'] ?? null, 1);
        $quantity = validateQuantity($ingredient['cantidad'] ?? null, 0.001, 1000000);
        if ($inventoryId === null || $quantity === null) {
            throw new InvalidArgumentException('Cada ingrediente debe tener un artículo y una cantidad mayor a cero.');
        }
        $normalized[$inventoryId] = round(($normalized[$inventoryId] ?? 0) + $quantity, 3);
    }

    if ($normalized !== []) {
        $placeholders = implode(',', array_fill(0, count($normalized), '?'));
        $inventoryCheck = $pdo->prepare("SELECT COUNT(*) FROM inventario WHERE id IN ($placeholders)");
        $inventoryCheck->execute(array_keys($normalized));
        if ((int) $inventoryCheck->fetchColumn() !== count($normalized)) {
            throw new InvalidArgumentException('Uno o más ingredientes ya no existen en el inventario.');
        }
    }

    $pdo->beginTransaction();
    try {
        $delete = $pdo->prepare('DELETE FROM menu_recetas WHERE menu_id = ?');
        $delete->execute([$menuId]);
        $insert = $pdo->prepare(
            'INSERT INTO menu_recetas (menu_id, inventario_id, cantidad) VALUES (?, ?, ?)'
        );
        foreach ($normalized as $inventoryId => $quantity) {
            $insert->execute([$menuId, $inventoryId, $quantity]);
        }
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}

function adjustInventoryStock($id, $quantity, $reason)
{
    global $pdo;
    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare('UPDATE inventario SET cantidad = cantidad + ? WHERE id = ?');
        $stmt->execute([$quantity, $id]);
        if ($stmt->rowCount() !== 1) {
            throw new RuntimeException('El artículo de inventario no existe.');
        }
        $movement = $pdo->prepare(
            "INSERT INTO movimientos_inventario (inventario_id, tipo, cantidad, motivo, usuario_id)
             VALUES (?, 'reposicion', ?, ?, ?)"
        );
        $movement->execute([$id, $quantity, $reason, $_SESSION['user']['id'] ?? null]);
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}

function registrarCompra($proveedor, $folio, $fecha, $observaciones, $items, $usuarioId)
{
    global $pdo;
    if (!$pdo) {
        throw new RuntimeException('No hay conexión con la base de datos.');
    }
    if (!is_array($items) || $items === []) {
        throw new InvalidArgumentException('Agrega por lo menos un artículo a la compra.');
    }
    if (count($items) > 100) {
        throw new InvalidArgumentException('Una compra no puede tener más de 100 artículos.');
    }

    $normalizedItems = [];
    $inventoryIds = [];
    foreach ($items as $item) {
        if (!is_array($item)) {
            throw new InvalidArgumentException('Uno de los artículos de la compra no es válido.');
        }
        $inventoryId = validateInteger($item['inventario_id'] ?? null, 1, PHP_INT_MAX);
        $quantity = validateQuantity($item['cantidad'] ?? null, 0.001, 100000000);
        $unitCost = validateDecimal($item['costo_unitario'] ?? null, 0, 1000000);
        if ($inventoryId === null || $quantity === null || $unitCost === null) {
            throw new InvalidArgumentException('Revisa el artículo, la cantidad y el costo unitario.');
        }
        if (isset($inventoryIds[$inventoryId])) {
            throw new InvalidArgumentException('No repitas un artículo en la compra.');
        }
        $inventoryIds[$inventoryId] = $inventoryId;
        $normalizedItems[] = [
            'inventario_id' => $inventoryId,
            'cantidad' => $quantity,
            'costo_unitario' => $unitCost,
        ];
    }

    sort($inventoryIds, SORT_NUMERIC);
    $pdo->beginTransaction();
    try {
        $placeholders = implode(',', array_fill(0, count($inventoryIds), '?'));
        $inventoryQuery = $pdo->prepare(
            "SELECT id, item FROM inventario WHERE id IN ($placeholders) ORDER BY id FOR UPDATE"
        );
        $inventoryQuery->execute($inventoryIds);
        $inventoryById = [];
        foreach ($inventoryQuery->fetchAll(PDO::FETCH_ASSOC) as $inventoryItem) {
            $inventoryById[(int) $inventoryItem['id']] = $inventoryItem;
        }
        if (count($inventoryById) !== count($inventoryIds)) {
            throw new InvalidArgumentException('Uno o más artículos ya no existen en el inventario.');
        }

        $total = 0;
        foreach ($normalizedItems as &$item) {
            $item['nombre'] = $inventoryById[$item['inventario_id']]['item'];
            $item['subtotal'] = round($item['cantidad'] * $item['costo_unitario'], 2);
            $total += $item['subtotal'];
        }
        unset($item);

        $purchase = $pdo->prepare(
            'INSERT INTO compras (proveedor, folio, fecha_compra, total, observaciones, usuario_id)
             VALUES (?, ?, ?, ?, ?, ?)'
        );
        $purchase->execute([$proveedor, $folio !== '' ? $folio : null, $fecha, round($total, 2), $observaciones, $usuarioId]);
        $purchaseId = (int) $pdo->lastInsertId();
        $detail = $pdo->prepare(
            'INSERT INTO compra_detalles
             (compra_id, inventario_id, item_nombre, cantidad, costo_unitario, subtotal)
             VALUES (?, ?, ?, ?, ?, ?)'
        );
        $stock = $pdo->prepare(
            'UPDATE inventario SET cantidad = cantidad + ?, precio_costo = ? WHERE id = ?'
        );
        $movement = $pdo->prepare(
            "INSERT INTO movimientos_inventario (inventario_id, tipo, cantidad, motivo, usuario_id)
             VALUES (?, 'compra', ?, ?, ?)"
        );
        foreach ($normalizedItems as $item) {
            $detail->execute([
                $purchaseId,
                $item['inventario_id'],
                $item['nombre'],
                $item['cantidad'],
                $item['costo_unitario'],
                $item['subtotal'],
            ]);
            $stock->execute([$item['cantidad'], $item['costo_unitario'], $item['inventario_id']]);
            $reason = 'Compra #' . $purchaseId . ' de ' . $proveedor;
            if ($folio !== '') {
                $reason .= ' (folio ' . $folio . ')';
            }
            $movement->execute([
                $item['inventario_id'],
                $item['cantidad'],
                mb_substr($reason, 0, 255),
                $usuarioId,
            ]);
        }

        $pdo->commit();
        return $purchaseId;
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}

function getComprasRecientes($limit = 50)
{
    global $pdo;
    $limit = max(1, min(200, (int) $limit));
    $stmt = $pdo->prepare(
        'SELECT c.*, e.nombre AS usuario_nombre,
                (SELECT COUNT(*) FROM compra_detalles d WHERE d.compra_id = c.id) AS cantidad_articulos
         FROM compras c
         LEFT JOIN empleados e ON e.id = c.usuario_id
         ORDER BY c.fecha_compra DESC, c.id DESC
         LIMIT ?'
    );
    $stmt->bindValue(1, $limit, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getDetallesCompras($compraIds)
{
    global $pdo;
    if (!is_array($compraIds) || $compraIds === []) {
        return [];
    }
    $ids = array_values(array_unique(array_filter(
        array_map('intval', $compraIds),
        static fn($id) => $id > 0
    )));
    if ($ids === []) {
        return [];
    }
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $stmt = $pdo->prepare(
        "SELECT compra_id, item_nombre, cantidad, costo_unitario, subtotal
         FROM compra_detalles WHERE compra_id IN ($placeholders)
         ORDER BY compra_id DESC, id ASC"
    );
    $stmt->execute($ids);
    $details = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $details[(int) $row['compra_id']][] = $row;
    }
    return $details;
}

function getInventarioItemById($id)
{
    global $pdo;
    $stmt = $pdo->prepare("SELECT * FROM inventario WHERE id = ?");
    $stmt->execute([$id]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

function addInventarioItem($item, $cantidad, $cantidad_minima, $cantidad_maxima, $precio_costo, $proveedor, $codigo_barras = '', $categoria = 'General', $precio_venta = 0, $unidad = 'pieza')
{
    global $pdo;
    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare("INSERT INTO inventario (item, codigo_barras, categoria, cantidad, cantidad_minima, cantidad_maxima, precio_costo, precio_venta, proveedor, unidad) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$item, $codigo_barras ?: null, $categoria, $cantidad, $cantidad_minima, $cantidad_maxima, $precio_costo, $precio_venta, $proveedor, $unidad]);
        $id = (int) $pdo->lastInsertId();
        if ($cantidad > 0) {
            $movement = $pdo->prepare(
                "INSERT INTO movimientos_inventario (inventario_id, tipo, cantidad, motivo, usuario_id)
                 VALUES (?, 'ajuste_inicial', ?, 'Existencia inicial', ?)"
            );
            $movement->execute([$id, $cantidad, $_SESSION['user']['id'] ?? null]);
        }
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
    registrarAuditoria($_SESSION['user']['id'] ?? null, 'CREATE', 'inventario', $id, "Item de inventario agregado: $item");
}

function updateInventarioItem($id, $item, $cantidad, $cantidad_minima, $cantidad_maxima, $precio_costo, $proveedor, $codigo_barras = '', $categoria = 'General', $precio_venta = 0, $unidad = 'pieza')
{
    global $pdo;
    $pdo->beginTransaction();
    try {
        $existing = $pdo->prepare('SELECT cantidad, unidad FROM inventario WHERE id = ? FOR UPDATE');
        $existing->execute([$id]);
        $existingItem = $existing->fetch(PDO::FETCH_ASSOC);
        if (!$existingItem) {
            throw new RuntimeException('El artículo de inventario no existe.');
        }
        if ($existingItem['unidad'] !== $unidad) {
            $recipeUsage = $pdo->prepare('SELECT COUNT(*) FROM menu_recetas WHERE inventario_id = ?');
            $recipeUsage->execute([$id]);
            $movementHistory = $pdo->prepare('SELECT COUNT(*) FROM movimientos_inventario WHERE inventario_id = ?');
            $movementHistory->execute([$id]);
            if ((int) $recipeUsage->fetchColumn() > 0 || (int) $movementHistory->fetchColumn() > 0 || (float) $existingItem['cantidad'] !== 0.0) {
                throw new InvalidArgumentException('No se puede cambiar la unidad de un artículo con recetas, existencias o movimientos registrados.');
            }
        }
        $stmt = $pdo->prepare("UPDATE inventario SET item = ?, codigo_barras = ?, categoria = ?, cantidad = ?, cantidad_minima = ?, cantidad_maxima = ?, precio_costo = ?, precio_venta = ?, proveedor = ?, unidad = ? WHERE id = ?");
        $stmt->execute([$item, $codigo_barras ?: null, $categoria, $cantidad, $cantidad_minima, $cantidad_maxima, $precio_costo, $precio_venta, $proveedor, $unidad, $id]);
        $delta = round((float) $cantidad - (float) $existingItem['cantidad'], 3);
        if ($delta !== 0.0) {
            $movement = $pdo->prepare(
                "INSERT INTO movimientos_inventario (inventario_id, tipo, cantidad, motivo, usuario_id)
                 VALUES (?, 'ajuste', ?, 'Ajuste manual de existencia', ?)"
            );
            $movement->execute([$id, $delta, $_SESSION['user']['id'] ?? null]);
        }
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
    registrarAuditoria($_SESSION['user']['id'] ?? null, 'UPDATE', 'inventario', $id, "Item de inventario actualizado: $item");
}

function deleteInventarioItem($id)
{
    global $pdo;
    $recipeUsage = $pdo->prepare('SELECT COUNT(*) FROM menu_recetas WHERE inventario_id = ?');
    $recipeUsage->execute([$id]);
    if ((int) $recipeUsage->fetchColumn() > 0) {
        throw new InvalidArgumentException('No se puede eliminar este artículo porque se usa en una receta. Actualiza o elimina primero esas recetas.');
    }
    $movementHistory = $pdo->prepare('SELECT COUNT(*) FROM movimientos_inventario WHERE inventario_id = ?');
    $movementHistory->execute([$id]);
    if ((int) $movementHistory->fetchColumn() > 0) {
        throw new InvalidArgumentException('No se puede eliminar este artículo porque tiene historial de inventario. Conserva el registro para mantener la auditoría.');
    }
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

function consumeInventoryForSale($order, $saleId)
{
    global $pdo;
    $requirements = [];
    $menuIds = [];
    foreach ($order as $item) {
        $quantity = max(1, (int) ($item['cantidad'] ?? 1));
        if (($item['tipo'] ?? 'menu') === 'inventario') {
            $inventoryId = (int) ($item['id'] ?? 0);
            $requirements[$inventoryId] = ($requirements[$inventoryId] ?? 0) + $quantity;
        } else {
            $menuId = (int) ($item['id'] ?? 0);
            $menuIds[$menuId] = ($menuIds[$menuId] ?? 0) + $quantity;
        }
    }

    if ($menuIds !== []) {
        $placeholders = implode(',', array_fill(0, count($menuIds), '?'));
        $recipeQuery = $pdo->prepare(
            "SELECT inventario_id, menu_id, cantidad FROM menu_recetas WHERE menu_id IN ($placeholders)"
        );
        $recipeQuery->execute(array_keys($menuIds));
        foreach ($recipeQuery->fetchAll(PDO::FETCH_ASSOC) as $recipe) {
            $inventoryId = (int) $recipe['inventario_id'];
            $used = (float) $recipe['cantidad'] * $menuIds[(int) $recipe['menu_id']];
            $requirements[$inventoryId] = ($requirements[$inventoryId] ?? 0) + $used;
        }
    }

    if ($requirements === []) {
        return;
    }

    $adjust = $pdo->prepare('UPDATE inventario SET cantidad = cantidad - ? WHERE id = ?');
    $movement = $pdo->prepare(
        'INSERT INTO movimientos_inventario (inventario_id, tipo, cantidad, motivo, venta_id, usuario_id)
         VALUES (?, ?, ?, ?, ?, ?)'
    );
    foreach ($requirements as $inventoryId => $quantity) {
        if ($inventoryId <= 0 || $quantity <= 0) {
            throw new RuntimeException('No se pudo calcular el consumo de inventario de la venta.');
        }
        $adjust->execute([$quantity, $inventoryId]);
        if ($adjust->rowCount() !== 1) {
            throw new RuntimeException('Un artículo requerido ya no existe en el inventario.');
        }
        $movement->execute([
            $inventoryId,
            'venta',
            -round($quantity, 3),
            'Consumo automático en venta #' . $saleId,
            $saleId,
            $_SESSION['user']['id'] ?? null,
        ]);
    }
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
    $ownsTransaction = !$pdo->inTransaction();
    if ($ownsTransaction) {
        $pdo->beginTransaction();
    }
    try {
        $orden_json = json_encode($orden, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
        $estadoCocina = $suspendida ? 'no_enviada' : 'pendiente';
        $stmt = $pdo->prepare("INSERT INTO ventas (mesero_id, mesa, orden, suspendida, tipo_pago, propina, monto_pagado, descuento, impuesto, estado_cocina, cocina_actualizada) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");
        $stmt->execute([$mesero_id, $mesa, $orden_json, $suspendida, $tipo_pago, $propina, $monto_pagado, $descuento, $impuesto, $estadoCocina]);
        $ventaId = (int) $pdo->lastInsertId();
        if (!$suspendida) {
            consumeInventoryForSale($orden, $ventaId);
        }
        if ($ownsTransaction) {
            $pdo->commit();
        }
        return $ventaId;
    } catch (Throwable $e) {
        if ($ownsTransaction && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}

function getMeseros()
{
    global $pdo;
    $stmt = $pdo->prepare(
        "SELECT * FROM empleados
         WHERE rol = 'mesero' AND suspendido = 0 AND eliminado_en IS NULL
         ORDER BY nombre"
    );
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
    $sql .= ' ORDER BY v.fecha DESC, v.id DESC';
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("DB Error in getVentasSuspendidas: " . $e->getMessage());
        throw new RuntimeException('No se pudieron consultar las ventas en espera.', 0, $e);
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

function completarVenta($id, $tipo_pago, $propina = 0, $monto_pagado = 0, $descuento = 0, $impuesto = 0, $orden = null)
{
    global $pdo;
    $propina = max(0, floatval($propina));
    $monto_pagado = max(0, floatval($monto_pagado));
    $descuento = max(0, floatval($descuento));
    $impuesto = max(0, floatval($impuesto));
    $pdo->beginTransaction();
    try {
        $saleQuery = $pdo->prepare('SELECT orden FROM ventas WHERE id = ? AND suspendida = 1 FOR UPDATE');
        $saleQuery->execute([$id]);
        $currentOrderJson = $saleQuery->fetchColumn();
        if ($currentOrderJson === false) {
            throw new RuntimeException('La venta ya fue completada o no está disponible.');
        }
        if ($orden === null) {
            $orden = json_decode($currentOrderJson, true);
        }
        if (!is_array($orden) || $orden === []) {
            throw new RuntimeException('La orden de venta no es válida.');
        }

        $orderJson = json_encode($orden, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
        $stmt = $pdo->prepare("UPDATE ventas SET orden = ?, suspendida = 0, tipo_pago = ?, propina = ?, monto_pagado = ?, descuento = ?, impuesto = ?, estado_cocina = 'pendiente', cocina_actualizada = NOW() WHERE id = ? AND suspendida = 1");
        $stmt->execute([$orderJson, $tipo_pago, $propina, $monto_pagado, $descuento, $impuesto, $id]);
        if ($stmt->rowCount() !== 1) {
            throw new RuntimeException('La venta ya fue completada o no está disponible.');
        }
        consumeInventoryForSale($orden, $id);
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
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
    $auditSaved = registrarAuditoria(
        $user_id,
        'ACCION_SISTEMA',
        'SISTEMA',
        null,
        sanitizeString($action, 1000)
    );
    try {
        $stmt = $pdo->prepare("INSERT INTO logs (action, user_id, timestamp) VALUES (?, ?, NOW())");
        $legacySaved = $stmt->execute([$action, $user_id]);
        return $auditSaved && $legacySaved;
    } catch (PDOException $e) {
        error_log("Log failed for action: $action - " . $e->getMessage());
        return false;
    }
}

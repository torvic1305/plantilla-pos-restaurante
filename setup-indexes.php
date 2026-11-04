<?php
/**
 * Script para optimizar índices de la base de datos
 * Ejecutar una sola vez: http://localhost/restaurante/setup-indexes.php
 */

include 'data.php';

if (!isset($_SESSION['user']) || $_SESSION['user']['rol'] !== 'gerente') {
    die('Acceso denegado');
}

$indexes = [
    "CREATE INDEX IF NOT EXISTS idx_ventas_fecha ON ventas(fecha)",
    "CREATE INDEX IF NOT EXISTS idx_ventas_suspendida ON ventas(suspendida)",
    "CREATE INDEX IF NOT EXISTS idx_ventas_mesero ON ventas(mesero_id)",
    "CREATE INDEX IF NOT EXISTS idx_ventas_mesa ON ventas(mesa)",
    "CREATE INDEX IF NOT EXISTS idx_empleados_usuario ON empleados(usuario)",
    "CREATE INDEX IF NOT EXISTS idx_empleados_rol ON empleados(rol)",
    "CREATE INDEX IF NOT EXISTS idx_menu_categoria ON menu(categoria)",
    "CREATE INDEX IF NOT EXISTS idx_inventario_item ON inventario(item)",
    "CREATE INDEX IF NOT EXISTS idx_clientes_email ON clientes(email)",
    "CREATE INDEX IF NOT EXISTS idx_clientes_telefono ON clientes(telefono)",
    "CREATE INDEX IF NOT EXISTS idx_audit_log_usuario ON audit_log(usuario_id)",
    "CREATE INDEX IF NOT EXISTS idx_audit_log_fecha ON audit_log(fecha)",
    "CREATE INDEX IF NOT EXISTS idx_logs_user ON logs(user_id)",
    "CREATE INDEX IF NOT EXISTS idx_mermas_fecha ON mermas(fecha)",
    "CREATE INDEX IF NOT EXISTS idx_promociones_activa ON promociones(activa)",
    "CREATE INDEX IF NOT EXISTS idx_cierres_caja_fecha ON cierres_caja(fecha_cierre)"
];

$results = [];
foreach ($indexes as $sql) {
    try {
        $pdo->exec($sql);
        $results[] = ['status' => 'ok', 'sql' => $sql];
    } catch (PDOException $e) {
        $results[] = ['status' => 'error', 'sql' => $sql, 'error' => $e->getMessage()];
    }
}

header('Content-Type: application/json');
echo json_encode([
    'status' => 'ok',
    'message' => 'Proceso de indexación completado',
    'results' => $results
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
?>
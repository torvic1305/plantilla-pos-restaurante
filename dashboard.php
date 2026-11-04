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
$permisos = $user['permisos'] ?? [];
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Restaurante POS</title>
    <link rel="stylesheet" href="style.php">
</head>

<body>
    <?php $lowStockItems = getInventarioBajoStock(); ?>
    <header>
        <h1>Bienvenido, <?php echo htmlspecialchars($user['nombre']); ?></h1>
        <nav>
            <?php if ($permisos['pos'] ?? false): ?><a href="pos.php">Punto de Venta</a><?php endif; ?>
            <?php if ($user['rol'] === 'gerente'): ?><a href="empleados.php">Empleados</a><?php endif; ?>
            <?php if ($permisos['menu_ver'] ?? false): ?><a href="menu.php">Menú</a><?php endif; ?>
            <?php if ($permisos['menu_editar'] ?? false): ?><a href="clientes.php">Clientes</a><a href="inventario.php">Inventario</a><?php endif; ?>
            <?php if ($permisos['informes'] ?? false): ?>
                <a href="informes.php">Informes</a>
                <a href="reportes_avanzados.php">Reportes Avanzados</a>
                <a href="propinas.php">Propinas</a>
            <?php endif; ?>
            <?php if ($permisos['mermas'] ?? false): ?><a href="merma.php">Merma</a><?php endif; ?>
            <?php if ($permisos['promociones'] ?? false): ?><a href="promociones.php">Promociones</a><?php endif; ?>
            <?php if ($permisos['cocina'] ?? false): ?><a href="cocina.php">Cocina</a><?php endif; ?>
            <?php if ($permisos['pos'] ?? false): ?><a href="cierre_caja.php">Cierre de Caja</a><?php endif; ?>
            <?php if ($user['rol'] === 'gerente'): ?><a href="config.php">Configuración</a><?php endif; ?>
            <a href="logout.php">Cerrar Sesión</a>
        </nav>
    </header>
    <main>
        <h2>Panel Principal</h2>
        <p>Selecciona una opción del menú.</p>
        <?php if (!empty($lowStockItems)): ?>
            <section
                style="margin-top: 20px; padding: 15px; background: #ffe6e6; border: 1px solid #ff8c8c; border-radius: 8px;">
                <h3 style="margin-top: 0; color: #aa0000;">Alerta de Stock Bajo</h3>
                <p>Hay
                    <?php echo count($lowStockItems); ?> artículo(s) en inventario con stock igual o menor al mínimo:
                </p>
                <ul>
                    <?php foreach ($lowStockItems as $item): ?>
                        <li>
                            <?php echo htmlspecialchars($item['item']); ?> — Cantidad:
                            <?php echo intval($item['cantidad']); ?> / Mínimo:
                            <?php echo intval($item['cantidad_minima']); ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
                <p><a href="inventario.php" style="color: #aa0000; font-weight: bold;">Ir al inventario para revisar</a></p>
            </section>
        <?php endif; ?>
    </main>
    <script src="2.js"></script>
</body>

</html>
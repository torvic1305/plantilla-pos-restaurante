<?php
include 'data.php';
include 'security.php';
if (!isset($_SESSION['user'])) {
    header('Location: index.php');
    exit;
}

if (!($_SESSION['user']['permisos']['promociones'] ?? false)) {
    header('Location: dashboard.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Promociones - Restaurante POS</title>
    <link rel="stylesheet" href="style.php">
</head>

<body>
    <header>
        <h1>Promociones</h1>
        <a href="dashboard.php">Volver</a>
    </header>
    <main>
        <h2>Promociones Activas</h2>
        <ul>
            <?php $promociones = getPromociones();
            foreach ($promociones as $p) { ?>
                <li><?php echo htmlspecialchars($p['nombre']); ?> - Descuento:
                    <?php echo htmlspecialchars($p['descuento']); ?>% - Desde: <?php echo htmlspecialchars($p['desde']); ?>
                    Hasta: <?php echo htmlspecialchars($p['hasta']); ?></li>
            <?php } ?>
        </ul>
        <form action="agregar_promocion.php" method="post">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(getCsrfToken()) ?>">
            <input type="text" name="nombre" placeholder="Nombre" required>
            <input type="number" name="descuento" placeholder="Descuento %" required>
            <input type="datetime-local" name="desde" required>
            <input type="datetime-local" name="hasta" required>
            <button type="submit">Agregar</button>
        </form>
    </main>
    <script src="2.js"></script>
</body>

</html>
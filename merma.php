<?php
include 'data.php';
include 'security.php';
if (!isset($_SESSION['user'])) {
    header('Location: index.php');
    exit;
}

requirePermission('mermas');
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Merma - Restaurante POS</title>
    <link rel="stylesheet" href="style.php">
</head>

<body>
    <header>
        <h1>Mercancía Dañada</h1>
        <a href="dashboard.php">Volver</a>
    </header>
    <main>
        <h2>Registro de Merma</h2>
        <ul>
            <?php $mermas = getMermas();
            foreach ($mermas as $m) { ?>
                <li><?php echo htmlspecialchars($m['item']); ?> - Cantidad: <?php echo htmlspecialchars($m['cantidad']); ?>
                    - Razón: <?php echo htmlspecialchars($m['razon']); ?></li>
            <?php } ?>
        </ul>
        <form action="agregar_merma.php" method="post">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(getCsrfToken()) ?>">
            <input type="text" name="item" placeholder="Item" required>
            <input type="number" name="cantidad" placeholder="Cantidad" required>
            <input type="text" name="razon" placeholder="Razón" required>
            <button type="submit">Agregar</button>
        </form>
    </main>
    <script src="2.js"></script>
</body>

</html>
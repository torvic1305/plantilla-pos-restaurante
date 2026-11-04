<?php
include 'data.php';
include 'security.php';
if (!isset($_SESSION['user']) || !($_SESSION['user']['permisos']['cocina'] ?? false)) {
    header('Location: dashboard.php');
    exit;
}
$ventas = getPedidosParaCocina();
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Comandas - Cocina</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="style.php">
</head>

<body>
    <header>
        <h1>Comandas para Cocina</h1>
        <a href="dashboard.php">Volver</a>
    </header>
    <main>
        <h2>Pedidos Pendientes</h2>
        <ul class="list-group">
            <?php foreach ($ventas as $v) {
                $orden = json_decode($v['orden'], true);
                if (is_array($orden)) {
                    $nombres = array_column($orden, 'nombre');
                    $escapeNombres = array_map(function ($item) {
                        return htmlspecialchars($item, ENT_QUOTES | ENT_HTML5, 'UTF-8');
                    }, $nombres);
                    echo "<li class='list-group-item'>Mesa " . htmlspecialchars($v['mesa']) . ": " . implode(', ', $escapeNombres) . "</li>";
                }
            } ?>
        </ul>
    </main>
    <script src="2.js"></script>
</body>

</html>
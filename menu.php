<?php
include 'data.php';
include 'security.php';
if (!isset($_SESSION['user'])) {
    header('Location: index.php');
    exit;
}
requirePermission('menu_ver');
$canEditMenu = !empty($_SESSION['user']['permisos']['menu_editar']);
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Menú - Restaurante POS</title>
    <link rel="stylesheet" href="style.php">
</head>

<body>
    <header>
        <h1>Gestión de Menú</h1>
        <a href="dashboard.php">Volver</a>
    </header>
    <main>
        <h2>Menú</h2>
        <?php
        $menu = getMenu();
        $categorias = ['Comida' => [], 'Bebidas' => []];
        foreach ($menu as $item) {
            $cat = $item['categoria'];
            if (stripos($cat, 'bebida') !== false || $cat == 'Bebidas') {
                $categorias['Bebidas'][] = $item;
            } else {
                $categorias['Comida'][] = $item;
            }
        }
        foreach ($categorias as $cat_name => $items) {
            echo "<h3>" . htmlspecialchars($cat_name, ENT_QUOTES | ENT_HTML5, 'UTF-8') . "</h3>";
            echo "<div class='menu-grid'>";
            foreach ($items as $item) {
                echo "<div class='menu-item'>
                        <span>" . htmlspecialchars($item['nombre'], ENT_QUOTES | ENT_HTML5, 'UTF-8') . " - $" . htmlspecialchars(number_format($item['precio'], 2), ENT_QUOTES | ENT_HTML5, 'UTF-8') . "</span>
                        " . ($canEditMenu ? "<button type='button' onclick='editarItem(" . intval($item['id']) . ")'>Editar</button>
                        <button type='button' onclick='eliminarItem(" . intval($item['id']) . ")'>Eliminar</button>" : '') . "
                      </div>";
            }
            echo "</div>";
        }
        ?>
        <?php if ($canEditMenu): ?>
        <h2>Agregar Item</h2>
        <form action="agregar_menu.php" method="post">
            <input type="hidden" name="csrf_token"
                value="<?= htmlspecialchars(getCsrfToken(), ENT_QUOTES | ENT_HTML5, 'UTF-8') ?>">
            <input type="text" name="nombre" placeholder="Nombre" required>
            <input type="number" step="0.01" name="precio" placeholder="Precio" required>
            <select name="categoria" required>
                <option value="Comida">Comida</option>
                <option value="Bebidas">Bebidas</option>
            </select>
            <button type="submit">Agregar</button>
        </form>
        <?php endif; ?>
    </main>
    <script src="2.js"></script>
    <script>
        const csrfToken = '<?= htmlspecialchars(getCsrfToken(), ENT_QUOTES | ENT_HTML5, 'UTF-8') ?>';

        function createHiddenInput(form, name, value) {
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = name;
            input.value = value;
            form.appendChild(input);
        }

        function editarItem(id) {
            const nuevoNombre = prompt('Nuevo nombre:');
            const nuevoPrecio = prompt('Nuevo precio:');
            const nuevaCategoria = prompt('Nueva categoría (Comida/Bebidas):');
            if (nuevoNombre && nuevoPrecio && nuevaCategoria) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.action = 'editar_menu.php';
                createHiddenInput(form, 'csrf_token', csrfToken);
                createHiddenInput(form, 'id', id);
                createHiddenInput(form, 'nombre', nuevoNombre);
                createHiddenInput(form, 'precio', nuevoPrecio);
                createHiddenInput(form, 'categoria', nuevaCategoria);
                document.body.appendChild(form);
                form.submit();
            }
        }

        function eliminarItem(id) {
            if (confirm('¿Eliminar este item?')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.action = 'eliminar_menu.php';
                createHiddenInput(form, 'csrf_token', csrfToken);
                createHiddenInput(form, 'id', id);
                document.body.appendChild(form);
                form.submit();
            }
        }
    </script>
</body>

</html>
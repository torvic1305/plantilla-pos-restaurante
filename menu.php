<?php
include 'data.php';
include 'security.php';
if (!isset($_SESSION['user'])) {
    header('Location: index.php');
    exit;
}
requirePermission('menu_ver');
$canEditMenu = !empty($_SESSION['user']['permisos']['menu_editar']);
$canEditRecipes = $canEditMenu && !empty($_SESSION['user']['permisos']['inventario_ver']);
$recipeMessage = isset($_GET['success']) ? 'Receta guardada correctamente.' : '';
$recipeError = isset($_GET['error']) && is_string($_GET['error']) ? sanitizeString($_GET['error'], 180) : '';
$menu = getMenu();
$recipes = $canEditRecipes ? getRecetasMenu() : [];
$recipeInventory = $canEditRecipes ? getInventario() : [];
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
        <?php if ($recipeMessage !== ''): ?><p class="settings-message success" role="status"><?= htmlspecialchars($recipeMessage, ENT_QUOTES | ENT_HTML5, 'UTF-8') ?></p><?php endif; ?>
        <?php if ($recipeError !== ''): ?><p class="settings-message error" role="alert"><?= htmlspecialchars($recipeError, ENT_QUOTES | ENT_HTML5, 'UTF-8') ?></p><?php endif; ?>
        <?php
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
        <?php if ($canEditRecipes): ?>
            <section class="recipes-section">
                <h2>Recetas y consumo de inventario</h2>
                <p>Define cuánto consume cada platillo por unidad vendida. Usa la misma unidad configurada en el inventario; al cobrar, las cantidades se descuentan automáticamente.</p>
                <?php if (!$recipeInventory): ?><p role="status">Agrega primero artículos al inventario para poder definir ingredientes.</p><?php endif; ?>
                <?php foreach ($menu as $menuItem): ?>
                    <?php $menuRecipe = $recipes[(int) $menuItem['id']] ?? []; ?>
                    <details class="recipe-editor">
                        <summary><?= htmlspecialchars($menuItem['nombre'], ENT_QUOTES | ENT_HTML5, 'UTF-8') ?> · <?= count($menuRecipe) ?> ingrediente(s)</summary>
                        <form method="post" action="guardar_receta.php" class="recipe-form">
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(getCsrfToken(), ENT_QUOTES | ENT_HTML5, 'UTF-8') ?>">
                            <input type="hidden" name="menu_id" value="<?= (int) $menuItem['id'] ?>">
                            <div class="recipe-rows">
                                <?php foreach ($menuRecipe as $index => $ingredient): ?>
                                    <div class="recipe-row">
                                        <label>Insumo
                                            <select name="ingredientes[<?= (int) $index ?>][inventario_id]" required>
                                                <?php foreach ($recipeInventory as $inventoryItem): ?>
                                                    <option value="<?= (int) $inventoryItem['id'] ?>" <?= (int) $inventoryItem['id'] === (int) $ingredient['inventario_id'] ? 'selected' : '' ?>>
                                                        <?= htmlspecialchars($inventoryItem['item'] . ' (' . $inventoryItem['unidad'] . ')', ENT_QUOTES | ENT_HTML5, 'UTF-8') ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </label>
                                        <label>Cantidad por platillo
                                            <input type="number" name="ingredientes[<?= (int) $index ?>][cantidad]" min="0.001" step="0.001" value="<?= htmlspecialchars((string) $ingredient['cantidad'], ENT_QUOTES | ENT_HTML5, 'UTF-8') ?>" required>
                                        </label>
                                        <button type="button" class="remove-recipe-row">Quitar</button>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            <div class="recipe-actions">
                                <button type="button" class="add-recipe-row" <?= !$recipeInventory ? 'disabled' : '' ?>>Agregar ingrediente</button>
                                <button type="submit" <?= !$recipeInventory ? 'disabled' : '' ?>>Guardar receta</button>
                            </div>
                        </form>
                    </details>
                <?php endforeach; ?>
                <template id="recipe-row-template">
                    <div class="recipe-row">
                        <label>Insumo
                            <select required>
                                <?php foreach ($recipeInventory as $inventoryItem): ?>
                                    <option value="<?= (int) $inventoryItem['id'] ?>"><?= htmlspecialchars($inventoryItem['item'] . ' (' . $inventoryItem['unidad'] . ')', ENT_QUOTES | ENT_HTML5, 'UTF-8') ?></option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                        <label>Cantidad por platillo
                            <input type="number" min="0.001" step="0.001" required>
                        </label>
                        <button type="button" class="remove-recipe-row">Quitar</button>
                    </div>
                </template>
            </section>
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

        const recipeTemplate = document.getElementById('recipe-row-template');
        document.querySelectorAll('.recipe-form').forEach(form => {
            let nextIndex = form.querySelectorAll('.recipe-row').length;
            form.querySelector('.add-recipe-row').addEventListener('click', () => {
                const row = recipeTemplate.content.firstElementChild.cloneNode(true);
                row.querySelector('select').name = `ingredientes[${nextIndex}][inventario_id]`;
                row.querySelector('input').name = `ingredientes[${nextIndex}][cantidad]`;
                nextIndex += 1;
                form.querySelector('.recipe-rows').appendChild(row);
            });
            form.addEventListener('click', event => {
                if (event.target.classList.contains('remove-recipe-row')) {
                    event.target.closest('.recipe-row').remove();
                }
            });
        });
    </script>
</body>

</html>
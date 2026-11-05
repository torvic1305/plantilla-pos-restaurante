<?php
include 'data.php';
include 'security.php';

requireMethod('POST');
requirePermission('menu_editar');
requirePermission('inventario_ver');

try {
    if (!is_string($_POST['csrf_token'] ?? null) || !validateCsrfToken($_POST['csrf_token'])) {
        throw new InvalidArgumentException('Token de seguridad inválido.');
    }

    $menuId = validateInteger($_POST['menu_id'] ?? null, 1, PHP_INT_MAX);
    if ($menuId === null) {
        throw new InvalidArgumentException('El platillo seleccionado no es válido.');
    }

    $ingredients = $_POST['ingredientes'] ?? [];
    if (!is_array($ingredients)) {
        throw new InvalidArgumentException('La lista de ingredientes no es válida.');
    }

    saveMenuRecipe($menuId, $ingredients);
    registrarAuditoria(
        (int) $_SESSION['user']['id'],
        'GUARDAR_RECETA',
        'MENU',
        $menuId,
        'Ingredientes: ' . count($ingredients)
    );
    header('Location: menu.php?success=1');
    exit;
} catch (InvalidArgumentException $e) {
    header('Location: menu.php?error=' . rawurlencode($e->getMessage()));
    exit;
} catch (Throwable $e) {
    error_log('Recipe save failed: ' . $e->getMessage());
    header('Location: menu.php?error=' . rawurlencode('No se pudo guardar la receta. Inténtalo de nuevo.'));
    exit;
}

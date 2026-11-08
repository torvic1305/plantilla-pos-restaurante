<?php
include 'data.php';
include 'security.php';

if (!isset($_SESSION['user']['id'])) {
    header('Location: index.php');
    exit;
}
if (!$pdo instanceof PDO) {
    http_response_code(503);
    exit('No se pudo consultar el historial del sistema.');
}

$identityQuery = $pdo->prepare(
    'SELECT usuario, rol, suspendido, eliminado_en FROM empleados WHERE id = ?'
);
$identityQuery->execute([(int) $_SESSION['user']['id']]);
$identity = $identityQuery->fetch(PDO::FETCH_ASSOC);
$isDeveloper = $identity && $identity['usuario'] === 'super';
$isOwner = $identity && $identity['rol'] === 'gerente';
if (
    !$identity || !empty($identity['suspendido']) || $identity['eliminado_en'] !== null
    || (!$isDeveloper && !$isOwner)
) {
    http_response_code(403);
    exit('El historial está disponible únicamente para el programador y el administrador del negocio.');
}

$escape = static fn($value) => htmlspecialchars((string) $value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
$parseDate = static function ($value) {
    if (!is_string($value) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
        return null;
    }
    $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
    $errors = DateTimeImmutable::getLastErrors();
    return $date && ($errors === false || ($errors['warning_count'] === 0 && $errors['error_count'] === 0))
        && $date->format('Y-m-d') === $value
        ? $date
        : null;
};

$fromInput = is_string($_GET['desde'] ?? null) ? $_GET['desde'] : '';
$toInput = is_string($_GET['hasta'] ?? null) ? $_GET['hasta'] : '';
$actionInput = is_string($_GET['accion'] ?? null) ? trim($_GET['accion']) : '';
$entityInput = is_string($_GET['entidad'] ?? null) ? trim($_GET['entidad']) : '';
$fromDate = $fromInput === '' ? null : $parseDate($fromInput);
$toDate = $toInput === '' ? null : $parseDate($toInput);
$filterError = '';
if (($fromInput !== '' && !$fromDate) || ($toInput !== '' && !$toDate)) {
    $filterError = 'Una fecha del filtro no es válida.';
    $fromDate = null;
    $toDate = null;
} elseif ($fromDate && $toDate && $fromDate > $toDate) {
    $filterError = 'La fecha inicial no puede ser posterior a la fecha final.';
    $fromDate = null;
    $toDate = null;
}

$action = mb_substr($actionInput, 0, 100);
$entity = mb_substr($entityInput, 0, 100);
$page = validateInteger($_GET['pagina'] ?? 1, 1, 1000000) ?? 1;
$pageSize = 50;
$where = [];
$params = [];
if ($fromDate) {
    $where[] = 'a.fecha >= ?';
    $params[] = $fromDate->format('Y-m-d') . ' 00:00:00';
}
if ($toDate) {
    $where[] = 'a.fecha < ?';
    $params[] = $toDate->modify('+1 day')->format('Y-m-d') . ' 00:00:00';
}
if ($action !== '') {
    $where[] = 'a.accion LIKE ?';
    $params[] = '%' . $action . '%';
}
if ($entity !== '') {
    $where[] = 'a.entidad LIKE ?';
    $params[] = '%' . $entity . '%';
}
$whereSql = $where === [] ? '' : ' WHERE ' . implode(' AND ', $where);
$countQuery = $pdo->prepare('SELECT COUNT(*) FROM audit_log a' . $whereSql);
$countQuery->execute($params);
$totalRows = (int) $countQuery->fetchColumn();
$totalPages = max(1, (int) ceil($totalRows / $pageSize));
$page = min($page, $totalPages);
$offset = ($page - 1) * $pageSize;
$historyQuery = $pdo->prepare(
    'SELECT a.id, a.usuario_id, e.nombre AS usuario_nombre, e.usuario AS usuario_cuenta,
            a.accion, a.entidad, a.entidad_id, a.descripcion, a.fecha
     FROM audit_log a
     LEFT JOIN empleados e ON e.id = a.usuario_id'
    . $whereSql
    . ' ORDER BY a.fecha DESC, a.id DESC LIMIT ? OFFSET ?'
);
foreach ($params as $index => $value) {
    $historyQuery->bindValue($index + 1, $value, PDO::PARAM_STR);
}
$historyQuery->bindValue(count($params) + 1, $pageSize, PDO::PARAM_INT);
$historyQuery->bindValue(count($params) + 2, $offset, PDO::PARAM_INT);
$historyQuery->execute();
$historyRows = $historyQuery->fetchAll(PDO::FETCH_ASSOC);
$queryParams = array_filter([
    'desde' => $fromDate ? $fromDate->format('Y-m-d') : '',
    'hasta' => $toDate ? $toDate->format('Y-m-d') : '',
    'accion' => $action,
    'entidad' => $entity,
]);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Historial del sistema - Restaurante POS</title>
    <link rel="stylesheet" href="style.php">
</head>
<body>
    <header>
        <h1>Historial del sistema</h1>
        <a href="dashboard.php">Volver al panel</a>
    </header>
    <main class="dashboard-content history-page">
        <div class="page-heading">
            <div>
                <h2>Actividad registrada</h2>
                <p>Accesible solo para la cuenta del programador y el administrador del negocio.</p>
            </div>
            <strong><?= number_format($totalRows) ?> movimientos</strong>
        </div>
        <?php if ($filterError !== ''): ?>
            <p class="settings-message error" role="alert"><?= $escape($filterError) ?></p>
        <?php endif; ?>
        <section class="purchase-card">
            <form method="get" class="history-filters">
                <label>Desde<input type="date" name="desde" value="<?= $escape($fromDate ? $fromDate->format('Y-m-d') : '') ?>"></label>
                <label>Hasta<input type="date" name="hasta" value="<?= $escape($toDate ? $toDate->format('Y-m-d') : '') ?>"></label>
                <label>Acción<input type="search" name="accion" maxlength="100" value="<?= $escape($action) ?>" placeholder="Ej. LOGIN, VENTA"></label>
                <label>Área<input type="search" name="entidad" maxlength="100" value="<?= $escape($entity) ?>" placeholder="Ej. INVENTARIO"></label>
                <button type="submit">Filtrar</button>
                <a href="historial.php">Limpiar</a>
            </form>
            <?php if ($historyRows === []): ?>
                <p class="purchase-empty">No hay movimientos para mostrar con estos filtros.</p>
            <?php else: ?>
                <div class="purchase-history-scroll">
                    <table class="audit-history-table">
                        <thead>
                            <tr><th>Fecha y hora</th><th>Usuario</th><th>Acción</th><th>Área / registro</th><th>Detalle</th></tr>
                        </thead>
                        <tbody>
                            <?php foreach ($historyRows as $row): ?>
                                <tr>
                                    <td><?= $escape(date('d/m/Y H:i:s', strtotime($row['fecha']))) ?></td>
                                    <td><?= $escape($row['usuario_nombre'] ?? 'No identificado') ?><small><?= $escape($row['usuario_cuenta'] ?? ($row['usuario_id'] === null ? 'Acceso fallido' : 'Cuenta archivada')) ?></small></td>
                                    <td><strong><?= $escape($row['accion']) ?></strong></td>
                                    <td><?= $escape($row['entidad']) ?><?= $row['entidad_id'] !== null ? ' #' . (int) $row['entidad_id'] : '' ?></td>
                                    <td class="audit-description"><?= $escape($row['descripcion']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php if ($totalPages > 1): ?>
                    <nav class="history-pagination" aria-label="Paginación del historial">
                        <?php if ($page > 1): ?><a href="?<?= $escape(http_build_query($queryParams + ['pagina' => $page - 1])) ?>">← Anterior</a><?php endif; ?>
                        <span>Página <?= $page ?> de <?= $totalPages ?></span>
                        <?php if ($page < $totalPages): ?><a href="?<?= $escape(http_build_query($queryParams + ['pagina' => $page + 1])) ?>">Siguiente →</a><?php endif; ?>
                    </nav>
                <?php endif; ?>
            <?php endif; ?>
        </section>
    </main>
</body>
</html>

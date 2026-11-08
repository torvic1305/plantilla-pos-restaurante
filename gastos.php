<?php
include 'data.php';
include 'security.php';

if (!isset($_SESSION['user'])) {
    header('Location: index.php');
    exit;
}
requirePermission('gastos');

$config = getAppConfig();
$paymentMethods = is_array($config['formas_pago'] ?? null) ? $config['formas_pago'] : ['Efectivo'];
$paymentMethods = array_values(array_filter($paymentMethods, 'is_string'));
if ($paymentMethods === []) {
    $paymentMethods = ['Efectivo'];
}
$message = '';
$errorMessage = '';
$formValues = [
    'concepto' => '',
    'categoria' => '',
    'monto' => '',
    'fecha' => date('Y-m-d'),
    'metodo_pago' => $paymentMethods[0],
    'observaciones' => '',
];

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    foreach (['concepto', 'categoria', 'monto', 'fecha', 'metodo_pago', 'observaciones'] as $field) {
        $formValues[$field] = is_string($_POST[$field] ?? null) ? $_POST[$field] : '';
    }

    try {
        requireMethod('POST');
        $csrfToken = $_POST['csrf_token'] ?? '';
        if (!is_string($csrfToken) || !validateCsrfToken($csrfToken)) {
            throw new InvalidArgumentException('La sesión de seguridad venció. Recarga la página e inténtalo de nuevo.');
        }

        $concept = sanitizeString($formValues['concepto'], 160);
        $category = sanitizeString($formValues['categoria'], 80);
        $amount = validateDecimal($formValues['monto'], 0.01, 9999999999.99);
        $paymentMethod = sanitizeString($formValues['metodo_pago'], 40);
        $observations = sanitizeString($formValues['observaciones'], 500);
        $expenseDate = DateTimeImmutable::createFromFormat('!Y-m-d', $formValues['fecha']);
        $dateErrors = DateTimeImmutable::getLastErrors();
        $validDate = $expenseDate
            && ($dateErrors === false || ($dateErrors['warning_count'] === 0 && $dateErrors['error_count'] === 0))
            && $expenseDate->format('Y-m-d') === $formValues['fecha']
            && $expenseDate <= new DateTimeImmutable('today');

        if (
            $concept === '' || $category === '' || $amount === null || !$validDate
            || !in_array($paymentMethod, $paymentMethods, true)
        ) {
            throw new InvalidArgumentException('Verifica el concepto, la categoría, el monto, la fecha y la forma de pago.');
        }

        registrarGasto(
            $concept,
            $category,
            $amount,
            $formValues['fecha'],
            $paymentMethod,
            $observations,
            (int) $_SESSION['user']['id']
        );
        header('Location: gastos.php?registrado=1');
        exit;
    } catch (Throwable $e) {
        $errorMessage = $e instanceof InvalidArgumentException
            ? $e->getMessage()
            : 'No se pudo registrar el gasto. Inténtalo de nuevo.';
        if (!$e instanceof InvalidArgumentException) {
            error_log('Unable to register expense: ' . $e->getMessage());
        }
    }
}

$filterDateInput = $_GET['fecha'] ?? '';
$filterDate = is_string($filterDateInput) ? $filterDateInput : '';
if ($filterDate !== '') {
    $parsedFilterDate = DateTimeImmutable::createFromFormat('!Y-m-d', $filterDate);
    $filterErrors = DateTimeImmutable::getLastErrors();
    if (
        !$parsedFilterDate
        || ($filterErrors !== false && ($filterErrors['warning_count'] > 0 || $filterErrors['error_count'] > 0))
        || $parsedFilterDate->format('Y-m-d') !== $filterDate
    ) {
        $filterDate = '';
        $errorMessage = 'La fecha del filtro no es válida; se muestran los registros recientes.';
    }
}
$expenses = getGastosRecientes(100, $filterDate !== '' ? $filterDate : null);
$todayTotal = getTotalGastosPorFecha(date('Y-m-d'));
$escape = static fn($value) => htmlspecialchars((string) $value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
$registered = ($_GET['registrado'] ?? '') === '1';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gastos - Restaurante POS</title>
    <link rel="stylesheet" href="style.php">
</head>
<body>
    <header>
        <h1>Gastos del restaurante</h1>
        <a href="dashboard.php">Volver al panel</a>
    </header>
    <main class="dashboard-content expense-page">
        <div class="page-heading">
            <div>
                <h2>Control de gastos</h2>
                <p>Registra salidas de dinero y consulta el historial.</p>
            </div>
            <strong class="expense-today-total">Gastos de hoy: $<?= number_format($todayTotal, 2) ?></strong>
        </div>
        <?php if ($registered): ?>
            <p class="settings-message success" role="status">El gasto se registró correctamente.</p>
        <?php endif; ?>
        <?php if ($errorMessage !== ''): ?>
            <p class="settings-message error" role="alert"><?= $escape($errorMessage) ?></p>
        <?php endif; ?>

        <section class="purchase-card">
            <div class="purchase-card-heading">
                <div>
                    <h2>Registrar gasto</h2>
                    <p>El registro se guarda con el empleado y la fecha indicados.</p>
                </div>
            </div>
            <form method="post" class="expense-form">
                <input type="hidden" name="csrf_token" value="<?= $escape(getCsrfToken()) ?>">
                <label>Concepto
                    <input type="text" name="concepto" maxlength="160" required value="<?= $escape($formValues['concepto']) ?>" placeholder="Ej. Pago de electricidad">
                </label>
                <label>Categoría
                    <input type="text" name="categoria" maxlength="80" required value="<?= $escape($formValues['categoria']) ?>" placeholder="Ej. Servicios, renta, limpieza">
                </label>
                <label>Monto
                    <input type="number" name="monto" min="0.01" max="9999999999.99" step="0.01" required value="<?= $escape($formValues['monto']) ?>">
                </label>
                <label>Fecha del gasto
                    <input type="date" name="fecha" max="<?= date('Y-m-d') ?>" required value="<?= $escape($formValues['fecha']) ?>">
                </label>
                <label>Forma de pago
                    <select name="metodo_pago" required>
                        <?php foreach ($paymentMethods as $method): ?>
                            <option value="<?= $escape($method) ?>" <?= $formValues['metodo_pago'] === $method ? 'selected' : '' ?>><?= $escape($method) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label class="expense-form-wide">Notas (opcional)
                    <textarea name="observaciones" maxlength="500" rows="2"><?= $escape($formValues['observaciones']) ?></textarea>
                </label>
                <div class="expense-form-actions">
                    <button type="submit">Guardar gasto</button>
                </div>
            </form>
        </section>

        <section class="purchase-card">
            <div class="purchase-card-heading">
                <div>
                    <h2>Historial de gastos</h2>
                    <p><?= count($expenses) ?> registros<?= $filterDate !== '' ? ' del ' . $escape(date('d/m/Y', strtotime($filterDate))) : ' recientes' ?>.</p>
                </div>
                <form method="get" class="expense-filter">
                    <label>Filtrar por fecha
                        <input type="date" name="fecha" value="<?= $escape($filterDate) ?>">
                    </label>
                    <button type="submit">Filtrar</button>
                    <?php if ($filterDate !== ''): ?><a href="gastos.php">Ver recientes</a><?php endif; ?>
                </form>
            </div>
            <?php if ($expenses === []): ?>
                <p class="purchase-empty">No hay gastos para mostrar en este periodo.</p>
            <?php else: ?>
                <div class="purchase-history-scroll">
                    <table class="purchase-history expense-history">
                        <thead>
                            <tr><th>Fecha</th><th>Concepto</th><th>Categoría</th><th>Forma de pago</th><th>Registró</th><th>Monto</th></tr>
                        </thead>
                        <tbody>
                            <?php foreach ($expenses as $expense): ?>
                                <tr>
                                    <td><?= $escape(date('d/m/Y', strtotime($expense['fecha']))) ?></td>
                                    <td><?= $escape($expense['concepto']) ?><?php if (!empty($expense['observaciones'])): ?><small class="expense-note"><?= $escape($expense['observaciones']) ?></small><?php endif; ?></td>
                                    <td><?= $escape($expense['categoria']) ?></td>
                                    <td><?= $escape($expense['metodo_pago']) ?></td>
                                    <td><?= $escape($expense['usuario_nombre'] ?? 'Empleado archivado') ?></td>
                                    <td><strong>$<?= number_format((float) $expense['monto'], 2) ?></strong></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </section>
    </main>
</body>
</html>

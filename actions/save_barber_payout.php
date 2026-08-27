<?php
require_once __DIR__ . '/../includes/auth.php';
$user = require_role('owner');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: ../owner.php?tab=finanzas&subtab=barberos_pago");
    exit();
}

verify_csrf_token();

$barber_id      = sanitize_int($_POST['barber_id'] ?? 0);
$date           = validate_date($_POST['date'] ?? '') ? $_POST['date'] : date('Y-m-d');
$type           = sanitize_string($_POST['type'] ?? 'pago', 50);
$amount         = sanitize_float($_POST['amount'] ?? 0);
$payment_method = sanitize_string($_POST['payment_method'] ?? 'efectivo', 50);
$description    = sanitize_string($_POST['description'] ?? '', 255);
$shop_id        = $user['shop_id'];

if ($barber_id <= 0 || $amount <= 0) {
    set_flash_message('danger', 'Por favor selecciona un barbero y un monto válido.');
    header("Location: ../owner.php?tab=finanzas&subtab=barberos_pago");
    exit();
}

try {
    $receipt_number = 'REC-' . strtoupper(substr(uniqid(), -6));

    $stmt = $pdo->prepare("
        INSERT INTO barber_payouts (shop_id, barber_id, date, type, amount, payment_method, description, receipt_number)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([
        $shop_id,
        $barber_id,
        $date,
        $type,
        $amount,
        $payment_method,
        $description,
        $receipt_number
    ]);

    $payout_id = $pdo->lastInsertId();

    // Obtener nombre del barbero para auditoría
    $stmtB = $pdo->prepare("SELECT name FROM users WHERE id = ?");
    $stmtB->execute([$barber_id]);
    $bRow = $stmtB->fetch();
    $bName = $bRow ? $bRow['name'] : "ID #$barber_id";

    log_audit('CREAR', 'pago_barbero', $payout_id, "Se registró " . strtoupper($type) . " de $" . number_format($amount, 2) . " para el barbero '$bName' (Recibo #$receipt_number)");

    set_flash_message('success', 'Registro contable guardado exitosamente. Recibo #' . $receipt_number);
    header("Location: ../owner.php?tab=finanzas&subtab=barberos_pago&receipt_id=" . $payout_id);
    exit();
} catch (Exception $e) {
    set_flash_message('danger', 'Error al guardar movimiento: ' . $e->getMessage());
    header("Location: ../owner.php?tab=finanzas&subtab=barberos_pago");
    exit();
}

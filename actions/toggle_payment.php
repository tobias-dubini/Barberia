<?php
require_once __DIR__ . '/../includes/auth.php';
$user = require_role('owner');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: ../owner.php?tab=finanzas");
    exit();
}

verify_csrf_token();

$closure_id = sanitize_int($_POST['closure_id'] ?? 0);
$month      = sanitize_string($_POST['month'] ?? date('Y-m'), 20);
$barber_id  = sanitize_string($_POST['barber_id'] ?? 'todos', 20);

if ($closure_id > 0) {
    try {
        $stmt = $pdo->prepare("UPDATE daily_closures SET is_paid = NOT is_paid WHERE id = ? AND shop_id = ?");
        $stmt->execute([$closure_id, $user['shop_id']]);

        log_audit('CAMBIAR_ESTADO_PAGO', 'cierre', $closure_id, "Se alternó el estado de pago de la liquidación ID #$closure_id");

        set_flash_message('success', 'Estado de pago de la liquidación actualizado.');
    } catch (Exception $e) {
        set_flash_message('danger', 'Error al actualizar pago: ' . $e->getMessage());
    }
}

header("Location: ../owner.php?tab=finanzas&month=" . urlencode($month) . "&barber_id=" . urlencode($barber_id));
exit();

<?php
require_once __DIR__ . '/../includes/auth.php';
$user = require_role('owner');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: ../owner.php?tab=finanzas&subtab=gastos");
    exit();
}

verify_csrf_token();

$id      = sanitize_int($_POST['id'] ?? 0);
$shop_id = $user['shop_id'];

if ($id <= 0) {
    set_flash_message('danger', 'ID de gasto inválido.');
    header("Location: ../owner.php?tab=finanzas&subtab=gastos");
    exit();
}

try {
    $stmtCheck = $pdo->prepare("SELECT description, amount FROM expenses WHERE id = ? AND shop_id = ?");
    $stmtCheck->execute([$id, $shop_id]);
    $exp = $stmtCheck->fetch();

    if ($exp) {
        $stmt = $pdo->prepare("DELETE FROM expenses WHERE id = ? AND shop_id = ?");
        $stmt->execute([$id, $shop_id]);

        log_audit('ELIMINAR', 'gasto', $id, "Se eliminó el gasto '" . $exp['description'] . "' ($" . number_format((float)$exp['amount'], 2) . ")");
    }

    set_flash_message('success', 'Gasto eliminado correctamente.');
} catch (Exception $e) {
    set_flash_message('danger', 'Error al eliminar gasto: ' . $e->getMessage());
}

header("Location: ../owner.php?tab=finanzas&subtab=gastos");
exit();

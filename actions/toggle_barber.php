<?php
require_once __DIR__ . '/../includes/auth.php';
$user = require_role('owner');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: ../owner.php?tab=barberos");
    exit();
}

verify_csrf_token();

$id             = sanitize_int($_POST['id'] ?? 0);
$current_status = sanitize_int($_POST['current_status'] ?? 1);
$new_status     = $current_status ? 0 : 1;

if ($id > 0) {
    try {
        $stmtCheck = $pdo->prepare("SELECT name FROM users WHERE id = ? AND shop_id = ?");
        $stmtCheck->execute([$id, $user['shop_id']]);
        $barber = $stmtCheck->fetch();

        if ($barber) {
            $stmt = $pdo->prepare("UPDATE users SET is_active = ? WHERE id = ? AND shop_id = ? AND role = 'barber'");
            $stmt->execute([$new_status, $id, $user['shop_id']]);

            $stText = $new_status ? 'activó' : 'desactivó';
            log_audit('CAMBIAR_ESTADO', 'barbero', $id, "Se $stText la cuenta del barbero '" . $barber['name'] . "'");
        }

        set_flash_message('success', 'Estado del barbero actualizado correctamente.');
    } catch (Exception $e) {
        set_flash_message('danger', 'Error al cambiar estado: ' . $e->getMessage());
    }
}

header("Location: ../owner.php?tab=barberos");
exit();

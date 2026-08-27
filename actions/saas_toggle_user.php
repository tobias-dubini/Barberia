<?php
require_once __DIR__ . '/../includes/auth.php';

$user = require_super_admin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: ../super_admin.php");
    exit();
}

verify_csrf_token();

$target_user_id = (int)($_POST['user_id'] ?? 0);

if ($target_user_id <= 0) {
    set_flash_message('danger', 'Identificador de usuario no válido.');
    header("Location: ../super_admin.php?tab=admins");
    exit();
}

// Prevenir que el Super Admin se desactive a sí mismo
if ($target_user_id === (int)$user['id']) {
    set_flash_message('danger', 'No puedes desactivar tu propia cuenta de Super Admin.');
    header("Location: ../super_admin.php?tab=admins");
    exit();
}

$stmt = $pdo->prepare("SELECT id, name, is_active FROM users WHERE id = ?");
$stmt->execute([$target_user_id]);
$target_user = $stmt->fetch();

if (!$target_user) {
    set_flash_message('danger', 'El usuario especificado no existe.');
    header("Location: ../super_admin.php?tab=admins");
    exit();
}

$new_status = $target_user['is_active'] ? 0 : 1;
$stmtUpdate = $pdo->prepare("UPDATE users SET is_active = ? WHERE id = ?");
$stmtUpdate->execute([$new_status, $target_user_id]);

$status_str = $new_status ? 'activada' : 'desactivada';
log_audit('SAAS_TOGGLE_USER', 'user', $target_user_id, "La cuenta del usuario '" . $target_user['name'] . "' fue " . $status_str . " por el Super Admin.");

set_flash_message('success', 'La cuenta de "' . htmlspecialchars($target_user['name']) . '" fue ' . $status_str . ' correctamente.');
header("Location: ../super_admin.php?tab=admins");
exit();

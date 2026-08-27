<?php
require_once __DIR__ . '/../includes/auth.php';

$user = require_super_admin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: ../super_admin.php");
    exit();
}

verify_csrf_token();

$target_user_id = (int)($_POST['user_id'] ?? 0);
$new_password   = trim($_POST['new_password'] ?? '');

if ($target_user_id <= 0 || empty($new_password)) {
    set_flash_message('danger', 'Debe ingresar una contraseña válida.');
    header("Location: ../super_admin.php?tab=admins");
    exit();
}

if (strlen($new_password) < 4) {
    set_flash_message('danger', 'La contraseña debe tener al menos 4 caracteres.');
    header("Location: ../super_admin.php?tab=admins");
    exit();
}

$stmt = $pdo->prepare("SELECT id, name, email FROM users WHERE id = ?");
$stmt->execute([$target_user_id]);
$target_user = $stmt->fetch();

if (!$target_user) {
    set_flash_message('danger', 'Usuario no encontrado.');
    header("Location: ../super_admin.php?tab=admins");
    exit();
}

$hash = password_hash($new_password, PASSWORD_BCRYPT);
$stmtUpdate = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
$stmtUpdate->execute([$hash, $target_user_id]);

log_audit('SAAS_RESET_PASSWORD', 'user', $target_user_id, "Super Admin restableció la contraseña para el usuario '" . $target_user['name'] . "' (" . $target_user['email'] . ")");

set_flash_message('success', 'La contraseña para "' . htmlspecialchars($target_user['name']) . '" ha sido restablecida con éxito.');
header("Location: ../super_admin.php?tab=admins");
exit();

<?php
require_once __DIR__ . '/../includes/auth.php';

$user = require_super_admin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: ../super_admin.php");
    exit();
}

verify_csrf_token();

$role_input = sanitize_string($_POST['role'] ?? 'admin_barberia', 50);
$role       = in_array($role_input, ['admin_barberia', 'barbero', 'owner', 'barber']) ? $role_input : 'admin_barberia';
$name       = sanitize_string($_POST['name'] ?? '', 150);
$email      = sanitize_email($_POST['email'] ?? '');
$password   = trim($_POST['password'] ?? '');

if (empty($name) || empty($email) || empty($password)) {
    set_flash_message('danger', 'Por favor completa todos los campos obligatorios.');
    header("Location: ../super_admin.php?tab=admins");
    exit();
}

$stmtCheck = $pdo->prepare("SELECT id FROM users WHERE email = ?");
$stmtCheck->execute([$email]);
if ($stmtCheck->fetch()) {
    set_flash_message('danger', 'El correo electrónico ya pertenece a una cuenta registrada.');
    header("Location: ../super_admin.php?tab=admins");
    exit();
}

// 1 es la ID principal de Brotherhood Barbershop
$shop_id = 1;
$hash = password_hash($password, PASSWORD_DEFAULT);
$stmt = $pdo->prepare("INSERT INTO users (shop_id, name, email, password, role, is_active) VALUES (?, ?, ?, ?, ?, 1)");
$stmt->execute([$shop_id, $name, $email, $hash, $role]);
$new_user_id = $pdo->lastInsertId();

// Si la barbería no tenía owner_id y se crea un admin, asignarlo
if (in_array($role, ['admin_barberia', 'owner'])) {
    $stmtShop = $pdo->prepare("UPDATE shops SET owner_id = ? WHERE id = ? AND (owner_id IS NULL OR owner_id = 0)");
    $stmtShop->execute([$new_user_id, $shop_id]);
}

$role_label = ($role === 'barbero' || $role === 'barber') ? 'Barbero Staff' : 'Admin de Barbería';
log_audit('CREATE_USER', 'user', $new_user_id, "Super Admin creó la cuenta '$name' ($email) con rol $role_label para Brotherhood Barbershop");

set_flash_message('success', 'Cuenta de ' . $role_label . ' "' . htmlspecialchars($name) . '" creada exitosamente.');
header("Location: ../super_admin.php?tab=admins");
exit();

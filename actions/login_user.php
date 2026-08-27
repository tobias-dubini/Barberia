<?php
require_once __DIR__ . '/../includes/auth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: ../login.php");
    exit();
}

verify_csrf_token();

$email    = sanitize_email($_POST['email'] ?? '');
$password = trim($_POST['password'] ?? '');

if (empty($email) || empty($password)) {
    set_flash_message('danger', 'Por favor ingresa un correo válido y contraseña.');
    header("Location: ../login.php");
    exit();
}

$stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
$stmt->execute([$email]);
$user = $stmt->fetch();

if (!$user) {
    log_audit('LOGIN_FAIL', 'user', null, 'Intento de inicio de sesión con correo no registrado: ' . $email);
    set_flash_message('danger', 'Credenciales inválidas. Usuario no encontrado.');
    header("Location: ../login.php");
    exit();
}

if (!$user['is_active']) {
    log_audit('LOGIN_DISABLED', 'user', $user['id'], 'Intento de inicio de sesión de usuario desactivado: ' . $email);
    set_flash_message('danger', 'Tu cuenta ha sido desactivada. Comunícate con el administrador del sistema.');
    header("Location: ../login.php");
    exit();
}

// Si pertenece a una barbería, comprobar si la barbería está activa (no aplica para Super Admin sin shop_id)
if (!empty($user['shop_id'])) {
    $stmtShop = $pdo->prepare("SELECT is_active, name FROM shops WHERE id = ?");
    $stmtShop->execute([$user['shop_id']]);
    $shop = $stmtShop->fetch();
    if ($shop && !$shop['is_active'] && !is_super_admin($user)) {
        log_audit('LOGIN_SHOP_DISABLED', 'shop', $user['shop_id'], 'Acceso bloqueado por barbería desactivada.');
        set_flash_message('danger', 'La barbería "' . htmlspecialchars($shop['name']) . '" se encuentra suspendida o desactivada.');
        header("Location: ../login.php");
        exit();
    }
}

if (password_verify($password, $user['password'])) {
    session_regenerate_id(true);
    $_SESSION['user_id'] = $user['id'];
    log_audit('LOGIN_SUCCESS', 'user', $user['id'], 'Inicio de sesión exitoso con rol (' . $user['role'] . ') para: ' . $user['name']);

    set_flash_message('success', '¡Bienvenido de nuevo, ' . htmlspecialchars($user['name']) . '!');

    // Redirección basada en roles RBAC
    if (is_super_admin($user)) {
        header("Location: ../super_admin.php");
    } elseif (is_shop_admin($user)) {
        header("Location: ../owner.php");
    } else {
        header("Location: ../barber.php");
    }
    exit();
} else {
    log_audit('LOGIN_FAIL', 'user', $user['id'], 'Contraseña incorrecta para el usuario: ' . $email);
    set_flash_message('danger', 'Contraseña incorrecta.');
    header("Location: ../login.php");
    exit();
}

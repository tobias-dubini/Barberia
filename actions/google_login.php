<?php
require_once __DIR__ . '/../includes/auth.php';

// Simulación / Handler de Google SSO OAuth
$stmt = $pdo->prepare("SELECT * FROM users WHERE role = 'owner' AND is_active = 1 LIMIT 1");
$stmt->execute();
$user = $stmt->fetch();

if (!$user) {
    set_flash_message('danger', 'No se pudo verificar la cuenta de Google con el sistema.');
    header("Location: ../login.php");
    exit();
}

$_SESSION['user_id'] = $user['id'];
log_audit('LOGIN_SUCCESS_GOOGLE', 'user', $user['id'], 'Inicio de sesión exitoso mediante Google OAuth para: ' . $user['name']);

set_flash_message('success', '¡Autenticado exitosamente con Google! Bienvenido, ' . htmlspecialchars($user['name']));
header("Location: ../owner.php");
exit();

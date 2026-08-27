<?php
require_once __DIR__ . '/../includes/auth.php';

$user = require_super_admin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: ../super_admin.php?tab=admins");
    exit();
}

verify_csrf_token();

$target_user_id = (int)($_POST['user_id'] ?? 0);

if ($target_user_id <= 0) {
    set_flash_message('danger', 'Identificador de usuario no válido.');
    header("Location: ../super_admin.php?tab=admins");
    exit();
}

// Prevenir que el Super Admin se elimine a sí mismo
if ($target_user_id === (int)$user['id']) {
    set_flash_message('danger', 'No puedes eliminar tu propia cuenta de Super Admin.');
    header("Location: ../super_admin.php?tab=admins");
    exit();
}

try {
    $stmt = $pdo->prepare("SELECT id, name, email FROM users WHERE id = ?");
    $stmt->execute([$target_user_id]);
    $target_user = $stmt->fetch();

    if (!$target_user) {
        set_flash_message('danger', 'El usuario especificado no existe.');
        header("Location: ../super_admin.php?tab=admins");
        exit();
    }

    $pdo->beginTransaction();

    // 1. Desvincular de la barbería si era el dueño/administrador registrado
    $stmtUnlinkOwner = $pdo->prepare("UPDATE shops SET owner_id = NULL WHERE owner_id = ?");
    $stmtUnlinkOwner->execute([$target_user_id]);

    // 2. Eliminar el usuario
    $stmtDelete = $pdo->prepare("DELETE FROM users WHERE id = ?");
    $stmtDelete->execute([$target_user_id]);

    $pdo->commit();

    log_audit('SAAS_DELETE_USER', 'user', $target_user_id, "El usuario '" . $target_user['name'] . "' (" . $target_user['email'] . ") fue eliminado permanentemente por el Super Admin.");

    set_flash_message('success', 'El usuario "' . htmlspecialchars($target_user['name']) . '" fue eliminado permanentemente de la plataforma.');
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    set_flash_message('danger', 'Error al eliminar el usuario: ' . $e->getMessage());
}

header("Location: ../super_admin.php?tab=admins");
exit();

<?php
require_once __DIR__ . '/../includes/auth.php';
$user = require_shop_admin();
$shop_id = get_current_shop_id($user);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: ../owner.php?tab=barberos");
    exit();
}

verify_csrf_token();

$id = sanitize_int($_POST['id'] ?? 0);

if ($id <= 0 || $id == $user['id']) {
    set_flash_message('danger', 'No puedes eliminar esta cuenta de usuario.');
    header("Location: ../owner.php?tab=barberos");
    exit();
}

try {
    $stmtCheck = $pdo->prepare("SELECT name FROM users WHERE id = ? AND shop_id = ? AND role IN ('barber', 'barbero')");
    $stmtCheck->execute([$id, $shop_id]);
    $barber = $stmtCheck->fetch();

    if (!$barber) {
        set_flash_message('danger', 'Barbero no encontrado.');
        header("Location: ../owner.php?tab=barberos");
        exit();
    }

    $stmt = $pdo->prepare("DELETE FROM users WHERE id = ? AND shop_id = ? AND role IN ('barber', 'barbero')");
    $stmt->execute([$id, $shop_id]);

    log_audit('ELIMINAR', 'barbero', $id, "Se eliminó al barbero '" . $barber['name'] . "'");

    set_flash_message('success', 'Barbero ' . htmlspecialchars($barber['name']) . ' eliminado correctamente.');
} catch (Exception $e) {
    set_flash_message('danger', 'Error al eliminar barbero: ' . $e->getMessage());
}

header("Location: ../owner.php?tab=barberos");
exit();

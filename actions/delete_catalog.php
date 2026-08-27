<?php
require_once __DIR__ . '/../includes/auth.php';
$user = require_shop_admin();
$shop_id = get_current_shop_id($user);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: ../owner.php?tab=catalog");
    exit();
}

verify_csrf_token();

$id = sanitize_int($_POST['id'] ?? 0);

if ($id > 0) {
    try {
        $stmtCheck = $pdo->prepare("SELECT name FROM catalog WHERE id = ? AND shop_id = ?");
        $stmtCheck->execute([$id, $shop_id]);
        $item = $stmtCheck->fetch();

        if ($item) {
            $stmt = $pdo->prepare("DELETE FROM catalog WHERE id = ? AND shop_id = ?");
            $stmt->execute([$id, $shop_id]);

            log_audit('ELIMINAR', 'servicio', $id, "Se eliminó del catálogo el servicio '" . $item['name'] . "'");
        }

        set_flash_message('success', 'Ítem eliminado del catálogo.');
    } catch (Exception $e) {
        set_flash_message('danger', 'Error al eliminar ítem: ' . $e->getMessage());
    }
}

header("Location: ../owner.php?tab=catalog");
exit();

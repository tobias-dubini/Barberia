<?php
require_once __DIR__ . '/../includes/auth.php';
$user = require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: ../barber.php");
    exit();
}

verify_csrf_token();

$id   = sanitize_int($_POST['id'] ?? 0);
$date = validate_date($_POST['date'] ?? '') ? $_POST['date'] : date('Y-m-d');

$shop_id = get_current_shop_id($user);

if ($id > 0) {
    try {
        $stmtCheck = $pdo->prepare("SELECT client_name FROM appointments WHERE id = ? AND shop_id = ?");
        $stmtCheck->execute([$id, $shop_id]);
        $appt = $stmtCheck->fetch();

        if ($appt) {
            $stmt = $pdo->prepare("DELETE FROM appointments WHERE id = ? AND shop_id = ?");
            $stmt->execute([$id, $shop_id]);

            log_audit('ELIMINAR', 'turno', $id, "Se eliminó definitivamente el turno del cliente '" . $appt['client_name'] . "'");
        }

        set_flash_message('success', 'Registro eliminado correctamente.');
    } catch (Exception $e) {
        set_flash_message('danger', 'Error al eliminar: ' . $e->getMessage());
    }
}

$tab_redirect  = sanitize_string($_POST['tab_redirect'] ?? 'my_grid', 50);
$barber_filter = sanitize_string($_POST['barber_filter'] ?? '', 50);
$filter_param  = !empty($barber_filter) ? '&barber_id=' . urlencode($barber_filter) : '';
$redirect      = is_shop_admin($user) ? '../owner.php?tab=' . urlencode($tab_redirect) . '&date=' . urlencode($date) . $filter_param : '../barber.php?tab=' . urlencode($tab_redirect) . '&date=' . urlencode($date) . $filter_param;
header("Location: " . $redirect);
exit();

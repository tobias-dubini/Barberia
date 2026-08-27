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

if ($id <= 0) {
    header("Location: ../barber.php?date=" . urlencode($date));
    exit();
}

$shop_id = get_current_shop_id($user);

try {
    $stmt = $pdo->prepare("
        SELECT 
            a.*, 
            COALESCE(bc.price, c.price) as base_price, 
            COALESCE(bc.commission_percent, c.commission_percent) as commission_percent 
        FROM appointments a 
        JOIN catalog c ON a.item_id = c.id 
        LEFT JOIN barber_commissions bc ON bc.barber_id = a.barber_id AND bc.catalog_id = a.item_id AND bc.shop_id = a.shop_id
        WHERE a.id = ? AND a.shop_id = ?
    ");
    $stmt->execute([$id, $shop_id]);
    $appt = $stmt->fetch();

    if ($appt) {
        $pdo->beginTransaction();

        $stmtUp = $pdo->prepare("UPDATE appointments SET status = 'completed' WHERE id = ? AND shop_id = ?");
        $stmtUp->execute([$id, $shop_id]);

        $base = (float)$appt['base_price'];
        $final = ($appt['payment_method'] === 'transferencia') ? ($base * 1.20) : $base;
        $comm = ($base * (float)$appt['commission_percent']) / 100.0;

        $stmtSale = $pdo->prepare("
            INSERT INTO sales (shop_id, barber_id, appointment_id, sale_date, base_amount, total_amount, total_commission, payment_method, is_direct_sale)
            VALUES (?, ?, ?, NOW(), ?, ?, ?, ?, 0)
        ");
        $stmtSale->execute([
            $shop_id,
            $appt['barber_id'],
            $id,
            $base,
            $final,
            $comm,
            $appt['payment_method']
        ]);

        $pdo->commit();

        log_audit('COMPLETAR', 'turno', $id, "Se completó y cobró el turno del cliente '" . $appt['client_name'] . "' ($" . number_format($final, 2) . ")");

        set_flash_message('success', 'Turno marcado como completado y cobrado.');
    }
} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    set_flash_message('danger', 'Error al completar turno: ' . $e->getMessage());
}

$barber_filter = sanitize_string($_POST['barber_filter'] ?? '', 50);
$filter_param  = !empty($barber_filter) ? '&barber_id=' . urlencode($barber_filter) : '';
$redirect      = is_shop_admin($user) ? '../owner.php?tab=my_grid&date=' . urlencode($date) . $filter_param : '../barber.php?date=' . urlencode($date) . $filter_param;
header("Location: " . $redirect);
exit();

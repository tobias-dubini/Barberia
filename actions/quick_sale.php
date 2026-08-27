<?php
require_once __DIR__ . '/../includes/auth.php';
$user = require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: ../barber.php");
    exit();
}

verify_csrf_token();

$date           = validate_date($_POST['date'] ?? '') ? $_POST['date'] : date('Y-m-d');
$item_id        = sanitize_int($_POST['item_id'] ?? 0);
$client_name    = sanitize_string($_POST['client_name'] ?? 'Cliente Mostrador', 150);
$payment_method = sanitize_string($_POST['payment_method'] ?? 'efectivo', 50);

if ($item_id <= 0) {
    set_flash_message('danger', 'Selecciona un producto o promoción válida.');
    header("Location: ../barber.php?date=" . urlencode($date));
    exit();
}

$shop_id = get_current_shop_id($user);
$seller_barber_id = sanitize_int($_POST['barber_id'] ?? $user['id']);

try {
    $stmtCat = $pdo->prepare("
        SELECT 
            c.*, 
            COALESCE(bc.price, c.price) as effective_price,
            COALESCE(bc.commission_percent, c.commission_percent) as effective_commission
        FROM catalog c
        LEFT JOIN barber_commissions bc ON bc.barber_id = ? AND bc.catalog_id = c.id AND bc.shop_id = c.shop_id
        WHERE c.id = ? AND c.shop_id = ?
    ");
    $stmtCat->execute([$seller_barber_id, $item_id, $shop_id]);
    $item = $stmtCat->fetch();

    if ($item) {
        $pdo->beginTransaction();

        $datetime = $date . ' 12:00:00';

        $stmtAppt = $pdo->prepare("
            INSERT INTO appointments (shop_id, barber_id, client_name, item_id, appointment_datetime, payment_method, observation, status, is_direct_sale)
            VALUES (?, ?, ?, ?, ?, ?, 'Venta de Mostrador', 'completed', 1)
        ");
        $stmtAppt->execute([
            $shop_id,
            $seller_barber_id,
            empty($client_name) ? 'Cliente Mostrador' : $client_name,
            $item_id,
            $datetime,
            $payment_method
        ]);
        $appt_id = $pdo->lastInsertId();

        $base = (float)$item['effective_price'];
        $final = ($payment_method === 'transferencia') ? ($base * 1.20) : $base;
        $comm = ($base * (float)$item['effective_commission']) / 100.0;

        $stmtSale = $pdo->prepare("
            INSERT INTO sales (shop_id, barber_id, appointment_id, sale_date, base_amount, total_amount, total_commission, payment_method, is_direct_sale)
            VALUES (?, ?, ?, NOW(), ?, ?, ?, ?, 1)
        ");
        $stmtSale->execute([
            $shop_id,
            $user['id'],
            $appt_id,
            $base,
            $final,
            $comm,
            $payment_method
        ]);

        $pdo->commit();

        log_audit('VENTA_RAPIDA', 'venta', $appt_id, "Venta rápida de mostrador: '" . $item['name'] . "' ($" . number_format($final, 2) . ")");

        set_flash_message('success', 'Venta rápida de ' . htmlspecialchars($item['name']) . ' registrada exitosamente.');
    }
} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    set_flash_message('danger', 'Error al procesar la venta: ' . $e->getMessage());
}

$redirect = is_shop_admin($user) ? '../owner.php?tab=ventas&date=' : '../barber.php?tab=ventas&date=';
header("Location: " . $redirect . urlencode($date));
exit();

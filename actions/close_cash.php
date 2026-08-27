<?php
require_once __DIR__ . '/../includes/auth.php';
$user = require_shop_admin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: ../owner.php?tab=finanzas");
    exit();
}

verify_csrf_token();

$date    = validate_date($_POST['date'] ?? '') ? $_POST['date'] : date('Y-m-d');
$shop_id = get_current_shop_id($user);

try {
    $stmtBarbers = $pdo->prepare("SELECT id, name FROM users WHERE shop_id = ? AND role IN ('barber', 'barbero', 'owner', 'admin_barberia')");
    $stmtBarbers->execute([$shop_id]);
    $barbers = $stmtBarbers->fetchAll();

    $closed_count = 0;

    foreach ($barbers as $barber) {
        $barber_id = $barber['id'];

        $stmtApp = $pdo->prepare("
            SELECT 
                SUM(CASE WHEN a.payment_method = 'efectivo' THEN COALESCE(bc.price, c.price) ELSE 0 END) as efec_app,
                SUM(CASE WHEN a.payment_method != 'efectivo' THEN COALESCE(bc.price, c.price) ELSE 0 END) as trans_app,
                SUM(COALESCE(bc.price, c.price)) as total_app,
                SUM(COALESCE(bc.price, c.price) * (COALESCE(bc.commission_percent, c.commission_percent) / 100)) as comision_app
            FROM appointments a
            JOIN catalog c ON a.item_id = c.id
            LEFT JOIN barber_commissions bc ON bc.barber_id = a.barber_id AND bc.catalog_id = a.item_id AND bc.shop_id = a.shop_id
            WHERE a.shop_id = ? 
              AND a.barber_id = ? 
              AND DATE(a.appointment_datetime) = ?
              AND a.status != 'cancelled'
        ");
        $stmtApp->execute([$shop_id, $barber_id, $date]);
        $appData = $stmtApp->fetch();

        $stmtSales = $pdo->prepare("
            SELECT 
                SUM(CASE WHEN payment_method = 'efectivo' THEN total_amount ELSE 0 END) as efec_sale,
                SUM(CASE WHEN payment_method != 'efectivo' THEN total_amount ELSE 0 END) as trans_sale,
                SUM(total_amount) as total_sale,
                SUM(total_commission) as comision_sale
            FROM sales
            WHERE shop_id = ? 
              AND barber_id = ? 
              AND DATE(created_at) = ?
        ");
        $stmtSales->execute([$shop_id, $barber_id, $date]);
        $salesData = $stmtSales->fetch();

        $tot_efectivo      = (float)($appData['efec_app'] ?? 0) + (float)($salesData['efec_sale'] ?? 0);
        $tot_transferencia = (float)($appData['trans_app'] ?? 0) + (float)($salesData['trans_sale'] ?? 0);
        $tot_ingresos      = $tot_efectivo + $tot_transferencia;
        $tot_comisiones    = (float)($appData['comision_app'] ?? 0) + (float)($salesData['comision_sale'] ?? 0);
        $neto_barberia     = $tot_ingresos - $tot_comisiones;

        $stmtClosure = $pdo->prepare("
            INSERT INTO daily_closures (shop_id, barber_id, date, total_efectivo, total_transferencia, total_ingresos, total_comisiones, neto_barberia, is_paid)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, 0)
            ON DUPLICATE KEY UPDATE 
                total_efectivo = VALUES(total_efectivo),
                total_transferencia = VALUES(total_transferencia),
                total_ingresos = VALUES(total_ingresos),
                total_comisiones = VALUES(total_comisiones),
                neto_barberia = VALUES(neto_barberia)
        ");
        $stmtClosure->execute([
            $shop_id,
            $barber_id,
            $date,
            $tot_efectivo,
            $tot_transferencia,
            $tot_ingresos,
            $tot_comisiones,
            $neto_barberia
        ]);

        $closed_count++;
    }

    log_audit('CONSOLIDAR_CIERRE', 'caja', null, "Consolidación de cierre diario para la fecha " . date('d/m/Y', strtotime($date)));

    set_flash_message('success', '¡Cierre de caja registrado exitosamente para la fecha ' . date('d/m/Y', strtotime($date)) . '!');
} catch (Exception $e) {
    set_flash_message('danger', 'Error al ejecutar el cierre de caja: ' . $e->getMessage());
}

$redirect_tab = $_POST['redirect_tab'] ?? 'cierre_caja';
$redirect = is_shop_admin($user) ? '../owner.php?tab=' . urlencode($redirect_tab) . '&date=' . urlencode($date) : '../barber.php?tab=cierre_caja&date=' . urlencode($date);
header("Location: " . $redirect);
exit();

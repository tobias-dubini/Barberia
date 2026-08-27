<?php
require_once __DIR__ . '/../includes/auth.php';
$user = require_role('owner');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: ../owner.php?tab=finanzas&subtab=caja");
    exit();
}

verify_csrf_token();

$action   = sanitize_string($_POST['action'] ?? '', 50);
$shop_id  = $user['shop_id'];
$user_id  = $user['id'];

try {
    if ($action === 'open_cash') {
        $initial_amount = sanitize_float($_POST['initial_amount'] ?? 0);
        $observations   = sanitize_string($_POST['observations'] ?? '', 500);

        $stmtCheck = $pdo->prepare("SELECT id FROM cash_registers WHERE shop_id = ? AND status = 'open'");
        $stmtCheck->execute([$shop_id]);
        if ($stmtCheck->fetch()) {
            set_flash_message('danger', 'Ya existe una caja abierta en este momento.');
            header("Location: ../owner.php?tab=finanzas&subtab=caja");
            exit();
        }

        $stmt = $pdo->prepare("
            INSERT INTO cash_registers (shop_id, opened_by, open_date, initial_amount, status, observations)
            VALUES (?, ?, NOW(), ?, 'open', ?)
        ");
        $stmt->execute([$shop_id, $user_id, $initial_amount, $observations]);
        $reg_id = $pdo->lastInsertId();

        log_audit('ABRIR_CAJA', 'caja', $reg_id, "Apertura de caja con fondo inicial de $" . number_format($initial_amount, 2));

        set_flash_message('success', '¡Caja abierta exitosamente con un monto inicial de $' . number_format($initial_amount, 2) . '!');
    } 
    elseif ($action === 'close_cash') {
        $register_id = sanitize_int($_POST['register_id'] ?? 0);
        $real_cash   = sanitize_float($_POST['real_cash'] ?? 0);
        $observations= sanitize_string($_POST['observations'] ?? '', 500);

        $stmtReg = $pdo->prepare("SELECT * FROM cash_registers WHERE id = ? AND shop_id = ? AND status = 'open'");
        $stmtReg->execute([$register_id, $shop_id]);
        $register = $stmtReg->fetch();

        if (!$register) {
            set_flash_message('danger', 'No se encontró una caja abierta para cerrar.');
            header("Location: ../owner.php?tab=finanzas&subtab=caja");
            exit();
        }

        $open_date = $register['open_date'];

        $stmtApp = $pdo->prepare("
            SELECT 
                SUM(CASE WHEN payment_method = 'efectivo' THEN c.price ELSE 0 END) as app_cash,
                SUM(CASE WHEN payment_method = 'transferencia' THEN c.price ELSE 0 END) as app_trans,
                SUM(CASE WHEN payment_method = 'mercado_pago' THEN c.price ELSE 0 END) as app_mp,
                SUM(CASE WHEN payment_method = 'tarjeta' THEN c.price ELSE 0 END) as app_card
            FROM appointments a
            JOIN catalog c ON a.item_id = c.id
            WHERE a.shop_id = ? AND a.appointment_datetime >= ? AND a.status != 'cancelled'
        ");
        $stmtApp->execute([$shop_id, $open_date]);
        $appCalc = $stmtApp->fetch();

        $stmtSale = $pdo->prepare("
            SELECT 
                SUM(CASE WHEN payment_method = 'efectivo' THEN total_amount ELSE 0 END) as sale_cash,
                SUM(CASE WHEN payment_method = 'transferencia' THEN total_amount ELSE 0 END) as sale_trans,
                SUM(CASE WHEN payment_method = 'mercado_pago' THEN total_amount ELSE 0 END) as sale_mp,
                SUM(CASE WHEN payment_method = 'tarjeta' THEN total_amount ELSE 0 END) as sale_card
            FROM sales
            WHERE shop_id = ? AND created_at >= ?
        ");
        $stmtSale->execute([$shop_id, $open_date]);
        $saleCalc = $stmtSale->fetch();

        $stmtExp = $pdo->prepare("
            SELECT SUM(amount) as exp_cash
            FROM expenses
            WHERE shop_id = ? AND created_at >= ? AND payment_method = 'efectivo'
        ");
        $stmtExp->execute([$shop_id, $open_date]);
        $expCalc = $stmtExp->fetch();

        $cash_sales     = (float)($appCalc['app_cash'] ?? 0) + (float)($saleCalc['sale_cash'] ?? 0);
        $transfer_sales = (float)($appCalc['app_trans'] ?? 0) + (float)($saleCalc['sale_trans'] ?? 0);
        $mp_sales       = (float)($appCalc['app_mp'] ?? 0) + (float)($saleCalc['sale_mp'] ?? 0);
        $card_sales     = (float)($appCalc['app_card'] ?? 0) + (float)($saleCalc['sale_card'] ?? 0);
        $cash_expenses  = (float)($expCalc['exp_cash'] ?? 0);

        $initial        = (float)$register['initial_amount'];
        $expected_cash  = $initial + $cash_sales - $cash_expenses;
        $difference     = $real_cash - $expected_cash;

        $stmtClose = $pdo->prepare("
            UPDATE cash_registers 
            SET closed_by = ?, close_date = NOW(), cash_sales = ?, transfer_sales = ?, mp_sales = ?, card_sales = ?, cash_expenses = ?, expected_cash = ?, real_cash = ?, difference = ?, status = 'closed', observations = ?
            WHERE id = ? AND shop_id = ?
        ");
        $stmtClose->execute([
            $user_id,
            $cash_sales,
            $transfer_sales,
            $mp_sales,
            $card_sales,
            $cash_expenses,
            $expected_cash,
            $real_cash,
            $difference,
            $observations,
            $register_id,
            $shop_id
        ]);

        $diffMsg = ($difference >= 0) ? 'Sobrante: +$' . number_format($difference, 2) : 'Faltante: -$' . number_format(abs($difference), 2);

        log_audit('CIERRE_CAJA', 'caja', $register_id, "Cierre de caja. Efectivo real contado: $" . number_format($real_cash, 2) . " vs Esperado: $" . number_format($expected_cash, 2) . " ($diffMsg)");

        set_flash_message('success', '¡Caja cerrada correctamente! Arqueo finalizado. (' . $diffMsg . ')');
    } 
    elseif ($action === 'reopen_cash') {
        $register_id = sanitize_int($_POST['register_id'] ?? 0);

        $stmtReopen = $pdo->prepare("
            UPDATE cash_registers 
            SET status = 'open', close_date = NULL, closed_by = NULL
            WHERE id = ? AND shop_id = ?
        ");
        $stmtReopen->execute([$register_id, $shop_id]);

        log_audit('REABRIR_CAJA', 'caja', $register_id, "Reapertura de caja autorizada por Administrador");

        set_flash_message('warning', 'Caja reabierta autorizada por el Administrador.');
    }
} catch (Exception $e) {
    set_flash_message('danger', 'Error al gestionar caja: ' . $e->getMessage());
}

header("Location: ../owner.php?tab=finanzas&subtab=caja");
exit();

<?php
require_once __DIR__ . '/../includes/auth.php';
$user = require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: ../barber.php");
    exit();
}

verify_csrf_token();

$date           = validate_date($_POST['date'] ?? '') ? $_POST['date'] : date('Y-m-d');
$hour           = sanitize_string($_POST['hour'] ?? '', 10);
$client_name    = sanitize_string($_POST['client_name'] ?? '', 150);
$client_phone   = sanitize_string($_POST['client_phone'] ?? '', 50);
$item_id        = sanitize_int($_POST['item_id'] ?? 0);
$payment_method = sanitize_string($_POST['payment_method'] ?? 'efectivo', 50);
$observation    = sanitize_string($_POST['observation'] ?? '', 500);
$barber_id      = sanitize_int($_POST['barber_id'] ?? $user['id']);

if (empty($date) || empty($hour) || empty($client_name) || $item_id <= 0) {
    set_flash_message('danger', 'Por favor completa todos los campos obligatorios para agendar el turno.');
    header("Location: ../barber.php?date=" . urlencode($date));
    exit();
}

$datetime = $date . ' ' . $hour . ':00';

$barber_filter = sanitize_string($_POST['barber_filter'] ?? '', 50);
$filter_param  = !empty($barber_filter) ? '&barber_id=' . urlencode($barber_filter) : '';

$shop_id = get_current_shop_id($user);

try {
    $stmtCheck = $pdo->prepare("
        SELECT COUNT(*) FROM appointments 
        WHERE shop_id = ? AND barber_id = ? AND appointment_datetime = ? AND status != 'cancelled'
    ");
    $stmtCheck->execute([$shop_id, $barber_id, $datetime]);
    if ($stmtCheck->fetchColumn() > 0) {
        set_flash_message('danger', 'El horario seleccionado (' . $hour . ' hs) ya está reservado para este barbero.');
        $redirect = is_shop_admin($user) ? '../owner.php?tab=my_grid&date=' . urlencode($date) . $filter_param : '../barber.php?date=' . urlencode($date) . $filter_param;
        header("Location: " . $redirect);
        exit();
    }

    $stmt = $pdo->prepare("
        INSERT INTO appointments (shop_id, barber_id, client_name, client_phone, item_id, appointment_datetime, payment_method, observation, status, is_direct_sale) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'scheduled', 0)
    ");
    $stmt->execute([
        $shop_id,
        $barber_id,
        $client_name,
        $client_phone,
        $item_id,
        $datetime,
        $payment_method,
        $observation
    ]);

    $app_id = $pdo->lastInsertId();

    log_audit('CREAR', 'turno', $app_id, "Se agendó un turno para el cliente '$client_name' el $date a las $hour hs");

    set_flash_message('success', 'Turno agendado correctamente para ' . htmlspecialchars($client_name));
} catch (Exception $e) {
    set_flash_message('danger', 'Error al agendar turno: ' . $e->getMessage());
}

$redirect = is_shop_admin($user) ? '../owner.php?tab=my_grid&date=' . urlencode($date) . $filter_param : '../barber.php?date=' . urlencode($date) . $filter_param;
header("Location: " . $redirect);
exit();

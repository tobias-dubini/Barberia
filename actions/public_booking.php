<?php
require_once __DIR__ . '/../includes/auth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: ../index.php");
    exit();
}

verify_csrf_token();

$shop_id        = sanitize_int($_POST['shop_id'] ?? 1);
$barber_id      = sanitize_int($_POST['barber_id'] ?? 0);
$item_id        = sanitize_int($_POST['item_id'] ?? 0);
$date           = validate_date($_POST['date'] ?? '') ? $_POST['date'] : '';
$hour           = sanitize_int($_POST['hour'] ?? 0);
$client_name    = sanitize_string($_POST['client_name'] ?? '', 150);
$client_phone   = sanitize_string($_POST['client_phone'] ?? '', 50);
$payment_method = sanitize_string($_POST['payment_method'] ?? 'efectivo', 50);
$observation    = sanitize_string($_POST['observation'] ?? '', 500);

if ($barber_id <= 0 || $item_id <= 0 || empty($date) || $hour < 10 || $hour > 21 || empty($client_name) || empty($client_phone)) {
    set_flash_message('danger', 'Por favor completa todos los campos obligatorios para agendar tu turno.');
    header("Location: ../index.php?barber_id={$barber_id}&date=" . urlencode($date) . "#reserva");
    exit();
}

if (date('w', strtotime($date)) == 0) {
    set_flash_message('danger', 'El local permanece cerrado los domingos. Elige otro día de la semana.');
    header("Location: ../index.php?barber_id={$barber_id}&date=" . urlencode($date) . "#reserva");
    exit();
}

if ($date < date('Y-m-d')) {
    set_flash_message('danger', 'No puedes reservar turnos en fechas pasadas.');
    header("Location: ../index.php#reserva");
    exit();
}

if ($date === date('Y-m-d') && $hour <= (int)date('H')) {
    set_flash_message('danger', 'Lo sentimos, ese horario ya ha transcurrido. Por favor selecciona un horario disponible futuro.');
    header("Location: ../index.php?barber_id={$barber_id}&date=" . urlencode($date) . "#reserva");
    exit();
}


$datetime = $date . ' ' . sprintf('%02d:00:00', $hour);

try {
    $stmtCheck = $pdo->prepare("
        SELECT COUNT(*) FROM appointments 
        WHERE shop_id = ? AND barber_id = ? AND appointment_datetime = ? AND status != 'cancelled'
    ");
    $stmtCheck->execute([$shop_id, $barber_id, $datetime]);
    if ($stmtCheck->fetchColumn() > 0) {
        set_flash_message('danger', 'Lo sentimos, el horario de las ' . sprintf('%02d:00', $hour) . ' hs ya ha sido reservado. Por favor elige otro horario disponible.');
        header("Location: ../index.php?barber_id={$barber_id}&date=" . urlencode($date) . "#reserva");
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

    $appt_id = $pdo->lastInsertId();

    log_audit('RESERVA_PUBLICA', 'turno', $appt_id, "Reserva pública web realizada por '$client_name' ($client_phone) para el $date $hour:00 hs");

    set_flash_message('success', '¡Turno reservado con éxito!');
    header("Location: ../index.php?booking_success=1&id={$appt_id}#reserva");
    exit();

} catch (Exception $e) {
    set_flash_message('danger', 'Error al procesar la reserva: ' . $e->getMessage());
    header("Location: ../index.php#reserva");
    exit();
}

<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../config/db.php';

$shop_id   = (int)($_GET['shop_id'] ?? 1);
$barber_id = (int)($_GET['barber_id'] ?? 0);
$date      = trim($_GET['date'] ?? '');

if (empty($date) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) || $barber_id <= 0) {
    echo json_encode([
        'success' => false,
        'message' => 'Parámetros inválidos.'
    ]);
    exit();
}

// Verificar si es Domingo (0 = Domingo)
$day_of_week = date('w', strtotime($date));
if ($day_of_week == 0) {
    echo json_encode([
        'success'   => true,
        'is_sunday' => true,
        'message'   => 'El local permanece cerrado los domingos.',
        'slots'     => []
    ]);
    exit();
}

try {
    $next_date_str = date('Y-m-d', strtotime($date . ' +1 day'));
    $stmt = $pdo->prepare("
        SELECT HOUR(appointment_datetime) as hour_slot 
        FROM appointments 
        WHERE shop_id = ? AND barber_id = ? 
          AND appointment_datetime >= ? AND appointment_datetime < ? 
          AND status != 'cancelled'
    ");
    $stmt->execute([$shop_id, $barber_id, $date . ' 00:00:00', $next_date_str . ' 00:00:00']);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $taken_hours = [];
    foreach ($rows as $r) {
        $taken_hours[(int)$r['hour_slot']] = true;
    }

    $today        = date('Y-m-d');
    $current_hour = (int)date('H');

    $slots = [];
    for ($h = 10; $h <= 21; $h++) {
        $is_taken = isset($taken_hours[$h]);
        $is_past  = ($date === $today && $h <= $current_hour);
        $is_available = !$is_taken && !$is_past;

        $slots[] = [
            'hour'           => $h,
            'formatted_time' => sprintf('%02d:00', $h),
            'is_available'   => $is_available,
            'is_past'        => $is_past,
            'is_taken'       => $is_taken
        ];
    }

    echo json_encode([
        'success'   => true,
        'is_sunday' => false,
        'date'      => $date,
        'barber_id' => $barber_id,
        'slots'     => $slots
    ]);
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error al consultar disponibilidad: ' . $e->getMessage()
    ]);
}

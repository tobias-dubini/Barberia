<?php
require_once __DIR__ . '/../includes/auth.php';
$user = require_role('owner');

$type    = trim($_GET['type'] ?? 'gastos');
$shop_id = $user['shop_id'];
$month   = trim($_GET['month'] ?? date('Y-m'));

header('Content-Type: text/csv; charset=utf-8');

// Escribir BOM UTF-8 para abrir directamente en Microsoft Excel
echo "\xEF\xBB\xBF";

$output = fopen('php://output', 'w');

if ($type === 'gastos') {
    $filename = "reporte_gastos_" . $month . ".csv";
    header('Content-Disposition: attachment; filename="' . $filename . '"');

    fputcsv($output, ['ID Gasto', 'Fecha', 'Categoria', 'Descripcion', 'Monto ($)', 'Metodo de Pago', 'Observaciones'], ';');

    $stmt = $pdo->prepare("SELECT * FROM expenses WHERE shop_id = ? AND DATE_FORMAT(date, '%Y-%m') = ? ORDER BY date DESC");
    $stmt->execute([$shop_id, $month]);
    $expenses = $stmt->fetchAll();

    $sum = 0;
    foreach ($expenses as $e) {
        $sum += (float)$e['amount'];
        fputcsv($output, [
            $e['id'],
            date('d/m/Y', strtotime($e['date'])),
            strtoupper($e['category']),
            $e['description'],
            number_format((float)$e['amount'], 2, ',', ''),
            strtoupper($e['payment_method']),
            $e['observations'] ?? ''
        ], ';');
    }

    fputcsv($output, [], ';');
    fputcsv($output, ['TOTAL GASTOS', '', '', '', number_format($sum, 2, ',', ''), '', ''], ';');

} elseif ($type === 'barberos') {
    $filename = "reporte_pagos_barberos_" . $month . ".csv";
    header('Content-Disposition: attachment; filename="' . $filename . '"');

    fputcsv($output, ['ID Pago', 'Fecha', 'Barbero', 'Tipo Movimiento', 'Monto ($)', 'Metodo de Pago', 'N° Recibo', 'Descripcion'], ';');

    $stmt = $pdo->prepare("
        SELECT bp.*, u.name as barber_name 
        FROM barber_payouts bp 
        JOIN users u ON bp.barber_id = u.id 
        WHERE bp.shop_id = ? AND DATE_FORMAT(bp.date, '%Y-%m') = ? 
        ORDER BY bp.date DESC
    ");
    $stmt->execute([$shop_id, $month]);
    $payouts = $stmt->fetchAll();

    $sum = 0;
    foreach ($payouts as $p) {
        $sum += (float)$p['amount'];
        fputcsv($output, [
            $p['id'],
            date('d/m/Y', strtotime($p['date'])),
            $p['barber_name'],
            strtoupper($p['type']),
            number_format((float)$p['amount'], 2, ',', ''),
            strtoupper($p['payment_method']),
            $p['receipt_number'] ?? '',
            $p['description'] ?? ''
        ], ';');
    }

    fputcsv($output, [], ';');
    fputcsv($output, ['TOTAL PAGADO', '', '', '', number_format($sum, 2, ',', ''), '', '', ''], ';');

} elseif ($type === 'turnos') {
    $filename = "reporte_turnos_" . $month . ".csv";
    header('Content-Disposition: attachment; filename="' . $filename . '"');

    fputcsv($output, ['ID Turno', 'Fecha/Hora', 'Cliente', 'Telefono', 'Barbero', 'Servicio', 'Precio ($)', 'Metodo Pago', 'Estado'], ';');

    $stmt = $pdo->prepare("
        SELECT a.id, a.appointment_datetime, a.client_name, a.client_phone, u.name as barber_name, c.name as service_name, c.price, a.payment_method, a.status
        FROM appointments a
        JOIN users u ON a.barber_id = u.id
        JOIN catalog c ON a.item_id = c.id
        WHERE a.shop_id = ? AND DATE_FORMAT(a.appointment_datetime, '%Y-%m') = ?
        ORDER BY a.appointment_datetime DESC
    ");
    $stmt->execute([$shop_id, $month]);
    $apps = $stmt->fetchAll();

    $sum = 0;
    foreach ($apps as $a) {
        if ($a['status'] !== 'cancelled') {
            $sum += (float)$a['price'];
        }
        fputcsv($output, [
            $a['id'],
            date('d/m/Y H:i', strtotime($a['appointment_datetime'])),
            $a['client_name'],
            $a['client_phone'],
            $a['barber_name'],
            $a['service_name'],
            number_format((float)$a['price'], 2, ',', ''),
            strtoupper($a['payment_method']),
            strtoupper($a['status'])
        ], ';');
    }

    fputcsv($output, [], ';');
    fputcsv($output, ['TOTAL RECAUDADO', '', '', '', '', '', number_format($sum, 2, ',', ''), '', ''], ';');
}

fclose($output);
exit();

<?php
require_once __DIR__ . '/../includes/auth.php';
$user = require_role('owner');

$month = trim($_GET['month'] ?? date('Y-m'));

$stmt = $pdo->prepare("
    SELECT 
        dc.id,
        dc.date,
        u.name as barber_name,
        dc.total_efectivo,
        dc.total_transferencia,
        dc.total_ingresos,
        dc.total_comisiones,
        dc.neto_barberia,
        dc.is_paid
    FROM daily_closures dc
    JOIN users u ON dc.barber_id = u.id
    WHERE dc.shop_id = ? AND DATE_FORMAT(dc.date, '%Y-%m') = ?
    ORDER BY dc.date DESC, u.name ASC
");
$stmt->execute([$user['shop_id'], $month]);
$closures = $stmt->fetchAll();

$filename = "cierre_caja_barberia_" . $month . ".csv";

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');

// Escribir BOM UTF-8 para compatibilidad directa con Microsoft Excel en español
echo "\xEF\xBB\xBF";

$output = fopen('php://output', 'w');

// Encabezados en español
fputcsv($output, [
    'ID Cierre',
    'Fecha',
    'Barbero',
    'Cobrado Efectivo ($)',
    'Cobrado Digital/MP ($)',
    'Ingreso Total ($)',
    'Pago a Barbero (Comisión) ($)',
    'Neto Barbería ($)',
    'Estado del Pago'
], ';');

$sum_ingresos = 0;
$sum_comisiones = 0;
$sum_neto = 0;

foreach ($closures as $row) {
    $sum_ingresos   += (float)$row['total_ingresos'];
    $sum_comisiones += (float)$row['total_comisiones'];
    $sum_neto       += (float)$row['neto_barberia'];

    fputcsv($output, [
        $row['id'],
        date('d/m/Y', strtotime($row['date'])),
        $row['barber_name'],
        number_format((float)$row['total_efectivo'], 2, ',', ''),
        number_format((float)$row['total_transferencia'], 2, ',', ''),
        number_format((float)$row['total_ingresos'], 2, ',', ''),
        number_format((float)$row['total_comisiones'], 2, ',', ''),
        number_format((float)$row['neto_barberia'], 2, ',', ''),
        $row['is_paid'] ? 'PAGADO' : 'PENDIENTE'
    ], ';');
}

// Fila de Totales
fputcsv($output, [], ';');
fputcsv($output, [
    'TOTALES',
    '',
    '',
    '',
    '',
    number_format($sum_ingresos, 2, ',', ''),
    number_format($sum_comisiones, 2, ',', ''),
    number_format($sum_neto, 2, ',', ''),
    ''
], ';');

fclose($output);
exit();

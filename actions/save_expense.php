<?php
require_once __DIR__ . '/../includes/auth.php';
$user = require_role('owner');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: ../owner.php?tab=finanzas&subtab=gastos");
    exit();
}

verify_csrf_token();

$id             = sanitize_int($_POST['id'] ?? 0);
$date           = validate_date($_POST['date'] ?? '') ? $_POST['date'] : date('Y-m-d');
$category       = sanitize_string($_POST['category'] ?? 'otros', 50);
$description    = sanitize_string($_POST['description'] ?? '', 255);
$amount         = sanitize_float($_POST['amount'] ?? 0);
$payment_method = sanitize_string($_POST['payment_method'] ?? 'efectivo', 50);
$observations   = sanitize_string($_POST['observations'] ?? '', 500);
$shop_id        = $user['shop_id'];

if (empty($description) || $amount <= 0) {
    set_flash_message('danger', 'Por favor ingresa una descripción válida y un monto mayor a 0.');
    header("Location: ../owner.php?tab=finanzas&subtab=gastos");
    exit();
}

try {
    if ($id > 0) {
        $stmt = $pdo->prepare("
            UPDATE expenses 
            SET date = ?, category = ?, description = ?, amount = ?, payment_method = ?, observations = ?
            WHERE id = ? AND shop_id = ?
        ");
        $stmt->execute([$date, $category, $description, $amount, $payment_method, $observations, $id, $shop_id]);

        log_audit('EDITAR', 'gasto', $id, "Se actualizó el gasto '$description' por $" . number_format($amount, 2));

        set_flash_message('success', 'Gasto actualizado correctamente.');
    } else {
        $stmt = $pdo->prepare("
            INSERT INTO expenses (shop_id, date, category, description, amount, payment_method, observations)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([$shop_id, $date, $category, $description, $amount, $payment_method, $observations]);

        $new_id = $pdo->lastInsertId();

        log_audit('CREAR', 'gasto', $new_id, "Se registró un nuevo gasto de $" . number_format($amount, 2) . " ('$description')");

        set_flash_message('success', 'Gasto de $' . number_format($amount, 2) . ' registrado exitosamente.');
    }
} catch (Exception $e) {
    set_flash_message('danger', 'Error al guardar gasto: ' . $e->getMessage());
}

header("Location: ../owner.php?tab=finanzas&subtab=gastos");
exit();

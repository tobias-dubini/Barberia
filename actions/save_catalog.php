<?php
require_once __DIR__ . '/../includes/auth.php';
$user = require_shop_admin();
$shop_id = get_current_shop_id($user);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: ../owner.php?tab=catalog");
    exit();
}

verify_csrf_token();

$id                 = sanitize_int($_POST['id'] ?? 0);
$type               = sanitize_string($_POST['type'] ?? 'service', 50);
$name               = sanitize_string($_POST['name'] ?? '', 150);
$price              = sanitize_float($_POST['price'] ?? 0);
$commission_percent = sanitize_float($_POST['commission_percent'] ?? 0);

if (empty($name) || $price < 0 || $commission_percent < 0) {
    set_flash_message('danger', 'Por favor ingresa datos válidos para el ítem del catálogo.');
    header("Location: ../owner.php?tab=catalog");
    exit();
}

try {
    if ($id > 0) {
        $stmt = $pdo->prepare("UPDATE catalog SET type = ?, name = ?, price = ?, commission_percent = ? WHERE id = ? AND shop_id = ?");
        $stmt->execute([$type, $name, $price, $commission_percent, $id, $shop_id]);

        log_audit('EDITAR', 'servicio', $id, "Se actualizó el servicio '$name' ($" . number_format($price, 2) . ")");

        set_flash_message('success', 'Ítem del catálogo actualizado correctamente.');
    } else {
        $stmt = $pdo->prepare("INSERT INTO catalog (shop_id, name, type, price, commission_percent) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$shop_id, $name, $type, $price, $commission_percent]);

        $new_id = $pdo->lastInsertId();

        log_audit('CREAR', 'servicio', $new_id, "Se creó el servicio '$name' ($" . number_format($price, 2) . ")");

        set_flash_message('success', 'Nuevo ítem agregado al catálogo.');
    }
} catch (Exception $e) {
    set_flash_message('danger', 'Error al guardar ítem del catálogo: ' . $e->getMessage());
}

header("Location: ../owner.php?tab=catalog");
exit();

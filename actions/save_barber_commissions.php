<?php
require_once __DIR__ . '/../includes/auth.php';
$user = require_shop_admin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: ../owner.php?tab=barberos");
    exit();
}

verify_csrf_token();

$shop_id   = get_current_shop_id($user);
$barber_id = sanitize_int($_POST['barber_id'] ?? 0);

if ($barber_id <= 0) {
    set_flash_message('danger', 'Barbero no válido.');
    header("Location: ../owner.php?tab=barberos");
    exit();
}

// Obtener nombre del barbero
$stmtB = $pdo->prepare("SELECT name FROM users WHERE id = ? AND shop_id = ?");
$stmtB->execute([$barber_id, $shop_id]);
$barber = $stmtB->fetch();

if (!$barber) {
    set_flash_message('danger', 'El barbero especificado no existe.');
    header("Location: ../owner.php?tab=barberos");
    exit();
}

$commissions = $_POST['commissions'] ?? [];
$prices      = $_POST['prices'] ?? [];

try {
    $stmtUpsert = $pdo->prepare("
        INSERT INTO barber_commissions (shop_id, barber_id, catalog_id, commission_percent, price)
        VALUES (?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE 
            commission_percent = VALUES(commission_percent),
            price = VALUES(price)
    ");

    $updated_count = 0;
    foreach ($commissions as $cat_id => $percent_str) {
        $catalog_id = sanitize_int($cat_id);
        $percent    = sanitize_float($percent_str, 0);
        if ($percent > 100) $percent = 100.00;

        $raw_price = isset($prices[$catalog_id]) ? trim((string)$prices[$catalog_id]) : '';
        $custom_price = null;
        if ($raw_price !== '') {
            $parsed_price = sanitize_float($raw_price, 0);
            if ($parsed_price > 0) {
                $custom_price = $parsed_price;
            }
        }

        if ($catalog_id > 0) {
            $stmtUpsert->execute([$shop_id, $barber_id, $catalog_id, $percent, $custom_price]);
            $updated_count++;
        }
    }

    log_audit('ACTUALIZAR', 'tarifas_barbero', $barber_id, "Se actualizaron las tarifas y comisiones personalizadas para el barbero '" . $barber['name'] . "' ($updated_count ítems)");

    set_flash_message('success', 'Precios y comisiones personalizados actualizados correctamente para ' . htmlspecialchars($barber['name']) . '.');
} catch (Exception $e) {
    set_flash_message('danger', 'Error al guardar precios y comisiones: ' . $e->getMessage());
}

$referer = $_POST['redirect_url'] ?? ('../owner.php?tab=barberos&edit_commissions=' . $barber_id);
header("Location: " . $referer);
exit();

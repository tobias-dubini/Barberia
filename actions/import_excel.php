<?php
require_once __DIR__ . '/../includes/auth.php';
$user = require_role('owner');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: ../owner.php?tab=finanzas&subtab=importador");
    exit();
}

$entity  = trim($_POST['entity'] ?? 'expenses');
$shop_id = $user['shop_id'];

if (!isset($_FILES['import_file']) || $_FILES['import_file']['error'] !== UPLOAD_ERR_OK) {
    set_flash_message('danger', 'Por favor selecciona un archivo CSV o Excel válido.');
    header("Location: ../owner.php?tab=finanzas&subtab=importador");
    exit();
}

$fileTmpPath = $_FILES['import_file']['tmp_name'];
$fileName    = $_FILES['import_file']['name'];
$ext         = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

if (!in_array($ext, ['csv', 'txt', 'xlsx'])) {
    set_flash_message('danger', 'Formato de archivo no soportado. Por favor sube un archivo .csv o .xlsx');
    header("Location: ../owner.php?tab=finanzas&subtab=importador");
    exit();
}

$imported = 0;
$skipped  = 0;
$errors   = 0;
$log      = [];

try {
    $handle = fopen($fileTmpPath, 'r');
    if (!$handle) {
        throw new Exception("No se pudo abrir el archivo cargado.");
    }

    // Remover BOM si está presente
    $bom = fread($handle, 3);
    if ($bom !== "\xEF\xBB\xBF") {
        rewind($handle);
    }

    // Detectar delimitador (coma o punto y coma)
    $firstLine = fgets($handle);
    rewind($handle);
    if ($bom === "\xEF\xBB\xBF") {
        fread($handle, 3);
    }

    $delimiter = (substr_count($firstLine, ';') > substr_count($firstLine, ',')) ? ';' : ',';

    // Leer encabezados
    $headers = fgetcsv($handle, 1000, $delimiter);

    while (($data = fgetcsv($handle, 1000, $delimiter)) !== FALSE) {
        // Ignorar filas vacías
        if (empty(array_filter($data))) continue;

        if ($entity === 'expenses') {
            // Espera: Fecha, Categoria, Descripcion, Monto, MetodoPago
            $date        = trim($data[0] ?? date('Y-m-d'));
            $category    = strtolower(trim($data[1] ?? 'otros'));
            $description = trim($data[2] ?? '');
            $amount      = (float)str_replace(',', '.', trim($data[3] ?? 0));
            $method      = strtolower(trim($data[4] ?? 'efectivo'));

            if (empty($description) || $amount <= 0) {
                $errors++;
                continue;
            }

            // Comprobar duplicado exacto
            $stmtCheck = $pdo->prepare("SELECT id FROM expenses WHERE shop_id = ? AND date = ? AND description = ? AND amount = ?");
            $stmtCheck->execute([$shop_id, $date, $description, $amount]);
            if ($stmtCheck->fetch()) {
                $skipped++;
                continue;
            }

            $stmtIns = $pdo->prepare("INSERT INTO expenses (shop_id, date, category, description, amount, payment_method) VALUES (?, ?, ?, ?, ?, ?)");
            $stmtIns->execute([$shop_id, $date, $category, $description, $amount, $method]);
            $imported++;
        }
        elseif ($entity === 'services') {
            // Espera: Nombre, Tipo, Precio, Comision
            $name  = trim($data[0] ?? '');
            $type  = strtolower(trim($data[1] ?? 'service'));
            $price = (float)str_replace(',', '.', trim($data[2] ?? 0));
            $comm  = (float)str_replace(',', '.', trim($data[3] ?? 50));

            if (empty($name) || $price <= 0) {
                $errors++;
                continue;
            }

            $stmtCheck = $pdo->prepare("SELECT id FROM catalog WHERE shop_id = ? AND name = ?");
            $stmtCheck->execute([$shop_id, $name]);
            if ($stmtCheck->fetch()) {
                $skipped++;
                continue;
            }

            $stmtIns = $pdo->prepare("INSERT INTO catalog (shop_id, name, type, price, commission_percent) VALUES (?, ?, ?, ?, ?)");
            $stmtIns->execute([$shop_id, $name, $type, $price, $comm]);
            $imported++;
        }
        elseif ($entity === 'barbers') {
            // Espera: Nombre, Email
            $name  = trim($data[0] ?? '');
            $email = trim($data[1] ?? '');

            if (empty($name)) {
                $errors++;
                continue;
            }

            if (empty($email)) {
                $slug = strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $name));
                $email = $slug . rand(100, 999) . '@barberia.com';
            }

            $stmtCheck = $pdo->prepare("SELECT id FROM users WHERE email = ?");
            $stmtCheck->execute([$email]);
            if ($stmtCheck->fetch()) {
                $skipped++;
                continue;
            }

            $hash = password_hash('123456', PASSWORD_BCRYPT);
            $stmtIns = $pdo->prepare("INSERT INTO users (shop_id, name, email, password, role, is_active) VALUES (?, ?, ?, ?, 'barber', 1)");
            $stmtIns->execute([$shop_id, $name, $email, $hash]);
            $imported++;
        }
        else {
            $skipped++;
        }
    }

    fclose($handle);

    $_SESSION['import_summary'] = [
        'imported' => $imported,
        'skipped'  => $skipped,
        'errors'   => $errors,
        'entity'   => $entity
    ];

    set_flash_message('success', "Proceso de importación finalizado: {$imported} importados, {$skipped} omitidos por duplicado, {$errors} errores.");
} catch (Exception $e) {
    set_flash_message('danger', 'Error al procesar archivo: ' . $e->getMessage());
}

header("Location: ../owner.php?tab=finanzas&subtab=importador");
exit();

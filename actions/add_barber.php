<?php
require_once __DIR__ . '/../includes/auth.php';
$user = require_shop_admin();
$shop_id = get_current_shop_id($user);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: ../owner.php?tab=barberos");
    exit();
}

verify_csrf_token();

$name  = sanitize_string($_POST['name'] ?? '', 150);
$email = sanitize_email($_POST['email'] ?? '');

if (empty($name)) {
    set_flash_message('danger', 'Por favor ingresa el nombre completo del barbero.');
    header("Location: ../owner.php?tab=barberos");
    exit();
}

if (empty($email)) {
    $slug = strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $name));
    $email = $slug . rand(100, 999) . '@barberia.com';
}

// Procesar foto subida
$photoPath = null;
if (isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
    $fileTmpPath = $_FILES['photo']['tmp_name'];
    $fileName    = $_FILES['photo']['name'];
    $fileExtension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
    
    $allowedExtensions = ['jpg', 'jpeg', 'png', 'webp', 'gif'];
    if (in_array($fileExtension, $allowedExtensions)) {
        $uploadFileDir = __DIR__ . '/../uploads/barbers/';
        if (!is_dir($uploadFileDir)) {
            mkdir($uploadFileDir, 0755, true);
        }
        $newFileName = 'barber_' . time() . '_' . rand(1000, 9999) . '.' . $fileExtension;
        $destPath = $uploadFileDir . $newFileName;
        
        if (move_uploaded_file($fileTmpPath, $destPath)) {
            $photoPath = 'uploads/barbers/' . $newFileName;
        }
    }
}

try {
    $stmtCheck = $pdo->prepare("SELECT id FROM users WHERE email = ? AND shop_id = ?");
    $stmtCheck->execute([$email, $shop_id]);
    if ($stmtCheck->fetch()) {
        set_flash_message('danger', 'Ya existe un barbero registrado con ese correo o identificador.');
        header("Location: ../owner.php?tab=barberos");
        exit();
    }

    $hash = password_hash('123456', PASSWORD_BCRYPT);
    $stmt = $pdo->prepare("INSERT INTO users (shop_id, name, email, photo, password, role, is_active) VALUES (?, ?, ?, ?, ?, 'barbero', 1)");
    $stmt->execute([$shop_id, $name, $email, $photoPath, $hash]);

    $new_barber_id = $pdo->lastInsertId();

    log_audit('CREAR', 'barbero', $new_barber_id, "Se registró el barbero '$name' ($email)");

    set_flash_message('success', 'Barbero ' . htmlspecialchars($name) . ' agregado exitosamente.');
} catch (Exception $e) {
    set_flash_message('danger', 'Error al agregar barbero: ' . $e->getMessage());
}

header("Location: ../owner.php?tab=barberos");
exit();

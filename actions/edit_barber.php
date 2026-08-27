<?php
require_once __DIR__ . '/../includes/auth.php';
$user = require_shop_admin();
$shop_id = get_current_shop_id($user);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: ../owner.php?tab=barberos");
    exit();
}

verify_csrf_token();

$id    = sanitize_int($_POST['id'] ?? 0);
$name  = sanitize_string($_POST['name'] ?? '', 150);
$email = sanitize_email($_POST['email'] ?? '');

if ($id <= 0 || empty($name)) {
    set_flash_message('danger', 'Por favor ingresa los datos completos del barbero.');
    header("Location: ../owner.php?tab=barberos");
    exit();
}

try {
    $stmtCheck = $pdo->prepare("SELECT id, photo FROM users WHERE id = ? AND shop_id = ?");
    $stmtCheck->execute([$id, $shop_id]);
    $currentBarber = $stmtCheck->fetch();
    if (!$currentBarber) {
        set_flash_message('danger', 'Barbero no encontrado.');
        header("Location: ../owner.php?tab=barberos");
        exit();
    }

    if (!empty($email)) {
        $stmtEmail = $pdo->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
        $stmtEmail->execute([$email, $id]);
        if ($stmtEmail->fetch()) {
            set_flash_message('danger', 'El correo electrónico ya está en uso por otro usuario.');
            header("Location: ../owner.php?tab=barberos&edit_barber=" . $id);
            exit();
        }
    }

    $photoPath = $currentBarber['photo'];
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

    $stmt = $pdo->prepare("UPDATE users SET name = ?, email = ?, photo = ? WHERE id = ? AND shop_id = ?");
    $stmt->execute([$name, $email, $photoPath, $id, $shop_id]);

    log_audit('EDITAR', 'barbero', $id, "Se actualizaron los datos del barbero '$name' ($email)");

    set_flash_message('success', 'Barbero ' . htmlspecialchars($name) . ' actualizado correctamente.');
} catch (Exception $e) {
    set_flash_message('danger', 'Error al actualizar barbero: ' . $e->getMessage());
}

header("Location: ../owner.php?tab=barberos");
exit();

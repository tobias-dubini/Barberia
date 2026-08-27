<?php
if (session_status() === PHP_SESSION_NONE) {
    $is_secure = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on';
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'domain'   => '',
        'secure'   => $is_secure,
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
    session_start();
}

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/rbac.php';

function is_logged_in() {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

function get_logged_user() {
    static $cached_user = null;
    static $already_checked = false;

    if ($already_checked) {
        return $cached_user;
    }

    global $pdo;
    if (!is_logged_in()) {
        $already_checked = true;
        return null;
    }
    
    $stmt = $pdo->prepare("SELECT u.*, COALESCE(s.name, 'Brotherhood Barbershop') as shop_name FROM users u LEFT JOIN shops s ON u.shop_id = s.id WHERE u.id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch();

    if (!$user || !$user['is_active']) {
        session_destroy();
        $cached_user = null;
        $already_checked = true;
        return null;
    }

    $cached_user = $user;
    $already_checked = true;
    return $cached_user;
}

function require_login() {
    $user = get_logged_user();
    if (!$user) {
        header("Location: login.php");
        exit();
    }
    return $user;
}

function require_role($required_role) {
    $user = require_login();
    if (!has_role($required_role, $user) && !is_super_admin($user)) {
        set_flash_message('danger', 'Acceso denegado. Únicamente usuarios autorizados pueden ingresar.');
        header("Location: login.php");
        exit();
    }
    return $user;
}

function set_flash_message($type, $message) {
    $_SESSION['flash'] = [
        'type' => $type, // 'success' o 'danger'
        'message' => $message
    ];
}

function get_flash_message() {
    if (isset($_SESSION['flash'])) {
        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $flash;
    }
    return null;
}

// ==================== PROTECCIÓN CSRF ====================
function generate_csrf_token() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_token() {
    return generate_csrf_token();
}

function csrf_field() {
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') . '">';
}

function verify_csrf_token() {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $token = $_POST['csrf_token'] ?? '';
        if (empty($token) || empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $token)) {
            set_flash_message('danger', 'Error de seguridad (CSRF token inválido). Inténtalo nuevamente.');
            $referer = $_SERVER['HTTP_REFERER'] ?? 'index.php';
            header("Location: " . $referer);
            exit();
        }
    }
}

// ==================== SANITIZACIÓN Y VALIDACIÓN ====================
function sanitize_string($str, $max_len = 255) {
    $clean = trim((string)$str);
    $clean = strip_tags($clean);
    if ($max_len > 0 && mb_strlen($clean) > $max_len) {
        $clean = mb_substr($clean, 0, $max_len);
    }
    return $clean;
}

function sanitize_email($email) {
    $clean = trim((string)$email);
    return filter_var($clean, FILTER_VALIDATE_EMAIL) ? $clean : null;
}

function sanitize_float($val, $min = 0) {
    $float = (float)str_replace(',', '.', trim((string)$val));
    return ($float < $min) ? $min : $float;
}

function sanitize_int($val, $default = 0) {
    return (int)filter_var($val, FILTER_SANITIZE_NUMBER_INT);
}

function validate_date($date_str) {
    if (empty($date_str)) return false;
    $d = DateTime::createFromFormat('Y-m-d', $date_str);
    return $d && $d->format('Y-m-d') === $date_str;
}

// ==================== REGISTRO DE AUDITORÍA ====================
function log_audit($action, $entity_type, $entity_id = null, $details = '') {
    global $pdo;
    try {
        $user = get_logged_user();
        $shop_id   = $user['shop_id'] ?? 1;
        $user_id   = $user['id'] ?? null;
        $user_name = $user['name'] ?? 'Cliente / Público';
        $ip        = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';

        $stmt = $pdo->prepare("
            INSERT INTO audit_logs (shop_id, user_id, user_name, action, entity_type, entity_id, details, ip_address)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([$shop_id, $user_id, $user_name, strtoupper($action), strtolower($entity_type), $entity_id, $details, $ip]);
    } catch (Exception $ex) {
        // Ignorar fallo de auditoría para no interrumpir flujo
    }
}

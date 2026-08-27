<?php
/**
 * Módulo de Control de Acceso Basado en Roles y Permisos (RBAC)
 * Arquitectura modular y escalable para la gestión de permisos en SaaS Barbería
 */

if (!defined('ROLE_SUPER_ADMIN')) {
    define('ROLE_SUPER_ADMIN', 'super_admin');
    define('ROLE_ADMIN_BARBERIA', 'admin_barberia');
    define('ROLE_BARBERO', 'barbero');

    // Permisos Granulares
    define('PERM_MANAGE_SAAS', 'manage_saas');
    define('PERM_MANAGE_SHOPS', 'manage_shops');
    define('PERM_MANAGE_PLANS', 'manage_plans');
    define('PERM_VIEW_GLOBAL_STATS', 'view_global_stats');
    define('PERM_MANAGE_SHOP_DATA', 'manage_shop_data');
    define('PERM_MANAGE_BARBERS', 'manage_barbers');
    define('PERM_MANAGE_CATALOG', 'manage_catalog');
    define('PERM_MANAGE_FINANCES', 'manage_finances');
    define('PERM_VIEW_GRID', 'view_grid');
    define('PERM_QUICK_SALES', 'quick_sales');
    define('PERM_CLOSE_CASH', 'close_cash');
}

/**
 * Matriz global de roles y sus permisos asociados.
 * Permite agregar nuevos roles (ej. recepcionista, contador) en cualquier momento.
 */
function get_role_permissions_matrix() {
    return [
        ROLE_SUPER_ADMIN => [
            PERM_MANAGE_SAAS,
            PERM_MANAGE_SHOPS,
            PERM_MANAGE_PLANS,
            PERM_VIEW_GLOBAL_STATS,
            PERM_MANAGE_SHOP_DATA,
            PERM_MANAGE_BARBERS,
            PERM_MANAGE_CATALOG,
            PERM_MANAGE_FINANCES,
            PERM_VIEW_GRID,
            PERM_QUICK_SALES,
            PERM_CLOSE_CASH,
        ],
        ROLE_ADMIN_BARBERIA => [
            PERM_MANAGE_SHOP_DATA,
            PERM_MANAGE_BARBERS,
            PERM_MANAGE_CATALOG,
            PERM_MANAGE_FINANCES,
            PERM_VIEW_GRID,
            PERM_QUICK_SALES,
            PERM_CLOSE_CASH,
        ],
        'owner' => [ // Alias de retrocompatibilidad
            PERM_MANAGE_SHOP_DATA,
            PERM_MANAGE_BARBERS,
            PERM_MANAGE_CATALOG,
            PERM_MANAGE_FINANCES,
            PERM_VIEW_GRID,
            PERM_QUICK_SALES,
            PERM_CLOSE_CASH,
        ],
        ROLE_BARBERO => [
            PERM_VIEW_GRID,
            PERM_QUICK_SALES,
            PERM_CLOSE_CASH,
        ],
        'barber' => [ // Alias de retrocompatibilidad
            PERM_VIEW_GRID,
            PERM_QUICK_SALES,
            PERM_CLOSE_CASH,
        ],
    ];
}

/**
 * Normaliza el nombre del rol (mapea 'owner' a 'admin_barberia', 'barber' a 'barbero')
 */
function normalize_role($role) {
    if ($role === 'owner') return ROLE_ADMIN_BARBERIA;
    if ($role === 'barber') return ROLE_BARBERO;
    return $role;
}

/**
 * Verifica si un usuario tiene un rol específico
 */
function has_role($role, $user = null) {
    if (!$user) {
        $user = get_logged_user();
    }
    if (!$user || empty($user['role'])) {
        return false;
    }

    $user_role = normalize_role($user['role']);
    $target_role = normalize_role($role);

    return $user_role === $target_role;
}

/**
 * Comprueba si el usuario logueado es Super Admin
 */
function is_super_admin($user = null) {
    return has_role(ROLE_SUPER_ADMIN, $user);
}

/**
 * Comprueba si el usuario es Administrador de Barbería
 */
function is_shop_admin($user = null) {
    return has_role(ROLE_ADMIN_BARBERIA, $user) || is_super_admin($user);
}

/**
 * Comprueba si el usuario es Barbero
 */
function is_barber($user = null) {
    return has_role(ROLE_BARBERO, $user);
}

/**
 * Comprueba si un usuario posee un permiso granular
 */
function has_permission($permission, $user = null) {
    if (!$user) {
        $user = get_logged_user();
    }
    if (!$user || empty($user['role'])) {
        return false;
    }

    // Super Admin posee todos los permisos
    if (is_super_admin($user)) {
        return true;
    }

    $matrix = get_role_permissions_matrix();
    $role = $user['role'];

    if (!isset($matrix[$role])) {
        return false;
    }

    return in_array($permission, $matrix[$role], true);
}

/**
 * Obliga a que el usuario conectado sea Super Admin. Si no, deniega el acceso.
 */
function require_super_admin() {
    $user = require_login();
    if (!is_super_admin($user)) {
        log_audit('UNAUTHORIZED_ACCESS', 'saas', null, 'Intento de ingreso no autorizado al panel Super Admin por usuario: ' . ($user['email'] ?? 'desconocido'));
        set_flash_message('danger', 'Acceso Denegado. Se requieren privilegios de Super Admin para acceder a esta área.');
        
        // Redirigir a su panel correspondiente según su rol
        if (is_shop_admin($user)) {
            header("Location: owner.php");
        } else {
            header("Location: barber.php");
        }
        exit();
    }
    return $user;
}

/**
 * Obliga a que el usuario sea Administrador de Barbería o Super Admin.
 */
function require_shop_admin() {
    $user = require_login();
    if (!is_shop_admin($user)) {
        log_audit('UNAUTHORIZED_ACCESS', 'shop', $user['shop_id'] ?? null, 'Acceso restringido: El usuario no es administrador de barbería.');
        set_flash_message('danger', 'Acceso restringido únicamente a administradores de barbería.');
        header("Location: barber.php");
        exit();
    }
    return $user;
}

/**
 * Obliga a poseer un permiso específico.
 */
function require_permission($permission) {
    $user = require_login();
    if (!has_permission($permission, $user)) {
        log_audit('UNAUTHORIZED_ACTION', 'permission', null, 'Intento de acción sin permiso requerido (' . $permission . ') por usuario: ' . ($user['email'] ?? 'desconocido'));
        set_flash_message('danger', 'No cuentas con los permisos necesarios para realizar esta acción.');
        
        $referer = $_SERVER['HTTP_REFERER'] ?? 'index.php';
        header("Location: " . $referer);
        exit();
    }
    return $user;
}

/**
 * Obtiene el ID de la barbería actual en contexto para consultas multi-inquilino.
 * Si es Super Admin y no se especifica shop_id, retorna null o el shop_id activo en GET/POST.
 */
function get_current_shop_id($user = null) {
    if (!$user) {
        $user = get_logged_user();
    }
    if (!$user) {
        return 1; // ID por defecto para acceso público (reserva de turnos en index.php)
    }

    if (!is_super_admin($user)) {
        return (int)$user['shop_id'];
    }

    // Si es super admin, puede pasar un shop_id en la petición o usar el predeterminado
    if (isset($_GET['shop_id']) && (int)$_GET['shop_id'] > 0) {
        return (int)$_GET['shop_id'];
    }
    if (isset($_POST['shop_id']) && (int)$_POST['shop_id'] > 0) {
        return (int)$_POST['shop_id'];
    }

    return $user['shop_id'] ? (int)$user['shop_id'] : 1;
}

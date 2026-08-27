<?php
require_once __DIR__ . '/includes/auth.php';

$user = require_super_admin();

$tab = $_GET['tab'] ?? 'admins';

// ---------------------------------------------------------
// CARGA DE DATOS PARA BROTHERHOOD BARBERSHOP
// ---------------------------------------------------------
$total_admins  = $pdo->query("SELECT COUNT(*) FROM users WHERE role IN ('admin_barberia', 'owner')")->fetchColumn();
$total_barbers = $pdo->query("SELECT COUNT(*) FROM users WHERE role IN ('barbero', 'barber')")->fetchColumn();
$total_appts   = $pdo->query("SELECT COUNT(*) FROM appointments")->fetchColumn();

// Recaudación Global Consolidada
$stmtGlobalMoney = $pdo->query("SELECT COALESCE(SUM(total_ingresos), 0) FROM daily_closures");
$global_revenue  = (float)$stmtGlobalMoney->fetchColumn();

// ---------------------------------------------------------
// CARGA DE TODOS LOS USUARIOS DEL SISTEMA
// ---------------------------------------------------------
$stmtUsers = $pdo->query("
    SELECT 
        u.*, 
        COALESCE(s.name, 'Brotherhood Barbershop') as shop_name 
    FROM users u
    LEFT JOIN shops s ON u.shop_id = s.id
    ORDER BY u.role ASC, u.id DESC
");
$all_users = $stmtUsers->fetchAll();

// ---------------------------------------------------------
// CARGA DE LOGS DE AUDITORÍA (SOLO REGISTROS DE SEGURIDAD Y ACCESOS DE ADMINS)
// ---------------------------------------------------------
$filter_action = strtolower(trim($_GET['filter_action'] ?? ''));
$filter_user   = (int)($_GET['filter_user'] ?? 0);

$where_clauses = [
    "LOWER(entity_type) NOT IN ('appointment', 'turno', 'service', 'servicio', 'catalog', 'catalogo', 'producto', 'caja', 'sale', 'venta')",
    "LOWER(entity_type) NOT LIKE '%servicio%'",
    "LOWER(entity_type) NOT LIKE '%catalogo%'",
    "LOWER(details) NOT LIKE '%servicio%'",
    "LOWER(details) NOT LIKE '%catálogo%'",
    "LOWER(details) NOT LIKE '%catalogo%'"
];
$params = [];

if (!empty($filter_action)) {
    $where_clauses[] = "LOWER(action) = ?";
    $params[] = $filter_action;
}

if ($filter_user > 0) {
    $where_clauses[] = "user_id = ?";
    $params[] = $filter_user;
}

$where_sql = "WHERE " . implode(" AND ", $where_clauses);
$stmtLogs = $pdo->prepare("SELECT * FROM audit_logs {$where_sql} ORDER BY id DESC LIMIT 100");
$stmtLogs->execute($params);
$audit_logs = $stmtLogs->fetchAll();

// Lista de administradores para filtro por usuario
$admin_users = array_filter($all_users, function($u) {
    return in_array($u['role'], ['super_admin', 'admin_barberia', 'owner']);
});

include __DIR__ . '/includes/header.php';
?>

<!-- ENCABEZADO PANEL SUPER ADMIN -->
<div class="panel-top-nav-bar mb-6 flex justify-between items-center flex-wrap gap-4" style="background: rgba(212, 175, 55, 0.08); border-bottom: 2px solid var(--accent-primary); padding: 1rem 1.5rem; border-radius: 12px;">
  <div>
    <h1 style="font-size: 1.8rem; margin:0; color: var(--accent-primary); font-family: var(--font-heading);" class="flex items-center gap-2">
      ⚡ Panel de Control Super Admin — Brotherhood Barbershop
    </h1>
    <p style="font-size: 0.88rem; color: var(--text-secondary); margin: 2px 0 0 0;">
      Administración centralizada del sistema: Cuentas de usuario, accesos de staff, métricas globales y auditoría de seguridad
    </p>
  </div>

  <nav class="panel-tabs" aria-label="Secciones Super Admin">
    <a href="super_admin.php?tab=admins" class="tab-link <?php echo $tab === 'admins' ? 'active' : ''; ?>">
      👥 Usuarios & Accesos (<?php echo count($all_users); ?>)
    </a>
    <a href="super_admin.php?tab=stats" class="tab-link <?php echo $tab === 'stats' ? 'active' : ''; ?>">
      📈 Auditoría & Métricas
    </a>
  </nav>
</div>



<div class="glass-panel" style="margin-bottom: 2.5rem;">

  <!-- ==================== PESTAÑA 1: GESTIÓN DE USUARIOS Y ACCESOS ==================== -->
  <?php if ($tab === 'admins'): ?>
    <?php
      $reset_user_id = (int)($_GET['reset_user'] ?? 0);
      $reset_user_item = null;
      if ($reset_user_id > 0) {
          foreach ($all_users as $u) {
              if ((int)$u['id'] === $reset_user_id) {
                  $reset_user_item = $u;
                  break;
              }
          }
      }
    ?>

    <div class="superadmin-grid-layout">
      
      <!-- LISTA DE USUARIOS -->
      <div>
        <h2 class="text-accent mb-3" style="font-size: 1.5rem;">👥 Usuarios y Cuentas del Sistema</h2>
        <p style="color: var(--text-secondary); font-size: 0.88rem; margin-bottom: 1.25rem;">
          Control centralizado de administradores, barberos y permisos.
        </p>

        <div class="table-responsive">
          <table>
            <thead>
              <tr>
                <th style="min-width: 170px;">Usuario</th>
                <th style="min-width: 140px;">Rol</th>
                <th style="min-width: 150px;">Barbería Asignada</th>
                <th style="min-width: 90px;">Estado</th>
                <th style="min-width: 190px; text-align: right;">Acciones</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($all_users as $u): ?>
                <tr>
                  <td>
                    <strong><?php echo htmlspecialchars($u['name']); ?></strong>
                    <br><small style="color:var(--text-secondary);">📧 <?php echo htmlspecialchars($u['email']); ?></small>
                  </td>
                  <td>
                    <?php if (is_super_admin($u)): ?>
                      <span class="role-tag" style="background: rgba(239,68,68,0.2); color:#f87171; border:1px solid #f87171; font-size:0.75rem;">⚡ Super Admin</span>
                    <?php elseif (is_shop_admin($u)): ?>
                      <span class="role-tag" style="background: rgba(212,175,55,0.2); color:var(--accent-primary); border:1px solid var(--accent-primary); font-size:0.75rem;">👑 Admin Barbería</span>
                    <?php else: ?>
                      <span class="role-tag" style="background: rgba(16,185,129,0.2); color:#34d399; border:1px solid #34d399; font-size:0.75rem;">💈 Barbero Staff</span>
                    <?php endif; ?>
                  </td>
                  <td>
                    <?php echo htmlspecialchars($u['shop_name'] ?? 'Brotherhood Barbershop'); ?>
                  </td>
                  <td>
                    <span class="status-badge <?php echo $u['is_active'] ? 'status-badge-active' : 'status-badge-inactive'; ?>">
                      <?php echo $u['is_active'] ? 'Activo' : 'Inactivo'; ?>
                    </span>
                  </td>
                  <td style="text-align: right;">
                    <div class="flex gap-1 flex-nowrap items-center justify-end" style="white-space: nowrap;">
                      <!-- Restablecer Clave -->
                      <a href="super_admin.php?tab=admins&reset_user=<?php echo $u['id']; ?>" class="btn btn-outline btn-sm" style="white-space: nowrap;" title="Restablecer Contraseña">
                        🔑 Clave
                      </a>

                      <!-- Activar / Desactivar Usuario (si no es el super admin actual) -->
                      <?php if ((int)$u['id'] !== (int)$user['id']): ?>
                        <form action="actions/saas_toggle_user.php" method="POST" style="margin:0; display:inline-block;">
                          <?php echo csrf_field(); ?>
                          <input type="hidden" name="user_id" value="<?php echo $u['id']; ?>">
                          <button type="submit" class="btn <?php echo $u['is_active'] ? 'btn-warning' : 'btn-success'; ?> btn-sm" style="white-space: nowrap;">
                            <?php echo $u['is_active'] ? '🚫 Desactivar' : '✓ Activar'; ?>
                          </button>
                        </form>

                        <!-- Eliminar Usuario -->
                        <form action="actions/saas_delete_user.php" method="POST" style="margin:0; display:inline-block;" onsubmit="return confirm('⚠️ ¿Estás seguro de ELIMINAR PERMANENTEMENTE al usuario \'<?php echo htmlspecialchars(addslashes($u['name'])); ?>\' (<?php echo htmlspecialchars(addslashes($u['email'])); ?>)?\n\nEsta acción NO se puede deshacer.');">
                          <?php echo csrf_field(); ?>
                          <input type="hidden" name="user_id" value="<?php echo $u['id']; ?>">
                          <button type="submit" class="btn btn-danger btn-sm" style="white-space: nowrap;" title="Eliminar usuario del sistema">
                            🗑️ Eliminar
                          </button>
                        </form>
                      <?php endif; ?>
                    </div>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>

      <!-- SECCIÓN ACCIÓN: CREAR O RESTABLECER CLAVE -->
      <div>
        <?php if ($reset_user_item): ?>
          <!-- FORMULARIO RESTABLECER CONTRASEÑA -->
          <h2 class="text-accent mb-3" style="font-size: 1.5rem;">🔑 Restablecer Contraseña</h2>
          <p style="color: var(--text-secondary); font-size: 0.88rem; margin-bottom: 1.25rem;">
            Cambiar la clave de acceso para <strong><?php echo htmlspecialchars($reset_user_item['name']); ?></strong> (<?php echo htmlspecialchars($reset_user_item['email']); ?>).
          </p>

          <form action="actions/saas_reset_password.php" method="POST" class="glass-panel flex flex-col gap-3" style="padding: 1.75rem; margin:0;">
            <?php echo csrf_field(); ?>
            <input type="hidden" name="user_id" value="<?php echo $reset_user_item['id']; ?>">

            <div class="input-group">
              <label class="input-label">Nueva Contraseña *</label>
              <input type="password" name="new_password" class="input-field" placeholder="Escribe la nueva contraseña" minlength="4" required autocomplete="new-password">
            </div>

            <div class="flex gap-2 mt-2">
              <button type="submit" class="btn btn-primary flex-1">
                💾 Guardar Nueva Contraseña
              </button>
              <a href="super_admin.php?tab=admins" class="btn btn-outline">Cancelar</a>
            </div>
          </form>
        <?php else: ?>
          <!-- FORMULARIO CREAR USUARIO / ADMIN / BARBERO -->
          <h2 class="text-accent mb-3" style="font-size: 1.5rem;">➕ Crear Nueva Cuenta de Usuario</h2>
          <p style="color: var(--text-secondary); font-size: 0.88rem; margin-bottom: 1.25rem;">
            Registra una nueva cuenta autorizada.
          </p>

          <form action="actions/saas_create_admin.php" method="POST" class="glass-panel flex flex-col gap-3" style="padding: 1.75rem; margin:0;">
            <?php echo csrf_field(); ?>

            <div class="input-group">
              <label class="input-label">Rol del Usuario *</label>
              <select name="role" class="input-field" required>
                <option value="admin_barberia">👑 Admin de Barbería (Gestión Operativa Completa)</option>
                <option value="barbero">💈 Barbero Staff (Grilla de Turnos y Ventas)</option>
              </select>
            </div>

            <div class="input-group">
              <label class="input-label">Nombre Completo *</label>
              <input type="text" name="name" class="input-field" placeholder="Ej. Roberto Franco" required>
            </div>

            <div class="input-group">
              <label class="input-label">Correo Electrónico *</label>
              <input type="email" name="email" class="input-field" placeholder="usuario@barberia.com" required autocomplete="off">
            </div>

            <div class="input-group">
              <label class="input-label">Contraseña Inicial *</label>
              <input type="password" name="password" class="input-field" placeholder="••••••••" minlength="4" required autocomplete="new-password">
            </div>

            <button type="submit" class="btn btn-primary mt-2" style="padding: 0.85rem; font-weight:700;">
              👤 Crear Cuenta de Usuario
            </button>
          </form>
        <?php endif; ?>
      </div>

    </div>

  <!-- ==================== PESTAÑA 2: AUDITORÍA Y SEGURIDAD ==================== -->
  <?php elseif ($tab === 'stats'): ?>
    <div class="flex justify-between items-center flex-wrap gap-4 mb-4">
      <div>
        <h2 class="text-accent" style="font-size: 1.5rem; margin: 0;">🛡️ Historial de Seguridad y Administradores</h2>
        <p style="color: var(--text-secondary); font-size: 0.88rem; margin-top: 4px;">
          Registro exclusivo de cambios de usuarios, accesos, claves y eventos de seguridad.
        </p>
      </div>

      <!-- DESPLEGABLES DE FILTRADO SEGURO -->
      <form method="GET" action="super_admin.php" class="flex items-center gap-3 flex-wrap">
        <input type="hidden" name="tab" value="stats">

        <!-- FILTRO POR ACCIÓN DE SEGURIDAD -->
        <div class="flex items-center gap-2">
          <label for="filter_action" style="font-size: 0.82rem; color: var(--text-secondary); font-weight: 600;">Filtrar Acción:</label>
          <select id="filter_action" name="filter_action" class="input-field" style="min-width: 220px; padding: 0.45rem 0.8rem; font-size: 0.84rem;" onchange="this.form.submit()">
            <option value="">🔒 Todos los Registros de Seguridad</option>
            <option value="create_user" <?php echo $filter_action === 'create_user' ? 'selected' : ''; ?>>➕ Creación de Cuentas (CREATE_USER)</option>
            <option value="toggle_user_status" <?php echo $filter_action === 'toggle_user_status' ? 'selected' : ''; ?>>🚫 / ✓ Cambios de Estado (TOGGLE_USER)</option>
            <option value="reset_password" <?php echo $filter_action === 'reset_password' ? 'selected' : ''; ?>>🔑 Restablecimiento de Claves (RESET_PASSWORD)</option>
            <option value="delete_user" <?php echo $filter_action === 'delete_user' ? 'selected' : ''; ?>>🗑️ Eliminación de Cuentas (DELETE_USER)</option>
            <option value="login" <?php echo $filter_action === 'login' ? 'selected' : ''; ?>>🔓 Inicios de Sesión (LOGIN)</option>
          </select>
        </div>

        <!-- FILTRO POR ADMINISTRADOR -->
        <div class="flex items-center gap-2">
          <label for="filter_user" style="font-size: 0.82rem; color: var(--text-secondary); font-weight: 600;">Administrador:</label>
          <select id="filter_user" name="filter_user" class="input-field" style="min-width: 200px; padding: 0.45rem 0.8rem; font-size: 0.84rem;" onchange="this.form.submit()">
            <option value="0">👤 Todos los Administradores</option>
            <?php foreach ($admin_users as $admin): ?>
              <option value="<?php echo $admin['id']; ?>" <?php echo $filter_user === (int)$admin['id'] ? 'selected' : ''; ?>>
                <?php echo htmlspecialchars($admin['name']); ?> (<?php echo $admin['role'] === 'super_admin' ? 'Super Admin' : 'Admin Barbería'; ?>)
              </option>
            <?php endforeach; ?>
          </select>
        </div>

        <?php if (!empty($filter_action) || $filter_user > 0): ?>
          <a href="super_admin.php?tab=stats" class="btn btn-outline btn-sm" style="padding: 0.45rem 0.75rem; font-size: 0.8rem;">
            🔄 Limpiar
          </a>
        <?php endif; ?>
      </form>
    </div>

    <!-- TABLA DE LOGS DE AUDITORÍA CON SCROLL INTERNO Y CABECERA FIJA -->
    <div class="table-responsive" style="max-height: 480px; overflow-y: auto; border: 1px solid var(--glass-border); border-radius: 12px; background: rgba(7, 9, 12, 0.5);">
      <table style="width: 100%; border-collapse: separate; border-spacing: 0;">
        <thead style="position: sticky; top: 0; z-index: 10; background: #0d1117; box-shadow: 0 2px 8px rgba(0,0,0,0.6);">
          <tr>
            <th style="width: 140px; background: #0d1117;">Fecha y Hora</th>
            <th style="width: 180px; background: #0d1117;">Administrador / Usuario</th>
            <th style="width: 170px; background: #0d1117;">Acción de Seguridad</th>
            <th style="width: 120px; background: #0d1117;">Tipo Entidad</th>
            <th style="background: #0d1117;">Detalles del Cambio</th>
            <th style="width: 120px; background: #0d1117;">Dirección IP</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($audit_logs)): ?>
            <tr>
              <td colspan="6" class="text-center" style="padding: 2rem; color: var(--text-secondary);">
                <span>🔒 No se encontraron registros de seguridad con los filtros seleccionados.</span>
              </td>
            </tr>
          <?php else: ?>
            <?php foreach ($audit_logs as $log): ?>
              <tr>
                <td style="font-size:0.8rem; color:var(--text-secondary); white-space:nowrap;">
                  <?php echo date('d/m/Y H:i', strtotime($log['created_at'])); ?>
                </td>
                <td>
                  <strong><?php echo htmlspecialchars($log['user_name'] ?? 'Sistema'); ?></strong>
                </td>
                <td>
                  <?php
                    $actionUpper = strtoupper($log['action']);
                    $badgeStyle = 'background: rgba(201,167,82,0.15); color:var(--accent-primary); border:1px solid var(--accent-primary);';
                    if (strpos($actionUpper, 'DELETE') !== false) {
                        $badgeStyle = 'background: rgba(220,53,69,0.18); color:#ff6b6b; border:1px solid #dc3545;';
                    } elseif (strpos($actionUpper, 'CREATE') !== false) {
                        $badgeStyle = 'background: rgba(40,167,69,0.18); color:#51cf66; border:1px solid #28a745;';
                    } elseif (strpos($actionUpper, 'RESET') !== false || strpos($actionUpper, 'TOGGLE') !== false) {
                        $badgeStyle = 'background: rgba(13,202,240,0.18); color:#33d9ef; border:1px solid #0dcaf0;';
                    }
                  ?>
                  <span class="role-tag" style="<?php echo $badgeStyle; ?> font-size:0.75rem; font-weight:700;">
                    <?php echo htmlspecialchars($actionUpper); ?>
                  </span>
                </td>
                <td style="font-size:0.8rem; text-transform:uppercase; color:var(--text-secondary); font-weight:600;">
                  <?php echo htmlspecialchars($log['entity_type']); ?>
                </td>
                <td style="font-size:0.85rem; color:var(--text-primary);"><?php echo htmlspecialchars($log['details']); ?></td>
                <td style="font-size:0.8rem; font-family:monospace; color:var(--text-secondary);"><?php echo htmlspecialchars($log['ip_address'] ?? '127.0.0.1'); ?></td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>

  <?php endif; ?>

</div>

<?php include __DIR__ . '/includes/footer.php'; ?>

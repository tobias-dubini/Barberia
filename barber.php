<?php
require_once __DIR__ . '/includes/auth.php';
$is_panel_view = true;
$user = require_login();

$shop_id = $user['shop_id'];

// Determinar pestaña activa (grid, ventas, cierre_caja)
$view_tab = $view_tab ?? ($_GET['tab'] ?? 'grid');
if ($view_tab === 'my_grid') {
    $view_tab = 'grid';
}

// Cargar todos los barberos activos de la barbería
$stmtB = $pdo->prepare("SELECT id, name, role, photo FROM users WHERE shop_id = ? AND is_active = 1 ORDER BY name ASC");
$stmtB->execute([$shop_id]);
$active_barbers_list = $stmtB->fetchAll();

// Barbero seleccionado en el filtro (por defecto 'todos')
$selected_barber_filter = $_GET['barber_id'] ?? 'todos';

if ($selected_barber_filter === 'todos') {
    $current_barber_name = "Todos los Barberos";
    $barber_id = 0;
    $barbers_to_show = $active_barbers_list;
} else {
    $barber_id = (int)$selected_barber_filter;
    $stmtCurrentBarber = $pdo->prepare("SELECT id, name, role, photo FROM users WHERE id = ? AND shop_id = ?");
    $stmtCurrentBarber->execute([$barber_id, $shop_id]);
    $current_barber_data = $stmtCurrentBarber->fetch();
    
    if ($current_barber_data) {
        $current_barber_name = $current_barber_data['name'];
        $barbers_to_show = [$current_barber_data];
    } else {
        $current_barber_name = "Todos los Barberos";
        $selected_barber_filter = 'todos';
        $barber_id = 0;
        $barbers_to_show = $active_barbers_list;
    }
}

// Fecha seleccionada (por defecto hoy Y-m-d)
$selected_date = $_GET['date'] ?? date('Y-m-d');
$day_of_week   = date('w', strtotime($selected_date)); // 0 = Domingo
$is_sunday     = ($day_of_week == 0);

// Cargar Catálogo
$stmtCat = $pdo->prepare("SELECT * FROM catalog WHERE shop_id = ? ORDER BY type, name");
$stmtCat->execute([$shop_id]);
$catalog = $stmtCat->fetchAll();

$selected_next_date  = date('Y-m-d', strtotime($selected_date . ' +1 day'));
$selected_date_start = $selected_date . ' 00:00:00';
$selected_date_end   = $selected_next_date . ' 00:00:00';

// Cargar Turnos del día según el filtro
if ($selected_barber_filter === 'todos') {
    $stmtAppts = $pdo->prepare("
      SELECT 
        a.*, 
        c.name as item_name, 
        c.type as item_type,
        COALESCE(bc.price, c.price) as base_price, 
        COALESCE(bc.commission_percent, c.commission_percent) as commission_percent, 
        u.name as barber_name, 
        u.photo as barber_photo 
      FROM appointments a
      JOIN catalog c ON a.item_id = c.id
      JOIN users u ON a.barber_id = u.id
      LEFT JOIN barber_commissions bc ON bc.barber_id = a.barber_id AND bc.catalog_id = a.item_id AND bc.shop_id = a.shop_id
      WHERE a.shop_id = ? AND a.appointment_datetime >= ? AND a.appointment_datetime < ?
      ORDER BY a.appointment_datetime ASC
    ");
    $stmtAppts->execute([$shop_id, $selected_date_start, $selected_date_end]);
} else {
    $stmtAppts = $pdo->prepare("
      SELECT 
        a.*, 
        c.name as item_name, 
        c.type as item_type,
        COALESCE(bc.price, c.price) as base_price, 
        COALESCE(bc.commission_percent, c.commission_percent) as commission_percent, 
        u.name as barber_name, 
        u.photo as barber_photo 
      FROM appointments a
      JOIN catalog c ON a.item_id = c.id
      JOIN users u ON a.barber_id = u.id
      LEFT JOIN barber_commissions bc ON bc.barber_id = a.barber_id AND bc.catalog_id = a.item_id AND bc.shop_id = a.shop_id
      WHERE a.shop_id = ? AND a.barber_id = ? AND a.appointment_datetime >= ? AND a.appointment_datetime < ?
      ORDER BY a.appointment_datetime ASC
    ");
    $stmtAppts->execute([$shop_id, $barber_id, $selected_date_start, $selected_date_end]);
}
$all_today_records = $stmtAppts->fetchAll();

$grid_appointments = array_filter($all_today_records, function($r) { return !$r['is_direct_sale']; });
$product_sales     = array_filter($all_today_records, function($r) { return $r['is_direct_sale']; });

// Indexar turnos por hora Y por barbero para la Grilla Matriz
$appointments_matrix = [];
$appointments_by_hour = [];
foreach ($grid_appointments as $appt) {
    $hour = (int)date('H', strtotime($appt['appointment_datetime']));
    $bId  = (int)$appt['barber_id'];
    $appointments_matrix[$hour][$bId][] = $appt;
    $appointments_by_hour[$hour][]      = $appt;
}

// Calcular Totales de Caja para el día y vista actual
$total_efectivo = 0.00;
$total_transferencia = 0.00;
$total_comisiones = 0.00;

// Estructura para el deslose por barbero en cierre de caja
$barber_cash_breakdown = [];
foreach ($active_barbers_list as $ab) {
    $barber_cash_breakdown[$ab['id']] = [
        'name' => $ab['name'],
        'photo' => $ab['photo'],
        'appts_count' => 0,
        'sales_count' => 0,
        'efectivo' => 0.00,
        'transferencia' => 0.00,
        'total' => 0.00,
        'comision' => 0.00,
        'neto_barberia' => 0.00
    ];
}

foreach ($all_today_records as $rec) {
    if ($rec['status'] === 'completed') {
        $base  = (float)$rec['base_price'];
        $final = ($rec['payment_method'] === 'transferencia') ? ($base * 1.20) : $base;
        $comm  = ($base * (float)$rec['commission_percent']) / 100.0;
        $bId   = (int)$rec['barber_id'];

        if ($rec['payment_method'] === 'transferencia') {
            $total_transferencia += $final;
            if (isset($barber_cash_breakdown[$bId])) {
                $barber_cash_breakdown[$bId]['transferencia'] += $final;
            }
        } else {
            $total_efectivo += $final;
            if (isset($barber_cash_breakdown[$bId])) {
                $barber_cash_breakdown[$bId]['efectivo'] += $final;
            }
        }
        $total_comisiones += $comm;

        if (isset($barber_cash_breakdown[$bId])) {
            if ($rec['is_direct_sale']) {
                $barber_cash_breakdown[$bId]['sales_count']++;
            } else {
                $barber_cash_breakdown[$bId]['appts_count']++;
            }
            $barber_cash_breakdown[$bId]['total'] += $final;
            $barber_cash_breakdown[$bId]['comision'] += $comm;
            $barber_cash_breakdown[$bId]['neto_barberia'] += ($final - $comm);
        }
    }
}
$total_ingresos = $total_efectivo + $total_transferencia;

// Comprobar si ya existe un Cierre de Caja guardado hoy
$closure_barber_id = ($barber_id > 0) ? $barber_id : ($user['id']);
$stmtClosure = $pdo->prepare("SELECT * FROM daily_closures WHERE shop_id = ? AND barber_id = ? AND date = ?");
$stmtClosure->execute([$shop_id, $closure_barber_id, $selected_date]);
$existing_closure = $stmtClosure->fetch();

if (!defined('HEADER_INCLUDED')) {
    include __DIR__ . '/includes/header.php';
}

$tab_param = ($view_tab === 'grid') ? 'my_grid' : $view_tab;
$url_script = ($user['role'] === 'owner') ? 'owner.php' : 'barber.php';
$link_prefix = $url_script . '?tab=' . $tab_param . '&barber_id=' . urlencode($selected_barber_filter) . '&date=';
?>

<!-- NAVEGACIÓN SECUNDARIA SI ACCEDE DIRECTAMENTE UN BARBERO -->
<?php if ($user['role'] === 'barber'): ?>
  <nav class="panel-tabs panel-tabs--sm" aria-label="Secciones del panel">
    <a href="barber.php?tab=grid&date=<?php echo $selected_date; ?>" class="tab-link <?php echo $view_tab === 'grid' ? 'active' : ''; ?>" <?php echo $view_tab === 'grid' ? 'aria-current="page"' : ''; ?>>
      💈 Grilla de Turnos
    </a>
    <a href="barber.php?tab=ventas&date=<?php echo $selected_date; ?>" class="tab-link <?php echo $view_tab === 'ventas' ? 'active' : ''; ?>" <?php echo $view_tab === 'ventas' ? 'aria-current="page"' : ''; ?>>
      🛍 Ventas Rápidas
    </a>
    <a href="barber.php?tab=cierre_caja&date=<?php echo $selected_date; ?>" class="tab-link <?php echo $view_tab === 'cierre_caja' ? 'active' : ''; ?>" <?php echo $view_tab === 'cierre_caja' ? 'aria-current="page"' : ''; ?>>
      🔒 Cierre de Caja
    </a>
  </nav>
<?php endif; ?>

<!-- ================================================================= -->
<!-- PESTAÑA 1: GRILLA DE TURNOS (EXCLUSIVA) -->
<!-- ================================================================= -->
<?php if ($view_tab === 'grid'): ?>

  <div class="flex justify-between items-center mb-4 flex-wrap gap-4">
    <div>
      <h1 class="text-accent">Grilla de Turnos</h1>
      <p>Vista de Agenda: <strong><?php echo htmlspecialchars($current_barber_name); ?></strong> (<?php echo date('d/m/Y', strtotime($selected_date)); ?>)</p>
    </div>

    <div class="flex items-center gap-2 flex-wrap">
      <!-- Botón Abrir Modal de Agendado Rápido -->
      <button type="button" class="btn btn-primary" onclick="openBookingModal()">
        ➕ Agendar Turno
      </button>

      <!-- Control de Navegación por Fecha -->
      <?php
        $prev_date = date('Y-m-d', strtotime($selected_date . ' -1 day'));
        $next_date = date('Y-m-d', strtotime($selected_date . ' +1 day'));
        $today_date = date('Y-m-d');
      ?>
      <form method="GET" action="<?php echo $url_script; ?>" class="flex items-center gap-3 flex-wrap" style="margin:0;">
        <input type="hidden" name="tab" value="<?php echo htmlspecialchars($tab_param); ?>">
        <input type="hidden" name="barber_id" value="<?php echo htmlspecialchars($selected_barber_filter); ?>">

        <a href="<?php echo $link_prefix . $prev_date; ?>" class="btn btn-outline" style="padding:0.45rem 0.75rem;" title="Día Anterior">◀ Anterior</a>
        <input type="date" name="date" class="input-field" value="<?php echo $selected_date; ?>" onchange="this.form.submit()" style="margin:0; width: auto; padding: 0.45rem 0.6rem;">
        <a href="<?php echo $link_prefix . $next_date; ?>" class="btn btn-outline" style="padding:0.45rem 0.75rem;" title="Día Siguiente">Siguiente ▶</a>
        <?php if ($selected_date !== $today_date): ?>
          <a href="<?php echo $link_prefix . $today_date; ?>" class="btn btn-outline" style="padding:0.45rem 0.75rem; border-color:var(--accent-primary); color:var(--accent-primary);" title="Ir a Hoy">Hoy</a>
        <?php endif; ?>
      </form>
    </div>
  </div>

  <!-- BARRA DE SELECCIÓN DE BARBERO (PILLS) -->
  <div class="barber-pills-bar">
    <?php 
      $all_pill_active = ($selected_barber_filter === 'todos') ? 'active' : '';
      $all_url = $url_script . '?tab=' . $tab_param . '&barber_id=todos&date=' . $selected_date;
    ?>
    <a href="<?php echo $all_url; ?>" class="barber-pill <?php echo $all_pill_active; ?>">
      <span>👥</span> Todos los Barberos (<?php echo count($active_barbers_list); ?>)
    </a>

    <?php foreach ($active_barbers_list as $ab): 
      $isActive = ((string)$selected_barber_filter === (string)$ab['id']) ? 'active' : '';
      $bUrl = $url_script . '?tab=' . $tab_param . '&barber_id=' . $ab['id'] . '&date=' . $selected_date;
      $bImg = !empty($ab['photo']) ? $ab['photo'] : 'assets/img/service_corte_clasico_1785440488368.png';
    ?>
      <a href="<?php echo $bUrl; ?>" class="barber-pill <?php echo $isActive; ?>">
        <img src="<?php echo htmlspecialchars($bImg); ?>" class="barber-pill-img" alt="<?php echo htmlspecialchars($ab['name']); ?>">
        <span><?php echo htmlspecialchars($ab['name']); ?></span>
      </a>
    <?php endforeach; ?>
  </div>

  <div class="glass-panel">

    <?php if ($is_sunday): ?>
      <div class="text-center p-8" style="color: var(--text-secondary); margin: 2rem 0;">
        <div style="font-size: 3.5rem;">🔒</div>
        <h3 class="mt-2 text-accent">Local Cerrado los Domingos</h3>
        <p>No se agendan turnos en esta fecha. Selecciona otro día laboral.</p>
        <a href="<?php echo $link_prefix . $next_date; ?>" class="btn btn-primary mt-4">Ver Lunes Siguiente ▶</a>
      </div>
    <?php else: ?>

      <div class="flex justify-between items-center mb-4 flex-wrap gap-2">
        <h2 class="text-accent">
          Grilla Diaria de Agenda — <?php echo date('d/m/Y', strtotime($selected_date)); ?>
        </h2>

        <!-- Alternador de Vista (Matriz en Paraleo vs Lista Detallada) -->
        <div class="flex gap-2">
          <button type="button" id="btn-view-matrix" class="btn btn-primary" onclick="switchGridView('matrix')" style="padding:0.4rem 0.8rem; font-size:0.82rem;">
            📊 Columnas por Barbero
          </button>
          <button type="button" id="btn-view-list" class="btn btn-outline" onclick="switchGridView('list')" style="padding:0.4rem 0.8rem; font-size:0.82rem;">
            📋 Vista Lista
          </button>
        </div>
      </div>

      <!-- ================================================================= -->
      <!-- VISTA MATRIZ EN PARALELO (COLUMN PER BARBER) -->
      <!-- ================================================================= -->
      <div id="grid-matrix-view">
        <div class="matrix-container mb-2">
          <table class="matrix-table">
            <thead>
              <tr>
                <th class="hour-cell">Hora</th>
                <?php foreach ($barbers_to_show as $barber): 
                  $bPhoto = !empty($barber['photo']) ? $barber['photo'] : 'assets/img/service_corte_clasico_1785440488368.png';
                ?>
                  <th>
                    <div class="barber-header-card">
                      <img src="<?php echo htmlspecialchars($bPhoto); ?>" class="barber-header-avatar" alt="<?php echo htmlspecialchars($barber['name']); ?>">
                      <strong><?php echo htmlspecialchars($barber['name']); ?></strong>
                    </div>
                  </th>
                <?php endforeach; ?>
              </tr>
            </thead>
            <tbody>
              <?php for ($hour = 10; $hour <= 21; $hour++): 
                $time_label = sprintf("%02d:00", $hour);
              ?>
                <tr>
                  <td class="hour-cell"><?php echo $time_label; ?></td>

                  <?php foreach ($barbers_to_show as $barber): 
                    $bId = $barber['id'];
                    $cell_appts = $appointments_matrix[$hour][$bId] ?? [];
                  ?>
                    <td>
                      <?php if (!empty($cell_appts)): ?>
                        <?php foreach ($cell_appts as $appt): 
                          $basePrice = (float)$appt['base_price'];
                          $finalPrice = ($appt['payment_method'] === 'transferencia') ? ($basePrice * 1.20) : $basePrice;
                          $commission = ($basePrice * (float)$appt['commission_percent']) / 100.0;
                          $clean_phone = preg_replace('/[^0-9]/', '', $appt['client_phone'] ?? '');
                          $statusClass = 'status-' . $appt['status'];
                        ?>
                          <div class="matrix-appt-card <?php echo $statusClass; ?>">
                            <div class="matrix-appt-header">
                              <div>
                                <div class="matrix-client-name"><?php echo htmlspecialchars($appt['client_name']); ?></div>
                                <?php if (!empty($appt['client_phone'])): ?>
                                  <a href="https://wa.me/<?php echo $clean_phone; ?>" target="_blank" style="color: var(--success); font-size: 0.78rem; text-decoration: none; display: inline-flex; align-items: center; gap: 2px;">
                                    📱 <?php echo htmlspecialchars($appt['client_phone']); ?>
                                  </a>
                                <?php endif; ?>
                              </div>
                              
                              <div>
                                <?php if ($appt['status'] === 'scheduled'): ?>
                                  <span class="status-badge status-badge-active">Agendado</span>
                                <?php elseif ($appt['status'] === 'completed'): ?>
                                  <span class="status-badge status-badge-active" style="background:rgba(16,185,129,0.2);">✓ Cobrado</span>
                                <?php else: ?>
                                  <span class="status-badge status-badge-inactive">✕ Cancelado</span>
                                <?php endif; ?>
                              </div>
                            </div>

                            <div class="matrix-service-name">
                              ✂️ <strong><?php echo htmlspecialchars($appt['item_name']); ?></strong>
                            </div>

                            <div class="matrix-appt-meta">
                              <span class="badge-price">$<?php echo number_format($finalPrice, 2); ?></span>
                              <span class="badge-payment"><?php echo ucfirst($appt['payment_method']); ?></span>
                              <span style="font-size:0.72rem; color:var(--text-secondary);">Com: $<?php echo number_format($commission, 2); ?></span>
                            </div>

                            <?php if (!empty($appt['observation'])): ?>
                              <div style="font-size:0.75rem; color:var(--text-secondary); margin-bottom:0.35rem; font-style:italic;">
                                💬 <?php echo htmlspecialchars($appt['observation']); ?>
                              </div>
                            <?php endif; ?>

                            <div class="matrix-appt-actions">
                              <?php if ($appt['status'] === 'scheduled'): ?>
                                <form action="actions/complete_appointment.php" method="POST" onsubmit="return confirm('¿Marcar turno como completado y cobrado?')" style="margin:0;">
                                  <?php echo csrf_field(); ?>
                                  <input type="hidden" name="id" value="<?php echo $appt['id']; ?>">
                                  <input type="hidden" name="date" value="<?php echo $selected_date; ?>">
                                  <input type="hidden" name="barber_filter" value="<?php echo htmlspecialchars($selected_barber_filter); ?>">
                                  <button type="submit" class="btn btn-success btn-sm" title="Marcar Pagado">✓ Cobrar</button>
                                </form>

                                <form action="actions/cancel_appointment.php" method="POST" onsubmit="return confirm('¿Cancelar este turno?')" style="margin:0;">
                                  <?php echo csrf_field(); ?>
                                  <input type="hidden" name="id" value="<?php echo $appt['id']; ?>">
                                  <input type="hidden" name="date" value="<?php echo $selected_date; ?>">
                                  <input type="hidden" name="barber_filter" value="<?php echo htmlspecialchars($selected_barber_filter); ?>">
                                  <button type="submit" class="btn btn-outline btn-sm" style="color:var(--danger); border-color:var(--danger);" title="Cancelar Turno">✕</button>
                                </form>
                              <?php endif; ?>

                              <form action="actions/delete_appointment.php" method="POST" onsubmit="return confirm('¿Eliminar registro?')" style="margin:0;">
                                <?php echo csrf_field(); ?>
                                <input type="hidden" name="id" value="<?php echo $appt['id']; ?>">
                                <input type="hidden" name="date" value="<?php echo $selected_date; ?>">
                                <input type="hidden" name="barber_filter" value="<?php echo htmlspecialchars($selected_barber_filter); ?>">
                                <input type="hidden" name="tab_redirect" value="my_grid">
                                <button type="submit" class="btn btn-danger btn-sm" title="Eliminar definitivamente">🗑</button>
                              </form>
                            </div>
                          </div>
                        <?php endforeach; ?>

                        <!-- Botón para añadir turno adicional si ya hay turnos -->
                        <button type="button" class="btn btn-outline btn-sm w-full" style="border-style:dashed; border-color:var(--accent-primary);" onclick="openBookingModal(<?php echo $bId; ?>, '<?php echo sprintf('%02d:00', $hour); ?>')">
                          + Añadir Turno Extra
                        </button>
                      <?php else: ?>
                        <!-- Slot Libre disponible -->
                        <button type="button" class="slot-available-btn" onclick="openBookingModal(<?php echo $bId; ?>, '<?php echo sprintf('%02d:00', $hour); ?>')">
                          <span class="slot-plus">+</span>
                          <span class="slot-label">Agendar</span>
                          <span class="slot-time"><?php echo $time_label; ?></span>
                        </button>
                      <?php endif; ?>
                    </td>
                  <?php endforeach; ?>
                </tr>
              <?php endfor; ?>
            </tbody>
          </table>
        </div>
      </div>

      <!-- ================================================================= -->
      <!-- VISTA LISTA COMPLETA DETALLADA (TABLA TRADICIONAL) -->
      <!-- ================================================================= -->
      <div id="grid-list-view" style="display: none;">
        <div class="table-responsive mb-2">
          <table>
            <thead>
              <tr>
                <th>Hora</th>
                <th>Barbero</th>
                <th>Cliente / Teléfono</th>
                <th>Servicio</th>
                <th>Precio Base</th>
                <th>Medio Pago</th>
                <th>Precio Final (+20% transf)</th>
                <th>% Com.</th>
                <th>Ganancia Barbero</th>
                <th>Observaciones</th>
                <th>Estado</th>
                <th>Acciones</th>
              </tr>
            </thead>
            <tbody>
              <?php if (empty($grid_appointments)): ?>
                <tr>
                  <td colspan="12" class="text-center p-4" style="color:var(--text-secondary);">
                    No hay turnos agendados para esta fecha.
                  </td>
                </tr>
              <?php else: ?>
                <?php foreach ($grid_appointments as $appt): 
                  $basePrice = (float)$appt['base_price'];
                  $finalPrice = ($appt['payment_method'] === 'transferencia') ? ($basePrice * 1.20) : $basePrice;
                  $commission = ($basePrice * (float)$appt['commission_percent']) / 100.0;
                  $clean_phone = preg_replace('/[^0-9]/', '', $appt['client_phone'] ?? '');
                  $time_str = date('H:i', strtotime($appt['appointment_datetime']));
                ?>
                  <tr style="background: <?php echo $appt['status'] === 'cancelled' ? 'rgba(255,0,0,0.05)' : 'transparent'; ?>; opacity: <?php echo $appt['status'] === 'cancelled' ? 0.6 : 1; ?>;">
                    <td class="font-mono text-accent"><strong><?php echo $time_str; ?></strong></td>
                    <td><span class="role-tag" style="background: rgba(212, 175, 55, 0.15); color: var(--accent-primary); border: 1px solid var(--accent-primary);">💈 <?php echo htmlspecialchars($appt['barber_name']); ?></span></td>
                    <td>
                      <strong><?php echo htmlspecialchars($appt['client_name']); ?></strong>
                      <?php if (!empty($appt['client_phone'])): ?>
                        <br>
                        <a href="https://wa.me/<?php echo $clean_phone; ?>" target="_blank" style="color: var(--success); font-size: 0.8rem; text-decoration: none; display: inline-flex; align-items: center; gap: 2px;">
                          📱 <?php echo htmlspecialchars($appt['client_phone']); ?>
                        </a>
                      <?php endif; ?>
                    </td>
                    <td><?php echo htmlspecialchars($appt['item_name']); ?></td>
                    <td>$<?php echo number_format($basePrice, 2); ?></td>
                    <td style="text-transform: capitalize;"><?php echo htmlspecialchars($appt['payment_method']); ?></td>
                    <td class="text-warning font-bold">$<?php echo number_format($finalPrice, 2); ?></td>
                    <td><?php echo (float)$appt['commission_percent']; ?>%</td>
                    <td class="text-success font-bold">$<?php echo number_format($commission, 2); ?></td>
                    <td style="font-size:0.85rem; color: var(--text-secondary);"><?php echo htmlspecialchars($appt['observation'] ?: '-'); ?></td>
                    <td>
                      <?php if ($appt['status'] === 'scheduled'): ?>
                        <span class="text-accent font-bold">Agendado</span>
                      <?php elseif ($appt['status'] === 'completed'): ?>
                        <span class="text-success font-bold">✓ Completado</span>
                      <?php else: ?>
                        <span class="text-danger font-bold">✕ Cancelado</span>
                      <?php endif; ?>
                    </td>
                    <td>
                      <div class="flex gap-1">
                        <?php if ($appt['status'] === 'scheduled'): ?>
                          <form action="actions/complete_appointment.php" method="POST" onsubmit="return confirm('¿Marcar como pagado y completado?')">
                            <?php echo csrf_field(); ?>
                            <input type="hidden" name="id" value="<?php echo $appt['id']; ?>">
                            <input type="hidden" name="date" value="<?php echo $selected_date; ?>">
                            <input type="hidden" name="barber_filter" value="<?php echo htmlspecialchars($selected_barber_filter); ?>">
                            <button type="submit" class="btn btn-success btn-sm" title="Marcar Pagado">✓ Cobrar</button>
                          </form>

                          <form action="actions/cancel_appointment.php" method="POST" onsubmit="return confirm('¿Cancelar este turno?')">
                            <?php echo csrf_field(); ?>
                            <input type="hidden" name="id" value="<?php echo $appt['id']; ?>">
                            <input type="hidden" name="date" value="<?php echo $selected_date; ?>">
                            <input type="hidden" name="barber_filter" value="<?php echo htmlspecialchars($selected_barber_filter); ?>">
                            <button type="submit" class="btn btn-outline btn-sm" style="color:var(--danger); border-color:var(--danger);" title="Cancelar Turno">✕ Cancelar</button>
                          </form>
                        <?php endif; ?>

                        <form action="actions/delete_appointment.php" method="POST" onsubmit="return confirm('¿Eliminar registro por completo?')">
                          <?php echo csrf_field(); ?>
                          <input type="hidden" name="id" value="<?php echo $appt['id']; ?>">
                          <input type="hidden" name="date" value="<?php echo $selected_date; ?>">
                          <input type="hidden" name="barber_filter" value="<?php echo htmlspecialchars($selected_barber_filter); ?>">
                          <input type="hidden" name="tab_redirect" value="my_grid">
                          <button type="submit" class="btn btn-danger btn-sm" title="Eliminar">🗑</button>
                        </form>
                      </div>
                    </td>
                  </tr>
                <?php endforeach; ?>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>

    <?php endif; ?>
  </div>

<!-- ================================================================= -->
<!-- PESTAÑA 2: SECCIÓN DE VENTAS RÁPIDAS (MOSTRADOR) EXCLUSIVA -->
<!-- ================================================================= -->
<?php elseif ($view_tab === 'ventas'): ?>

  <div class="flex justify-between items-center mb-4 flex-wrap gap-4">
    <div>
      <h1 class="text-accent">Ventas Rápidas (Mostrador)</h1>
      <p>Registro y Control de Ventas de Productos y Promociones Directas — <strong><?php echo date('d/m/Y', strtotime($selected_date)); ?></strong></p>
    </div>

    <!-- Control de Navegación por Fecha -->
    <?php
      $prev_date = date('Y-m-d', strtotime($selected_date . ' -1 day'));
      $next_date = date('Y-m-d', strtotime($selected_date . ' +1 day'));
      $today_date = date('Y-m-d');
    ?>
    <form method="GET" action="<?php echo $url_script; ?>" class="flex items-center gap-3 flex-wrap" style="margin:0;">
      <input type="hidden" name="tab" value="ventas">
      <input type="hidden" name="barber_id" value="<?php echo htmlspecialchars($selected_barber_filter); ?>">

      <a href="<?php echo $link_prefix . $prev_date; ?>" class="btn btn-outline" style="padding:0.45rem 0.75rem;" title="Día Anterior">◀ Anterior</a>
      <input type="date" name="date" class="input-field" value="<?php echo $selected_date; ?>" onchange="this.form.submit()" style="margin:0; width: auto; padding: 0.45rem 0.6rem;">
      <a href="<?php echo $link_prefix . $next_date; ?>" class="btn btn-outline" style="padding:0.45rem 0.75rem;" title="Día Siguiente">Siguiente ▶</a>
      <?php if ($selected_date !== $today_date): ?>
        <a href="<?php echo $link_prefix . $today_date; ?>" class="btn btn-outline" style="padding:0.45rem 0.75rem; border-color:var(--accent-primary); color:var(--accent-primary);" title="Ir a Hoy">Hoy</a>
      <?php endif; ?>
    </form>
  </div>

  <!-- REGISTRAR VENTA RÁPIDA (MOSTRADOR) -->
  <div class="glass-panel mb-8" style="border-left: 4px solid var(--success);">
    <h3 class="text-success mb-2">🛍 Registrar Nueva Venta Directa</h3>
    <p style="font-size: 0.85rem; color: var(--text-secondary); margin-bottom: 1rem;">
      Venta directa de productos, ceras, pomadas o promociones al instante sin necesidad de asignar horario en la agenda.
    </p>

    <form action="actions/quick_sale.php" method="POST" class="flex gap-2 flex-wrap items-center">
      <?php echo csrf_field(); ?>
      <input type="hidden" name="date" value="<?php echo $selected_date; ?>">
      
      <select name="barber_id" class="input-field" required style="width: 180px;">
        <?php foreach ($active_barbers_list as $ab): ?>
          <option value="<?php echo $ab['id']; ?>" <?php echo ($barber_id == $ab['id'] || $user['id'] == $ab['id']) ? 'selected' : ''; ?>>
            💈 <?php echo htmlspecialchars($ab['name']); ?>
          </option>
        <?php endforeach; ?>
      </select>

      <select name="item_id" class="input-field flex-1" required style="min-width: 220px;">
        <option value="">Seleccionar Producto o Promo...</option>
        <?php foreach ($catalog as $item): ?>
          <option value="<?php echo $item['id']; ?>">
            [<?php echo strtoupper($item['type']); ?>] <?php echo htmlspecialchars($item['name']); ?> ($<?php echo number_format($item['price'], 2); ?>)
          </option>
        <?php endforeach; ?>
      </select>

      <select name="payment_method" class="input-field" style="width: 160px;">
        <option value="efectivo">💵 Efectivo</option>
        <option value="transferencia">💳 Transferencia</option>
      </select>

      <input type="text" name="client_name" class="input-field" placeholder="Cliente (Opcional)" style="width: 180px;">

      <button type="submit" class="btn btn-success" style="padding:0.6rem 1.2rem;">
        🛒 Registrar Venta
      </button>
    </form>
  </div>

  <!-- REGISTRO DE VENTAS DIRECTAS REALIZADAS HOY -->
  <div class="glass-panel">
    <h3 class="text-accent mb-3">🧾 Ventas de Mostrador Registradas Hoy (<?php echo count($product_sales); ?>)</h3>
    <?php if (empty($product_sales)): ?>
      <p style="color:var(--text-secondary); font-size:0.9rem;">No hay ventas de mostrador registradas para esta fecha.</p>
    <?php else: ?>
      <div class="table-responsive">
        <table>
          <thead>
            <tr>
              <th>Hora</th>
              <th>Barbero Vendedor</th>
              <th>Cliente</th>
              <th>Producto / Promo</th>
              <th>Medio Pago</th>
              <th>Monto Total</th>
              <th>Comisión</th>
              <th>Acción</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($product_sales as $ps): 
              $pPrice = (float)$ps['base_price'];
              $pFinal = ($ps['payment_method'] === 'transferencia') ? ($pPrice * 1.20) : $pPrice;
              $pComm  = ($pPrice * (float)$ps['commission_percent']) / 100.0;
            ?>
              <tr>
                <td class="font-mono text-accent"><?php echo date('H:i', strtotime($ps['appointment_datetime'])); ?></td>
                <td>💈 <?php echo htmlspecialchars($ps['barber_name']); ?></td>
                <td><?php echo htmlspecialchars($ps['client_name'] ?: 'Cliente Mostrador'); ?></td>
                <td><strong><?php echo htmlspecialchars($ps['item_name']); ?></strong></td>
                <td><span class="badge-payment"><?php echo ucfirst($ps['payment_method']); ?></span></td>
                <td class="text-warning font-bold">$<?php echo number_format($pFinal, 2); ?></td>
                <td class="text-success font-bold">$<?php echo number_format($pComm, 2); ?></td>
                <td>
                  <form action="actions/delete_appointment.php" method="POST" onsubmit="return confirm('¿Anular esta venta de producto?')" style="margin:0;">
                    <?php echo csrf_field(); ?>
                    <input type="hidden" name="id" value="<?php echo $ps['id']; ?>">
                    <input type="hidden" name="date" value="<?php echo $selected_date; ?>">
                    <input type="hidden" name="barber_filter" value="<?php echo htmlspecialchars($selected_barber_filter); ?>">
                    <input type="hidden" name="tab_redirect" value="ventas">
                    <button type="submit" class="btn btn-danger btn-sm" title="Eliminar Venta">🗑 Anular</button>
                  </form>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </div>

<!-- ================================================================= -->
<!-- PESTAÑA 3: SECCIÓN DE CIERRE Y ARQUEO DIARIO DE CAJA EXCLUSIVA -->
<!-- ================================================================= -->
<?php elseif ($view_tab === 'cierre_caja'): ?>

  <div class="flex justify-between items-center mb-4 flex-wrap gap-4">
    <div>
      <h1 class="text-accent">Cierre y Arqueo Diario de Caja</h1>
      <p>Resumen Consolidado de Recaudación y Cierre Diarios — <strong><?php echo date('d/m/Y', strtotime($selected_date)); ?></strong></p>
    </div>

    <!-- Control de Navegación por Fecha -->
    <?php
      $prev_date = date('Y-m-d', strtotime($selected_date . ' -1 day'));
      $next_date = date('Y-m-d', strtotime($selected_date . ' +1 day'));
      $today_date = date('Y-m-d');
    ?>
    <form method="GET" action="<?php echo $url_script; ?>" class="flex items-center gap-3 flex-wrap" style="margin:0;">
      <input type="hidden" name="tab" value="cierre_caja">
      <input type="hidden" name="barber_id" value="<?php echo htmlspecialchars($selected_barber_filter); ?>">

      <a href="<?php echo $link_prefix . $prev_date; ?>" class="btn btn-outline" style="padding:0.45rem 0.75rem;" title="Día Anterior">◀ Anterior</a>
      <input type="date" name="date" class="input-field" value="<?php echo $selected_date; ?>" onchange="this.form.submit()" style="margin:0; width: auto; padding: 0.45rem 0.6rem;">
      <a href="<?php echo $link_prefix . $next_date; ?>" class="btn btn-outline" style="padding:0.45rem 0.75rem;" title="Día Siguiente">Siguiente ▶</a>
      <?php if ($selected_date !== $today_date): ?>
        <a href="<?php echo $link_prefix . $today_date; ?>" class="btn btn-outline" style="padding:0.45rem 0.75rem; border-color:var(--accent-primary); color:var(--accent-primary);" title="Ir a Hoy">Hoy</a>
      <?php endif; ?>
    </form>
  </div>

  <!-- TARJETAS DE RESUMEN DIARIO -->
  <div class="kpi-grid">
    <div class="kpi-card kpi-success">
      <span class="kpi-label">Total Efectivo</span>
      <span class="kpi-value">$<?php echo number_format($total_efectivo, 2); ?></span>
    </div>

    <div class="kpi-card kpi-warning">
      <span class="kpi-label">Total Transferencias</span>
      <span class="kpi-value">$<?php echo number_format($total_transferencia, 2); ?></span>
    </div>

    <div class="kpi-card kpi-blue">
      <span class="kpi-label">Total Ingresos Brutos</span>
      <span class="kpi-value">$<?php echo number_format($total_ingresos, 2); ?></span>
    </div>

    <div class="kpi-card kpi-gold kpi-featured">
      <span class="kpi-label">Comisiones Barberos</span>
      <span class="kpi-value">$<?php echo number_format($total_comisiones, 2); ?></span>
    </div>
  </div>

  <!-- DESGLOSE DE RECAUDACIÓN POR BARBERO -->
  <div class="glass-panel mb-6">
    <h3 class="text-accent mb-3">📊 Desglose de Caja por Barbero</h3>
    <div class="table-responsive">
      <table>
        <thead>
          <tr>
            <th>Barbero</th>
            <th>Turnos Realizados</th>
            <th>Ventas Directas</th>
            <th>Efectivo</th>
            <th>Transferencias</th>
            <th>Total Recaudado</th>
            <th>Comisión Barbero</th>
            <th>Neto Barbería</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($barber_cash_breakdown as $bId => $bData): 
            $bPhoto = !empty($bData['photo']) ? $bData['photo'] : 'assets/img/service_corte_clasico_1785440488368.png';
          ?>
            <tr>
              <td>
                <div class="flex items-center gap-2">
                  <img src="<?php echo htmlspecialchars($bPhoto); ?>" style="width:28px; height:28px; border-radius:50%; object-fit:cover; border:1px solid var(--accent-primary);">
                  <strong><?php echo htmlspecialchars($bData['name']); ?></strong>
                </div>
              </td>
              <td><?php echo $bData['appts_count']; ?></td>
              <td><?php echo $bData['sales_count']; ?></td>
              <td class="text-success">$<?php echo number_format($bData['efectivo'], 2); ?></td>
              <td class="text-warning">$<?php echo number_format($bData['transferencia'], 2); ?></td>
              <td><strong>$<?php echo number_format($bData['total'], 2); ?></strong></td>
              <td class="text-accent font-bold">$<?php echo number_format($bData['comision'], 2); ?></td>
              <td class="font-bold">$<?php echo number_format($bData['neto_barberia'], 2); ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>

  <!-- EJECUTAR CIERRE DE CAJA -->
  <div class="glass-panel">
    <h3 class="text-accent mb-3">🔒 Estado y Consolidación del Cierre</h3>

    <?php if ($user['role'] === 'owner'): ?>
      <form action="actions/close_cash.php" method="POST" class="flex justify-between items-center flex-wrap gap-4">
        <?php echo csrf_field(); ?>
        <input type="hidden" name="date" value="<?php echo $selected_date; ?>">
        <input type="hidden" name="redirect_tab" value="cierre_caja">

        <div>
          <?php if ($existing_closure): ?>
            <span class="text-success font-bold" style="font-size:1.05rem;">✓ Cierre de Caja Realizado y Registrado para esta Fecha</span>
            <p style="font-size:0.85rem; color:var(--text-secondary);">Si se registraron nuevos turnos o ventas, puedes volver a hacer clic para actualizar los montos.</p>
          <?php else: ?>
            <span class="text-warning font-bold" style="font-size:1.05rem;">⚠️ Cierre de Caja Pendiente de Registro</span>
            <p style="font-size:0.85rem; color:var(--text-secondary);">Al realizar el cierre, los totales quedarán archivados para los reportes de finanzas.</p>
          <?php endif; ?>
        </div>

        <button type="submit" class="btn <?php echo $existing_closure ? 'btn-outline' : 'btn-primary'; ?>" style="padding:0.75rem 1.5rem;">
          🔒 <?php echo $existing_closure ? 'Actualizar Cierre de Caja' : 'Realizar Cierre de Caja Consolidado'; ?>
        </button>
      </form>
    <?php else: ?>
      <p style="color:var(--text-secondary);">Únicamente el administrador puede consolidar el cierre de caja diario.</p>
    <?php endif; ?>
  </div>

<?php endif; ?>

<!-- ================================================================= -->
<!-- MODAL INTERACTIVO PARA AGENDAR TURNO MANUAL -->
<!-- ================================================================= -->
<div id="bookingModal" class="modal-overlay" style="display: none;">
  <div class="modal-content glass-panel animate-fade-in">
    <div class="modal-header flex justify-between items-center mb-4">
      <h3 class="text-accent flex items-center gap-2">
        <span>💈</span> Agendar Nuevo Turno
      </h3>
      <button type="button" class="modal-close-btn" onclick="closeBookingModal()">&times;</button>
    </div>

    <form action="actions/save_appointment.php" method="POST" class="flex flex-col gap-3">
      <?php echo csrf_field(); ?>
      <input type="hidden" name="date" value="<?php echo $selected_date; ?>">
      <input type="hidden" name="barber_filter" value="<?php echo htmlspecialchars($selected_barber_filter); ?>">

      <div class="grid-cards" style="grid-template-columns: 1fr 1fr; gap: 0.75rem;">
        <div class="input-group">
          <label class="input-label">Barbero *</label>
          <select id="modal_barber_id" name="barber_id" class="input-field" required>
            <?php foreach ($active_barbers_list as $ab): ?>
              <option value="<?php echo $ab['id']; ?>">
                💈 <?php echo htmlspecialchars($ab['name']); ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="input-group">
          <label class="input-label">Hora del Turno *</label>
          <select id="modal_hour" name="hour" class="input-field" required>
            <?php for ($h = 10; $h <= 21; $h++): 
              $h_str = sprintf("%02d:00", $h);
            ?>
              <option value="<?php echo $h_str; ?>"><?php echo $h_str; ?> hs</option>
            <?php endfor; ?>
          </select>
        </div>
      </div>

      <div class="grid-cards" style="grid-template-columns: 1fr 1fr; gap: 0.75rem;">
        <div class="input-group">
          <label class="input-label">Nombre del Cliente *</label>
          <input type="text" id="modal_client_name" name="client_name" class="input-field" placeholder="Ej. Juan Pérez" required>
        </div>

        <div class="input-group">
          <label class="input-label">Teléfono / WhatsApp</label>
          <input type="text" id="modal_client_phone" name="client_phone" class="input-field" placeholder="Ej. 11 2345 6789">
        </div>
      </div>

      <div class="grid-cards" style="grid-template-columns: 1fr 1fr; gap: 0.75rem;">
        <div class="input-group">
          <label class="input-label">Servicio del Catálogo *</label>
          <select name="item_id" class="input-field" required>
            <option value="">Seleccionar Servicio...</option>
            <?php foreach ($catalog as $item): ?>
              <option value="<?php echo $item['id']; ?>">
                [<?php echo strtoupper($item['type']); ?>] <?php echo htmlspecialchars($item['name']); ?> ($<?php echo number_format($item['price'], 2); ?>)
              </option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="input-group">
          <label class="input-label">Medio de Pago</label>
          <select name="payment_method" class="input-field">
            <option value="efectivo">💵 Efectivo</option>
            <option value="transferencia">💳 Transferencia (+20%)</option>
          </select>
        </div>
      </div>

      <div class="input-group">
        <label class="input-label">Observaciones (Opcional)</label>
        <input type="text" name="observation" class="input-field" placeholder="Ej. Cliente frecuente, quiere degradé alto">
      </div>

      <div class="flex justify-end gap-2 mt-4">
        <button type="button" class="btn btn-outline" onclick="closeBookingModal()">Cancelar</button>
        <button type="submit" class="btn btn-primary">💾 Guardar Turno</button>
      </div>
    </form>
  </div>
</div>

<script>
function openBookingModal(barberId = null, hour = null) {
  const modal = document.getElementById('bookingModal');
  if (!modal) return;

  if (barberId) {
    const selectBarber = document.getElementById('modal_barber_id');
    if (selectBarber) selectBarber.value = barberId;
  }

  if (hour) {
    const selectHour = document.getElementById('modal_hour');
    if (selectHour) selectHour.value = hour;
  }

  modal.style.display = 'flex';

  setTimeout(() => {
    const inputName = document.getElementById('modal_client_name');
    if (inputName) inputName.focus();
  }, 100);
}

function closeBookingModal() {
  const modal = document.getElementById('bookingModal');
  if (modal) modal.style.display = 'none';
}

function switchGridView(viewMode) {
  const matrixView = document.getElementById('grid-matrix-view');
  const listView = document.getElementById('grid-list-view');
  const btnMatrix = document.getElementById('btn-view-matrix');
  const btnList = document.getElementById('btn-view-list');

  if (viewMode === 'matrix') {
    if (matrixView) matrixView.style.display = 'block';
    if (listView) listView.style.display = 'none';
    if (btnMatrix) { btnMatrix.classList.add('btn-primary'); btnMatrix.classList.remove('btn-outline'); }
    if (btnList) { btnList.classList.add('btn-outline'); btnList.classList.remove('btn-primary'); }
  } else {
    if (matrixView) matrixView.style.display = 'none';
    if (listView) listView.style.display = 'block';
    if (btnList) { btnList.classList.add('btn-primary'); btnList.classList.remove('btn-outline'); }
    if (btnMatrix) { btnMatrix.classList.add('btn-outline'); btnMatrix.classList.remove('btn-primary'); }
  }
}

// Cerrar modal haciendo clic fuera del diálogo
window.addEventListener('click', function(e) {
  const modal = document.getElementById('bookingModal');
  if (e.target === modal) {
    closeBookingModal();
  }
});
</script>

<?php 
if (!defined('HEADER_INCLUDED')) {
    include __DIR__ . '/includes/footer.php';
}
?>

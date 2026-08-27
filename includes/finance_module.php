<?php
// includes/finance_module.php - Módulo de Finanzas Integrado para la Barbería
if (!defined('HEADER_INCLUDED')) {
    require_once __DIR__ . '/auth.php';
    $user = require_role('owner');
}

$shop_id = $user['shop_id'];
$subtab  = $_GET['subtab'] ?? 'dashboard';

// NAVEGACIÓN SECUNDARIA DE FINANZAS
?>
<div class="panel-header" style="margin-bottom:1rem;">
  <div>
    <h2 class="text-accent m-0" style="font-size: 1.6rem; font-weight: 800;">📊 Módulo Financiero e Indicadores</h2>
    <p style="color:var(--text-secondary); font-size:0.88rem;">Control de ingresos, egresos, comisiones de barberos y estadísticas en tiempo real.</p>
  </div>
</div>

<div class="subtabs-wrapper mb-8" style="margin-bottom: 2.75rem !important; clear: both; width: 100%;">
  <nav class="panel-tabs panel-tabs--sm" aria-label="Secciones de finanzas" style="margin-bottom: 0 !important;">
    <a href="owner.php?tab=finanzas&subtab=dashboard" class="tab-link <?php echo $subtab === 'dashboard' ? 'active' : ''; ?>" <?php echo $subtab === 'dashboard' ? 'aria-current="page"' : ''; ?>>
      📈 Dashboard
    </a>
    <a href="owner.php?tab=finanzas&subtab=periodos" class="tab-link <?php echo $subtab === 'periodos' ? 'active' : ''; ?>" <?php echo $subtab === 'periodos' ? 'aria-current="page"' : ''; ?>>
      📅 Análisis por Períodos
    </a>
    <a href="owner.php?tab=finanzas&subtab=gastos" class="tab-link <?php echo $subtab === 'gastos' ? 'active' : ''; ?>" <?php echo $subtab === 'gastos' ? 'aria-current="page"' : ''; ?>>
      💸 Gastos
    </a>
    <a href="owner.php?tab=finanzas&subtab=barberos_pago" class="tab-link <?php echo $subtab === 'barberos_pago' ? 'active' : ''; ?>" <?php echo $subtab === 'barberos_pago' ? 'aria-current="page"' : ''; ?>>
      💈 Pago a Barberos
    </a>
    <a href="owner.php?tab=finanzas&subtab=exportar" class="tab-link <?php echo ($subtab === 'exportar' || $subtab === 'importador') ? 'active' : ''; ?>" <?php echo ($subtab === 'exportar' || $subtab === 'importador') ? 'aria-current="page"' : ''; ?>>
      📤 Exportar Excel/CSV
    </a>
  </nav>
</div>

<?php
// =========================================================================
// SUB-TAB 1: DASHBOARD FINANCIERO (KPIs + CHART.JS)
// =========================================================================
if ($subtab === 'dashboard'):
?>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<?php
    $today = date('Y-m-d');
    $this_month = date('Y-m');

    // 1. Ingresos del Día (Turnos + Ventas)
    $stmtIncToday = $pdo->prepare("
        SELECT 
            (SELECT COALESCE(SUM(c.price), 0) FROM appointments a JOIN catalog c ON a.item_id = c.id WHERE a.shop_id = ? AND DATE(a.appointment_datetime) = ? AND a.status != 'cancelled') +
            (SELECT COALESCE(SUM(total_amount), 0) FROM sales WHERE shop_id = ? AND DATE(created_at) = ?) as total
    ");
    $stmtIncToday->execute([$shop_id, $today, $shop_id, $today]);
    $ingresos_dia = (float)$stmtIncToday->fetch()['total'];

    // 2. Ingresos del Mes
    $stmtIncMonth = $pdo->prepare("
        SELECT 
            (SELECT COALESCE(SUM(c.price), 0) FROM appointments a JOIN catalog c ON a.item_id = c.id WHERE a.shop_id = ? AND DATE_FORMAT(a.appointment_datetime, '%Y-%m') = ? AND a.status != 'cancelled') +
            (SELECT COALESCE(SUM(total_amount), 0) FROM sales WHERE shop_id = ? AND DATE_FORMAT(created_at, '%Y-%m') = ?) as total
    ");
    $stmtIncMonth->execute([$shop_id, $this_month, $shop_id, $this_month]);
    $ingresos_mes = (float)$stmtIncMonth->fetch()['total'];

    // 3. Gastos del Día
    $stmtExpToday = $pdo->prepare("SELECT COALESCE(SUM(amount), 0) as total FROM expenses WHERE shop_id = ? AND date = ?");
    $stmtExpToday->execute([$shop_id, $today]);
    $gastos_dia = (float)$stmtExpToday->fetch()['total'];

    // 4. Gastos del Mes
    $stmtExpMonth = $pdo->prepare("SELECT COALESCE(SUM(amount), 0) as total FROM expenses WHERE shop_id = ? AND DATE_FORMAT(date, '%Y-%m') = ?");
    $stmtExpMonth->execute([$shop_id, $this_month]);
    $gastos_mes = (float)$stmtExpMonth->fetch()['total'];

    // 5. Comisiones Totales Barberos (Mes)
    $stmtCommMonth = $pdo->prepare("
        SELECT 
            (SELECT COALESCE(SUM(c.price * (c.commission_percent / 100)), 0) FROM appointments a JOIN catalog c ON a.item_id = c.id WHERE a.shop_id = ? AND DATE_FORMAT(a.appointment_datetime, '%Y-%m') = ? AND a.status != 'cancelled') +
            (SELECT COALESCE(SUM(total_commission), 0) FROM sales WHERE shop_id = ? AND DATE_FORMAT(created_at, '%Y-%m') = ?) as total
    ");
    $stmtCommMonth->execute([$shop_id, $this_month, $shop_id, $this_month]);
    $comisiones_mes = (float)$stmtCommMonth->fetch()['total'];

    // 6. Total Pagado a Barberos (Mes)
    $stmtPaidMonth = $pdo->prepare("SELECT COALESCE(SUM(amount), 0) as total FROM barber_payouts WHERE shop_id = ? AND DATE_FORMAT(date, '%Y-%m') = ? AND type = 'pago'");
    $stmtPaidMonth->execute([$shop_id, $this_month]);
    $pagado_barberos = (float)$stmtPaidMonth->fetch()['total'];

    // 7. Ganancia Neta (Ingresos Mes - Gastos Mes - Comisiones Mes)
    $ganancia_neta = $ingresos_mes - $gastos_mes - $comisiones_mes;

    // 8. Caja Actual (Caja abierta o balance en efectivo)
    $stmtCashReg = $pdo->prepare("SELECT initial_amount FROM cash_registers WHERE shop_id = ? AND status = 'open' ORDER BY id DESC LIMIT 1");
    $stmtCashReg->execute([$shop_id]);
    $cajaRow = $stmtCashReg->fetch();
    $caja_actual = $cajaRow ? (float)$cajaRow['initial_amount'] : ($ingresos_dia - $gastos_dia);

    // 9. Cantidad de Servicios Realizados (Mes)
    $stmtServCount = $pdo->prepare("SELECT COUNT(*) as total FROM appointments WHERE shop_id = ? AND DATE_FORMAT(appointment_datetime, '%Y-%m') = ? AND status != 'cancelled'");
    $stmtServCount->execute([$shop_id, $this_month]);
    $servicios_cantidad = (int)$stmtServCount->fetch()['total'];

    // 10. Ticket Promedio
    $ticket_promedio = ($servicios_cantidad > 0) ? ($ingresos_mes / $servicios_cantidad) : 0;

    // 11. Clientes Atendidos Únicos (Mes)
    $stmtClientCount = $pdo->prepare("SELECT COUNT(DISTINCT client_phone) as total FROM appointments WHERE shop_id = ? AND DATE_FORMAT(appointment_datetime, '%Y-%m') = ? AND status != 'cancelled'");
    $stmtClientCount->execute([$shop_id, $this_month]);
    $clientes_atendidos = (int)$stmtClientCount->fetch()['total'];
?>

  <!-- GRID DE TARJETAS KPI -->
  <div class="kpi-grid">
    <div class="kpi-card kpi-success">
      <span class="kpi-label">Ingresos Día</span>
      <span class="kpi-value">$<?php echo number_format($ingresos_dia, 2); ?></span>
    </div>

    <div class="kpi-card kpi-success">
      <span class="kpi-label">Ingresos Mes</span>
      <span class="kpi-value">$<?php echo number_format($ingresos_mes, 2); ?></span>
    </div>

    <div class="kpi-card kpi-danger">
      <span class="kpi-label">Gastos Día</span>
      <span class="kpi-value">-$<?php echo number_format($gastos_dia, 2); ?></span>
    </div>

    <div class="kpi-card kpi-danger">
      <span class="kpi-label">Gastos Mes</span>
      <span class="kpi-value">-$<?php echo number_format($gastos_mes, 2); ?></span>
    </div>

    <div class="kpi-card kpi-gold kpi-featured">
      <span class="kpi-label">💎 Ganancia Neta</span>
      <span class="kpi-value">$<?php echo number_format($ganancia_neta, 2); ?></span>
      <span class="kpi-sub">Ingresos − Gastos − Comisiones</span>
    </div>

    <div class="kpi-card kpi-blue">
      <span class="kpi-label">Pagado Barberos</span>
      <span class="kpi-value">$<?php echo number_format($pagado_barberos, 2); ?></span>
    </div>

    <div class="kpi-card kpi-warning">
      <span class="kpi-label">Caja Actual</span>
      <span class="kpi-value">$<?php echo number_format($caja_actual, 2); ?></span>
    </div>

    <div class="kpi-card kpi-violet">
      <span class="kpi-label">Servicios (Mes)</span>
      <span class="kpi-value"><?php echo $servicios_cantidad; ?></span>
    </div>

    <div class="kpi-card kpi-pink">
      <span class="kpi-label">Ticket Promedio</span>
      <span class="kpi-value">$<?php echo number_format($ticket_promedio, 2); ?></span>
    </div>

    <div class="kpi-card kpi-cyan">
      <span class="kpi-label">Clientes Atendidos</span>
      <span class="kpi-value"><?php echo $clientes_atendidos; ?></span>
    </div>
  </div>

  <!-- SECCIÓN DE 6 GRÁFICOS CHART.JS -->
  <div class="grid-cards" style="grid-template-columns: repeat(auto-fit, minmax(400px, 1fr)); gap: 1.5rem;">
    
    <!-- GRÁFICO 1: INGRESOS POR DÍA -->
    <div class="glass-panel">
      <h4 class="text-accent mb-4">📈 Ingresos por Día (Últimos 15 Días)</h4>
      <div style="position: relative; height: 260px;">
        <canvas id="chartIngresosDia"></canvas>
      </div>
    </div>

    <!-- GRÁFICO 2: GASTOS POR CATEGORÍA -->
    <div class="glass-panel">
      <h4 class="text-accent mb-4">🍩 Gastos por Categoría</h4>
      <div style="position: relative; height: 260px;">
        <canvas id="chartGastosCategoria"></canvas>
      </div>
    </div>

    <!-- GRÁFICO 3: GANANCIA MENSUAL -->
    <div class="glass-panel">
      <h4 class="text-accent mb-4">📊 Ganancia Mensual</h4>
      <div style="position: relative; height: 260px;">
        <canvas id="chartGananciaMensual"></canvas>
      </div>
    </div>

    <!-- GRÁFICO 4: SERVICIOS MÁS VENDIDOS -->
    <div class="glass-panel">
      <h4 class="text-accent mb-4">✂️ Servicios Más Vendidos</h4>
      <div style="position: relative; height: 260px;">
        <canvas id="chartServiciosMasVendidos"></canvas>
      </div>
    </div>

    <!-- GRÁFICO 5: PRODUCCIÓN POR BARBERO -->
    <div class="glass-panel">
      <h4 class="text-accent mb-4">💈 Producción y Comisiones por Barbero</h4>
      <div style="position: relative; height: 260px;">
        <canvas id="chartProduccionBarberos"></canvas>
      </div>
    </div>

    <!-- GRÁFICO 6: COMPARATIVA INGRESOS VS GASTOS -->
    <div class="glass-panel">
      <h4 class="text-accent mb-4">⚖️ Comparativa Ingresos vs Gastos</h4>
      <div style="position: relative; height: 260px;">
        <canvas id="chartIngresosVsGastos"></canvas>
      </div>
    </div>

  </div>

  <?php
    // Cargar datos para gráficos desde la BD
    // Data Gráfico 1: Ingresos por día
    $stmtDailyInc = $pdo->prepare("
        SELECT DATE(appointment_datetime) as f_date, SUM(c.price) as total
        FROM appointments a JOIN catalog c ON a.item_id = c.id
        WHERE a.shop_id = ? AND a.appointment_datetime >= DATE_SUB(CURDATE(), INTERVAL 14 DAY) AND a.status != 'cancelled'
        GROUP BY DATE(appointment_datetime) ORDER BY f_date ASC
    ");
    $stmtDailyInc->execute([$shop_id]);
    $dailyIncData = $stmtDailyInc->fetchAll();

    $labelsDays = [];
    $dataDays   = [];
    foreach ($dailyIncData as $d) {
        $labelsDays[] = date('d/m', strtotime($d['f_date']));
        $dataDays[]   = (float)$d['total'];
    }

    // Data Gráfico 2: Gastos por categoría
    $stmtCatExp = $pdo->prepare("SELECT category, SUM(amount) as total FROM expenses WHERE shop_id = ? GROUP BY category");
    $stmtCatExp->execute([$shop_id]);
    $catExpData = $stmtCatExp->fetchAll();

    $labelsCat = [];
    $dataCat   = [];
    foreach ($catExpData as $c) {
        $labelsCat[] = ucfirst($c['category']);
        $dataCat[]   = (float)$c['total'];
    }

    // Data Gráfico 4: Servicios más vendidos
    $stmtTopServices = $pdo->prepare("
        SELECT c.name, COUNT(*) as cant
        FROM appointments a JOIN catalog c ON a.item_id = c.id
        WHERE a.shop_id = ? AND a.status != 'cancelled'
        GROUP BY c.id ORDER BY cant DESC LIMIT 5
    ");
    $stmtTopServices->execute([$shop_id]);
    $topServData = $stmtTopServices->fetchAll();

    $labelsServ = [];
    $dataServ   = [];
    foreach ($topServData as $s) {
        $labelsServ[] = $s['name'];
        $dataServ[]   = (int)$s['cant'];
    }

    // Data Gráfico 5: Producción por Barbero
    $stmtBarberProd = $pdo->prepare("
        SELECT u.name, SUM(c.price) as rec, SUM(c.price * (c.commission_percent/100)) as com
        FROM appointments a JOIN catalog c ON a.item_id = c.id JOIN users u ON a.barber_id = u.id
        WHERE a.shop_id = ? AND a.status != 'cancelled'
        GROUP BY u.id
    ");
    $stmtBarberProd->execute([$shop_id]);
    $barberProdData = $stmtBarberProd->fetchAll();

    $labelsBarbers = [];
    $dataBarberRec = [];
    $dataBarberCom = [];
    foreach ($barberProdData as $bp) {
        $labelsBarbers[] = $bp['name'];
        $dataBarberRec[] = (float)$bp['rec'];
        $dataBarberCom[] = (float)$bp['com'];
    }
  ?>

  <script>
    document.addEventListener('DOMContentLoaded', () => {
      // 1. Chart Ingresos por día
      new Chart(document.getElementById('chartIngresosDia'), {
        type: 'line',
        data: {
          labels: <?php echo json_encode($labelsDays); ?>,
          datasets: [{
            label: 'Ingresos ($)',
            data: <?php echo json_encode($dataDays); ?>,
            borderColor: '#e6c875',
            backgroundColor: 'rgba(230, 200, 117, 0.15)',
            fill: true,
            tension: 0.35
          }]
        },
        options: { responsive: true, maintainAspectRatio: false }
      });

      // 2. Chart Gastos por categoría
      new Chart(document.getElementById('chartGastosCategoria'), {
        type: 'doughnut',
        data: {
          labels: <?php echo json_encode($labelsCat); ?>,
          datasets: [{
            data: <?php echo json_encode($dataCat); ?>,
            backgroundColor: ['#ef4444', '#f59e0b', '#3b82f6', '#8b5cf6', '#10b981', '#ec4899', '#06b6d4']
          }]
        },
        options: { responsive: true, maintainAspectRatio: false }
      });

      // 3. Chart Ganancia Mensual
      new Chart(document.getElementById('chartGananciaMensual'), {
        type: 'bar',
        data: {
          labels: ['Mes Actual'],
          datasets: [{
            label: 'Ganancia Neta ($)',
            data: [<?php echo $ganancia_neta; ?>],
            backgroundColor: '#10b981'
          }]
        },
        options: { responsive: true, maintainAspectRatio: false }
      });

      // 4. Chart Servicios Más Vendidos
      new Chart(document.getElementById('chartServiciosMasVendidos'), {
        type: 'bar',
        data: {
          labels: <?php echo json_encode($labelsServ); ?>,
          datasets: [{
            label: 'Cantidad Reservada',
            data: <?php echo json_encode($dataServ); ?>,
            backgroundColor: '#3b82f6'
          }]
        },
        options: { indexAxis: 'y', responsive: true, maintainAspectRatio: false }
      });

      // 5. Chart Producción por Barbero
      new Chart(document.getElementById('chartProduccionBarberos'), {
        type: 'bar',
        data: {
          labels: <?php echo json_encode($labelsBarbers); ?>,
          datasets: [
            { label: 'Recaudación ($)', data: <?php echo json_encode($dataBarberRec); ?>, backgroundColor: '#e6c875' },
            { label: 'Comisión ($)', data: <?php echo json_encode($dataBarberCom); ?>, backgroundColor: '#f59e0b' }
          ]
        },
        options: { responsive: true, maintainAspectRatio: false }
      });

      // 6. Chart Comparativa Ingresos vs Gastos
      new Chart(document.getElementById('chartIngresosVsGastos'), {
        type: 'bar',
        data: {
          labels: ['Mes Actual'],
          datasets: [
            { label: 'Ingresos ($)', data: [<?php echo $ingresos_mes; ?>], backgroundColor: '#10b981' },
            { label: 'Gastos ($)', data: [<?php echo $gastos_mes; ?>], backgroundColor: '#ef4444' }
          ]
        },
        options: { responsive: true, maintainAspectRatio: false }
      });
    });
  </script>

<?php
// =========================================================================
// SUB-TAB 2: GESTIÓN DE GASTOS
// =========================================================================
elseif ($subtab === 'gastos'):
    $search_q    = trim($_GET['search'] ?? '');
    $cat_filter  = trim($_GET['category'] ?? 'todos');
    $date_from   = trim($_GET['from'] ?? '');
    $date_to     = trim($_GET['to'] ?? '');

    $sqlExp = "SELECT * FROM expenses WHERE shop_id = ?";
    $paramsExp = [$shop_id];

    if (!empty($search_q)) {
        $sqlExp .= " AND (description LIKE ? OR observations LIKE ?)";
        $paramsExp[] = "%$search_q%";
        $paramsExp[] = "%$search_q%";
    }
    if ($cat_filter !== 'todos') {
        $sqlExp .= " AND category = ?";
        $paramsExp[] = $cat_filter;
    }
    if (!empty($date_from)) {
        $sqlExp .= " AND date >= ?";
        $paramsExp[] = $date_from;
    }
    if (!empty($date_to)) {
        $sqlExp .= " AND date <= ?";
        $paramsExp[] = $date_to;
    }

    $sqlExp .= " ORDER BY date DESC, id DESC";
    $stmtExpList = $pdo->prepare($sqlExp);
    $stmtExpList->execute($paramsExp);
    $expenses_list = $stmtExpList->fetchAll();

    $edit_exp_id = (int)($_GET['edit_exp'] ?? 0);
    $edit_exp_item = null;
    if ($edit_exp_id > 0) {
        foreach ($expenses_list as $el) {
            if ($el['id'] === $edit_exp_id) {
                $edit_exp_item = $el;
                break;
            }
        }
    }
?>

  <div class="grid-cards mb-6">
    <!-- REGISTRO Y EDICIÓN DE GASTOS -->
    <div>
      <h3 class="mb-4 text-accent"><?php echo $edit_exp_item ? '✏️ Editar Gasto' : '➕ Registrar Nuevo Gasto'; ?></h3>

      <form action="actions/save_expense.php" method="POST" class="glass-panel flex flex-col gap-3" style="margin:0;">
        <?php echo csrf_field(); ?>
        <?php if ($edit_exp_item): ?>
          <input type="hidden" name="id" value="<?php echo $edit_exp_item['id']; ?>">
        <?php endif; ?>

        <div class="grid-cards" style="grid-template-columns: 1fr 1fr;">
          <div class="input-group">
            <label class="input-label">Fecha del Gasto *</label>
            <input type="date" name="date" class="input-field" required value="<?php echo htmlspecialchars($edit_exp_item['date'] ?? date('Y-m-d')); ?>">
          </div>

          <div class="input-group">
            <label class="input-label">Categoría *</label>
            <select name="category" class="input-field" required>
              <?php 
                $cats = ['insumos'=>'Insumos', 'alquiler'=>'Alquiler', 'servicios'=>'Servicios', 'publicidad'=>'Publicidad', 'sueldos'=>'Sueldos', 'impuestos'=>'Impuestos', 'mantenimiento'=>'Mantenimiento', 'otros'=>'Otros'];
                foreach ($cats as $k => $v):
              ?>
                <option value="<?php echo $k; ?>" <?php echo ($edit_exp_item['category'] ?? '') === $k ? 'selected' : ''; ?>>
                  <?php echo $v; ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>

        <div class="input-group">
          <label class="input-label">Descripción del Gasto *</label>
          <input type="text" name="description" class="input-field" placeholder="Ej. Compra de productos de barbería (Champú, cera)" required value="<?php echo htmlspecialchars($edit_exp_item['description'] ?? ''); ?>">
        </div>

        <div class="grid-cards" style="grid-template-columns: 1fr 1fr;">
          <div class="input-group">
            <label class="input-label">Monto ($) *</label>
            <input type="number" step="0.01" name="amount" class="input-field" placeholder="0.00" required value="<?php echo htmlspecialchars($edit_exp_item['amount'] ?? ''); ?>">
          </div>

          <div class="input-group">
            <label class="input-label">Método de Pago *</label>
            <select name="payment_method" class="input-field" required>
              <option value="efectivo" <?php echo ($edit_exp_item['payment_method'] ?? '') === 'efectivo' ? 'selected' : ''; ?>>Efectivo</option>
              <option value="transferencia" <?php echo ($edit_exp_item['payment_method'] ?? '') === 'transferencia' ? 'selected' : ''; ?>>Transferencia Bancaria</option>
              <option value="mercado_pago" <?php echo ($edit_exp_item['payment_method'] ?? '') === 'mercado_pago' ? 'selected' : ''; ?>>Mercado Pago</option>
              <option value="tarjeta" <?php echo ($edit_exp_item['payment_method'] ?? '') === 'tarjeta' ? 'selected' : ''; ?>>Tarjeta de Débito/Crédito</option>
            </select>
          </div>
        </div>

        <div class="input-group">
          <label class="input-label">Observaciones (Opcional)</label>
          <input type="text" name="observations" class="input-field" placeholder="Ej. Factura #4582" value="<?php echo htmlspecialchars($edit_exp_item['observations'] ?? ''); ?>">
        </div>

        <div class="flex gap-2 mt-2">
          <button type="submit" class="btn btn-primary flex-1">
            <?php echo $edit_exp_item ? '💾 Guardar Cambios' : '➕ Registrar Gasto'; ?>
          </button>

          <?php if ($edit_exp_item): ?>
            <a href="owner.php?tab=finanzas&subtab=gastos" class="btn btn-outline">Cancelar</a>
          <?php endif; ?>
        </div>
      </form>
    </div>

    <!-- LISTADO Y FILTROS DE GASTOS -->
    <div>
      <h3 class="mb-4">Historial de Gastos Registrados (<?php echo count($expenses_list); ?>)</h3>

      <form method="GET" action="owner.php" class="flex gap-2 mb-4 flex-wrap items-center">
        <input type="hidden" name="tab" value="finanzas">
        <input type="hidden" name="subtab" value="gastos">

        <input type="text" name="search" class="input-field flex-1" placeholder="Buscar por descripción..." value="<?php echo htmlspecialchars($search_q); ?>" style="padding:6px 12px;">

        <select name="category" class="input-field" onchange="this.form.submit()" style="padding:6px 12px; width:auto;">
          <option value="todos">Todas las Categorías</option>
          <?php foreach ($cats as $k => $v): ?>
            <option value="<?php echo $k; ?>" <?php echo $cat_filter === $k ? 'selected' : ''; ?>><?php echo $v; ?></option>
          <?php endforeach; ?>
        </select>

        <button type="submit" class="btn btn-outline" style="padding:6px 12px;">🔍 Buscar</button>
      </form>

      <?php if (count($expenses_list) === 0): ?>
        <p style="color:var(--text-secondary);">No se encontraron gastos registrados con los filtros seleccionados.</p>
      <?php else: ?>
        <div class="table-responsive">
          <table>
            <thead>
              <tr>
                <th>Fecha</th>
                <th>Categoría</th>
                <th>Descripción</th>
                <th>Monto</th>
                <th>Pago</th>
                <th class="text-center">Acciones</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($expenses_list as $exp): ?>
                <tr>
                  <td><?php echo date('d/m/Y', strtotime($exp['date'])); ?></td>
                  <td>
                    <span class="role-tag" style="background:rgba(239,68,68,0.2); color:var(--danger); border:1px solid var(--danger);">
                      <?php echo strtoupper($exp['category']); ?>
                    </span>
                  </td>
                  <td>
                    <strong><?php echo htmlspecialchars($exp['description']); ?></strong>
                    <?php if (!empty($exp['observations'])): ?>
                      <br><small style="color:var(--text-secondary);"><?php echo htmlspecialchars($exp['observations']); ?></small>
                    <?php endif; ?>
                  </td>
                  <td class="font-bold text-danger">-$<?php echo number_format((float)$exp['amount'], 2); ?></td>
                  <td><small><?php echo strtoupper($exp['payment_method']); ?></small></td>
                  <td class="text-center">
                    <div class="flex gap-1 justify-center">
                      <a href="owner.php?tab=finanzas&subtab=gastos&edit_exp=<?php echo $exp['id']; ?>" class="btn btn-outline btn-sm" title="Editar Gasto">
                        ✏️
                      </a>
                      <form action="actions/delete_expense.php" method="POST" onsubmit="return confirm('¿Eliminar este gasto?')" style="margin:0;">
                        <?php echo csrf_field(); ?>
                        <input type="hidden" name="id" value="<?php echo $exp['id']; ?>">
                        <button type="submit" class="btn btn-danger btn-sm" title="Eliminar Gasto">
                          🗑
                        </button>
                      </form>
                    </div>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    </div>
  </div>

<?php
// =========================================================================
// SUB-TAB 3: PAGO A BARBEROS Y RECIBOS IMPRIMIBLES
// =========================================================================
elseif ($subtab === 'barberos_pago'):
    $stmtBarbers = $pdo->prepare("SELECT id, name, email, photo FROM users WHERE shop_id = ? AND role IN ('barber', 'owner')");
    $stmtBarbers->execute([$shop_id]);
    $barbers = $stmtBarbers->fetchAll();

    $receipt_id = (int)($_GET['receipt_id'] ?? 0);
    $receipt_data = null;
    if ($receipt_id > 0) {
        $stmtRec = $pdo->prepare("
            SELECT bp.*, u.name as barber_name, u.email as barber_email, s.name as shop_name
            FROM barber_payouts bp
            JOIN users u ON bp.barber_id = u.id
            JOIN shops s ON bp.shop_id = s.id
            WHERE bp.id = ? AND bp.shop_id = ?
        ");
        $stmtRec->execute([$receipt_id, $shop_id]);
        $receipt_data = $stmtRec->fetch();
    }
?>

  <!-- MODAL RECIBO DE PAGO IMPRIMIBLE -->
  <?php if ($receipt_data): ?>
    <div id="receipt-modal" class="modal-overlay">
      <div class="modal-card animate-scale-up" style="max-width: 550px; background: #ffffff; color: #111111; font-family: sans-serif; border-radius: 12px; padding: 2rem;">
        <button type="button" class="modal-close-btn" onclick="document.getElementById('receipt-modal').style.display='none';" style="color:#000;">&times;</button>
        
        <div class="text-center mb-4" style="border-bottom: 2px solid #e6c875; padding-bottom: 1rem;">
          <h2 style="margin:0; color:#111827; font-size:1.6rem;">💈 <?php echo htmlspecialchars($receipt_data['shop_name']); ?></h2>
          <p style="margin:4px 0 0 0; color:#6b7280; font-size:0.9rem;">COMPROBANTE OFICIAL DE LIQUIDACIÓN / PAGO</p>
        </div>

        <div class="flex justify-between mb-4" style="font-size:0.9rem;">
          <div>
            <strong>Recibo N°:</strong> <span style="font-family:monospace;"><?php echo htmlspecialchars($receipt_data['receipt_number']); ?></span><br>
            <strong>Fecha:</strong> <?php echo date('d/m/Y', strtotime($receipt_data['date'])); ?>
          </div>
          <div class="text-right">
            <strong>Barbero:</strong> <?php echo htmlspecialchars($receipt_data['barber_name']); ?><br>
            <strong>Tipo:</strong> <?php echo strtoupper($receipt_data['type']); ?>
          </div>
        </div>

        <div style="background:#f3f4f6; border-radius:8px; padding:1.25rem; margin-bottom:1.5rem;">
          <div class="flex justify-between items-center" style="font-size:1.1rem;">
            <span>Monto Abonado:</span>
            <strong style="font-size:1.4rem; color:#059669;">$<?php echo number_format((float)$receipt_data['amount'], 2); ?></strong>
          </div>
          <p style="margin:0.5rem 0 0 0; font-size:0.85rem; color:#4b5563;">
            Método de Pago: <strong><?php echo strtoupper($receipt_data['payment_method']); ?></strong><br>
            Detalle: <?php echo htmlspecialchars($receipt_data['description'] ?: 'Sin observaciones'); ?>
          </p>
        </div>

        <div class="flex justify-between items-center mt-6" style="border-top:1px dashed #d1d5db; padding-top:1.5rem; font-size:0.8rem; text-align:center;">
          <div style="width:45%; border-top:1px solid #9ca3af; padding-top:4px;">Firma Administrador</div>
          <div style="width:45%; border-top:1px solid #9ca3af; padding-top:4px;">Firma Barbero</div>
        </div>

        <div class="text-center mt-6">
          <button type="button" onclick="window.print()" class="btn btn-primary" style="padding:0.75rem 2rem; font-weight:bold;">
            🖨️ Imprimir Recibo
          </button>
        </div>
      </div>
    </div>
  <?php endif; ?>

  <div class="grid-cards mb-6">
    <!-- FORMULARIO REGISTRAR PAGO A BARBERO -->
    <div>
      <h3 class="mb-4 text-accent">💵 Registrar Pago / Adelanto a Barbero</h3>

      <form action="actions/save_barber_payout.php" method="POST" class="glass-panel flex flex-col gap-3" style="margin:0;">
        <?php echo csrf_field(); ?>
        <div class="input-group">
          <label class="input-label">Seleccionar Barbero *</label>
          <select name="barber_id" class="input-field" required>
            <?php foreach ($barbers as $b): ?>
              <option value="<?php echo $b['id']; ?>">💈 <?php echo htmlspecialchars($b['name']); ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="grid-cards" style="grid-template-columns: 1fr 1fr;">
          <div class="input-group">
            <label class="input-label">Tipo de Movimiento *</label>
            <select name="type" class="input-field" required>
              <option value="pago">Pago de Comisión</option>
              <option value="adelanto">Adelanto / Anticipo (-)</option>
              <option value="bonificacion">Bonificación (+)</option>
              <option value="descuento">Descuento (-)</option>
            </select>
          </div>

          <div class="input-group">
            <label class="input-label">Fecha *</label>
            <input type="date" name="date" class="input-field" required value="<?php echo date('Y-m-d'); ?>">
          </div>
        </div>

        <div class="grid-cards" style="grid-template-columns: 1fr 1fr;">
          <div class="input-group">
            <label class="input-label">Monto ($) *</label>
            <input type="number" step="0.01" name="amount" class="input-field" placeholder="0.00" required>
          </div>

          <div class="input-group">
            <label class="input-label">Método de Pago *</label>
            <select name="payment_method" class="input-field" required>
              <option value="efectivo">Efectivo</option>
              <option value="transferencia">Transferencia Bancaria</option>
              <option value="mercado_pago">Mercado Pago</option>
            </select>
          </div>
        </div>

        <div class="input-group">
          <label class="input-label">Observaciones / Motivo</label>
          <input type="text" name="description" class="input-field" placeholder="Ej. Liquidación semanal comisiones 50%">
        </div>

        <button type="submit" class="btn btn-primary mt-2">
          💾 Registrar Pago e Imprimir Recibo
        </button>
      </form>
    </div>

    <!-- CUENTAS CORRIENTES Y ESTADO DE BARBEROS -->
    <div>
      <h3 class="mb-4">Estado de Cuentas por Barbero</h3>

      <div class="flex flex-col gap-3">
        <?php foreach ($barbers as $b): ?>
          <?php
            // Calcular estadísticas del barbero
            $stmtStats = $pdo->prepare("
                SELECT 
                    COUNT(*) as cant_turnos,
                    COALESCE(SUM(c.price), 0) as rec_total,
                    COALESCE(SUM(c.price * (c.commission_percent / 100)), 0) as com_total
                FROM appointments a JOIN catalog c ON a.item_id = c.id
                WHERE a.shop_id = ? AND a.barber_id = ? AND a.status != 'cancelled'
            ");
            $stmtStats->execute([$shop_id, $b['id']]);
            $bStats = $stmtStats->fetch();

            $stmtPay = $pdo->prepare("SELECT COALESCE(SUM(amount), 0) as total FROM barber_payouts WHERE shop_id = ? AND barber_id = ? AND type = 'pago'");
            $stmtPay->execute([$shop_id, $b['id']]);
            $bPaid = (float)$stmtPay->fetch()['total'];

            $saldo_pendiente = (float)$bStats['com_total'] - $bPaid;
            $bPhoto = !empty($b['photo']) ? $b['photo'] : 'assets/img/service_corte_clasico_1785440488368.png';
          ?>
          <div class="glass-panel" style="margin:0;">
            <div class="flex justify-between items-center flex-wrap gap-2 mb-2">
              <div class="flex items-center gap-3">
                <img src="<?php echo htmlspecialchars($bPhoto); ?>" style="width:45px; height:45px; border-radius:50%; object-fit:cover; border:2px solid var(--accent-primary);">
                <div>
                  <strong style="font-size:1.1rem; color:var(--text-primary);"><?php echo htmlspecialchars($b['name']); ?></strong><br>
                  <small style="color:var(--text-secondary);">Cortes realizados: <strong><?php echo $bStats['cant_turnos']; ?></strong></small>
                </div>
              </div>

              <div class="text-right">
                <small style="color:var(--text-secondary);">Saldo Pendiente:</small><br>
                <strong style="font-size:1.3rem; color:<?php echo $saldo_pendiente > 0 ? 'var(--danger)' : 'var(--success)'; ?>;">
                  $<?php echo number_format($saldo_pendiente, 2); ?>
                </strong>
              </div>
            </div>

            <div class="flex justify-between items-center pt-2" style="border-top: 1px solid var(--glass-border); font-size:0.85rem;">
              <span>Recaudado: <strong>$<?php echo number_format((float)$bStats['rec_total'], 2); ?></strong></span>
              <span>Comisión Total: <strong class="text-accent">$<?php echo number_format((float)$bStats['com_total'], 2); ?></strong></span>
              <span>Pagado: <strong class="text-success">$<?php echo number_format($bPaid, 2); ?></strong></span>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
  </div>

<?php
// =========================================================================
// SUB-TAB 4: CIERRE Y APERTURA DE CAJA
// =========================================================================
elseif ($subtab === 'caja'):
    // Buscar si hay caja abierta
    $stmtActiveReg = $pdo->prepare("
        SELECT cr.*, u.name as opener_name 
        FROM cash_registers cr 
        JOIN users u ON cr.opened_by = u.id 
        WHERE cr.shop_id = ? AND cr.status = 'open' 
        ORDER BY cr.id DESC LIMIT 1
    ");
    $stmtActiveReg->execute([$shop_id]);
    $active_register = $stmtActiveReg->fetch();

    // Historial de cajas cerradas
    $stmtRegHist = $pdo->prepare("
        SELECT cr.*, u.name as opener_name, u2.name as closer_name
        FROM cash_registers cr
        JOIN users u ON cr.opened_by = u.id
        LEFT JOIN users u2 ON cr.closed_by = u2.id
        WHERE cr.shop_id = ?
        ORDER BY cr.id DESC LIMIT 15
    ");
    $stmtRegHist->execute([$shop_id]);
    $registers_history = $stmtRegHist->fetchAll();
?>

  <div class="grid-cards mb-6">
    <!-- PANEL ESTADO DE CAJA ACTUAL -->
    <div>
      <h3 class="mb-4 text-accent">🔒 Control de Caja Diaria</h3>

      <?php if ($active_register): ?>
        <div class="glass-panel" style="border: 2px solid var(--success); background: rgba(16, 185, 129, 0.08);">
          <div class="flex justify-between items-center mb-3">
            <span class="role-tag" style="background:var(--success); color:#000;">🟢 CAJA ABIERTA</span>
            <small style="color:var(--text-secondary);">Abierta por <strong><?php echo htmlspecialchars($active_register['opener_name']); ?></strong> el <?php echo date('d/m/Y H:i', strtotime($active_register['open_date'])); ?></small>
          </div>

          <h3 class="mb-4">Monto de Apertura: <span class="text-warning">$<?php echo number_format((float)$active_register['initial_amount'], 2); ?></span></h3>

          <form action="actions/manage_cash_register.php" method="POST" class="flex flex-col gap-3">
            <?php echo csrf_field(); ?>
            <input type="hidden" name="action" value="close_cash">
            <input type="hidden" name="register_id" value="<?php echo $active_register['id']; ?>">

            <div class="input-group">
              <label class="input-label">Contado Real en Caja (Efectivo Físico) *</label>
              <input type="number" step="0.01" name="real_cash" class="input-field" placeholder="Monto total en efectivo contado en billetes" required style="font-size:1.1rem; padding:10px;">
            </div>

            <div class="input-group">
              <label class="input-label">Observaciones del Cierre</label>
              <input type="text" name="observations" class="input-field" placeholder="Ej. Sin novedad / Faltante justificado">
            </div>

            <button type="submit" class="btn btn-danger w-full mt-2" style="font-size:1.1rem; padding:0.85rem;">
              🔒 Realizar Arqueo y Cerrar Caja
            </button>
          </form>
        </div>
      <?php else: ?>
        <div class="glass-panel" style="border: 2px solid var(--warning); background: rgba(245, 158, 11, 0.08);">
          <div class="mb-3">
            <span class="role-tag" style="background:var(--danger); color:#fff;">🔴 CAJA CERRADA</span>
            <p style="color:var(--text-secondary); margin-top:8px; font-size:0.9rem;">No hay ninguna caja abierta en este momento para la barbería. Registra el monto inicial de la caja para comenzar.</p>
          </div>

          <form action="actions/manage_cash_register.php" method="POST" class="flex flex-col gap-3">
            <?php echo csrf_field(); ?>
            <input type="hidden" name="action" value="open_cash">

            <div class="input-group">
              <label class="input-label">Monto Inicial de Apertura en Caja ($) *</label>
              <input type="number" step="0.01" name="initial_amount" class="input-field" placeholder="Ej. 10000.00" required value="0.00">
            </div>

            <div class="input-group">
              <label class="input-label">Observaciones</label>
              <input type="text" name="observations" class="input-field" placeholder="Ej. Cambio inicial en billetes de 500 y 1000">
            </div>

            <button type="submit" class="btn btn-primary w-full mt-2" style="font-size:1.1rem; padding:0.85rem;">
              🔑 Abrir Caja para la Jornada
            </button>
          </form>
        </div>
      <?php endif; ?>
    </div>

    <!-- HISTORIAL DE CAJAS -->
    <div>
      <h3 class="mb-4">Historial de Cajas y Arqueos</h3>

      <?php if (count($registers_history) === 0): ?>
        <p style="color:var(--text-secondary);">No hay cierres de caja registrados en el historial.</p>
      <?php else: ?>
        <div class="table-responsive">
          <table>
            <thead>
              <tr>
                <th>Apertura</th>
                <th>Inicial</th>
                <th>Cobrado Efec</th>
                <th>Esperado</th>
                <th>Real Contado</th>
                <th>Diferencia</th>
                <th>Acción</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($registers_history as $reg): ?>
                <tr>
                  <td><?php echo date('d/m H:i', strtotime($reg['open_date'])); ?></td>
                  <td>$<?php echo number_format((float)$reg['initial_amount'], 2); ?></td>
                  <td>$<?php echo number_format((float)$reg['cash_sales'], 2); ?></td>
                  <td>$<?php echo number_format((float)$reg['expected_cash'], 2); ?></td>
                  <td><strong>$<?php echo number_format((float)$reg['real_cash'], 2); ?></strong></td>
                  <td>
                    <?php 
                      $diff = (float)$reg['difference'];
                      if ($diff > 0) echo "<span class='text-success font-bold'>+$" . number_format($diff, 2) . "</span>";
                      elseif ($diff < 0) echo "<span class='text-danger font-bold'>-$" . number_format(abs($diff), 2) . "</span>";
                      else echo "<span class='text-secondary'>$0.00</span>";
                    ?>
                  </td>
                  <td>
                    <?php if ($reg['status'] === 'closed'): ?>
                      <form action="actions/manage_cash_register.php" method="POST" style="margin:0;">
                        <?php echo csrf_field(); ?>
                        <input type="hidden" name="action" value="reopen_cash">
                        <input type="hidden" name="register_id" value="<?php echo $reg['id']; ?>">
                        <button type="submit" class="btn btn-outline" style="padding:2px 6px; font-size:0.75rem;" title="Reabrir caja autorizada por Admin">
                          🔓 Reabrir
                        </button>
                      </form>
                    <?php else: ?>
                      <span class="text-success font-bold">ABIERTA</span>
                    <?php endif; ?>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    </div>
  </div>

<?php
// =========================================================================
// SUB-TAB 5: EXPORTADOR EXCEL / CSV
// =========================================================================
elseif ($subtab === 'exportar' || $subtab === 'importador'):
    $exp_month = date('Y-m');
?>

  <h3 class="mb-2 text-accent">📤 Exportar Reportes Financieros a Excel / CSV</h3>
  <p style="color:var(--text-secondary);" class="mb-6">Descarga informes oficiales en formato Excel (.CSV compatible con Microsoft Excel y Hojas de Cálculo) con codificación UTF-8 y montos formateados.</p>

  <div class="grid-cards mb-6" style="grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); gap: 1.5rem;">
    
    <!-- TARJETA 1: REPORTES DE GASTOS -->
    <div class="glass-panel flex flex-col justify-between" style="margin:0; border-top: 4px solid var(--danger);">
      <div>
        <div class="flex items-center gap-2 mb-2">
          <span style="font-size: 1.8rem;">💸</span>
          <h4 style="margin:0; font-size:1.15rem; color:var(--text-primary);">Reporte de Gastos</h4>
        </div>
        <p style="font-size:0.85rem; color:var(--text-secondary); margin-bottom:1.25rem; line-height:1.4;">
          Exporta el detalle de todos los egresos registrados, categorizados con sus métodos de pago.
        </p>
      </div>

      <form action="actions/export_finance_reports.php" method="GET" class="flex flex-col gap-3">
        <input type="hidden" name="type" value="gastos">
        <div class="input-group">
          <label class="input-label">Seleccionar Mes:</label>
          <input type="month" name="month" class="input-field" value="<?php echo $exp_month; ?>" required style="padding:6px 10px;">
        </div>
        <button type="submit" class="btn btn-danger w-full" style="font-weight:bold;">
          📊 Descargar Excel de Gastos
        </button>
      </form>
    </div>

    <!-- TARJETA 2: PAGOS Y COMISIONES A BARBEROS -->
    <div class="glass-panel flex flex-col justify-between" style="margin:0; border-top: 4px solid #3b82f6;">
      <div>
        <div class="flex items-center gap-2 mb-2">
          <span style="font-size: 1.8rem;">💈</span>
          <h4 style="margin:0; font-size:1.15rem; color:var(--text-primary);">Pagos a Barberos</h4>
        </div>
        <p style="font-size:0.85rem; color:var(--text-secondary); margin-bottom:1.25rem; line-height:1.4;">
          Exporta las liquidaciones, adelantos, bonificaciones y comprobantes de pago de los barberos.
        </p>
      </div>

      <form action="actions/export_finance_reports.php" method="GET" class="flex flex-col gap-3">
        <input type="hidden" name="type" value="barberos">
        <div class="input-group">
          <label class="input-label">Seleccionar Mes:</label>
          <input type="month" name="month" class="input-field" value="<?php echo $exp_month; ?>" required style="padding:6px 10px;">
        </div>
        <button type="submit" class="btn btn-primary w-full" style="font-weight:bold;">
          📊 Descargar Excel de Pagos
        </button>
      </form>
    </div>

    <!-- TARJETA 3: HISTORIAL DE TURNOS Y ATENCIONES -->
    <div class="glass-panel flex flex-col justify-between" style="margin:0; border-top: 4px solid var(--success);">
      <div>
        <div class="flex items-center gap-2 mb-2">
          <span style="font-size: 1.8rem;">✂️</span>
          <h4 style="margin:0; font-size:1.15rem; color:var(--text-primary);">Historial de Turnos</h4>
        </div>
        <p style="font-size:0.85rem; color:var(--text-secondary); margin-bottom:1.25rem; line-height:1.4;">
          Exporta el registro completo de atenciones a clientes, barberos asignados y valores cobrados.
        </p>
      </div>

      <form action="actions/export_finance_reports.php" method="GET" class="flex flex-col gap-3">
        <input type="hidden" name="type" value="turnos">
        <div class="input-group">
          <label class="input-label">Seleccionar Mes:</label>
          <input type="month" name="month" class="input-field" value="<?php echo $exp_month; ?>" required style="padding:6px 10px;">
        </div>
        <button type="submit" class="btn btn-success w-full" style="font-weight:bold;">
          📊 Descargar Excel de Turnos
        </button>
      </form>
    </div>

    <!-- TARJETA 4: CONSOLIDADO DE CIERRES DE CAJA -->
    <div class="glass-panel flex flex-col justify-between" style="margin:0; border-top: 4px solid var(--accent-primary);">
      <div>
        <div class="flex items-center gap-2 mb-2">
          <span style="font-size: 1.8rem;">🔒</span>
          <h4 style="margin:0; font-size:1.15rem; color:var(--text-primary);">Cierres de Caja</h4>
        </div>
        <p style="font-size:0.85rem; color:var(--text-secondary); margin-bottom:1.25rem; line-height:1.4;">
          Exporta la planilla diaria consolidada de cierres de caja con neto para la barbería.
        </p>
      </div>

      <form action="actions/export_closure_excel.php" method="GET" class="flex flex-col gap-3">
        <div class="input-group">
          <label class="input-label">Seleccionar Mes:</label>
          <input type="month" name="month" class="input-field" value="<?php echo $exp_month; ?>" required style="padding:6px 10px;">
        </div>
        <button type="submit" class="btn btn-outline w-full" style="font-weight:bold; border-color:var(--accent-primary); color:var(--accent-primary);">
          📊 Descargar Excel Cierres
        </button>
      </form>
    </div>

  </div>

<?php
// =========================================================================
// SUB-TAB 6: REGISTRO DE AUDITORÍA
// =========================================================================
elseif ($subtab === 'auditoria'):
    $search_action = trim($_GET['action_filter'] ?? 'todas');

    $sqlAudit = "SELECT * FROM audit_logs WHERE shop_id = ?";
    $paramsAudit = [$shop_id];

    if ($search_action !== 'todas') {
        $sqlAudit .= " AND action = ?";
        $paramsAudit[] = $search_action;
    }

    $sqlAudit .= " ORDER BY id DESC LIMIT 100";
    $stmtAudit = $pdo->prepare($sqlAudit);
    $stmtAudit->execute($paramsAudit);
    $audit_logs = $stmtAudit->fetchAll();
?>

  <div class="flex justify-between items-center mb-4 flex-wrap gap-4">
    <div>
      <h3 class="m-0 text-accent">📜 Registro de Auditoría de Operaciones</h3>
      <p style="color:var(--text-secondary); font-size:0.85rem; margin-top:2px;">
        Historial detallado e inalterable de quién creó, editó o eliminó registros en el sistema.
      </p>
    </div>

    <form method="GET" action="owner.php" class="flex gap-2 items-center">
      <input type="hidden" name="tab" value="finanzas">
      <input type="hidden" name="subtab" value="auditoria">

      <select name="action_filter" class="input-field" onchange="this.form.submit()" style="padding:6px 12px;">
        <option value="todas">Todas las Acciones</option>
        <option value="CREAR" <?php echo $search_action === 'CREAR' ? 'selected' : ''; ?>>CREAR</option>
        <option value="EDITAR" <?php echo $search_action === 'EDITAR' ? 'selected' : ''; ?>>EDITAR</option>
        <option value="ELIMINAR" <?php echo $search_action === 'ELIMINAR' ? 'selected' : ''; ?>>ELIMINAR</option>
        <option value="LOGIN_SUCCESS" <?php echo $search_action === 'LOGIN_SUCCESS' ? 'selected' : ''; ?>>LOGIN</option>
        <option value="CIERRE_CAJA" <?php echo $search_action === 'CIERRE_CAJA' ? 'selected' : ''; ?>>CIERRE DE CAJA</option>
      </select>
    </form>
  </div>

  <?php if (count($audit_logs) === 0): ?>
    <div class="glass-panel text-center" style="padding:2rem;">
      <p style="color:var(--text-secondary);">No se encontraron eventos registrados en la auditoría.</p>
    </div>
  <?php else: ?>
    <div class="table-responsive">
      <table>
        <thead>
          <tr>
            <th>Fecha / Hora</th>
            <th>Usuario</th>
            <th>Acción</th>
            <th>Entidad</th>
            <th>Detalles del Cambio</th>
            <th>Dirección IP</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($audit_logs as $log): ?>
            <tr>
              <td style="white-space:nowrap;"><small><?php echo date('d/m/Y H:i:s', strtotime($log['created_at'])); ?></small></td>
              <td>
                <strong><?php echo htmlspecialchars($log['user_name']); ?></strong>
                <?php if ($log['user_id']): ?>
                  <br><small style="color:var(--text-secondary);">ID #<?php echo $log['user_id']; ?></small>
                <?php endif; ?>
              </td>
              <td>
                <?php 
                  $act = $log['action'];
                  $badgeColor = 'var(--text-secondary)';
                  $bgColor = 'rgba(255,255,255,0.1)';

                  if (strpos($act, 'CREAR') !== false || strpos($act, 'RESERVA') !== false || strpos($act, 'ABRIR') !== false) {
                      $badgeColor = 'var(--success)';
                      $bgColor = 'rgba(16,185,129,0.15)';
                  } elseif (strpos($act, 'EDITAR') !== false || strpos($act, 'CAMBIAR') !== false) {
                      $badgeColor = 'var(--warning)';
                      $bgColor = 'rgba(245,158,11,0.15)';
                  } elseif (strpos($act, 'ELIMINAR') !== false || strpos($act, 'CANCELAR') !== false || strpos($act, 'FAIL') !== false) {
                      $badgeColor = 'var(--danger)';
                      $bgColor = 'rgba(239,68,68,0.15)';
                  } elseif (strpos($act, 'LOGIN') !== false || strpos($act, 'CIERRE') !== false) {
                      $badgeColor = 'var(--accent-primary)';
                      $bgColor = 'rgba(230,200,117,0.15)';
                  }
                ?>
                <span class="role-tag" style="background:<?php echo $bgColor; ?>; color:<?php echo $badgeColor; ?>; border:1px solid <?php echo $badgeColor; ?>;">
                  <?php echo htmlspecialchars($act); ?>
                </span>
              </td>
              <td><span style="font-family:monospace; font-size:0.8rem; text-transform:uppercase; color:var(--text-secondary);"><?php echo htmlspecialchars($log['entity_type']); ?></span></td>
              <td><small><?php echo htmlspecialchars($log['details']); ?></small></td>
              <td><small style="color:var(--text-secondary); font-family:monospace;"><?php echo htmlspecialchars($log['ip_address']); ?></small></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>

<?php elseif ($subtab === 'periodos'): ?>
  <?php include __DIR__ . '/period_report_module.php'; ?>
<?php endif; ?>

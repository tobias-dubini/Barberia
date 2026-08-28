<?php
// includes/period_report_module.php - Panel de Análisis Financiero por Períodos
if (!defined('HEADER_INCLUDED')) {
    require_once __DIR__ . '/auth.php';
    $user = require_role('owner');
}

$shop_id = $user['shop_id'];

// Obtener parámetros de filtro
$period_type = $_GET['period_type'] ?? 'mes'; // dia, semana, quincena, mes, personalizado
$selected_date = validate_date($_GET['period_date'] ?? '') ? $_GET['period_date'] : date('Y-m-d');
$selected_month = $_GET['period_month'] ?? date('Y-m');
$selected_fortnight = $_GET['period_fortnight'] ?? (date('d') <= 15 ? 'Q1' : 'Q2');
$date_from = validate_date($_GET['date_from'] ?? '') ? $_GET['date_from'] : date('Y-m-01');
$date_to   = validate_date($_GET['date_to'] ?? '') ? $_GET['date_to'] : date('Y-m-t');

// Determinar fechas exactas inicio y fin según la selección
if ($period_type === 'dia') {
    $start_date = $selected_date;
    $end_date   = $selected_date;
    $period_label = "Día: " . date('d/m/Y', strtotime($selected_date));
} elseif ($period_type === 'semana') {
    // Semana actual (Lunes a Domingo o últimos 7 días)
    $start_date = date('Y-m-d', strtotime($selected_date . ' - 6 days'));
    $end_date   = $selected_date;
    $period_label = "Semana (" . date('d/m/Y', strtotime($start_date)) . " al " . date('d/m/Y', strtotime($end_date)) . ")";
} elseif ($period_type === 'quincena') {
    $m_parts = explode('-', $selected_month);
    $year  = $m_parts[0] ?? date('Y');
    $month = $m_parts[1] ?? date('m');

    if ($selected_fortnight === 'Q1') {
        $start_date = "$year-$month-01";
        $end_date   = "$year-$month-15";
        $period_label = "1ª Quincena (01/ $month / $year al 15 / $month / $year)";
    } else {
        $start_date = "$year-$month-16";
        $last_day   = date('t', strtotime("$year-$month-01"));
        $end_date   = "$year-$month-$last_day";
        $period_label = "2ª Quincena (16 / $month / $year al $last_day / $month / $year)";
    }
} elseif ($period_type === 'personalizado') {
    $start_date = $date_from;
    $end_date   = $date_to;
    $period_label = "Período Personalizado (" . date('d/m/Y', strtotime($start_date)) . " al " . date('d/m/Y', strtotime($end_date)) . ")";
} else { // mes
    $start_date = date('Y-m-01', strtotime($selected_month . '-01'));
    $end_date   = date('Y-m-t', strtotime($selected_month . '-01'));
    $period_label = "Mes Completo: " . date('m/Y', strtotime($selected_month . '-01'));
}

// 1. Cargar Barberos Activos
$stmtB = $pdo->prepare("SELECT id, name, role, photo FROM users WHERE shop_id = ? AND is_active = 1 ORDER BY name ASC");
$stmtB->execute([$shop_id]);
$barbers_list = $stmtB->fetchAll();

// 2. Consulta Consolidada de Turnos y Ventas para el Período (usando comisiones editables por barbero)
$stmtApptsPeriod = $pdo->prepare("
    SELECT 
        a.*, 
        c.name as item_name, 
        c.type as item_type, 
        c.price as base_price, 
        c.commission_percent as default_commission_percent,
        COALESCE(bc.commission_percent, c.commission_percent) as commission_percent,
        u.name as barber_name,
        u.photo as barber_photo
    FROM appointments a
    JOIN catalog c ON a.item_id = c.id
    JOIN users u ON a.barber_id = u.id
    LEFT JOIN barber_commissions bc ON bc.barber_id = a.barber_id AND bc.catalog_id = a.item_id AND bc.shop_id = a.shop_id
    WHERE a.shop_id = ? 
      AND DATE(a.appointment_datetime) BETWEEN ? AND ?
      AND a.status != 'cancelled'
    ORDER BY a.appointment_datetime ASC
");
$stmtApptsPeriod->execute([$shop_id, $start_date, $end_date]);
$all_period_records = $stmtApptsPeriod->fetchAll();

// 3. Gastos del Período
$stmtExpensesPeriod = $pdo->prepare("
    SELECT COALESCE(SUM(amount), 0) as total 
    FROM expenses 
    WHERE shop_id = ? AND date BETWEEN ? AND ?
");
$stmtExpensesPeriod->execute([$shop_id, $start_date, $end_date]);
$total_gastos_periodo = (float)$stmtExpensesPeriod->fetch()['total'];

// 4. Pagos a Barberos Registrados en el Período
$stmtPayoutsPeriod = $pdo->prepare("
    SELECT barber_id, COALESCE(SUM(amount), 0) as total_paid
    FROM barber_payouts
    WHERE shop_id = ? AND date BETWEEN ? AND ? AND type = 'pago'
    GROUP BY barber_id
");
$stmtPayoutsPeriod->execute([$shop_id, $start_date, $end_date]);
$payouts_by_barber = $stmtPayoutsPeriod->fetchAll(PDO::FETCH_KEY_PAIR);

// 5. Cálculos Globales del Período
$total_efectivo_periodo = 0.00;
$total_transferencia_periodo = 0.00;
$total_comisiones_periodo = 0.00;
$total_productos_monto = 0.00;
$total_servicios_monto = 0.00;
$productos_unidades = 0;
$servicios_unidades = 0;

// Estructuras para desgloses
$barber_metrics = [];
foreach ($barbers_list as $b) {
    $barber_metrics[$b['id']] = [
        'id' => $b['id'],
        'name' => $b['name'],
        'photo' => $b['photo'],
        'appts_count' => 0,
        'appts_amount' => 0.00,
        'sales_count' => 0,
        'sales_amount' => 0.00,
        'total_generated' => 0.00,
        'total_commission' => 0.00,
        'total_paid' => (float)($payouts_by_barber[$b['id']] ?? 0.00)
    ];
}

$catalog_metrics = [];

foreach ($all_period_records as $rec) {
    $basePrice = (float)$rec['base_price'];
    $finalPrice = ($rec['payment_method'] === 'transferencia') ? ($basePrice * 1.20) : $basePrice;
    $commPercent = (float)$rec['commission_percent'];
    $commAmount = ($basePrice * $commPercent) / 100.0;
    $bId = (int)$rec['barber_id'];
    $catId = (int)$rec['item_id'];

    if ($rec['payment_method'] === 'transferencia') {
        $total_transferencia_periodo += $finalPrice;
    } else {
        $total_efectivo_periodo += $finalPrice;
    }
    $total_comisiones_periodo += $commAmount;

    // Desglose por Producto vs Servicio
    if ($rec['item_type'] === 'product') {
        $total_productos_monto += $finalPrice;
        $productos_unidades++;
    } else {
        $total_servicios_monto += $finalPrice;
        $servicios_unidades++;
    }

    // Acumular métricas del Barbero
    if (isset($barber_metrics[$bId])) {
        if ($rec['is_direct_sale']) {
            $barber_metrics[$bId]['sales_count']++;
            $barber_metrics[$bId]['sales_amount'] += $finalPrice;
        } else {
            $barber_metrics[$bId]['appts_count']++;
            $barber_metrics[$bId]['appts_amount'] += $finalPrice;
        }
        $barber_metrics[$bId]['total_generated'] += $finalPrice;
        $barber_metrics[$bId]['total_commission'] += $commAmount;
    }

    // Acumular métricas por Ítem de Catálogo (Ceras, Shampoo, Cremas, Cortes, etc.)
    if (!isset($catalog_metrics[$catId])) {
        $catalog_metrics[$catId] = [
            'name' => $rec['item_name'],
            'type' => $rec['item_type'],
            'unit_price' => $basePrice,
            'units_sold' => 0,
            'total_revenue' => 0.00,
            'total_commission' => 0.00
        ];
    }
    $catalog_metrics[$catId]['units_sold']++;
    $catalog_metrics[$catId]['total_revenue'] += $finalPrice;
    $catalog_metrics[$catId]['total_commission'] += $commAmount;
}

$total_ingresos_periodo = $total_efectivo_periodo + $total_transferencia_periodo;
$ganancia_neta_periodo = $total_ingresos_periodo - $total_comisiones_periodo - $total_gastos_periodo;
?>

<div class="flex justify-between items-end flex-wrap gap-3 mb-4">
  <div>
    <h2 class="text-accent mb-1" style="font-size:1.6rem; font-weight:800; margin:0;">
      📈 Panel de Análisis por Períodos
    </h2>
    <p style="color:var(--text-secondary); font-size:0.88rem; margin:0;">
      Consultá Ingresos, Venta de Productos y Comisiones por Día, Semana, Quincena o Mes.
    </p>
  </div>

  <div class="flex items-center gap-2 flex-wrap">
    <span class="status-badge status-badge-active" style="font-size:0.82rem; padding:0.35rem 0.85rem; border-radius:999px;">
      📍 <?php echo htmlspecialchars($period_label); ?>
    </span>
    <span style="color:var(--text-secondary); font-size:0.8rem; background: rgba(201,167,82,0.08); padding:0.35rem 0.75rem; border-radius:999px; border:1px solid rgba(201,167,82,0.25);">
      📊 <strong><?php echo count($all_period_records); ?></strong> registros (<?php echo $servicios_unidades; ?> Servicios + <?php echo $productos_unidades; ?> Productos)
    </span>
  </div>
</div>

<!-- FORMULARIO DE FILTRO DE PERÍODO -->
<div class="glass-panel mb-6" style="border-left: 4px solid var(--accent-primary); padding: 1.25rem 1.75rem;">
  <form method="GET" action="owner.php" class="flex flex-wrap items-end gap-4" style="margin:0;">
    <input type="hidden" name="tab" value="<?php echo htmlspecialchars($_GET['tab'] ?? 'periodos'); ?>">
    <?php if (isset($_GET['subtab'])): ?>
      <input type="hidden" name="subtab" value="<?php echo htmlspecialchars($_GET['subtab']); ?>">
    <?php endif; ?>

    <div class="input-group" style="margin:0; width:auto;">
      <label class="input-label" style="margin-bottom:0.4rem; font-weight:600;">Seleccionar Período</label>
      <select name="period_type" class="input-field" onchange="this.form.submit()" style="margin:0; font-weight:600; height:44px; min-width:240px; padding-left:1rem; padding-right:2.5rem; text-overflow:ellipsis;">
        <option value="dia" <?php echo $period_type === 'dia' ? 'selected' : ''; ?>>📅 Día Específico</option>
        <option value="semana" <?php echo $period_type === 'semana' ? 'selected' : ''; ?>>📆 Esta Semana (7 Días)</option>
        <option value="quincena" <?php echo $period_type === 'quincena' ? 'selected' : ''; ?>>📅 Quincena</option>
        <option value="mes" <?php echo $period_type === 'mes' ? 'selected' : ''; ?>>🗓 Mes Completo</option>
        <option value="personalizado" <?php echo $period_type === 'personalizado' ? 'selected' : ''; ?>>⚙️ Rango Personalizado</option>
      </select>
    </div>

    <?php if ($period_type === 'dia' || $period_type === 'semana'): ?>
      <div class="input-group" style="margin:0; width:auto;">
        <label class="input-label" style="margin-bottom:0.4rem; font-weight:600;">Fecha de Referencia</label>
        <input type="date" name="period_date" class="input-field" value="<?php echo $selected_date; ?>" onchange="this.form.submit()" style="margin:0; height:44px; min-width:190px; padding: 0.5rem 1rem;">
      </div>
    <?php elseif ($period_type === 'quincena'): ?>
      <div class="input-group" style="margin:0; width:auto;">
        <label class="input-label" style="margin-bottom:0.4rem; font-weight:600;">Mes</label>
        <input type="month" name="period_month" class="input-field" value="<?php echo $selected_month; ?>" onchange="this.form.submit()" style="margin:0; height:44px; min-width:190px; padding: 0.5rem 1rem;">
      </div>
      <div class="input-group" style="margin:0; width:auto;">
        <label class="input-label" style="margin-bottom:0.4rem; font-weight:600;">Quincena</label>
        <select name="period_fortnight" class="input-field" onchange="this.form.submit()" style="margin:0; height:44px; min-width:230px; padding-left:1rem; padding-right:2.5rem;">
          <option value="Q1" <?php echo $selected_fortnight === 'Q1' ? 'selected' : ''; ?>>1ª Quincena (Días 1 al 15)</option>
          <option value="Q2" <?php echo $selected_fortnight === 'Q2' ? 'selected' : ''; ?>>2ª Quincena (Días 16 al Fin)</option>
        </select>
      </div>
    <?php elseif ($period_type === 'mes'): ?>
      <div class="input-group" style="margin:0; width:auto;">
        <label class="input-label" style="margin-bottom:0.4rem; font-weight:600;">Mes Seleccionado</label>
        <input type="month" name="period_month" class="input-field" value="<?php echo $selected_month; ?>" onchange="this.form.submit()" style="margin:0; height:44px; min-width:190px; padding: 0.5rem 1rem;">
      </div>
    <?php elseif ($period_type === 'personalizado'): ?>
      <div class="input-group" style="margin:0; width:auto;">
        <label class="input-label" style="margin-bottom:0.4rem; font-weight:600;">Fecha Desde</label>
        <input type="date" name="date_from" class="input-field" value="<?php echo $start_date; ?>" style="margin:0; height:44px; min-width:170px; padding: 0.5rem 1rem;">
      </div>
      <div class="input-group" style="margin:0; width:auto;">
        <label class="input-label" style="margin-bottom:0.4rem; font-weight:600;">Fecha Hasta</label>
        <input type="date" name="date_to" class="input-field" value="<?php echo $end_date; ?>" style="margin:0; height:44px; min-width:170px; padding: 0.5rem 1rem;">
      </div>
    <?php endif; ?>

    <div class="input-group" style="margin:0; width:auto;">
      <label class="input-label" style="margin-bottom:0.4rem; visibility:hidden; user-select:none;">&nbsp;</label>
      <button type="submit" class="btn btn-primary" style="padding: 0 1.4rem; display: inline-flex; align-items: center; justify-content: center; height: 44px; font-weight: 700; white-space: nowrap; margin:0;">🔍 Consultar Período</button>
    </div>
  </form>
</div>

<!-- TARJETAS DE MÉTRICAS KPI DEL PERÍODO -->
<div class="kpi-grid mb-8">
  <div class="kpi-card kpi-success">
    <span class="kpi-label">Ingresos Brutos Totales</span>
    <span class="kpi-value">$<?php echo number_format($total_ingresos_periodo, 2); ?></span>
    <span class="kpi-sub">Efec: $<?php echo number_format($total_efectivo_periodo, 2); ?> | Transf: $<?php echo number_format($total_transferencia_periodo, 2); ?></span>
  </div>

  <div class="kpi-card kpi-gold kpi-featured">
    <span class="kpi-label">💈 Comisiones Barberos</span>
    <span class="kpi-value">$<?php echo number_format($total_comisiones_periodo, 2); ?></span>
    <span class="kpi-sub">Calculado según comisiones de cada barbero</span>
  </div>

  <div class="kpi-card kpi-blue">
    <span class="kpi-label">🛍 Venta Productos / Mostrador</span>
    <span class="kpi-value">$<?php echo number_format($total_productos_monto, 2); ?></span>
    <span class="kpi-sub"><?php echo $productos_unidades; ?> unidades vendidas (cremas, ceras, etc.)</span>
  </div>

  <div class="kpi-card kpi-violet">
    <span class="kpi-label">✂️ Recaudación Servicios</span>
    <span class="kpi-value">$<?php echo number_format($total_servicios_monto, 2); ?></span>
    <span class="kpi-sub"><?php echo $servicios_unidades; ?> servicios realizados</span>
  </div>

  <div class="kpi-card kpi-danger">
    <span class="kpi-label">Gastos del Período</span>
    <span class="kpi-value">-$<?php echo number_format($total_gastos_periodo, 2); ?></span>
    <span class="kpi-sub">Insumos, servicios y costos de la barbería</span>
  </div>

  <div class="kpi-card kpi-success kpi-featured">
    <span class="kpi-label">💎 Ganancia Neta Barbería</span>
    <span class="kpi-value">$<?php echo number_format($ganancia_neta_periodo, 2); ?></span>
    <span class="kpi-sub">Neto tras pagar comisiones y gastos</span>
  </div>
</div>

<!-- TABLA 1: DESGLOSE DE COMISIONES A PAGAR POR BARBERO EN EL PERÍODO -->
<div class="glass-panel mb-8">
  <div class="flex justify-between items-center mb-4 flex-wrap gap-2">
    <h3 class="text-accent">💈 Comisiones a Pagar por Barbero en el Período</h3>
    <a href="owner.php?tab=finanzas&subtab=barberos_pago" class="btn btn-outline btn-sm">
      💵 Registrar Pago a Barbero ▶
    </a>
  </div>

  <div class="table-responsive">
    <table>
      <thead>
        <tr>
          <th>Barbero</th>
          <th>Servicios Atendidos</th>
          <th>Productos Vendidos</th>
          <th>Total Recaudado por Barbero</th>
          <th>Comisión Total a Pagar</th>
          <th>Pagado Registrado</th>
          <th>Saldo Pendiente</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($barber_metrics as $bId => $bData): 
          $bPhoto = !empty($bData['photo']) ? $bData['photo'] : 'assets/img/service_corte_clasico_1785440488368.png';
          $saldo_pendiente = $bData['total_commission'] - $bData['total_paid'];
        ?>
          <tr>
            <td>
              <div class="flex items-center gap-2">
                <img src="<?php echo htmlspecialchars($bPhoto); ?>" style="width:32px; height:32px; border-radius:50%; object-fit:cover; border:2px solid var(--accent-primary);">
                <strong><?php echo htmlspecialchars($bData['name']); ?></strong>
              </div>
            </td>
            <td>
              <strong><?php echo $bData['appts_count']; ?></strong> servicios
              <br><small style="color:var(--text-secondary);">$<?php echo number_format($bData['appts_amount'], 2); ?></small>
            </td>
            <td>
              <strong><?php echo $bData['sales_count']; ?></strong> ventas
              <br><small style="color:var(--text-secondary);">$<?php echo number_format($bData['sales_amount'], 2); ?></small>
            </td>
            <td class="font-bold">$<?php echo number_format($bData['total_generated'], 2); ?></td>
            <td class="text-accent font-bold" style="font-size:1.05rem;">$<?php echo number_format($bData['total_commission'], 2); ?></td>
            <td class="text-success">$<?php echo number_format($bData['total_paid'], 2); ?></td>
            <td>
              <?php if ($saldo_pendiente > 0): ?>
                <span class="text-warning font-bold">$<?php echo number_format($saldo_pendiente, 2); ?></span>
              <?php else: ?>
                <span class="text-success font-bold">✓ Al día ($<?php echo number_format($saldo_pendiente, 2); ?>)</span>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- TABLA 2: REPORTE DETALLADO DE VENTAS POR PRODUCTO Y SERVICIO (CREMAS, CERAS, SHAMPOO, ETC.) -->
<div class="glass-panel mb-6">
  <h3 class="text-accent mb-3">🛍 Detalle de Ventas por Servicio y Producto (Ceras, Cremas, Shampoo, etc.)</h3>
  <p style="font-size:0.85rem; color:var(--text-secondary); margin-bottom:1rem;">
    Desglose de unidades vendidas y montos recaudados de cada ítem del catálogo en el período seleccionado.
  </p>

  <?php if (empty($catalog_metrics)): ?>
    <p style="color:var(--text-secondary);">No hay ventas registradas en el período seleccionado.</p>
  <?php else: ?>
    <div class="table-responsive">
      <table>
        <thead>
          <tr>
            <th>Ítem del Catálogo</th>
            <th>Categoría / Tipo</th>
            <th>Precio Unitario Base</th>
            <th>Unidades Vendidas</th>
            <th>Total Recaudado</th>
            <th>Comisiones Generadas</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($catalog_metrics as $catId => $cData): 
            $typeLabel = strtoupper($cData['type']);
            if ($cData['type'] === 'product') {
                $typeBadge = '<span class="status-badge" style="background:rgba(59,130,246,0.15); color:#3b82f6; border:1px solid #3b82f6;">🛍 Producto (Cera/Shampoo/Crema)</span>';
            } elseif ($cData['type'] === 'service') {
                $typeBadge = '<span class="status-badge" style="background:rgba(201,167,82,0.15); color:var(--accent-primary); border:1px solid var(--accent-primary);">✂️ Servicio</span>';
            } elseif ($cData['type'] === 'promo') {
                $typeBadge = '<span class="status-badge" style="background:rgba(16,185,129,0.15); color:var(--success); border:1px solid var(--success);">🎁 Promo Combo</span>';
            } else {
                $typeBadge = '<span class="status-badge" style="background:rgba(236,72,153,0.15); color:#ec4899; border:1px solid #ec4899;">🎨 Coloración</span>';
            }
          ?>
            <tr>
              <td><strong style="font-size:0.95rem;"><?php echo htmlspecialchars($cData['name']); ?></strong></td>
              <td><?php echo $typeBadge; ?></td>
              <td>$<?php echo number_format($cData['unit_price'], 2); ?></td>
              <td class="font-bold text-accent" style="font-size:1.1rem;"><?php echo $cData['units_sold']; ?> un.</td>
              <td class="text-warning font-bold">$<?php echo number_format($cData['total_revenue'], 2); ?></td>
              <td class="text-success font-bold">$<?php echo number_format($cData['total_commission'], 2); ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>

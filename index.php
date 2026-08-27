<?php
require_once __DIR__ . '/includes/auth.php';

// Cargar la Barbería (por defecto shop_id = 1)
$shop_id = 1;
$stmtShop = $pdo->prepare("SELECT * FROM shops WHERE id = ?");
$stmtShop->execute([$shop_id]);
$shop = $stmtShop->fetch();
$shop_name = $shop['name'] ?? 'Brotherhood Barbershop';

// Cargar Barberos / Personal Activos
$stmtBarbers = $pdo->prepare("SELECT id, name, email, photo FROM users WHERE shop_id = ? AND is_active = 1 ORDER BY name ASC");
$stmtBarbers->execute([$shop_id]);
$barbers = $stmtBarbers->fetchAll();

// Cargar Catálogo
$stmtCatalog = $pdo->prepare("SELECT * FROM catalog WHERE shop_id = ? AND type IN ('service', 'promo', 'color') ORDER BY type, name ASC");
$stmtCatalog->execute([$shop_id]);
$catalog_services = $stmtCatalog->fetchAll();

// Comprobante de reserva
$booking_success_data = null;
if (isset($_GET['booking_success']) && $_GET['booking_success'] == '1' && isset($_GET['id'])) {
    $appt_id = (int)$_GET['id'];
    $stmtReceipt = $pdo->prepare("
        SELECT a.*, u.name as barber_name, c.name as service_name, c.price as service_price 
        FROM appointments a
        JOIN users u ON a.barber_id = u.id
        JOIN catalog c ON a.item_id = c.id
        WHERE a.id = ?
    ");
    $stmtReceipt->execute([$appt_id]);
    $booking_success_data = $stmtReceipt->fetch();
}

$preset_barber_id = (int)($_GET['barber_id'] ?? ($barbers[0]['id'] ?? 0));
$preset_date      = trim($_GET['date'] ?? date('Y-m-d'));
$preset_service   = (int)($_GET['service_id'] ?? 0);

include __DIR__ . '/includes/header.php';
?>

<!-- HERO SECTION REPLICA DE BUENOS AIRES / BROTHERHOOD BARBERSHOP -->
<section class="hero-full animate-fade-in" id="hero">

  <!-- LOGO EMBLEMA PRINCIPAL (assets/img/logosinfondo.png) -->
  <div class="hero-brand-logo-wrapper mb-3">
    <img src="assets/img/logosinfondo.png" alt="Brotherhood Barbershop" class="hero-brand-logo-img">
  </div>
  
  <div class="hero-est">
    — Mas que un corte, una experiencia. —
  </div>

  <h1 class="hero-full-title">
    Brotherhood <em>Barbershop</em>
  </h1>

  <div class="flex justify-center gap-4 flex-wrap mt-2">
    <a href="#reserva" class="btn-hero-gold">
      RESERVAR UN TURNO ↗
    </a>
    <a href="#barberia" class="btn-hero-outline">
      CONOCER LA MANSION
    </a>
  </div>

  <div class="hero-quote-italic">
    Cada servicio incluye una bebida de cortesía — Agua, Jugo, Infusiones.
  </div>

  <!-- STRIP TICKER BAR INFERIOR ESTILO BROTHERHOOD BARBERSHOP EN MOVIMIENTO -->
  <div class="hero-bottom-ticker">
    <div class="ticker-track">
      <div class="ticker-content">
        <span>Mar a Vie 11:00–20:30</span><span class="dot">•</span>
        <span>Sáb 10:00–20:30</span><span class="dot">•</span>
        <span>Lun y Dom cerrado</span><span class="dot">•</span>
        <span>Tel. +54 11 3881-0158</span><span class="dot">•</span>
        <span>25 de Mayo 147, Avellaneda</span><span class="dot">•</span>
        <span>Desde 2015</span><span class="dot">•</span>
        <span>Corte clásico</span><span class="dot">•</span>
        <span>Barba</span><span class="dot">•</span>
        <span>Afeitado a navaja</span><span class="dot">•</span>
      </div>
      <div class="ticker-content" aria-hidden="true">
        <span>Mar a Vie 11:00–20:30</span><span class="dot">•</span>
        <span>Sáb 10:00–20:30</span><span class="dot">•</span>
        <span>Lun y Dom cerrado</span><span class="dot">•</span>
        <span>Tel. +54 11 3881-0158</span><span class="dot">•</span>
        <span>25 de Mayo 147, Avellaneda</span><span class="dot">•</span>
        <span>Desde 2015</span><span class="dot">•</span>
        <span>Corte clásico</span><span class="dot">•</span>
        <span>Barba</span><span class="dot">•</span>
        <span>Afeitado a navaja</span><span class="dot">•</span>
      </div>
    </div>
  </div>
</section>

<!-- MODAL DE CONFIRMACIÓN DE RESERVA -->
<?php if ($booking_success_data): ?>
  <div id="booking-modal" class="modal-overlay">
    <div class="modal-card animate-scale-up">
      <button type="button" class="modal-close-btn" onclick="closeBookingModal()">&times;</button>
      
      <div class="text-center mb-4">
        <div style="font-size: 3.5rem; line-height:1; margin-bottom:0.5rem;">🎉</div>
        <h2 style="color: var(--accent-primary); margin: 0; font-size: 1.6rem;">¡Turno Reservado con Éxito!</h2>
        <p style="color: var(--text-secondary); font-size: 0.9rem; margin-top: 4px;">Tu turno ha sido registrado en nuestro sistema.</p>
      </div>

      <div class="booking-receipt-box mb-4">
        <div class="flex justify-between items-center mb-3 pb-2" style="border-bottom: 1px dashed rgba(255,255,255,0.15);">
          <span style="font-size: 0.85rem; color: var(--text-secondary);">Código de Reserva</span>
          <strong class="font-mono text-accent" style="font-size: 1.1rem;">#TURN-<?php echo sprintf('%04d', $booking_success_data['id']); ?></strong>
        </div>

        <div class="flex flex-col gap-2" style="font-size: 0.95rem;">
          <div><strong style="color: var(--accent-primary);">👤 Cliente:</strong> <?php echo htmlspecialchars($booking_success_data['client_name']); ?> (<?php echo htmlspecialchars($booking_success_data['client_phone']); ?>)</div>
          <div><strong style="color: var(--accent-primary);">💈 Barbero:</strong> <?php echo htmlspecialchars($booking_success_data['barber_name']); ?></div>
          <div><strong style="color: var(--accent-primary);">✂️ Servicio:</strong> <?php echo htmlspecialchars($booking_success_data['service_name']); ?> ($<?php echo number_format($booking_success_data['service_price'], 2); ?>)</div>
          <div><strong style="color: var(--accent-primary);">📅 Fecha y Hora:</strong> <span class="text-warning font-bold"><?php echo date('d/m/Y', strtotime($booking_success_data['appointment_datetime'])); ?> - <?php echo date('H:i', strtotime($booking_success_data['appointment_datetime'])); ?> hs</span></div>
          <div><strong style="color: var(--accent-primary);">💳 Medio de Pago:</strong> <span style="text-transform: capitalize;"><?php echo htmlspecialchars($booking_success_data['payment_method']); ?></span></div>
        </div>
      </div>

      <div class="flex justify-center gap-3 flex-wrap">
        <?php
          $wa_msg = "Hola! Reservé un turno en " . $shop_name . ":%0A• Cliente: " . urlencode($booking_success_data['client_name']) . "%0A• Servicio: " . urlencode($booking_success_data['service_name']) . "%0A• Fecha: " . date('d/m/Y H:i', strtotime($booking_success_data['appointment_datetime'])) . " hs";
        ?>
        <a href="https://wa.me/541138810158?text=<?php echo $wa_msg; ?>" target="_blank" class="btn btn-success" style="padding: 0.65rem 1.25rem;">
          💬 Confirmar por WhatsApp
        </a>
        <button type="button" onclick="closeBookingModal()" class="btn btn-outline" style="padding: 0.65rem 1.25rem;">
          Cerrar
        </button>
      </div>
    </div>
  </div>

  <style>
    .modal-overlay {
      position: fixed; top: 0; left: 0; right: 0; bottom: 0;
      background: rgba(7, 9, 12, 0.9); backdrop-filter: blur(8px);
      z-index: 9999; display: flex; align-items: center; justify-content: center; padding: 1rem;
    }
    .modal-card {
      background: var(--bg-secondary); border: 2px solid var(--accent-primary);
      border-radius: 16px; max-width: 500px; width: 100%; padding: 2rem 1.75rem; position: relative;
    }
    .modal-close-btn { position: absolute; top: 12px; right: 16px; background: transparent; border: none; color: var(--text-secondary); font-size: 1.8rem; cursor: pointer; }
    .booking-receipt-box { background: rgba(7, 9, 12, 0.8); border: 1px dashed var(--accent-primary); border-radius: 12px; padding: 1.25rem; }
  </style>

  <script>
    function closeBookingModal() { document.getElementById('booking-modal').style.display = 'none'; }
  </script>
<?php endif; ?>

<!-- CONTENEDOR PRINCIPAL FLUIDO Y ANCHO -->
<div class="container" style="padding-top: 1rem; padding-bottom: 2rem;">

  <!-- SECCIÓN LA BARBERÍA / LA CASA -->
  <section class="section mb-6" id="barberia">
    <div class="glass-panel grid-cards" style="grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); gap: 2.5rem; align-items: center;">
      <div>
        <img src="assets/img/bh.jpg" alt="Fachada de la Barbería" style="width: 100%; border-radius: 12px; border: 1px solid var(--glass-border); box-shadow: 0 12px 35px rgba(0,0,0,0.6); object-fit: cover; max-height: 420px;">
      </div>
      <div class="barberia-text">
        <p class="eyebrow">— La Mansion BH</p>
        <h2 style="font-size: 2.8rem; line-height: 1.15; margin-bottom: 1.25rem;">Una barbería <em>hecha con sacrificio</em></h2>
        <p class="lead" style="font-size: 1.1rem; line-height: 1.7; color: var(--text-secondary);">
          Este espacio se creo cortando dia y noche, a pura maquina y tijera, sin que se escape un pelo, para que no solo sea una barberia mas, sino una Mansion de experiencias.
        </p>
      </div>
    </div>
  </section>

  <!-- SECCIÓN DE SERVICIOS -->
  <section class="section mb-6" id="servicios">
    <header class="section-head">
      <p class="eyebrow">— Menú de Oficios</p>
      <h2 style="font-size: 2.8rem;">Servicios & Experiencias</h2>
      <p style="color: var(--text-secondary); font-size: 1.05rem;">Cada servicio incluye una bebida de cortesía — Agua, Jugo o Infusiones.</p>
    </header>

    <div class="services-grid">
      <?php 
        $durations = [
          1 => '40 min',
          2 => '30 min',
          3 => '1 h 10 min',
          6 => '45 min',
          11 => '45 min',
          15 => '45 min',
        ];
        foreach ($catalog_services as $srv): 
          $dur = $durations[$srv['id']] ?? '45 min';
          $cash_price = (float)$srv['price'];
          // Transferencia / MP con +20% de recargo
          $transfer_price = round($cash_price * 1.20);
      ?>
        <div class="service-card">
          <div>
            <div class="flex justify-between items-center gap-2 mb-2 flex-wrap">
              <h3 class="service-title"><?php echo htmlspecialchars($srv['name']); ?></h3>
              <span class="service-dur-pill">⏱️ <?php echo $dur; ?></span>
            </div>
            <p class="service-desc-text">Atención artesanal personalizada con productos de primera línea y terminaciones a navaja.</p>
          </div>

          <div class="service-price-block">
            <div class="flex justify-between items-baseline mb-1">
              <span class="service-transfer-price">$<?php echo number_format($transfer_price, 0, ',', '.'); ?></span>
              <span class="service-transfer-label">Transferencia / MP (+20%)</span>
            </div>

            <div class="service-cash-badge">
              <span>💵 <strong>$<?php echo number_format($cash_price, 0, ',', '.'); ?></strong> abonando en efectivo</span>
              <span class="cash-off-tag">EFECTIVO</span>
            </div>

            <button type="button" onclick="selectServiceFromCard(<?php echo $srv['id']; ?>)" class="btn btn-outline w-full mt-3 btn-reserve-compact">
              RESERVAR SERVICIO ↗
            </button>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  </section>

  <!-- SECCIÓN EL EQUIPO -->
  <section class="section mb-6" id="equipo">
    <header class="section-head">
      <p class="eyebrow">— La familia BH</p>
      <h2 style="font-size: 2.8rem;">Las maquinas no solo son las que usamos, tambien somos nosotros.</h2>
      <p style="color: var(--text-secondary); font-size: 1.05rem;">Somos Barberos creciendo y capacitandonos constantemente para brindar el mejor servicio.</p>
    </header>

    <div class="team-grid">
      <?php foreach ($barbers as $b): ?>
        <?php 
          $barberImg = !empty($b['photo']) ? $b['photo'] : 'assets/img/service_corte_clasico_1785440488368.png'; 
          $formattedName = ucwords(strtolower(trim($b['name'])));
        ?>
        <div class="barber-card">
          <div>
            <div class="barber-avatar">
              <img src="<?php echo htmlspecialchars($barberImg); ?>" alt="<?php echo htmlspecialchars($formattedName); ?>">
            </div>
            <h3 class="barber-name"><?php echo htmlspecialchars($formattedName); ?></h3>
            <p class="barber-role">BARBERO PROFESIONAL</p>
          </div>

          <button type="button" onclick="selectBarberFromCard(<?php echo $b['id']; ?>)" class="btn btn-outline w-full mt-3 btn-reserve-barber">
            Reservar con <?php echo htmlspecialchars($formattedName); ?>
          </button>
        </div>
      <?php endforeach; ?>
    </div>
  </section>

  <!-- SECCIÓN DE RESERVA INTERACTIVA EN TIEMPO REAL (ORIGINAL INTACTA) -->
  <section class="section mb-6" id="reserva">
    <div class="glass-panel" style="border: 1px solid var(--accent-primary); padding: 2.5rem;">
      <header class="section-head mb-6">
        <p class="eyebrow">— Reservas Online</p>
        <h2 style="font-size: 2.6rem;">Agendá tu Cita Ritual</h2>
        <p style="color: var(--text-secondary); font-size: 1.05rem;">Seleccioná barbero, servicio, fecha y el horario disponible que desees.</p>
      </header>

      <form id="public-booking-form" action="actions/public_booking.php" method="POST" onsubmit="return validateBookingForm();">
        <?php echo csrf_field(); ?>
        <input type="hidden" name="shop_id" value="<?php echo $shop_id; ?>">
        <input type="hidden" id="selected_hour_input" name="hour" value="">

        <div class="grid-cards mb-6" style="grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 1.5rem;">
          
          <!-- PASO 1: BARBERO -->
          <div class="input-group">
            <label class="input-label" style="font-weight: 700; color: var(--accent-primary);">
              1. Elegí tu Barbero:
            </label>
            <select id="booking_barber_id" name="barber_id" class="input-field" required onchange="fetchAvailableSlots();">
              <?php foreach ($barbers as $b): ?>
                <option value="<?php echo $b['id']; ?>" <?php echo $b['id'] == $preset_barber_id ? 'selected' : ''; ?>>
                  💈 <?php echo htmlspecialchars($b['name']); ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>

          <!-- PASO 2: SERVICIO -->
          <div class="input-group">
            <label class="input-label" style="font-weight: 700; color: var(--accent-primary);">
              2. Elegí el Servicio:
            </label>
            <select id="booking_item_id" name="item_id" class="input-field" required>
              <option value="">-- Seleccionar Servicio --</option>
              <?php foreach ($catalog_services as $srv): ?>
                <option value="<?php echo $srv['id']; ?>" <?php echo $srv['id'] == $preset_service ? 'selected' : ''; ?>>
                  <?php echo htmlspecialchars($srv['name']); ?> — $<?php echo number_format((float)$srv['price'], 0, ',', '.'); ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>

          <!-- PASO 3: FECHA -->
          <div class="input-group">
            <label class="input-label" style="font-weight: 700; color: var(--accent-primary);">
              3. Seleccioná la Fecha:
            </label>
            <input type="date" id="booking_date" name="date" class="input-field" value="<?php echo $preset_date; ?>" min="<?php echo date('Y-m-d'); ?>" required onchange="fetchAvailableSlots();">
          </div>

        </div>

        <!-- PASO 4: MATRIZ DE HORARIOS -->
        <div class="mb-6" style="background: rgba(7, 9, 12, 0.7); padding: 1.5rem; border-radius: 12px; border: 1px solid var(--glass-border);">
          <div class="flex justify-between items-center mb-3 flex-wrap gap-2">
            <label class="input-label" style="font-weight: 700; color: var(--accent-primary); margin:0; font-size: 1.05rem;">
              4. Horarios Disponibles (Mar a Sáb 10:00 a 21:00 hs):
            </label>
            <span id="slots_loading_status" style="font-size: 0.85rem; color: var(--text-secondary);">
              Consultando disponibilidad...
            </span>
          </div>

          <div id="slots_container" class="grid-cards" style="grid-template-columns: repeat(auto-fill, minmax(120px, 1fr)); gap: 0.75rem;">
            <!-- Horarios dinámicos -->
          </div>
        </div>

        <!-- PASO 5: DATOS DEL CLIENTE -->
        <div style="background: rgba(13, 17, 23, 0.6); padding: 1.5rem; border-radius: 12px; border: 1px solid var(--glass-border); margin-bottom: 3rem !important;">
          <h3 class="text-accent mb-4" style="font-size: 1.15rem;">5. Tus Datos de Contacto</h3>

          <div class="grid-cards" style="grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 1.25rem;">
            <div class="input-group">
              <label class="input-label">Nombre Completo *</label>
              <input type="text" name="client_name" class="input-field" placeholder="Ej. Juan Pérez" required>
            </div>

            <div class="input-group">
              <label class="input-label">Teléfono / WhatsApp *</label>
              <input type="tel" name="client_phone" class="input-field" placeholder="Ej. 11 2345-6789" required>
            </div>

            <div class="input-group">
              <label class="input-label">Método de Pago Preferido *</label>
              <select name="payment_method" class="input-field" required>
                <option value="efectivo">Efectivo (En el local)</option>
                <option value="transferencia">Transferencia Bancaria / MP (+20% recargo)</option>
              </select>
            </div>
          </div>

          <div class="input-group mt-3" style="margin-bottom:0;">
            <label class="input-label">Notas o Preferencias (Opcional)</label>
            <input type="text" name="observation" class="input-field" placeholder="Ej. Degradado bajo con navaja...">
          </div>
        </div>

        <!-- BOTÓN FINAL -->
        <div class="text-center" style="clear: both; margin-top: 3rem !important; padding-top: 1rem;">
          <button type="submit" id="btn_submit_booking" class="btn btn-hero-gold" style="font-size: 1rem; padding: 1rem 3rem; width: 100%; max-width: 520px; letter-spacing: 0.15em; text-transform: uppercase; border-radius: 12px; margin: 0 auto; display: inline-flex;" disabled>
            🔒 Seleccioná un horario para reservar
          </button>
        </div>

      </form>
    </div>
  </section>

  <!-- VENTANA EMERGENTE AUTOMÁTICA DE RESERVA AL CARGAR LA PÁGINA (CON CRUZ 'X') -->
  <div id="reserva-modal" class="modal-overlay" onclick="handleBackdropClick(event)">
    <div class="modal-card animate-scale-up" onclick="event.stopPropagation()">
      
      <!-- BOTÓN DE CIERRE CRUZ 'X' PARA VER LA WEB -->
      <button type="button" class="modal-close-btn" onclick="closeReservaModal()" title="Cerrar para explorar la web">&times;</button>
      
      <header class="section-head mb-6" style="padding-right: 2.5rem; text-align: left;">
        <p class="eyebrow">— Reservas Online</p>
        <h2 style="font-size: 2.2rem; margin-bottom: 0.35rem;">Agendá tu Cita Ritual</h2>
        <p style="color: var(--text-secondary); font-size: 0.95rem;">Seleccioná barbero, servicio, fecha y el horario disponible que desees.</p>
      </header>

      <form id="modal-public-booking-form" action="actions/public_booking.php" method="POST" onsubmit="return validateModalBookingForm();">
        <?php echo csrf_field(); ?>
        <input type="hidden" name="shop_id" value="<?php echo $shop_id; ?>">
        <input type="hidden" id="modal_selected_hour_input" name="hour" value="">

        <div class="grid-cards mb-6" style="grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 1.25rem;">
          
          <!-- PASO 1: BARBERO -->
          <div class="input-group">
            <label class="input-label" style="font-weight: 700; color: var(--accent-primary);">
              1. Elegí tu Barbero:
            </label>
            <select id="modal_booking_barber_id" name="barber_id" class="input-field" required onchange="fetchModalAvailableSlots();">
              <?php foreach ($barbers as $b): ?>
                <option value="<?php echo $b['id']; ?>" <?php echo $b['id'] == $preset_barber_id ? 'selected' : ''; ?>>
                  💈 <?php echo htmlspecialchars($b['name']); ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>

          <!-- PASO 2: SERVICIO -->
          <div class="input-group">
            <label class="input-label" style="font-weight: 700; color: var(--accent-primary);">
              2. Elegí el Servicio:
            </label>
            <select id="modal_booking_item_id" name="item_id" class="input-field" required>
              <option value="">-- Seleccionar Servicio --</option>
              <?php foreach ($catalog_services as $srv): ?>
                <option value="<?php echo $srv['id']; ?>" <?php echo $srv['id'] == $preset_service ? 'selected' : ''; ?>>
                  <?php echo htmlspecialchars($srv['name']); ?> — $<?php echo number_format((float)$srv['price'], 0, ',', '.'); ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>

          <!-- PASO 3: FECHA -->
          <div class="input-group">
            <label class="input-label" style="font-weight: 700; color: var(--accent-primary);">
              3. Seleccioná la Fecha:
            </label>
            <input type="date" id="modal_booking_date" name="date" class="input-field" value="<?php echo $preset_date; ?>" min="<?php echo date('Y-m-d'); ?>" required onchange="fetchModalAvailableSlots();">
          </div>

        </div>

        <!-- PASO 4: MATRIZ DE HORARIOS -->
        <div class="mb-6" style="background: rgba(7, 9, 12, 0.7); padding: 1.25rem; border-radius: 12px; border: 1px solid var(--glass-border);">
          <div class="flex justify-between items-center mb-3 flex-wrap gap-2">
            <label class="input-label" style="font-weight: 700; color: var(--accent-primary); margin:0; font-size: 1rem;">
              4. Horarios Disponibles (Mar a Sáb 10:00 a 21:00 hs):
            </label>
            <span id="modal_slots_loading_status" style="font-size: 0.85rem; color: var(--text-secondary);">
              Consultando disponibilidad...
            </span>
          </div>

          <div id="modal_slots_container" class="grid-cards" style="grid-template-columns: repeat(auto-fill, minmax(110px, 1fr)); gap: 0.65rem;">
            <!-- Horarios dinámicos modal -->
          </div>
        </div>

        <!-- PASO 5: DATOS DEL CLIENTE -->
        <div style="background: rgba(13, 17, 23, 0.6); padding: 1.25rem; border-radius: 12px; border: 1px solid var(--glass-border); margin-bottom: 2rem !important;">
          <h3 class="text-accent mb-3" style="font-size: 1.1rem;">5. Tus Datos de Contacto</h3>

          <div class="grid-cards" style="grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 1rem;">
            <div class="input-group">
              <label class="input-label">Nombre Completo *</label>
              <input type="text" name="client_name" class="input-field" placeholder="Ej. Juan Pérez" required>
            </div>

            <div class="input-group">
              <label class="input-label">Teléfono / WhatsApp *</label>
              <input type="tel" name="client_phone" class="input-field" placeholder="Ej. 11 2345-6789" required>
            </div>

            <div class="input-group">
              <label class="input-label">Método de Pago Preferido *</label>
              <select name="payment_method" class="input-field" required>
                <option value="efectivo">Efectivo (En el local)</option>
                <option value="transferencia">Transferencia Bancaria / MP (+20% recargo)</option>
              </select>
            </div>
          </div>

          <div class="input-group mt-3" style="margin-bottom:0;">
            <label class="input-label">Notas o Preferencias (Opcional)</label>
            <input type="text" name="observation" class="input-field" placeholder="Ej. Degradado bajo con navaja...">
          </div>
        </div>

        <!-- BOTÓN FINAL -->
        <div class="text-center" style="clear: both; margin-top: 1.5rem !important;">
          <button type="submit" id="modal_btn_submit_booking" class="btn btn-hero-gold" style="font-size: 0.95rem; padding: 0.9rem 2rem; width: 100%; letter-spacing: 0.12em; text-transform: uppercase; border-radius: 12px; display: inline-flex; justify-content: center;" disabled>
            🔒 Seleccioná un horario para reservar
          </button>
        </div>

      </form>
    </div>
  </div>

  <!-- SECCIÓN UBICACIÓN & CONTACTO REPLICA LUXURY -->
  <section class="section mb-6" id="ubicacion">
    <div class="location-split-panel" id="contacto">
      
      <!-- COLUMNA IZQUIERDA: INFORMACIÓN & CONTACTO -->
      <div class="location-info-col">
        <p class="eyebrow">— Dónde Estamos</p>
        <h2 class="location-main-title">Pasá por La Mansion.</h2>
        <p class="location-lead-text">El primer corte siempre es la mejor presentación. Te esperamos en La Mansion BH en Avellaneda.</p>

        <div class="location-details-list">
          
          <!-- FILA DIRECCIÓN -->
          <div class="location-row">
            <span class="location-row-label">DIRECCIÓN</span>
            <span class="location-row-value">25 de Mayo 147, B1870 Avellaneda — Prov. de Buenos Aires</span>
          </div>

          <!-- FILA HORARIOS -->
          <div class="location-row">
            <span class="location-row-label">HORARIOS</span>
            <div class="location-row-value flex flex-col gap-1">
              <div>Martes a Sábado · <strong>10:00 — 21:00 hs</strong></div>
              <div class="text-danger" style="font-size: 0.85rem; font-weight: 600;">Lunes y Domingos · Cerrado</div>
            </div>
          </div>

          <!-- FILA TELÉFONO -->
          <div class="location-row">
            <span class="location-row-label">TELÉFONO</span>
            <span class="location-row-value">
              <a href="https://wa.me/541138810158" target="_blank" class="location-gold-link">+54 11 3881-0158</a>
            </span>
          </div>

          <!-- FILA REDES -->
          <div class="location-row">
            <span class="location-row-label">REDES</span>
            <span class="location-row-value">
            <a href="https://instagram.com/bhoodbarbershop_" target="_blank" class="location-gold-link">@bhoodbarbershop_</a>
            </span>
          </div>

        </div>

        <div class="mt-6">
          <a href="https://wa.me/541138810158" target="_blank" class="btn btn-success location-wa-btn">
            💬 Enviar Mensaje por WhatsApp
          </a>
        </div>
      </div>

      <!-- COLUMNA DERECHA: MAPA DE GOOGLE INTERACTIVO TALL -->
      <div class="location-map-col">
        <iframe src="https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d3281.391696229158!2d-58.3683838!3d-34.6649714!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x95a3334fa2515053%3A0x7d94f276df7d2a57!2s25%20de%20Mayo%20147%2C%20B1870%20Avellaneda%2C%20Provincia%20de%20Buenos%20Aires!5e0!3m2!1ses!2sar!4v1700000000000!5m2!1ses!2sar" width="100%" height="100%" style="border:0; filter: grayscale(90%) invert(92%) contrast(83%);" allowfullscreen="" loading="lazy"></iframe>
      </div>

    </div>
  </section>

</div>

<!-- SCRIPTS PARA DISPONIBILIDAD EN TIEMPO REAL -->
<script>
let selectedHour = null;

function fetchAvailableSlots() {
  const barberId = document.getElementById('booking_barber_id').value;
  const dateVal  = document.getElementById('booking_date').value;
  const container = document.getElementById('slots_container');
  const statusLbl = document.getElementById('slots_loading_status');
  const submitBtn = document.getElementById('btn_submit_booking');
  const hourInput = document.getElementById('selected_hour_input');

  selectedHour = null;
  hourInput.value = '';
  submitBtn.disabled = true;
  submitBtn.innerText = '🔒 Seleccioná un horario disponible';

  if (!barberId || !dateVal) {
    container.innerHTML = '<p style="color:var(--text-secondary); font-size:0.9rem;">Por favor seleccioná un barbero y una fecha.</p>';
    return;
  }

  statusLbl.innerText = 'Consultando disponibilidad en tiempo real...';
  container.innerHTML = '<p style="color:var(--text-secondary); font-size:0.9rem;">Cargando turnos...</p>';

  fetch(`actions/get_slots.php?shop_id=1&barber_id=${barberId}&date=${dateVal}`)
    .then(response => response.json())
    .then(data => {
      if (!data.success) {
        container.innerHTML = `<p style="color:var(--danger);">${data.message || 'Error al cargar horarios'}</p>`;
        statusLbl.innerText = '';
        return;
      }

      if (data.is_sunday) {
        container.innerHTML = `
          <div style="grid-column: 1 / -1; text-align: center; padding: 1rem; color: var(--warning);">
            🔒 <strong>El local permanece cerrado los domingos y lunes.</strong><br>Por favor elige una fecha de Martes a Sábado.
          </div>
        `;
        statusLbl.innerText = 'Local Cerrado';
        return;
      }

      statusLbl.innerText = 'Horarios actualizados';
      container.innerHTML = '';

      let availableCount = 0;
      data.slots.forEach(slot => {
        const btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'btn';
        btn.style.padding = '0.6rem 0.35rem';
        btn.style.fontSize = '0.85rem';
        btn.style.flexDirection = 'column';
        btn.style.gap = '2px';

        if (slot.is_available) {
          availableCount++;
          btn.classList.add('btn-outline');
          btn.style.borderColor = 'var(--success)';
          btn.style.color = 'var(--text-primary)';
          btn.innerHTML = `<strong>${slot.formatted_time}</strong><small style="color:var(--success); font-size:0.7rem;">Disponible</small>`;
          
          btn.onclick = () => {
            document.querySelectorAll('#slots_container .btn').forEach(b => {
              b.style.background = 'transparent';
              b.style.borderColor = 'var(--success)';
            });
            btn.style.background = 'var(--accent-primary)';
            btn.style.color = '#07090c';
            btn.style.borderColor = 'var(--accent-primary)';

            selectedHour = slot.hour;
            hourInput.value = slot.hour;
            submitBtn.disabled = false;
            submitBtn.innerText = `✂️ Confirmar Reserva para las ${slot.formatted_time} hs`;
          };
        } else {
          btn.classList.add('btn-outline');
          btn.disabled = true;
          btn.style.opacity = '0.4';
          btn.style.background = 'rgba(239, 68, 68, 0.08)';
          btn.style.borderColor = 'rgba(239, 68, 68, 0.25)';
          btn.style.color = 'var(--danger)';
          btn.style.cursor = 'not-allowed';
          
          const label = slot.is_past ? 'Pasado' : 'Reservado';
          btn.innerHTML = `<strong>${slot.formatted_time}</strong><small style="font-size:0.7rem;">${label}</small>`;
        }

        container.appendChild(btn);
      });

      if (availableCount === 0) {
        container.innerHTML = `<p style="grid-column:1/-1; text-align:center; color:var(--danger); padding:1rem;">No quedan turnos disponibles para esta fecha con este barbero.</p>`;
      }
    })
    .catch(err => {
      container.innerHTML = '<p style="color:var(--danger);">Error al consultar disponibilidad del servidor.</p>';
      statusLbl.innerText = '';
    });
}

function fetchModalAvailableSlots() {
  const barberSelect = document.getElementById('modal_booking_barber_id');
  const dateInput    = document.getElementById('modal_booking_date');
  const container    = document.getElementById('modal_slots_container');
  const statusLbl    = document.getElementById('modal_slots_loading_status');
  const submitBtn    = document.getElementById('modal_btn_submit_booking');
  const hourInput    = document.getElementById('modal_selected_hour_input');

  if (!barberSelect || !dateInput || !container) return;

  const barberId = barberSelect.value;
  const dateVal  = dateInput.value;

  hourInput.value = '';
  submitBtn.disabled = true;
  submitBtn.innerText = '🔒 Seleccioná un horario disponible';

  if (!barberId || !dateVal) {
    container.innerHTML = '<p style="color:var(--text-secondary); font-size:0.9rem;">Por favor seleccioná un barbero y una fecha.</p>';
    return;
  }

  statusLbl.innerText = 'Consultando disponibilidad...';
  container.innerHTML = '<p style="color:var(--text-secondary); font-size:0.9rem;">Cargando turnos...</p>';

  fetch(`actions/get_slots.php?shop_id=1&barber_id=${barberId}&date=${dateVal}`)
    .then(response => response.json())
    .then(data => {
      if (!data.success) {
        container.innerHTML = `<p style="color:var(--danger);">${data.message || 'Error al cargar horarios'}</p>`;
        statusLbl.innerText = '';
        return;
      }

      if (data.is_sunday) {
        container.innerHTML = `
          <div style="grid-column: 1 / -1; text-align: center; padding: 1rem; color: var(--warning);">
            🔒 <strong>El local permanece cerrado los domingos y lunes.</strong><br>Por favor elige una fecha de Martes a Sábado.
          </div>
        `;
        statusLbl.innerText = 'Local Cerrado';
        return;
      }

      statusLbl.innerText = 'Horarios actualizados';
      container.innerHTML = '';

      let availableCount = 0;
      data.slots.forEach(slot => {
        const btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'btn';
        btn.style.padding = '0.6rem 0.35rem';
        btn.style.fontSize = '0.85rem';
        btn.style.flexDirection = 'column';
        btn.style.gap = '2px';

        if (slot.is_available) {
          availableCount++;
          btn.classList.add('btn-outline');
          btn.style.borderColor = 'var(--success)';
          btn.style.color = 'var(--text-primary)';
          btn.innerHTML = `<strong>${slot.formatted_time}</strong><small style="color:var(--success); font-size:0.7rem;">Disponible</small>`;
          
          btn.onclick = () => {
            document.querySelectorAll('#modal_slots_container .btn').forEach(b => {
              b.style.background = 'transparent';
              b.style.borderColor = 'var(--success)';
            });
            btn.style.background = 'var(--accent-primary)';
            btn.style.color = '#07090c';
            btn.style.borderColor = 'var(--accent-primary)';

            hourInput.value = slot.hour;
            submitBtn.disabled = false;
            submitBtn.innerText = `✂️ Confirmar Reserva para las ${slot.formatted_time} hs`;
          };
        } else {
          btn.classList.add('btn-outline');
          btn.disabled = true;
          btn.style.opacity = '0.4';
          btn.style.background = 'rgba(239, 68, 68, 0.08)';
          btn.style.borderColor = 'rgba(239, 68, 68, 0.25)';
          btn.style.color = 'var(--danger)';
          btn.style.cursor = 'not-allowed';
          
          const label = slot.is_past ? 'Pasado' : 'Reservado';
          btn.innerHTML = `<strong>${slot.formatted_time}</strong><small style="font-size:0.7rem;">${label}</small>`;
        }

        container.appendChild(btn);
      });

      if (availableCount === 0) {
        container.innerHTML = `<p style="grid-column:1/-1; text-align:center; color:var(--danger); padding:1rem;">No quedan turnos disponibles para esta fecha con este barbero.</p>`;
      }
    })
    .catch(err => {
      container.innerHTML = '<p style="color:var(--danger);">Error al consultar disponibilidad del servidor.</p>';
      statusLbl.innerText = '';
    });
}

function openReservaModal() {
  const modal = document.getElementById('reserva-modal');
  if (modal) {
    modal.classList.add('active');
    document.body.style.overflow = 'hidden';
    fetchModalAvailableSlots();
  }
}

function closeReservaModal() {
  const modal = document.getElementById('reserva-modal');
  if (modal) {
    modal.classList.remove('active');
    document.body.style.overflow = '';
  }
}

function handleBackdropClick(event) {
  if (event.target && event.target.id === 'reserva-modal') {
    closeReservaModal();
  }
}

document.addEventListener('keydown', (e) => {
  if (e.key === 'Escape') {
    closeReservaModal();
  }
});

function selectServiceFromCard(serviceId) {
  const serviceSelect = document.getElementById('booking_item_id');
  if (serviceSelect) serviceSelect.value = serviceId;
  const modalServiceSelect = document.getElementById('modal_booking_item_id');
  if (modalServiceSelect) modalServiceSelect.value = serviceId;
  document.getElementById('reserva').scrollIntoView({ behavior: 'smooth' });
}

function selectBarberFromCard(barberId) {
  const barberSelect = document.getElementById('booking_barber_id');
  if (barberSelect) barberSelect.value = barberId;
  const modalBarberSelect = document.getElementById('modal_booking_barber_id');
  if (modalBarberSelect) modalBarberSelect.value = barberId;
  fetchAvailableSlots();
  fetchModalAvailableSlots();
  document.getElementById('reserva').scrollIntoView({ behavior: 'smooth' });
}

function validateBookingForm() {
  const hourVal = document.getElementById('selected_hour_input').value;
  if (!hourVal) {
    alert('Por favor seleccioná un horario disponible de la grilla.');
    return false;
  }
  return true;
}

function validateModalBookingForm() {
  const hourVal = document.getElementById('modal_selected_hour_input').value;
  if (!hourVal) {
    alert('Por favor seleccioná un horario disponible de la grilla.');
    return false;
  }
  return true;
}

// Cargar disponibilidad inicial y abrir la ventana emergente automáticamente cada vez que se cargue la página
document.addEventListener('DOMContentLoaded', () => {
  fetchAvailableSlots();
  fetchModalAvailableSlots();
  setInterval(fetchAvailableSlots, 60000);
  setInterval(fetchModalAvailableSlots, 60000);

  // Abrir emergente automáticamente al cargar la página
  openReservaModal();
});
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>

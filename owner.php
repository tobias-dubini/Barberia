<?php
require_once __DIR__ . '/includes/auth.php';
$user = require_shop_admin();

$shop_id = get_current_shop_id($user);
$tab     = $_GET['tab'] ?? 'finanzas';

// Cargar Barberos de la barbería
$stmtBarbers = $pdo->prepare("SELECT * FROM users WHERE shop_id = ? AND role IN ('barber', 'barbero', 'owner', 'admin_barberia') ORDER BY name ASC");
$stmtBarbers->execute([$shop_id]);
$all_barbers = $stmtBarbers->fetchAll();
$active_barbers = array_filter($all_barbers, function($b) { return $b['is_active']; });

// Cargar Catálogo
$stmtCatalog = $pdo->prepare("SELECT * FROM catalog WHERE shop_id = ? ORDER BY type, name ASC");
$stmtCatalog->execute([$shop_id]);
$catalog_items = $stmtCatalog->fetchAll();

include __DIR__ . '/includes/header.php';
?>

<!-- NAVEGACIÓN COMPACTA EN ANCHO COMPLETO ALINEADA A LA DERECHA SIN SUPERPOSICIONES -->
<div class="panel-top-nav-bar mb-6">
  <nav class="panel-tabs" aria-label="Secciones del panel">
    <a href="owner.php?tab=finanzas" class="tab-link <?php echo $tab === 'finanzas' ? 'active' : ''; ?>">
      📊 Finanzas
    </a>
    <a href="owner.php?tab=periodos" class="tab-link <?php echo $tab === 'periodos' ? 'active' : ''; ?>">
      📈 Análisis por Períodos
    </a>
    <a href="owner.php?tab=my_grid" class="tab-link <?php echo $tab === 'my_grid' ? 'active' : ''; ?>">
      💈 Grilla de Turnos
    </a>
    <a href="owner.php?tab=ventas" class="tab-link <?php echo $tab === 'ventas' ? 'active' : ''; ?>">
      🛍 Ventas Rápidas
    </a>
    <a href="owner.php?tab=cierre_caja" class="tab-link <?php echo $tab === 'cierre_caja' ? 'active' : ''; ?>">
      🔒 Cierre de Caja
    </a>
    <a href="owner.php?tab=barberos" class="tab-link <?php echo $tab === 'barberos' ? 'active' : ''; ?>">
      👥 Barberos (<?php echo count($all_barbers); ?>)
    </a>
    <a href="owner.php?tab=catalog" class="tab-link <?php echo $tab === 'catalog' ? 'active' : ''; ?>">
      ✂️ Catálogo (<?php echo count($catalog_items); ?>)
    </a>
  </nav>
</div>

<div class="glass-panel" style="margin-bottom: 2rem;">

  <!-- ==================== PESTAÑA 0: FINANZAS ==================== -->
  <?php if ($tab === 'finanzas'): ?>
    <?php include __DIR__ . '/includes/finance_module.php'; ?>

  <!-- ==================== PESTAÑA PERÍODOS: PANEL DE ANÁLISIS ==================== -->
  <?php elseif ($tab === 'periodos'): ?>
    <?php include __DIR__ . '/includes/period_report_module.php'; ?>

  <!-- ==================== PESTAÑA 1: GRILLA DE TURNOS ==================== -->
  <?php elseif ($tab === 'my_grid'): ?>
    <?php 
      $view_tab = 'grid';
      include __DIR__ . '/barber.php'; 
    ?>

  <!-- ==================== PESTAÑA 2: VENTAS RÁPIDAS (MOSTRADOR) ==================== -->
  <?php elseif ($tab === 'ventas'): ?>
    <?php 
      $view_tab = 'ventas';
      include __DIR__ . '/barber.php'; 
    ?>

  <!-- ==================== PESTAÑA 3: CIERRE Y ARQUEO DE CAJA ==================== -->
  <?php elseif ($tab === 'cierre_caja'): ?>
    <?php 
      $view_tab = 'cierre_caja';
      include __DIR__ . '/barber.php'; 
    ?>

  <!-- ==================== PESTAÑA 4: BARBEROS Y COMISIONES ==================== -->
  <?php elseif ($tab === 'barberos'): ?>
    <?php
      $edit_barber_id      = (int)($_GET['edit_barber'] ?? 0);
      $edit_commissions_id = (int)($_GET['edit_commissions'] ?? 0);

      $edit_barber_item = null;
      if ($edit_barber_id > 0) {
          foreach ($all_barbers as $ab) {
              if ($ab['id'] === $edit_barber_id) {
                  $edit_barber_item = $ab;
                  break;
              }
          }
      }

      $commissions_barber_item = null;
      if ($edit_commissions_id > 0) {
          foreach ($all_barbers as $ab) {
              if ($ab['id'] === $edit_commissions_id) {
                  $commissions_barber_item = $ab;
                  break;
              }
          }
      }
    ?>

    <h2 class="text-accent mb-6">Gestión de Barberos y Tarifas</h2>

    <div class="grid-cards" style="grid-template-columns: repeat(auto-fit, minmax(min(420px, 100%), 1fr)); gap: 2rem;">
      <div>
        <h3 class="mb-4">Barberos Registrados (<?php echo count($all_barbers); ?>)</h3>
        <?php if (count($all_barbers) === 0): ?>
          <p style="color:var(--text-secondary);">No hay barberos registrados.</p>
        <?php else: ?>
          <div class="flex flex-col gap-3">
            <?php foreach ($all_barbers as $b): ?>
              <?php $bPhoto = !empty($b['photo']) ? $b['photo'] : 'assets/img/service_corte_clasico_1785440488368.png'; ?>
              <div class="admin-list-item <?php echo $b['is_active'] ? '' : 'is-inactive'; ?>" style="padding:1rem; border-radius:12px; background:rgba(7,9,12,0.6); border:1px solid rgba(201,167,82,0.2);">
                <div class="admin-item-info">
                  <div class="admin-avatar">
                    <img src="<?php echo htmlspecialchars($bPhoto); ?>" alt="<?php echo htmlspecialchars($b['name']); ?>">
                  </div>
                  <div>
                    <div class="flex items-center gap-2 flex-wrap">
                      <strong style="font-size:1.05rem;"><?php echo htmlspecialchars($b['name']); ?></strong>
                      <span class="status-badge <?php echo $b['is_active'] ? 'status-badge-active' : 'status-badge-inactive'; ?>">
                        <?php echo $b['is_active'] ? '● Activo' : '○ Inactivo'; ?>
                      </span>
                    </div>
                    <small style="color:var(--text-secondary);">📧 <?php echo htmlspecialchars($b['email']); ?></small>
                  </div>
                </div>

                <div class="admin-item-actions">
                  <a href="owner.php?tab=barberos&edit_commissions=<?php echo $b['id']; ?>" class="btn btn-outline" style="border-color:var(--accent-primary); color:var(--accent-primary); padding:0.4rem 0.8rem;" title="Configurar Precios y Comisiones del Barbero">
                    ⚙️ Tarifas
                  </a>

                  <a href="owner.php?tab=barberos&edit_barber=<?php echo $b['id']; ?>" class="btn btn-outline" style="padding:0.4rem 0.8rem;" title="Editar Perfil">
                    ✏️ Editar
                  </a>

                  <form action="actions/delete_barber.php" method="POST" onsubmit="return confirm('¿Seguro que deseas eliminar a <?php echo htmlspecialchars($b['name']); ?>?')" style="margin:0;">
                    <?php echo csrf_field(); ?>
                    <input type="hidden" name="id" value="<?php echo $b['id']; ?>">
                    <button type="submit" class="btn btn-danger" style="padding:0.4rem 0.8rem;" title="Eliminar Barbero">
                      🗑 Eliminar
                    </button>
                  </form>
                </div>
              </div>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </div>

      <div>
        <?php if ($commissions_barber_item): ?>
          <!-- FORMULARIO DE EDICIÓN DE PRECIOS Y COMISIONES PERSONALIZADAS -->
          <?php
            $stmtBC = $pdo->prepare("SELECT catalog_id, commission_percent, price FROM barber_commissions WHERE shop_id = ? AND barber_id = ?");
            $stmtBC->execute([$shop_id, $commissions_barber_item['id']]);
            $custom_comm_rows = $stmtBC->fetchAll();
            $custom_comm_map = [];
            $custom_price_map = [];
            foreach ($custom_comm_rows as $row) {
                $custom_comm_map[$row['catalog_id']] = (float)$row['commission_percent'];
                $custom_price_map[$row['catalog_id']] = $row['price'] !== null ? (float)$row['price'] : null;
            }
          ?>

          <h3 class="mb-2 text-accent">⚙️ Precios y Comisiones: <?php echo htmlspecialchars($commissions_barber_item['name']); ?></h3>
          <p style="font-size:0.88rem; color:var(--text-secondary); margin-bottom:1.25rem;">
            Define la tarifa (precio) que cobra <strong><?php echo htmlspecialchars($commissions_barber_item['name']); ?></strong> por cada servicio/producto y su % de comisión.
          </p>

          <form action="actions/save_barber_commissions.php" method="POST" class="glass-panel flex flex-col gap-3" style="margin:0; padding:1.5rem;">
            <?php echo csrf_field(); ?>
            <input type="hidden" name="barber_id" value="<?php echo $commissions_barber_item['id']; ?>">
            <input type="hidden" name="redirect_url" value="owner.php?tab=barberos&edit_commissions=<?php echo $commissions_barber_item['id']; ?>">

            <div class="table-responsive" style="max-height: 420px; overflow-y: auto;">
              <table>
                <thead>
                  <tr>
                    <th>Ítem / Servicio</th>
                    <th>Precio Catálogo</th>
                    <th>Precio de este Barbero ($)</th>
                    <th>% Comisión</th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($catalog_items as $ci): 
                    $comm_val = isset($custom_comm_map[$ci['id']]) ? (float)$custom_comm_map[$ci['id']] : (float)$ci['commission_percent'];
                    $price_val = isset($custom_price_map[$ci['id']]) && $custom_price_map[$ci['id']] !== null ? (float)$custom_price_map[$ci['id']] : '';
                  ?>
                    <tr>
                      <td>
                        <strong><?php echo htmlspecialchars($ci['name']); ?></strong>
                        <br><small style="color:var(--text-secondary); text-transform:uppercase; font-size:0.7rem;"><?php echo $ci['type']; ?></small>
                      </td>
                      <td style="opacity:0.65;">$<?php echo number_format((float)$ci['price'], 2); ?></td>
                      <td>
                        <div class="flex items-center gap-1">
                          <span style="font-size:0.85rem; color:var(--text-secondary);">$</span>
                          <input type="number" step="100" min="0" name="prices[<?php echo $ci['id']; ?>]" value="<?php echo $price_val; ?>" placeholder="<?php echo (float)$ci['price']; ?>" class="input-field" style="width:110px; padding:0.35rem 0.5rem; font-weight:bold; color:var(--success);">
                        </div>
                      </td>
                      <td>
                        <div class="flex items-center gap-1">
                          <input type="number" step="0.5" min="0" max="100" name="commissions[<?php echo $ci['id']; ?>]" value="<?php echo $comm_val; ?>" class="input-field" style="width:85px; padding:0.35rem 0.5rem; text-align:right; font-weight:bold; color:var(--accent-primary);">
                          <span class="font-bold">%</span>
                        </div>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>

            <div class="flex gap-2 mt-3">
              <button type="submit" class="btn btn-primary flex-1">
                💾 Guardar Tarifas y Comisiones
              </button>
              <a href="owner.php?tab=barberos" class="btn btn-outline">Cancelar</a>
            </div>
          </form>

        <?php else: ?>
          <!-- FORMULARIO DE AGREGAR / EDITAR BARBERO -->
          <h3 class="mb-4"><?php echo $edit_barber_item ? '✏️ Editar Barbero' : '➕ Agregar Nuevo Barbero'; ?></h3>

          <form action="actions/<?php echo $edit_barber_item ? 'edit_barber.php' : 'add_barber.php'; ?>" method="POST" enctype="multipart/form-data" class="flex flex-col glass-panel" style="margin:0; padding:1.5rem;">
            <?php echo csrf_field(); ?>
            <?php if ($edit_barber_item): ?>
              <input type="hidden" name="id" value="<?php echo $edit_barber_item['id']; ?>">
            <?php endif; ?>

            <div class="input-group">
              <label class="input-label">Nombre Completo del Barbero *</label>
              <input type="text" name="name" class="input-field" placeholder="Ej. Franco Barber" required value="<?php echo htmlspecialchars($edit_barber_item['name'] ?? ''); ?>">
            </div>

            <div class="input-group">
              <label class="input-label">Correo / Contacto (Opcional)</label>
              <input type="text" name="email" class="input-field" placeholder="ej. franco@barberia.com" value="<?php echo htmlspecialchars($edit_barber_item['email'] ?? ''); ?>">
            </div>

            <div class="input-group">
              <label class="input-label">Foto del Barbero (Opcional)</label>
              <?php if (!empty($edit_barber_item['photo'])): ?>
                <div class="flex items-center gap-3 mb-2">
                  <img src="<?php echo htmlspecialchars($edit_barber_item['photo']); ?>" style="width: 50px; height: 50px; border-radius: 50%; object-fit: cover; border: 2px solid var(--accent-primary);">
                  <span style="font-size:0.8rem; color:var(--text-secondary);">Foto actual registrada</span>
                </div>
              <?php endif; ?>
              <input type="file" name="photo" accept="image/*" class="input-field" style="padding: 0.4rem;">
            </div>

            <div class="flex gap-2 mt-2">
              <button type="submit" class="btn btn-primary flex-1">
                <?php echo $edit_barber_item ? '💾 Guardar Cambios' : '➕ Agregar Barbero'; ?>
              </button>
              
              <?php if ($edit_barber_item): ?>
                <a href="owner.php?tab=barberos" class="btn btn-outline">Cancelar</a>
              <?php endif; ?>
            </div>
          </form>
        <?php endif; ?>
      </div>
    </div>

  <!-- ==================== PESTAÑA 5: CATÁLOGO DE SERVICIOS Y PRODUCTOS ==================== -->
  <?php elseif ($tab === 'catalog'): ?>
    <?php
      $edit_id = (int)($_GET['edit'] ?? 0);
      $edit_item = null;
      if ($edit_id > 0) {
          foreach ($catalog_items as $ci) {
              if ($ci['id'] === $edit_id) {
                  $edit_item = $ci;
                  break;
              }
          }
      }

      $selected_cat_filter = $_GET['cat_filter'] ?? 'todos';
      $filtered_items = array_filter($catalog_items, function($ci) use ($selected_cat_filter) {
          return $selected_cat_filter === 'todos' || $ci['type'] === $selected_cat_filter;
      });
    ?>

    <div class="flex justify-between items-center mb-6 flex-wrap gap-3">
      <div>
        <h2 class="text-accent" style="margin:0; font-size:1.6rem;">Catálogo de Servicios y Productos</h2>
        <p style="color:var(--text-secondary); font-size:0.88rem;">Gestiona los precios base y comisiones estándar de la barbería.</p>
      </div>

      <!-- PILLS DE FILTRO POR CATEGORÍA -->
      <div class="barber-pills-bar" style="margin-bottom:0;">
        <a href="owner.php?tab=catalog&cat_filter=todos" class="barber-pill <?php echo $selected_cat_filter === 'todos' ? 'active' : ''; ?>">
          ● Todos (<?php echo count($catalog_items); ?>)
        </a>
        <a href="owner.php?tab=catalog&cat_filter=service" class="barber-pill <?php echo $selected_cat_filter === 'service' ? 'active' : ''; ?>">
          ✂️ Servicios
        </a>
        <a href="owner.php?tab=catalog&cat_filter=product" class="barber-pill <?php echo $selected_cat_filter === 'product' ? 'active' : ''; ?>">
          🛍 Productos (Ceras/Shampoo)
        </a>
        <a href="owner.php?tab=catalog&cat_filter=promo" class="barber-pill <?php echo $selected_cat_filter === 'promo' ? 'active' : ''; ?>">
          🎁 Promos
        </a>
        <a href="owner.php?tab=catalog&cat_filter=color" class="barber-pill <?php echo $selected_cat_filter === 'color' ? 'active' : ''; ?>">
          🎨 Coloración
        </a>
      </div>
    </div>

    <div class="grid-cards" style="grid-template-columns: repeat(auto-fit, minmax(min(400px, 100%), 1fr)); gap: 2rem; align-items: start;">
      
      <!-- CONTENEDOR CON DESPLAZAMIENTO SCROLLABLE QUE EVITA LISTAS INFINITAS -->
      <div>
        <h3 class="mb-3 flex justify-between items-center">
          <span>Catálogo Actual</span>
          <small style="font-size:0.8rem; color:var(--text-secondary);">
            <?php echo count($filtered_items); ?> ítems en esta vista
          </small>
        </h3>

        <?php if (count($filtered_items) === 0): ?>
          <div class="glass-panel text-center" style="padding:2rem; margin:0;">
            <p style="color:var(--text-secondary);">No hay ítems registrados en esta categoría.</p>
          </div>
        <?php else: ?>
          <div class="glass-panel" style="max-height: 540px; overflow-y: auto; padding: 1.25rem; border: 1px solid var(--glass-border); margin:0;">
            <div class="flex flex-col gap-3">
              <?php foreach ($filtered_items as $item): ?>
                <div class="admin-list-item" style="padding:1rem; background:rgba(7,9,12,0.7); border-radius:10px; border:1px solid rgba(201,167,82,0.18);">
                  <div class="admin-item-info">
                    <div>
                      <div class="flex items-center gap-2 flex-wrap">
                        <strong style="font-size:1.02rem;"><?php echo htmlspecialchars($item['name']); ?></strong>
                        <span class="status-badge" style="background:rgba(201,167,82,0.15); color:var(--accent-primary); border:1px solid var(--accent-primary); font-size:0.7rem; text-transform:uppercase;">
                          <?php echo htmlspecialchars($item['type']); ?>
                        </span>
                      </div>
                      <p style="font-size:0.85rem; color:var(--text-secondary); margin-top:0.3rem;">
                        Precio Base: <strong class="text-success" style="font-size:0.95rem;">$<?php echo number_format((float)$item['price'], 2); ?></strong> | Comisión Base: <strong><?php echo (float)$item['commission_percent']; ?>%</strong>
                      </p>
                    </div>
                  </div>

                  <div class="admin-item-actions">
                    <a href="owner.php?tab=catalog&cat_filter=<?php echo $selected_cat_filter; ?>&edit=<?php echo $item['id']; ?>" class="btn btn-outline" style="padding:0.35rem 0.75rem; font-size:0.8rem;" title="Editar Ítem">✏️ Editar</a>

                    <form action="actions/delete_catalog.php" method="POST" onsubmit="return confirm('¿Eliminar este ítem del catálogo?')" style="margin:0;">
                      <?php echo csrf_field(); ?>
                      <input type="hidden" name="id" value="<?php echo $item['id']; ?>">
                      <button type="submit" class="btn btn-danger" style="padding:0.35rem 0.75rem; font-size:0.8rem;" title="Eliminar Ítem">🗑 Eliminar</button>
                    </form>
                  </div>
                </div>
              <?php endforeach; ?>
            </div>
          </div>
        <?php endif; ?>
      </div>

      <!-- FORMULARIO DE AÑADIR / EDITAR ÍTEM -->
      <div>
        <h3 class="mb-3"><?php echo $edit_item ? '✏️ Editar Ítem del Catálogo' : '➕ Añadir Nuevo Ítem al Catálogo'; ?></h3>

        <form action="actions/save_catalog.php" method="POST" class="glass-panel flex flex-col gap-3" style="padding:1.75rem; margin:0;">
          <?php echo csrf_field(); ?>
          <?php if ($edit_item): ?>
            <input type="hidden" name="id" value="<?php echo $edit_item['id']; ?>">
          <?php endif; ?>

          <div class="input-group">
            <label class="input-label">Tipo de Ítem *</label>
            <select name="type" class="input-field" required>
              <option value="service" <?php echo ($edit_item['type'] ?? '') === 'service' ? 'selected' : ''; ?>>✂️ Servicio (Corte/Barba/etc.)</option>
              <option value="product" <?php echo ($edit_item['type'] ?? '') === 'product' ? 'selected' : ''; ?>>🛍 Producto (Crema/Cera/Shampoo)</option>
              <option value="promo" <?php echo ($edit_item['type'] ?? '') === 'promo' ? 'selected' : ''; ?>>🎁 Promo / Combo</option>
              <option value="color" <?php echo ($edit_item['type'] ?? '') === 'color' ? 'selected' : ''; ?>>🎨 Servicio de Coloración</option>
            </select>
          </div>

          <div class="input-group">
            <label class="input-label">Nombre del Servicio o Producto *</label>
            <input type="text" name="name" class="input-field" placeholder="Ej. Pomada Modeladora o Corte Clásico" value="<?php echo htmlspecialchars($edit_item['name'] ?? ''); ?>" required>
          </div>

          <div class="input-group">
            <label class="input-label">Precio Base ($) *</label>
            <input type="number" step="0.01" name="price" class="input-field" placeholder="5000.00" value="<?php echo htmlspecialchars($edit_item['price'] ?? ''); ?>" required>
          </div>

          <div class="input-group">
            <label class="input-label">Comisión Base para el Barbero (%) *</label>
            <input type="number" step="0.01" name="commission_percent" class="input-field" placeholder="50" value="<?php echo htmlspecialchars($edit_item['commission_percent'] ?? ''); ?>" required>
          </div>

          <div class="flex gap-2 mt-2">
            <button type="submit" class="btn btn-primary flex-1">
              <?php echo $edit_item ? '💾 Guardar Cambios' : '➕ Añadir al Catálogo'; ?>
            </button>
            <?php if ($edit_item): ?>
              <a href="owner.php?tab=catalog" class="btn btn-outline">Cancelar</a>
            <?php endif; ?>
          </div>
        </form>
      </div>

    </div>
  <?php endif; ?>

</div>

<?php include __DIR__ . '/includes/footer.php'; ?>

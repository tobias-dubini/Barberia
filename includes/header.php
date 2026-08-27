<?php
if (!defined('HEADER_INCLUDED')) {
    define('HEADER_INCLUDED', true);
}
if (!headers_sent()) {
    header('Content-Type: text/html; charset=utf-8');
}
require_once __DIR__ . '/auth.php';
$user = get_logged_user();
$flash = get_flash_message();

$current_page  = basename($_SERVER['PHP_SELF']);
$is_panel_view = ($user && in_array($current_page, ['super_admin.php', 'owner.php', 'barber.php']));
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Brotherhood Barbershop — El último ritual</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,400;0,700;1,400;1,700&family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="css/style.css?v=<?php echo @filemtime(__DIR__ . '/../css/style.css') ?: '1.0'; ?>">
</head>
<body class="<?php echo $is_panel_view ? 'panel-view' : ''; ?>">

  <!-- PANTALLA DE CARGA / PRELOADER ELEGANT BROTHERHOOD BARBERSHOP -->
  <div id="site-preloader" class="site-preloader">
    <div class="preloader-inner">
      <div class="preloader-logo-wrapper">
        <img src="assets/img/logosinfondo.png" alt="Brotherhood Barbershop" class="preloader-logo-img">
      </div>
      <div class="preloader-text">
        BROTHERHOOD BARBERSHOP
      </div>
      <div class="preloader-line-loader"></div>
    </div>
  </div>

  <header class="nav" id="nav">
    <div class="container nav-inner flex justify-between items-center" style="min-height: 60px;">
      
      <!-- LOGO MARCA SLIM CON GLOW DORADO -->
      <a href="index.php#hero" class="nav-logo flex items-center gap-3" style="text-decoration:none;">
        <div class="nav-logo-badge">
          <img src="assets/img/bhlogo.png" alt="Brotherhood Barbershop" class="nav-logo-img">
        </div>
        <span class="nav-logo-text">
          Brotherhood <span style="color:var(--accent-primary); font-style:italic;">Barbershop</span>
        </span>
      </a>

      <!-- LINKS NAVEGACIÓN PRINCIPAL (PILL CONTAINER FLOTANTE) -->
      <?php if (!$is_panel_view): ?>
        <nav class="nav-links flex items-center">
          <a href="index.php#barberia">La Barbería</a>
          <a href="index.php#equipo">Equipo</a>
          <a href="index.php#servicios">Servicios</a>
          <a href="index.php#ubicacion">Ubicación & Contacto</a>
        </nav>
      <?php endif; ?>

      <!-- ACCIONES Y USUARIO LOGUEADO -->
      <div class="nav-actions-group flex items-center gap-3">
        <?php if (!$is_panel_view && !($user && (is_shop_admin($user) || is_super_admin($user)))): ?>
          <a href="index.php#reserva" class="nav-cta">
            <span style="font-size: 0.9rem;">✂️</span> Reservar
          </a>
        <?php endif; ?>

        <?php if ($user): ?>
          <?php
            $roleLabel = 'Barbero';
            if (is_super_admin($user)) {
                $roleLabel = '⚡ Super Admin';
            } elseif (is_shop_admin($user)) {
                $roleLabel = '👑 Admin Barbería';
            }
          ?>
          <div class="user-badge user-badge-pill">
            <span class="user-badge-name">👤 <?php echo htmlspecialchars($user['name']); ?></span>
            <span class="role-tag role-tag-gold">
              <?php echo $roleLabel; ?>
            </span>
          </div>

          <?php if ($is_panel_view): ?>
            <a href="index.php" class="nav-btn-secondary">
              🏠 Inicio
            </a>
          <?php else: ?>
            <?php
              $panelTarget = 'barber.php';
              if (is_super_admin($user)) {
                  $panelTarget = 'super_admin.php';
              } elseif (is_shop_admin($user)) {
                  $panelTarget = 'owner.php';
              }
            ?>
            <a href="<?php echo $panelTarget; ?>" class="nav-btn-secondary">
              📊 Mi Panel
            </a>
          <?php endif; ?>

          <a href="logout.php" class="btn btn-danger btn-sm" style="border-radius: 999px; padding: 0.4rem 0.85rem;">
            Salir
          </a>
        <?php else: ?>
          <a href="login.php" class="nav-btn-secondary">
            🔒 Acceso Admin
          </a>
        <?php endif; ?>
      </div>

    </div>
  </header>

  <main class="<?php echo $current_page === 'index.php' ? 'page-hero-wrapper' : 'container page-wrapper animate-fade-in'; ?>">

  <?php if ($flash): ?>
    <div class="container mt-4">
      <div class="alert alert-<?php echo $flash['type']; ?>" role="alert">
        <?php echo htmlspecialchars($flash['message']); ?>
      </div>
    </div>
  <?php endif; ?>

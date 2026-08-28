<?php
require_once __DIR__ . '/includes/auth.php';

$user = get_logged_user();
if ($user) {
    if (is_super_admin($user)) {
        header("Location: super_admin.php");
    } elseif (is_shop_admin($user)) {
        header("Location: owner.php");
    } else {
        header("Location: barber.php");
    }
    exit();
}

include __DIR__ . '/includes/header.php';
?>

<div class="login-full-hero-container animate-fade-in">
  <div class="login-grid-wrapper">

    <!-- COLUMNA IZQUIERDA: SHOWCASE DE LA MARCA & VALOR -->
    <div class="login-brand-panel">
      <div class="login-brand-logo-box">
        <img src="assets/img/logosinfondo.png" alt="Brotherhood Barbershop" class="login-brand-logo-img">
      </div>
      
      <h1 class="login-brand-title">
        BROTHERHOOD <em>BARBERSHOP</em>
      </h1>
      <p class="login-brand-tagline">El Último Ritual &bull; Sistema de Gestión Integral</p>

      <div class="login-features-list">
        <div class="login-feature-item">
          <span class="login-feature-icon">💈</span>
          <div>
            <strong>Gestión Operativa de Brotherhood Barbershop</strong>
            <p>Control de grilla de turnos, catálogo de servicios, barberos staff, ventas de caja y egresos.</p>
          </div>
        </div>

        <div class="login-feature-item">
          <span class="login-feature-icon">🔒</span>
          <div>
            <strong>Seguridad de Nivel Profesional</strong>
            <p>Autenticación con password hashing, protección CSRF, sesiones HTTP-Only y RBAC estricto.</p>
          </div>
        </div>
      </div>
    </div>

    <!-- COLUMNA DERECHA: FORMULARIO DE ACCESO EXCLUSIVO -->
    <div class="login-form-panel">
      
      <div class="auth-header text-center mb-3">
        <span class="auth-badge-icon" style="font-size: 2rem; display: inline-block; margin-bottom: 0.2rem;">👑</span>
        <h2 class="auth-title" style="font-size: 1.5rem; font-family: var(--font-heading); color: var(--accent-primary); margin-bottom: 0.2rem;">
          Acceso al Sistema
        </h2>
        <p class="auth-subtitle" style="font-size: 0.82rem; color: var(--text-secondary);">
          Ingresá tus credenciales de Super Admin, Administrador o Barbero
        </p>
      </div>

      <!-- FORMULARIO DE INICIO DE SESIÓN -->
      <form id="login-form" action="actions/login_user.php" method="POST" class="auth-form flex flex-col gap-3">
        <?php echo csrf_field(); ?>
        
        <div class="input-group">
          <label class="input-label" for="login-email" style="font-weight: 600;">Correo Electrónico *</label>
          <div class="input-wrapper">
            <span class="input-icon">✉️</span>
            <input type="email" id="login-email" name="email" class="input-field" placeholder="admin@barberia.com" required autocomplete="username">
          </div>
        </div>

        <div class="input-group">
          <label class="input-label" for="login-password" style="font-weight: 600;">Contraseña *</label>
          <div class="input-wrapper">
            <span class="input-icon">🔒</span>
            <input type="password" id="login-password" name="password" class="input-field" placeholder="••••••••" required autocomplete="current-password">
          </div>
        </div>

        <button type="submit" class="btn btn-primary btn-submit-main" style="padding: 0.75rem; font-size: 0.9rem; font-weight: 700; letter-spacing: 0.05em; margin-top: 0.35rem; width: 100%;">
          🔑 Iniciar Sesión en Brotherhood Barbershop
        </button>
      </form>

      <div class="mt-4 pt-3 text-center" style="border-top: 1px dashed rgba(255,255,255,0.12); font-size: 0.78rem; color: var(--text-secondary);">
        <span>🔒 Acceso exclusivo para personal autorizado.</span>
      </div>

    </div>

  </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>

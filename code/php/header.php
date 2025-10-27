<?php
// header.php - site header with a simple combo (select) menu.
// Behaviour:
// - muestra el menú y "Cerrar sesión" SOLO si $_SESSION['user'] existe.
// - si NO hay sesión iniciada, NO muestra el menú ni el botón "Entrar" (tal como solicitaste).
// - construye URLs públicas a partir de config (public_base) usando rutas web (no rutas del sistema).

require_once __DIR__ . '/lib_apio.php';
$cfg = apio_load_config();

// Asset URLs
$css_path  = $cfg['css_path']  ?? '/css/styles.css';
$logo_path = $cfg['logo_path'] ?? '/css/BeeViva_Logo_Colour.avif';

$css_url  = apio_public_from_cfg_path($css_path);
$logo_url = apio_public_from_cfg_path($logo_path);

// Page links (web paths)
$upload_url = apio_public_from_cfg_path('/code/php/upload_form.php');
$docs_url   = apio_public_from_cfg_path('/code/php/docs_list.php');
$login_url  = apio_public_from_cfg_path('/code/php/login.php');
$logout_url = apio_public_from_cfg_path('/code/php/logout.php');

// Start session if required
if (session_status() !== PHP_SESSION_ACTIVE) session_start();
?>
<link rel="stylesheet" href="<?php echo htmlspecialchars($css_url, ENT_QUOTES); ?>">

<style>
/* Minimal header styles and inline combo */
.site-header { background:#fff; border-bottom:1px solid #e6e6e6; padding:10px 14px; }
.container.header-inner { max-width:1000px; margin:0 auto; display:flex; align-items:center; justify-content:space-between; }
.brand { display:flex; align-items:center; gap:12px; }
.site-logo { height:48px; width:auto; display:block; }
.site-title { font-size:1.4rem; margin:0; color:#222; font-weight:700; font-family:"Georgia", serif; }
.header-right { display:flex; align-items:center; gap:12px; font-size:0.95rem; color:#444; }
.menu-select { padding:6px 8px; border-radius:6px; border:1px solid #d0d7e2; background:#fff; }
.btn.small { padding:4px 8px; font-size:0.9rem; background:#0b6cff; color:#fff; border-radius:6px; text-decoration:none; display:inline-block; }
.user-info { color:#333; margin-left:6px; }
</style>

<header class="site-header">
  <div class="container header-inner">
    <div class="brand">
      <img src="<?php echo htmlspecialchars($logo_url, ENT_QUOTES); ?>" alt="BeeVIVA" class="site-logo" />
      <h1 class="site-title">BeeVIVA</h1>
    </div>

    <div class="header-right">
      <?php if (!empty($_SESSION['user'])): ?>
        <!-- Usuario autenticado: mostrar menú y Cerrar sesión -->
        <label for="mainMenu" style="font-weight:700;color:#c00;margin-right:6px;">Menú</label>
        <select id="mainMenu" class="menu-select" onchange="if(this.value) window.location.href=this.value;">
          <option value="">— Seleccionar —</option>
          <option value="<?php echo htmlspecialchars($upload_url, ENT_QUOTES); ?>">Subir PDF (fase 1A)</option>
          <option value="<?php echo htmlspecialchars($docs_url, ENT_QUOTES); ?>">Lista de documentos procesados</option>
        </select>

        <div class="auth-area" style="display:flex;align-items:center;gap:10px;">
          <span class="user-info">Usuario: <?php echo htmlspecialchars($_SESSION['user']); ?></span>
          <!-- Aseguramos que aqui se use $logout_url -->
          <a class="btn small" href="<?php echo htmlspecialchars($logout_url, ENT_QUOTES); ?>">Cerrar sesión</a>
        </div>

      <?php else: ?>
        <!-- Usuario NO autenticado: NO mostrar menú ni botón "Entrar" (según tu instrucción) -->
        <!-- Espacio intencionalmente vacío -->
      <?php endif; ?>
    </div>
  </div>
</header>
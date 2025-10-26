<?php
// login.php - redirect on already-logged and on success
// Robust paths: compute project root and use config['public_base'] + relative panel path.

session_start();

// project root and config/users paths
$projectRoot = dirname(__DIR__, 2); // /home/udnpviva/public_html/ed_cfle
$cfgPath   = $projectRoot . '/config/config.json';
$usersFile = $projectRoot . '/config/users.json';

// load config
$cfg = [];
if (file_exists($cfgPath)) {
    $cfg = json_decode(file_get_contents($cfgPath), true) ?? [];
}

// public_base and panel relative path (no hardcoded /ed_cfle; subdomain maps to project root)
$pb = rtrim($cfg['public_base'] ?? '', '/');                // e.g. https://cfle.plazza.xyz
$panelRelative = $cfg['panel_path'] ?? '/code/php/index.php';
$panelUrl = $pb ? $pb . $panelRelative : $panelRelative;

// If already logged, redirect to panel
if (!empty($_SESSION['user'])) {
    header('Location: ' . $panelUrl, true, 302);
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!file_exists($usersFile)) {
        $error = 'Fichero de usuarios no encontrado.';
    } else {
        $data = json_decode(file_get_contents($usersFile), true) ?: [];
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';
        $found = false;
        foreach ($data as $u) {
            if (!empty($u['username']) && $u['username'] === $username) {
                $found = true;
                if (!empty($u['password_hash']) && password_verify($password, $u['password_hash'])) {
                    // authenticated
                    $_SESSION['user'] = $username;
                    if (function_exists('session_set_cookie_params')) {
                        session_set_cookie_params([
                            'httponly' => true,
                            'secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
                            'samesite' => 'Lax'
                        ]);
                    }
                    header('Location: ' . $panelUrl, true, 302);
                    exit;
                } else {
                    $error = 'Credenciales inválidas';
                }
                break;
            }
        }
        if (!$found && empty($error)) $error = 'Usuario no encontrado';
        if (!$error && $username === '') $error = 'Usuario requerido';
    }
}
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <title>Login - BeeVIVA</title>
  <!-- header.php will include CSS and logo built from config -->
</head>
<body>
  <?php require_once __DIR__ . '/header.php'; ?>
  <div class="container">
    <h2>Entrar</h2>
    <?php if (!empty($error)): ?><p style="color:red;"><?php echo htmlspecialchars($error); ?></p><?php endif; ?>
    <form method="post" action="">
      <label>Usuario: <input name="username" required value="<?php echo htmlspecialchars($_POST['username'] ?? '', ENT_QUOTES); ?>"></label><br><br>
      <label>Contraseña: <input name="password" type="password" required></label><br><br>
      <button class="btn">Entrar</button>
    </form>
  </div>
</body>
</html>
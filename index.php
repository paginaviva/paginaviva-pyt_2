<?php
// Landing principal (raíz de la app) - sin meta-refresh.
// Si no está logueado -> ir al login dentro de la app.
// Si está logueado -> ir al panel solicitado (absolute URL).

session_start();

// Cargar configuración para obtener URLs dinámicas
$projectRoot = dirname(__DIR__);
$cfgPath = $projectRoot . '/config/config.json';
$config = [];
if (file_exists($cfgPath)) {
    $config = json_decode(file_get_contents($cfgPath), true) ?? [];
}

$publicBase = rtrim($config['public_base'] ?? '', '/');
$loginPath = $publicBase . '/code/php/login.php';
$panelUrl = $publicBase . '/code/php/index.php';

if (!isset($_SESSION['user'])) {
    // Usuario no logueado -> lanzar login
    header('Location: ' . $loginPath, true, 302);
    exit;
} else {
    // Usuario ya logueado -> ir al panel
    header('Location: ' . $panelUrl, true, 302);
    exit;
}
?>
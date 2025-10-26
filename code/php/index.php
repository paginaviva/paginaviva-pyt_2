<?php
// index.php - minimal landing page: only the header is shown per request.
// Loads config based on project root but renders no page content beyond header.

session_start();
$projectRoot = dirname(__DIR__, 2);
$cfgPath = $projectRoot . '/config/config.json';
$config = [];
if (file_exists($cfgPath)) $config = json_decode(file_get_contents($cfgPath), true) ?? [];

require_once __DIR__ . '/header.php';
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <title>Ed CFLE - BeeVIVA</title>
  <!-- header.php already includes CSS and renders the header -->
</head>
<body>
  <!-- Intentionally empty: only header must remain on the page -->
</body>
</html>
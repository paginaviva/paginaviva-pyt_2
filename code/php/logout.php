<?php
session_start();
// Read config to build concrete URL for redirect
$configPath = __DIR__ . '/../../config/config.json';
$cfg = [];
if (file_exists($configPath)) $cfg = json_decode(file_get_contents($configPath), true);
$pb = rtrim($cfg['public_base'] ?? '', '/');

session_unset();
session_destroy();
header('Location: ' . $pb . '/ed_cfle/code/php/login.php');
exit;
<?php
// process_phase.php - scaffold endpoint that will orchestrate a phase run against APIO.
// Uses project config for paths.

session_start();
if (!isset($_SESSION['user'])) {
    $projectRoot = dirname(__DIR__, 2);
    $cfgPath = $projectRoot . '/config/config.json';
    $cfg = [];
    if (file_exists($cfgPath)) $cfg = json_decode(file_get_contents($cfgPath), true) ?? [];
    $pb = rtrim($cfg['public_base'] ?? '', '/');
    header('Location: ' . ($pb ? $pb . '/code/php/login.php' : '/code/php/login.php'));
    exit;
}

$projectRoot = dirname(__DIR__, 2);
$cfgPath = $projectRoot . '/config/config.json';
$config = [];
if (file_exists($cfgPath)) $config = json_decode(file_get_contents($cfgPath), true) ?? [];

require_once $projectRoot . '/config/prompts.php';

$fase = intval($_POST['fase'] ?? 0);
$template_id = $_POST['template_id'] ?? '';
$model = $_POST['model'] ?? $config['default_model'];
$temperature = floatval($_POST['temperature'] ?? $config['default_params']['temperature']);
$max_tokens = intval($_POST['max_tokens'] ?? $config['default_params']['max_tokens']);
$doc_basename = $_POST['doc_basename'] ?? '';

$logDir = rtrim($config['tmp_dir'] ?? ($projectRoot . '/tmp'), '/') . '/logs';
@mkdir($logDir, 0755, true);
$logFile = $logDir . '/opilog-' . date('Ymd') . '.log';

$entry = [
    'ts' => time(),
    'user' => $_SESSION['user'],
    'fase' => $fase,
    'template_id' => $template_id,
    'model' => $model,
    'temperature' => $temperature,
    'max_tokens' => $max_tokens,
    'doc_basename' => $doc_basename
];
file_put_contents($logFile, json_encode($entry, JSON_UNESCAPED_UNICODE) . PHP_EOL, FILE_APPEND);

// Placeholder response: create a result file in doc dir for review
if ($doc_basename) {
    $docDir = rtrim($config['docs_dir'], '/') . '/' . $doc_basename;
    @mkdir($docDir, 0755, true);
    $resultFile = $docDir . '/' . $doc_basename . ".phase{$fase}.result.txt";
    file_put_contents($resultFile, "Resultado simulado de fase {$fase}\nTemplate: {$template_id}\nGenerated at: " . date('c'));
    echo json_encode(['status'=>'ok','result_file'=>$resultFile]);
    exit;
}

echo json_encode(['status'=>'ok','note'=>'no doc_basename provided, logged only']);
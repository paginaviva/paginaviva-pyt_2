<?php
// process_1b.php - Backend MÍNIMO para Fase 1B
// Solo recibe parámetros y devuelve respuesta básica

session_start();
header('Content-Type: application/json; charset=utf-8');

// Verificar autenticación
if (empty($_SESSION['user'])) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'No autenticado']);
    exit;
}

// Solo POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Método no permitido']);
    exit;
}

// Obtener parámetros del formulario
$docBasename = trim($_POST['doc_basename'] ?? '');
$model = trim($_POST['model'] ?? '');
$temperature = floatval($_POST['temperature'] ?? 0);
$maxTokens = intval($_POST['max_tokens'] ?? 1500);
$topP = floatval($_POST['top_p'] ?? 1.0);

// Validar documento
if (!$docBasename) {
    echo json_encode(['ok' => false, 'error' => 'Nombre del documento requerido']);
    exit;
}

// RESPUESTA BÁSICA - Solo confirma que recibió los parámetros
$response = [
    'ok' => true,
    'message' => 'Parámetros recibidos correctamente',
    'doc_basename' => $docBasename,
    'parameters' => [
        'model' => $model,
        'temperature' => $temperature,
        'max_tokens' => $maxTokens,
        'top_p' => $topP
    ],
    'timestamp' => date('Y-m-d H:i:s'),
    'status' => 'ready_for_new_implementation'
];

echo json_encode($response, JSON_UNESCAPED_UNICODE);
?>
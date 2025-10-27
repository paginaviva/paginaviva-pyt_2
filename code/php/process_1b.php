<?php
// process_1b.php - Backend SIMPLIFICADO para Fase 1B
// Extrae texto de PDF y opcionalmente lo procesa con OpenAI

session_start();
require_once __DIR__ . '/lib_apio.php';
require_once __DIR__ . '/extract_pdf_text.php';

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

// Cargar configuración y prompts
$cfg = apio_load_config();
require_once __DIR__ . '/../../config/prompts.php';

// Obtener parámetros
$docBasename = trim($_POST['doc_basename'] ?? '');
$model = trim($_POST['model'] ?? '');
$temperature = floatval($_POST['temperature'] ?? 0.2);
$maxTokens = intval($_POST['max_tokens'] ?? 1500);
$topP = floatval($_POST['top_p'] ?? 1.0);

// Validar parámetros
if (!$docBasename) {
    echo json_encode(['ok' => false, 'error' => 'Nombre base del documento requerido']);
    exit;
}

if (!$model) {
    $model = $cfg['apio_defaults']['model'] ?? 'gpt-5-mini';
}

// Verificar que el documento existe
$docsDir = $cfg['docs_dir'] ?? '';
$docDir = $docsDir . DIRECTORY_SEPARATOR . $docBasename;
$pdfPath = $docDir . DIRECTORY_SEPARATOR . $docBasename . '.pdf';

if (!is_file($pdfPath)) {
    echo json_encode(apio_log_error($docBasename, '1B', 'Archivo PDF no encontrado: ' . $pdfPath));
    exit;
}

// Log inicio del proceso
apio_log_event($docBasename, '1B', 'INFO', 'Iniciando procesamiento Fase 1B', [
    'pdf_path' => $pdfPath,
    'model' => $model,
    'temperature' => $temperature,
    'max_tokens' => $maxTokens
]);

try {
    // ENFOQUE SIMPLIFICADO: Solo extraer texto del PDF
    apio_log_event($docBasename, '1B', 'INFO', 'Iniciando extracción simple de texto PDF');
    
    // Extraer texto usando función simple
    $extractResult = process_pdf_to_txt($pdfPath, $docBasename);
    
    if (!$extractResult['success']) {
        // Aún así devolvemos el resultado, aunque sea fallback
        apio_log_event($docBasename, '1B', 'WARNING', 'Extracción automática falló, usando método fallback', [
            'error' => $extractResult['error'],
            'method' => $extractResult['extraction_method']
        ]);
    } else {
        apio_log_event($docBasename, '1B', 'SUCCESS', 'Texto extraído correctamente', [
            'method' => $extractResult['extraction_method'],
            'text_length' => $extractResult['text_length']
        ]);
    }
    
    // Respuesta simple y clara
    $response = [
        'ok' => true,
        'message' => $extractResult['success'] ? 'Texto extraído exitosamente' : 'PDF procesado con método básico',
        'phase' => '1B',
        'doc_basename' => $docBasename,
        'txt_path' => $extractResult['txt_path'],
        'text_content' => file_get_contents($extractResult['txt_path']),
        'text_length' => $extractResult['text_length'],
        'extraction_method' => $extractResult['extraction_method'],
        'success_level' => $extractResult['success'] ? 'full' : 'partial',
        'timestamp' => date('Y-m-d H:i:s')
    ];
    
    // Debug info simplificado
    if (apio_should_show_debug('show_preflight')) {
        $response['debug_info'] = [
            'preflight' => [
                'pdf_exists' => true,
                'pdf_size' => filesize($pdfPath),
                'extraction_attempted' => true
            ],
            'extraction' => [
                'method' => $extractResult['extraction_method'],
                'success' => $extractResult['success'],
                'txt_created' => file_exists($extractResult['txt_path'])
            ]
        ];
    }
    
    echo json_encode($response, JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    $errorMsg = 'Excepción durante procesamiento: ' . $e->getMessage();
    echo json_encode(apio_log_error($docBasename, '1B', $errorMsg, [
        'file' => $e->getFile(),
        'line' => $e->getLine(),
        'trace' => $e->getTraceAsString()
    ]));
}
?>
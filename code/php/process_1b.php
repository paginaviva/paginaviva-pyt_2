<?php
// process_1b.php - Backend para Fase 1B: Procesamiento PDF con OpenAI API
// Recibe documentos de F1A, ejecuta p_extract_text, guarda .txt UTF-8 BOM

session_start();
require_once __DIR__ . '/lib_apio.php';

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
    // Obtener prompt p_extract_text
    if (!isset($PROMPTS[1]['p_extract_text'])) {
        echo json_encode(apio_log_error($docBasename, '1B', 'Prompt p_extract_text no encontrado'));
        exit;
    }
    
    $promptTemplate = $PROMPTS[1]['p_extract_text']['prompt'];
    
    // Para esta implementación inicial, usamos el prompt directamente
    // En futuras versiones se puede expandir para incluir placeholders PDF_BASE64, etc.
    $prompt = "Archivo PDF a procesar: " . $docBasename . ".pdf\n\n" . $promptTemplate;
    
    // Preparar parámetros API
    $apiParams = [
        'model' => $model,
        'temperature' => $temperature,
        'max_tokens' => $maxTokens,
        'top_p' => $topP
    ];
    
    // Realizar llamada a OpenAI
    $result = apio_call_openai($docBasename, $prompt, $apiParams);
    
    if (!$result['ok']) {
        echo json_encode($result);
        exit;
    }
    
    // Guardar texto extraído como .txt UTF-8 BOM
    $txtPath = $docDir . DIRECTORY_SEPARATOR . $docBasename . '.txt';
    $textContent = $result['content'];
    
    // Agregar BOM UTF-8
    $bomUtf8 = "\xEF\xBB\xBF";
    $textWithBom = $bomUtf8 . $textContent;
    
    if (file_put_contents($txtPath, $textWithBom, LOCK_EX) === false) {
        echo json_encode(apio_log_error($docBasename, '1B', 'Error al guardar archivo .txt'));
        exit;
    }
    
    // Log éxito
    $successData = [
        'txt_path' => $txtPath,
        'text_length' => strlen($textContent),
        'tokens_used' => $result['raw_response']['usage'] ?? null
    ];
    
    apio_log_success($docBasename, '1B', 'Fase 1B completada exitosamente', $successData);
    
    // Preparar respuesta
    $response = [
        'ok' => true,
        'message' => 'Texto extraído y guardado correctamente',
        'phase' => '1B',
        'doc_basename' => $docBasename,
        'txt_path' => $txtPath,
        'text_content' => $textContent,
        'text_length' => strlen($textContent),
        'api_usage' => $result['raw_response']['usage'] ?? null,
        'timestamp' => date('Y-m-d H:i:s')
    ];
    
    // Agregar información de debug si está habilitada
    if (apio_should_show_debug('show_headers') || apio_should_show_debug('show_body')) {
        $response['debug_info'] = [];
        
        if (apio_should_show_debug('show_preflight')) {
            $response['debug_info']['preflight'] = [
                'pdf_exists' => true,
                'pdf_size' => filesize($pdfPath),
                'prompt_ready' => true
            ];
        }
        
        if (apio_should_show_debug('show_model_info')) {
            $response['debug_info']['model_info'] = [
                'model' => $model,
                'temperature' => $temperature,
                'max_tokens' => $maxTokens,
                'top_p' => $topP
            ];
        }
        
        if (apio_should_show_debug('show_prompt')) {
            $response['debug_info']['prompt'] = $prompt;
        }
        
        if (apio_should_show_debug('show_headers')) {
            $response['debug_info']['request_headers'] = $result['debug_info']['request_headers'] ?? [];
        }
        
        if (apio_should_show_debug('show_body')) {
            $response['debug_info']['request_payload'] = $result['debug_info']['request_payload'] ?? [];
            $response['debug_info']['response_body'] = $result['raw_response'] ?? [];
        }
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
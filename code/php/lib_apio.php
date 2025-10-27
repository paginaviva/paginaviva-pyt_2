<?php
// lib_apio.php - helper utilities incl. carga centralizada de config.json
// Resuelve project_root y normaliza rutas absolutas/relativas.

// Devuelve la ruta al directorio 'code' (este archivo est� en code/php/)
function apio_get_code_dir() {
    return dirname(__DIR__); // .../code
}

// Devuelve el proyecto root (prefer config.project_root, si no existe, padre de 'code')
function apio_get_project_root() {
    static $root = null;
    if ($root !== null) return $root;

    // Intentar cargar config.json si est� disponible en ../config/
    $cfgPath = apio_get_code_dir() . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'config.json';
    $cfgPath = realpath($cfgPath) ?: $cfgPath;

    if (is_file($cfgPath)) {
        $raw = file_get_contents($cfgPath);
        $parsed = json_decode($raw, true);
        if (is_array($parsed) && !empty($parsed['project_root'])) {
            $root = rtrim($parsed['project_root'], "/\\");
            return $root;
        }
    }

    // Fallback: project root = parent of code dir
    $root = dirname(apio_get_code_dir());
    return $root;
}

// Resuelve ruta: si $path es absoluta (empieza por / o con letra:), devuelve tal cual,
// si es relativa, la interpreta respecto a project_root.
function apio_resolve_path($path) {
    if (!$path) return $path;
    // Windows absolute drive letter or Unix absolute
    if (DIRECTORY_SEPARATOR === '\\') {
        // Windows
        if (preg_match('/^[A-Za-z]:[\\\\\\/]/', $path)) return $path;
    } else {
        if (strpos($path, '/') === 0) return $path;
    }
    // Relativo => resolver respecto a project_root
    $root = apio_get_project_root();
    return rtrim($root, "/\\") . DIRECTORY_SEPARATOR . ltrim($path, "/\\");
}

function apio_get_config_path() {
    // Default location: ../config/config.json from code dir
    $p = apio_get_code_dir() . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'config.json';
    return realpath($p) ?: $p;
}

function apio_load_config() {
    static $cfg = null;
    if ($cfg !== null) return $cfg;

    $cfgPath = apio_get_config_path();
    if (!is_file($cfgPath)) {
        // defaults safe
        $defaultRoot = apio_get_project_root();
        $cfg = [
            'project_root' => $defaultRoot,
            'tmp_dir' => $defaultRoot . DIRECTORY_SEPARATOR . 'tmp',
            'docs_dir' => $defaultRoot . DIRECTORY_SEPARATOR . 'docs',
            'upload_max_filesize' => 10 * 1024 * 1024,
            'max_document_size' => 20 * 1024 * 1024,
            'public_base' => ''
        ];
        return $cfg;
    }

    $raw = @file_get_contents($cfgPath);
    $parsed = @json_decode($raw, true);
    if (!is_array($parsed)) $parsed = [];

    // project_root explicit or derive
    $projectRoot = !empty($parsed['project_root']) ? rtrim($parsed['project_root'], "/\\") : apio_get_project_root();

    // tmp_dir/docs_dir: if absolute use them, if relative resolve against project_root
    $tmp = $parsed['tmp_dir'] ?? 'tmp';
    $docs = $parsed['docs_dir'] ?? 'docs';

    // Resolve
    $tmpResolved = (strpos($tmp, DIRECTORY_SEPARATOR) === 0 || preg_match('/^[A-Za-z]:[\\\\\\/]/', $tmp)) ? $tmp : $projectRoot . DIRECTORY_SEPARATOR . ltrim($tmp, "/\\");
    $docsResolved = (strpos($docs, DIRECTORY_SEPARATOR) === 0 || preg_match('/^[A-Za-z]:[\\\\\\/]/', $docs)) ? $docs : $projectRoot . DIRECTORY_SEPARATOR . ltrim($docs, "/\\");

    // normalize
    $parsed['project_root'] = $projectRoot;
    $parsed['tmp_dir'] = rtrim($tmpResolved, "/\\");
    $parsed['docs_dir'] = rtrim($docsResolved, "/\\");
    if (empty($parsed['upload_max_filesize'])) $parsed['upload_max_filesize'] = 10 * 1024 * 1024;
    if (empty($parsed['max_document_size'])) $parsed['max_document_size'] = 20 * 1024 * 1024;
    if (!isset($parsed['public_base'])) $parsed['public_base'] = '';

    $cfg = $parsed;
    return $cfg;
}

// Helper: sanitize a basename (sin extensi�n)
function apio_safe_basename($name) {
    $name = preg_replace('/\.[^.]+$/', '', $name); // remove extension
    $name = preg_replace('/[^A-Za-z0-9._-]/', '_', $name);
    $name = trim($name, "._-");
    if ($name === '') $name = 'doc';
    return $name;
}

// === FUNCIONES DE LOGGING PARA FASE 1B ===

/**
 * Crear o actualizar archivo .log para un documento específico
 * @param string $docBasename - Nombre base del documento (sin extensión)
 * @param string $phase - Fase actual (1A, 1B, 2A, etc.)
 * @param string $event - Tipo de evento (INFO, ERROR, SUCCESS, DEBUG)
 * @param string $message - Mensaje a registrar
 * @param array $data - Datos adicionales (opcional)
 */
function apio_log_event($docBasename, $phase, $event, $message, $data = null) {
    $cfg = apio_load_config();
    $docsDir = $cfg['docs_dir'] ?? '';
    
    if (!$docsDir || !$docBasename) return false;
    
    $logPath = $docsDir . DIRECTORY_SEPARATOR . $docBasename . DIRECTORY_SEPARATOR . $docBasename . '.log';
    
    // Crear directorio si no existe
    $logDir = dirname($logPath);
    if (!is_dir($logDir)) {
        mkdir($logDir, 0755, true);
    }
    
    $timestamp = date('Y-m-d H:i:s');
    $logEntry = "[$timestamp] [$phase] [$event] $message";
    
    if ($data) {
        $logEntry .= " | Data: " . json_encode($data, JSON_UNESCAPED_UNICODE);
    }
    
    $logEntry .= "\n";
    
    return file_put_contents($logPath, $logEntry, FILE_APPEND | LOCK_EX) !== false;
}

/**
 * Registrar error en log y retornar estructura de error estándar
 */
function apio_log_error($docBasename, $phase, $error, $details = null) {
    apio_log_event($docBasename, $phase, 'ERROR', $error, $details);
    
    return [
        'ok' => false,
        'error' => $error,
        'phase' => $phase,
        'timestamp' => date('Y-m-d H:i:s'),
        'details' => $details
    ];
}

/**
 * Registrar éxito en log y retornar estructura de éxito estándar
 */
function apio_log_success($docBasename, $phase, $message, $data = null) {
    apio_log_event($docBasename, $phase, 'SUCCESS', $message, $data);
    
    return [
        'ok' => true,
        'message' => $message,
        'phase' => $phase,
        'timestamp' => date('Y-m-d H:i:s'),
        'data' => $data
    ];
}

/**
 * Obtener parámetros APIO desde configuración con fallbacks
 */
function apio_get_api_params($overrides = []) {
    $cfg = apio_load_config();
    $defaults = $cfg['apio_defaults'] ?? [];
    
    return array_merge([
        'model' => 'gpt-5-mini',
        'temperature' => 0.2,
        'max_tokens' => 1500,
        'top_p' => 1.0
    ], $defaults, $overrides);
}

/**
 * Verificar si se debe mostrar información de debug
 */
function apio_should_show_debug($debugType) {
    $cfg = apio_load_config();
    $debugDisplay = $cfg['debug_display'] ?? [];
    
    return $debugDisplay[$debugType] ?? false;
}

/**
 * Llamada a OpenAI API con logging completo
 */
function apio_call_openai($docBasename, $prompt, $params = []) {
    $cfg = apio_load_config();
    $apiKey = $cfg['apio_key'] ?? '';
    $apiUrl = $cfg['apio_url'] ?? 'https://api.openai.com/v1/chat/completions';
    
    if (!$apiKey) {
        return apio_log_error($docBasename, '1B', 'API key no configurada');
    }
    
    // Combinar parámetros
    $apiParams = apio_get_api_params($params);
    
    // Construir payload
    $payload = [
        'model' => $apiParams['model'],
        'messages' => [
            ['role' => 'user', 'content' => $prompt]
        ],
        'temperature' => floatval($apiParams['temperature']),
        'max_tokens' => intval($apiParams['max_tokens']),
        'top_p' => floatval($apiParams['top_p'])
    ];
    
    // Headers
    $headers = [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $apiKey,
        'User-Agent: Ed CFLE PDF Processor/1.0'
    ];
    
    // Log pre-flight
    apio_log_event($docBasename, '1B', 'INFO', 'Iniciando llamada OpenAI API', [
        'model' => $apiParams['model'],
        'url' => $apiUrl,
        'prompt_length' => strlen($prompt)
    ]);
    
    // Realizar llamada
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $apiUrl,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($payload),
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 300,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_FOLLOWLOCATION => true
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);
    
    // Manejar errores de cURL
    if ($curlError) {
        return apio_log_error($docBasename, '1B', 'Error cURL: ' . $curlError);
    }
    
    // Manejar errores HTTP
    if ($httpCode !== 200) {
        return apio_log_error($docBasename, '1B', 'Error HTTP: ' . $httpCode, [
            'response' => $response
        ]);
    }
    
    // Decodificar respuesta
    $result = json_decode($response, true);
    if (!$result) {
        return apio_log_error($docBasename, '1B', 'Respuesta JSON inválida', [
            'response' => substr($response, 0, 500)
        ]);
    }
    
    // Extraer contenido
    $content = $result['choices'][0]['content'] ?? '';
    if (!$content) {
        return apio_log_error($docBasename, '1B', 'No se pudo extraer contenido', $result);
    }
    
    // Log éxito
    apio_log_success($docBasename, '1B', 'Texto extraído correctamente', [
        'characters' => strlen($content),
        'tokens_used' => $result['usage'] ?? null
    ]);
    
    return [
        'ok' => true,
        'content' => $content,
        'raw_response' => $result,
        'debug_info' => [
            'request_headers' => $headers,
            'request_payload' => $payload,
            'http_code' => $httpCode
        ]
    ];
}
?>
<?php
/**
 * phase_3b_proxy.php
 * Proxy para Fase 3B: Optimización y redacción final SEO
 * 
 * Propósito: Optimizar campos textuales del JSON-FINAL y crear el campo
 * descripcion_larga_producto usando la TRÍADA (FILE_ID + JSON-FINAL + JSON-SEO)
 */

require_once __DIR__ . '/lib_apio.php';

header('Content-Type: application/json; charset=utf-8');

class Phase3BProxy
{
    private array $timeline = [];
    private array $debugHttp = [];
    private string $docBasename;
    private array $config;
    private array $input;
    private string $fileId;
    private array $jsonFinal;
    private array $jsonSEO;
    private ?string $assistantId = null;
    private string $model = 'gpt-4o';
    
    public function __construct(string $docBasename, array $input = [])
    {
        $this->docBasename = $docBasename;
        $this->input = $input;
        $this->config = apio_load_config();
        $this->model = $input['model'] ?? 'gpt-4o';
        $this->mark('init');
        
        // Cargar prompts
        $this->loadPrompts();
    }
    
    private function mark(string $stage): void
    {
        $this->timeline[] = [
            'ts' => (int) floor(microtime(true) * 1000),
            'stage' => $stage
        ];
    }
    
    private function loadPrompts(): void
    {
        global $PROMPTS;
        
        $configDir = $this->config['config_dir'] ?? (__DIR__ . '/../../config');
        $promptsFile = $configDir . '/prompts.php';
        
        if (!file_exists($promptsFile)) {
            $this->fail(500, 'Archivo prompts.php no encontrado en: ' . $configDir);
        }
        
        require_once $promptsFile;
    }
    
    public function execute(array $input): array
    {
        // 1. PRE-EJECUCIÓN: Validar entrada y cargar TRÍADA
        $this->mark('pre_execution.start');
        $this->validateInput();
        $this->loadTriad();
        $this->mark('pre_execution.done');
        
        // 2. EJECUCIÓN: Crear Assistant y ejecutar optimización
        $this->mark('execution.start');
        $jsonOptimized = $this->runAssistantOptimization();
        $this->mark('execution.done');
        
        // 3. POST-EJECUCIÓN: Guardar JSON optimizado y log
        $this->mark('post_execution.start');
        $this->saveResults($jsonOptimized);
        $this->mark('post_execution.done');
        
        return [
            'output' => [
                'tex' => "\xEF\xBB\xBFOptimización SEO completada",
                'json_data' => $jsonOptimized
            ],
            'debug' => ['http' => $this->debugHttp],
            'timeline' => $this->timeline,
            'files_saved' => $this->getFilePaths()
        ];
    }
    
    private function validateInput(): void
    {
        if (empty($this->docBasename)) {
            $this->fail(400, 'doc_basename requerido');
        }
        
        // Verificar que existe FILE_ID (F1C)
        $fileidFile = $this->getFileidPath();
        if (!file_exists($fileidFile)) {
            $this->fail(400, 'Debe completar Fase 1C primero. No se encontró: ' . basename($fileidFile));
        }
        
        // Verificar que existe JSON-FINAL (F2D/F2E)
        $jsonFinalFile = $this->getJsonFinalPath();
        if (!file_exists($jsonFinalFile)) {
            $this->fail(400, 'Debe completar Fase 2E primero. No se encontró: ' . basename($jsonFinalFile));
        }
        
        // Verificar que existe JSON-SEO (F3A)
        $jsonSeoFile = $this->getJsonSeoPath();
        if (!file_exists($jsonSeoFile)) {
            $this->fail(400, 'Debe completar Fase 3A primero. No se encontró: ' . basename($jsonSeoFile));
        }
        
        $this->mark('validation.done');
    }
    
    private function loadTriad(): void
    {
        $this->mark('triad.load.start');
        
        // 1. FILE_ID
        $this->fileId = trim(file_get_contents($this->getFileidPath()));
        if (empty($this->fileId)) {
            $this->fail(400, 'El archivo .fileid está vacío');
        }
        
        // 2. JSON-FINAL
        $jsonFinalContent = file_get_contents($this->getJsonFinalPath());
        $this->jsonFinal = json_decode($jsonFinalContent, true);
        if (!is_array($this->jsonFinal)) {
            $this->fail(400, 'JSON-FINAL no es válido o está corrupto');
        }
        
        // 3. JSON-SEO
        $jsonSeoContent = file_get_contents($this->getJsonSeoPath());
        $this->jsonSEO = json_decode($jsonSeoContent, true);
        if (!is_array($this->jsonSEO)) {
            $this->fail(400, 'JSON-SEO no es válido o está corrupto');
        }
        
        // Validar que JSON-SEO tiene al menos una clave necesaria
        if (!isset($this->jsonSEO['kw']) && !isset($this->jsonSEO['kw_lt']) && !isset($this->jsonSEO['terminos_semanticos'])) {
            $this->fail(400, 'JSON-SEO no contiene claves válidas (kw, kw_lt, terminos_semanticos)');
        }
        
        $this->mark('triad.loaded');
    }
    
    private function runAssistantOptimization(): array
    {
        // 1. Crear assistant fresh
        $assistantData = $this->createFreshAssistant();
        $this->assistantId = $assistantData['id'];
        
        // 2. Crear Thread
        $threadId = $this->createThread();
        
        // 3. Añadir mensaje con FILE_ID adjunto
        $this->addMessage($threadId);
        
        // 4. Ejecutar Run
        $runId = $this->createRun($threadId);
        
        // 5. Polling hasta completed
        $runResult = $this->pollRun($threadId, $runId);
        
        // 6. Obtener mensajes del asistente
        $messages = $this->getMessages($threadId);
        
        // 7. Extraer JSON optimizado de la respuesta
        $jsonOptimized = $this->extractJSON($messages);
        
        return $jsonOptimized;
    }
    
    private function createFreshAssistant(): array
    {
        $this->mark('assistant.create.start');
        
        global $PROMPTS;
        
        $promptTemplate = $PROMPTS[3]['p_optimize_final_content']['prompt'] ?? '';
        if (empty($promptTemplate)) {
            $this->fail(500, 'Prompt p_optimize_final_content no encontrado en PROMPTS[3]');
        }
        
        // Reemplazar placeholders
        $instructions = str_replace(
            ['{FILE_ID}', '{JSON_FINAL}', '{JSON_SEO}'],
            [
                $this->fileId,
                json_encode($this->jsonFinal, JSON_UNESCAPED_UNICODE),
                json_encode($this->jsonSEO, JSON_UNESCAPED_UNICODE)
            ],
            $promptTemplate
        );
        
        $apiKey = $this->config['apio_key'] ?? '';
        if (empty($apiKey)) {
            $this->fail(500, 'API key no configurada');
        }
        
        $payload = [
            'model' => $this->model,
            'name' => 'F3B SEO Writer - ' . $this->docBasename,
            'instructions' => $instructions,
            'tools' => [
                ['type' => 'code_interpreter']
            ]
        ];
        
        $ch = curl_init('https://api.openai.com/v1/assistants');
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $apiKey,
                'Content-Type: application/json',
                'OpenAI-Beta: assistants=v2'
            ],
            CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30
        ]);
        
        $t0 = microtime(true);
        $resp = curl_exec($ch);
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_error($ch);
        curl_close($ch);
        $t1 = microtime(true);
        
        $this->debugHttp[] = [
            'stage' => 'assistant.create',
            'status_code' => $status,
            'ms' => (int) round(($t1 - $t0) * 1000),
            'model' => $this->model
        ];
        
        if ($resp === false || $status < 200 || $status >= 300) {
            $this->fail(502, 'Error creando assistant: ' . ($err ?: 'HTTP ' . $status . ' ' . $resp));
        }
        
        $assistant = json_decode($resp, true);
        if (!isset($assistant['id'])) {
            $this->fail(502, 'Respuesta de assistant sin ID válido');
        }
        
        $this->mark('assistant.created');
        return $assistant;
    }
    
    private function createThread(): string
    {
        $this->mark('thread.create.start');
        
        $apiKey = $this->config['apio_key'] ?? '';
        $ch = curl_init('https://api.openai.com/v1/threads');
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $apiKey,
                'Content-Type: application/json',
                'OpenAI-Beta: assistants=v2'
            ],
            CURLOPT_POSTFIELDS => json_encode([]),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30
        ]);
        
        $t0 = microtime(true);
        $resp = curl_exec($ch);
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        $t1 = microtime(true);
        
        $this->debugHttp[] = [
            'stage' => 'thread.create',
            'status_code' => $status,
            'ms' => (int) round(($t1 - $t0) * 1000)
        ];
        
        if ($status < 200 || $status >= 300) {
            $this->fail(502, 'Error creando thread: HTTP ' . $status);
        }
        
        $thread = json_decode($resp, true);
        $threadId = $thread['id'] ?? null;
        if (!$threadId) {
            $this->fail(502, 'Thread sin ID válido');
        }
        
        $this->mark('thread.created');
        return $threadId;
    }
    
    private function addMessage(string $threadId): void
    {
        $this->mark('message.add.start');
        
        $apiKey = $this->config['apio_key'] ?? '';
        
        // Mensaje simple: la optimización se hace sobre la TRÍADA ya inyectada en instructions
        $messageContent = 'Optimiza el JSON-FINAL según las instrucciones, integrando la terminología de JSON-SEO y verificando contra el documento original.';
        
        $payload = [
            'role' => 'user',
            'content' => $messageContent,
            'attachments' => [
                [
                    'file_id' => $this->fileId,
                    'tools' => [
                        ['type' => 'code_interpreter']
                    ]
                ]
            ]
        ];
        
        $ch = curl_init("https://api.openai.com/v1/threads/{$threadId}/messages");
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $apiKey,
                'Content-Type: application/json',
                'OpenAI-Beta: assistants=v2'
            ],
            CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30
        ]);
        
        $t0 = microtime(true);
        $resp = curl_exec($ch);
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        $t1 = microtime(true);
        
        $this->debugHttp[] = [
            'stage' => 'message.add',
            'status_code' => $status,
            'ms' => (int) round(($t1 - $t0) * 1000),
            'attachments' => [['file_id' => $this->fileId]]
        ];
        
        if ($status < 200 || $status >= 300) {
            $this->fail(502, 'Error añadiendo mensaje: HTTP ' . $status . ' ' . $resp);
        }
        
        $this->mark('message.added');
    }
    
    private function createRun(string $threadId): string
    {
        $this->mark('run.create.start');
        
        $apiKey = $this->config['apio_key'] ?? '';
        $payload = [
            'assistant_id' => $this->assistantId,
            'model' => $this->model
        ];
        
        $ch = curl_init("https://api.openai.com/v1/threads/{$threadId}/runs");
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $apiKey,
                'Content-Type: application/json',
                'OpenAI-Beta: assistants=v2'
            ],
            CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30
        ]);
        
        $t0 = microtime(true);
        $resp = curl_exec($ch);
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        $t1 = microtime(true);
        
        $this->debugHttp[] = [
            'stage' => 'run.create',
            'status_code' => $status,
            'ms' => (int) round(($t1 - $t0) * 1000),
            'model' => $this->model
        ];
        
        if ($status < 200 || $status >= 300) {
            $this->fail(502, 'Error creando run: HTTP ' . $status . ' ' . $resp);
        }
        
        $run = json_decode($resp, true);
        $runId = $run['id'] ?? null;
        if (!$runId) {
            $this->fail(502, 'Run sin ID válido');
        }
        
        $this->mark('run.created');
        return $runId;
    }
    
    private function pollRun(string $threadId, string $runId): array
    {
        $this->mark('run.poll.start');
        
        $apiKey = $this->config['apio_key'] ?? '';
        $maxAttempts = 60; // F3B puede tomar más tiempo (generación de texto largo)
        $intervalMs = 2000;
        $timeoutSeconds = 120; // 2 minutos
        
        $startTime = time();
        
        for ($i = 0; $i < $maxAttempts; $i++) {
            if (time() - $startTime > $timeoutSeconds) {
                $this->fail(504, 'Timeout esperando completitud del run (120s)');
            }
            
            usleep($intervalMs * 1000);
            
            $ch = curl_init("https://api.openai.com/v1/threads/{$threadId}/runs/{$runId}");
            curl_setopt_array($ch, [
                CURLOPT_HTTPHEADER => [
                    'Authorization: Bearer ' . $apiKey,
                    'OpenAI-Beta: assistants=v2'
                ],
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 10
            ]);
            
            $resp = curl_exec($ch);
            $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            if ($status < 200 || $status >= 300) {
                continue;
            }
            
            $run = json_decode($resp, true);
            $runStatus = $run['status'] ?? 'unknown';
            
            $this->debugHttp[] = [
                'stage' => 'run.poll',
                'attempt' => $i + 1,
                'status_code' => $status,
                'run_status' => $runStatus
            ];
            
            if ($runStatus === 'completed') {
                $this->mark('run.completed');
                return $run;
            }
            
            if (in_array($runStatus, ['failed', 'cancelled', 'expired'])) {
                $errorMsg = $run['last_error']['message'] ?? 'Run falló sin mensaje';
                $this->fail(502, "Run terminó con estado: {$runStatus}. Error: {$errorMsg}");
            }
        }
        
        $this->fail(504, 'Run no completó después de ' . $maxAttempts . ' intentos');
    }
    
    private function getMessages(string $threadId): array
    {
        $this->mark('messages.get.start');
        
        $apiKey = $this->config['apio_key'] ?? '';
        $ch = curl_init("https://api.openai.com/v1/threads/{$threadId}/messages");
        curl_setopt_array($ch, [
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $apiKey,
                'OpenAI-Beta: assistants=v2'
            ],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30
        ]);
        
        $t0 = microtime(true);
        $resp = curl_exec($ch);
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        $t1 = microtime(true);
        
        $this->debugHttp[] = [
            'stage' => 'messages.get',
            'status_code' => $status,
            'ms' => (int) round(($t1 - $t0) * 1000)
        ];
        
        if ($status < 200 || $status >= 300) {
            $this->fail(502, 'Error obteniendo mensajes: HTTP ' . $status);
        }
        
        $messagesData = json_decode($resp, true);
        $this->mark('messages.retrieved');
        
        return $messagesData['data'] ?? [];
    }
    
    private function extractJSON(array $messages): array
    {
        $this->mark('json.extract.start');
        
        // Buscar el último mensaje del assistant
        foreach ($messages as $msg) {
            if (($msg['role'] ?? '') !== 'assistant') {
                continue;
            }
            
            $content = $msg['content'][0]['text']['value'] ?? '';
            if (empty($content)) {
                continue;
            }
            
            // Guardar contenido completo en debug para diagnóstico
            $this->debugHttp[] = [
                'stage' => 'json.extract',
                'raw_content_length' => mb_strlen($content),
                'raw_content_preview' => mb_substr($content, 0, 500)
            ];
            
            $jsonText = '';
            
            // Intento 1: JSON entre triple backticks con etiqueta json
            if (preg_match('/```json\s*(\{.*\})\s*```/s', $content, $matches)) {
                $jsonText = $matches[1];
            }
            // Intento 2: JSON entre triple backticks sin etiqueta
            elseif (preg_match('/```\s*(\{.*\})\s*```/s', $content, $matches)) {
                $jsonText = $matches[1];
            }
            // Intento 3: JSON directo (sin backticks) - greedy para capturar JSON anidado
            elseif (preg_match('/(\{.*\})/s', $content, $matches)) {
                $jsonText = $matches[1];
            }
            else {
                $this->fail(502, 'No se encontró JSON en la respuesta del assistant. Contenido: ' . mb_substr($content, 0, 200));
            }
            
            $jsonData = json_decode($jsonText, true);
            
            if (!$jsonData || !is_array($jsonData)) {
                $this->fail(502, 'JSON inválido recibido del assistant: ' . json_last_error_msg() . '. JSON: ' . mb_substr($jsonText, 0, 300));
            }
            
            // Validar que tiene el campo nuevo y campos esenciales
            if (!$this->validateOptimizedJSON($jsonData)) {
                $missingFields = [];
                if (!array_key_exists('descripcion_larga_producto', $jsonData)) {
                    $missingFields[] = 'descripcion_larga_producto';
                }
                $requiredKeys = ['nombre_producto', 'ficha_tecnica', 'resumen_tecnico'];
                foreach ($requiredKeys as $key) {
                    if (!array_key_exists($key, $jsonData)) {
                        $missingFields[] = $key;
                    }
                }
                $this->fail(502, 'JSON optimizado inválido. Faltan campos: ' . implode(', ', $missingFields));
            }
            
            $this->mark('json.extracted');
            return $jsonData;
        }
        
        $this->fail(502, 'No se encontró mensaje del assistant en el thread');
    }
    
    private function validateOptimizedJSON(array $json): bool
    {
        // Validar que tiene el campo nuevo descripcion_larga_producto
        if (!array_key_exists('descripcion_larga_producto', $json)) {
            return false;
        }
        
        // Validar que mantiene campos esenciales del JSON-FINAL
        $requiredKeys = ['nombre_producto', 'ficha_tecnica', 'resumen_tecnico'];
        foreach ($requiredKeys as $key) {
            if (!array_key_exists($key, $json)) {
                return false;
            }
        }
        
        return true;
    }
    
    private function saveResults(array $jsonOptimized): void
    {
        $docsDir = $this->config['docs_dir'] ?? '';
        $docDir = $docsDir . DIRECTORY_SEPARATOR . $this->docBasename;
        
        if (!is_dir($docDir)) {
            mkdir($docDir, 0755, true);
        }
        
        // SOBRESCRIBIR JSON-FINAL con versión optimizada
        $jsonFinalPath = $docDir . DIRECTORY_SEPARATOR . $this->docBasename . '.json';
        $savedJson = file_put_contents(
            $jsonFinalPath,
            json_encode($jsonOptimized, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
        );
        
        // Guardar log de fase 3B con metadata COMPLETA (JSON con debug APIO)
        $logPath = $docDir . DIRECTORY_SEPARATOR . $this->docBasename . '_3B.log';
        
        $descripcionLarga = $jsonOptimized['descripcion_larga_producto'] ?? '';
        $wordCount = str_word_count($descripcionLarga);
        $charCount = mb_strlen($descripcionLarga);
        
        $logData = [
            'timestamp' => date('Y-m-d H:i:s'),
            'phase' => '3B',
            'status' => 'SUCCESS',
            'doc_basename' => $this->docBasename,
            'file_id' => $this->fileId,
            'assistant_id' => $this->assistantId,
            'model' => $this->model,
            'descripcion_larga_producto' => [
                'words' => $wordCount,
                'characters' => $charCount
            ],
            'campos_optimizados' => ['ficha_tecnica', 'resumen_tecnico', 'razon_uso_formacion'],
            'campo_nuevo' => 'descripcion_larga_producto',
            'json_final_saved' => $savedJson !== false,
            'timeline' => $this->timeline,
            'debug_http' => $this->debugHttp
        ];
        
        file_put_contents($logPath, json_encode($logData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        
        // NO guardar assistant_id (F3B usa assistant fresco cada vez)
        
        $this->mark('files.saved');
    }
    
    private function getFilePaths(): array
    {
        $docsDir = $this->config['docs_dir'] ?? '';
        $docDir = $docsDir . DIRECTORY_SEPARATOR . $this->docBasename;
        
        return [
            'json_final' => $docDir . DIRECTORY_SEPARATOR . $this->docBasename . '.json',
            'log' => $docDir . DIRECTORY_SEPARATOR . $this->docBasename . '_3B.log'
        ];
    }
    
    private function getFileidPath(): string
    {
        $docsDir = $this->config['docs_dir'] ?? '';
        return $docsDir . DIRECTORY_SEPARATOR . $this->docBasename . DIRECTORY_SEPARATOR . $this->docBasename . '.fileid';
    }
    
    private function getJsonFinalPath(): string
    {
        $docsDir = $this->config['docs_dir'] ?? '';
        return $docsDir . DIRECTORY_SEPARATOR . $this->docBasename . DIRECTORY_SEPARATOR . $this->docBasename . '.json';
    }
    
    private function getJsonSeoPath(): string
    {
        $docsDir = $this->config['docs_dir'] ?? '';
        return $docsDir . DIRECTORY_SEPARATOR . $this->docBasename . DIRECTORY_SEPARATOR . $this->docBasename . '_SEO.json';
    }
    
    private function fail(int $code, string $msg): never
    {
        http_response_code($code);
        echo json_encode([
            'output' => ['tex' => "\xEF\xBB\xBF"],
            'debug' => ['http' => $this->debugHttp, 'error' => $msg],
            'timeline' => $this->timeline
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
}

// PUNTO DE ENTRADA
$rawInput = file_get_contents('php://input');
$input = json_decode($rawInput, true) ?? [];

$docBasename = $input['doc_basename'] ?? '';

if (empty($docBasename)) {
    http_response_code(400);
    echo json_encode([
        'output' => ['tex' => "\xEF\xBB\xBF"],
        'debug' => ['error' => 'Parámetro doc_basename requerido'],
        'timeline' => []
    ]);
    exit;
}

try {
    $proxy = new Phase3BProxy($docBasename, $input);
    $result = $proxy->execute($input);
    echo json_encode($result, JSON_UNESCAPED_UNICODE);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'output' => ['tex' => "\xEF\xBB\xBF"],
        'debug' => ['error' => 'Excepción: ' . $e->getMessage()],
        'timeline' => []
    ]);
}

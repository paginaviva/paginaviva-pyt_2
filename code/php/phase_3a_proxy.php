<?php
/**
 * phase_3a_proxy.php
 * Proxy para Fase 3A: Extracción de terminología técnica SEO
 * 
 * Propósito: Analizar documento original (FILE_ID) y extraer terminología
 * técnica clasificada jerárquicamente (kw, kw_lt, terminos_semanticos)
 * para generar diccionario SEO (JSON-SEO)
 */

require_once __DIR__ . '/lib_apio.php';

header('Content-Type: application/json; charset=utf-8');

class Phase3AProxy
{
    private array $timeline = [];
    private array $debugHttp = [];
    private string $docBasename;
    private array $config;
    private array $input;
    private string $fileId;
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
        // 1. PRE-EJECUCIÓN: Validar entrada
        $this->mark('pre_execution.start');
        $this->validateInput();
        $this->mark('pre_execution.done');
        
        // 2. EJECUCIÓN: Crear/reutilizar Assistant y ejecutar análisis
        $this->mark('execution.start');
        $jsonSEO = $this->runAssistantAnalysis();
        $this->mark('execution.done');
        
        // 3. POST-EJECUCIÓN: Guardar JSON-SEO y log
        $this->mark('post_execution.start');
        $this->saveResults($jsonSEO);
        $this->mark('post_execution.done');
        
        return [
            'output' => [
                'tex' => "\xEF\xBB\xBFAnálisis terminológico SEO completado",
                'json_data' => $jsonSEO
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
        
        // Verificar que existe el archivo .fileid de F1C
        $fileidFile = $this->getFileidPath();
        if (!file_exists($fileidFile)) {
            $this->fail(400, 'Debe completar Fase 1C primero. No se encontró: ' . basename($fileidFile));
        }
        
        // Leer file_id del documento
        $this->fileId = trim(file_get_contents($fileidFile));
        if (empty($this->fileId)) {
            $this->fail(400, 'El archivo .fileid está vacío');
        }
        
        // F3A NO requiere JSON previo - trabaja solo con FILE_ID
        $this->mark('validation.done');
    }
    
    private function runAssistantAnalysis(): array
    {
        // 1. Obtener o crear assistant
        $assistantData = $this->getOrCreateAssistant();
        $this->assistantId = $assistantData['id'];
        
        // 2. Crear Thread
        $threadId = $this->createThread();
        
        // 3. Preparar y enviar mensaje con FILE_ID adjunto
        $this->addMessage($threadId);
        
        // 4. Ejecutar Run
        $runId = $this->createRun($threadId);
        
        // 5. Polling hasta completed
        $runResult = $this->pollRun($threadId, $runId);
        
        // 6. Obtener mensajes del asistente
        $messages = $this->getMessages($threadId);
        
        // 7. Extraer JSON-SEO de la respuesta
        $jsonSEO = $this->extractJSON($messages);
        
        return $jsonSEO;
    }
    
    private function getOrCreateAssistant(): array
    {
        $this->mark('assistant.get_or_create.start');
        
        // F3A usa assistant FRESCO cada vez (sin persistencia)
        // Esto asegura análisis limpio sin contaminación de ejecuciones previas
        return $this->createFreshAssistant();
    }
    
    private function createFreshAssistant(): array
    {
        global $PROMPTS;
        
        $promptTemplate = $PROMPTS[3]['p_extract_terminology']['prompt'] ?? '';
        if (empty($promptTemplate)) {
            $this->fail(500, 'Prompt p_extract_terminology no encontrado en PROMPTS[3]');
        }
        
        // Reemplazar placeholder {FILE_ID}
        $instructions = str_replace('{FILE_ID}', $this->fileId, $promptTemplate);
        
        $apiKey = $this->config['apio_key'] ?? '';
        if (empty($apiKey)) {
            $this->fail(500, 'API key no configurada');
        }
        
        $payload = [
            'model' => $this->model,
            'name' => 'F3A Terminology Analyst - ' . $this->docBasename,
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
            'headers' => ['Authorization' => 'Bearer ***', 'OpenAI-Beta' => 'assistants=v2'],
            'payload_preview' => ['model' => $this->model, 'tools' => ['code_interpreter']]
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
        
        // Mensaje simple: el análisis se hace sobre FILE_ID adjunto
        $messageContent = 'Analiza el documento adjunto y extrae la terminología técnica SEO según las instrucciones.';
        
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
            'attachments' => [['file_id' => $this->fileId, 'tools' => ['code_interpreter']]]
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
        $maxAttempts = 30;
        $intervalMs = 2000;
        $timeoutSeconds = 60;
        
        $startTime = time();
        
        for ($i = 0; $i < $maxAttempts; $i++) {
            if (time() - $startTime > $timeoutSeconds) {
                $this->fail(504, 'Timeout esperando completitud del run (60s)');
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
        
        // Buscar el mensaje más reciente del asistente
        foreach ($messages as $msg) {
            if (($msg['role'] ?? '') !== 'assistant') continue;
            
            $content = $msg['content'] ?? [];
            foreach ($content as $block) {
                if (($block['type'] ?? '') !== 'text') continue;
                
                $text = $block['text']['value'] ?? '';
                if (empty($text)) continue;
                
                // Buscar bloque JSON en el texto
                if (preg_match('/```json\s*(\{.*?\})\s*```/s', $text, $matches)) {
                    $jsonText = $matches[1];
                } elseif (preg_match('/(\{[^{}]*"kw"[^{}]*\})/s', $text, $matches)) {
                    $jsonText = $matches[1];
                } else {
                    // Intentar todo el texto como JSON
                    $jsonText = $text;
                }
                
                $jsonData = json_decode($jsonText, true);
                if (is_array($jsonData)) {
                    // Validar estructura JSON-SEO
                    if ($this->validateJSONSEO($jsonData)) {
                        $this->mark('json.extracted');
                        return $jsonData;
                    }
                }
            }
        }
        
        $this->fail(502, 'No se pudo extraer JSON-SEO válido de la respuesta del asistente');
    }
    
    private function validateJSONSEO(array $json): bool
    {
        // Validar que tenga las 3 claves obligatorias
        $requiredKeys = ['kw', 'kw_lt', 'terminos_semanticos'];
        
        foreach ($requiredKeys as $key) {
            if (!array_key_exists($key, $json)) {
                return false;
            }
            if (!is_array($json[$key])) {
                return false;
            }
        }
        
        return true;
    }
    
    private function saveResults(array $jsonSEO): void
    {
        $docsDir = $this->config['docs_dir'] ?? '';
        $docDir = $docsDir . DIRECTORY_SEPARATOR . $this->docBasename;
        
        if (!is_dir($docDir)) {
            mkdir($docDir, 0755, true);
        }
        
        // Guardar JSON-SEO con nombre: NombreDocumento_SEO.json
        $jsonSeoPath = $docDir . DIRECTORY_SEPARATOR . $this->docBasename . '_SEO.json';
        file_put_contents(
            $jsonSeoPath,
            json_encode($jsonSEO, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
        );
        
        // Guardar log de fase 3A
        $logPath = $docDir . DIRECTORY_SEPARATOR . $this->docBasename . '_3A.log';
        $logEntry = sprintf(
            "[%s] F3A: Análisis terminológico SEO completado. Modelo: %s. Términos: kw=%d, kw_lt=%d, semánticos=%d\n",
            date('Y-m-d H:i:s'),
            $this->model,
            count($jsonSEO['kw'] ?? []),
            count($jsonSEO['kw_lt'] ?? []),
            count($jsonSEO['terminos_semanticos'] ?? [])
        );
        file_put_contents($logPath, $logEntry, FILE_APPEND);
        
        // NO guardar assistant_id (F3A usa assistant fresco cada vez)
        
        $this->mark('files.saved');
    }
    
    private function getFilePaths(): array
    {
        $docsDir = $this->config['docs_dir'] ?? '';
        $docDir = $docsDir . DIRECTORY_SEPARATOR . $this->docBasename;
        
        return [
            'json_seo' => $docDir . DIRECTORY_SEPARATOR . $this->docBasename . '_SEO.json',
            'log' => $docDir . DIRECTORY_SEPARATOR . $this->docBasename . '_3A.log'
        ];
    }
    
    private function getFileidPath(): string
    {
        $docsDir = $this->config['docs_dir'] ?? '';
        return $docsDir . DIRECTORY_SEPARATOR . $this->docBasename . DIRECTORY_SEPARATOR . $this->docBasename . '.fileid';
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
    $proxy = new Phase3AProxy($docBasename, $input);
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

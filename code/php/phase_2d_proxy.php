<?php
/**
 * phase_2d_proxy.php
 * Proxy para Fase 2D: Generar ficha técnica y resumen técnico usando Assistants API
 * 
 * Propósito: Añadir campos generativos (ficha_tecnica, resumen_tecnico) al JSON de F2C
 */

require_once __DIR__ . '/lib_apio.php';

header('Content-Type: application/json; charset=utf-8');

class Phase2DProxy
{
    private array $timeline = [];
    private array $debugHttp = [];
    private string $docBasename;
    private array $config;
    private array $input;
    private string $fileId;
    private array $jsonPrevio;
    private ?string $assistantId = null;
    
    public function __construct(string $docBasename, array $input = [])
    {
        $this->docBasename = $docBasename;
        $this->input = $input;
        $this->config = apio_load_config();
        $this->mark('init');
        
        // Cargar prompts con manejo de errores
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
        
        // 2. EJECUCIÓN: Crear/reutilizar Assistant y ejecutar generación
        $this->mark('execution.start');
        $jsonResult = $this->runAssistantAnalysis();
        $this->mark('execution.done');
        
        // 3. POST-EJECUCIÓN: Guardar JSON ampliado y log
        $this->mark('post_execution.start');
        $this->saveResults($jsonResult);
        $this->mark('post_execution.done');
        
        return [
            'output' => [
                'tex' => "\xEF\xBB\xBFFicha técnica y resumen generados",
                'json_data' => $jsonResult
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
        
        // Verificar que existe el JSON de F2C
        $jsonFile = $this->getJsonPath();
        if (!file_exists($jsonFile)) {
            $this->fail(400, 'Debe completar Fase 2C primero. No se encontró: ' . basename($jsonFile));
        }
        
        // Leer JSON previo de F2C (22 campos)
        $jsonContent = file_get_contents($jsonFile);
        $this->jsonPrevio = json_decode($jsonContent, true);
        
        if (!is_array($this->jsonPrevio)) {
            $this->fail(400, 'El JSON de F2C no es válido');
        }
        
        // Validar que tiene al menos los campos básicos de F2C
        $requiredKeys = ['file_id', 'nombre_producto', 'grupos_de_soluciones', 'familia', 'categoria'];
        foreach ($requiredKeys as $key) {
            if (!array_key_exists($key, $this->jsonPrevio)) {
                $this->fail(400, 'El JSON de F2C no tiene el campo requerido: ' . $key);
            }
        }
        
        $this->mark('validation.done');
    }
    
    private function runAssistantAnalysis(): array
    {
        $apiKey = $this->config['apio_key'] ?? '';
        if (!$apiKey) {
            $this->fail(500, 'API key de OpenAI no configurada');
        }
        
        // Modelo personalizado del frontend (si se envió)
        $modelOverride = $this->input['model'] ?? null;
        
        // Obtener o crear Assistant
        $this->assistantId = $this->getOrCreateAssistant($apiKey, $modelOverride);
        
        // Crear Thread
        $threadId = $this->createThread($apiKey);
        
        // Agregar mensaje con referencia al archivo y JSON previo
        $this->addMessage($apiKey, $threadId);
        
        // Ejecutar Assistant
        $runId = $this->createRun($apiKey, $threadId);
        
        // Esperar a que termine (polling)
        $this->pollRun($apiKey, $threadId, $runId);
        
        // Obtener respuesta
        $jsonResult = $this->getResponse($apiKey, $threadId);
        
        return $jsonResult;
    }
    
    private function getOrCreateAssistant(string $apiKey, ?string $modelOverride = null): string
    {
        $this->mark('assistant.check.start');
        
        // Verificar si existe assistant_id guardado para F2D
        $assistantIdFile = $this->getAssistantIdPath();
        
        if (file_exists($assistantIdFile)) {
            $assistantId = trim(file_get_contents($assistantIdFile));
            if ($assistantId) {
                $this->mark('assistant.reused');
                $this->debugHttp[] = [
                    'stage' => 'assistant.reused',
                    'assistant_id' => $assistantId,
                    'source' => 'file'
                ];
                return $assistantId;
            }
        }
        
        // Crear nuevo Assistant
        $this->mark('assistant.create.start');
        
        global $PROMPTS;
        $promptTemplate = $PROMPTS[2]['p_generate_technical_sheet']['prompt'] ?? '';
        
        if (!$promptTemplate) {
            $this->fail(500, 'Prompt de F2D no encontrado en config/prompts.php');
        }
        
        // Reemplazar placeholders
        $instructions = str_replace('{FILE_ID}', 'the file_id provided in the message', $promptTemplate);
        $instructions = str_replace('{JSON_PREVIO}', json_encode($this->jsonPrevio, JSON_UNESCAPED_UNICODE), $instructions);
        
        // Usar modelo del usuario o default fijo "gpt-4o"
        $model = $modelOverride ?? 'gpt-4o';
        
        $payload = [
            'model' => $model,
            'name' => 'Technical Writer Cofem',
            'description' => 'Generates technical sheet and summary for Cofem products',
            'instructions' => $instructions,
            'tools' => [
                ['type' => 'code_interpreter']
            ]
        ];
        
        $ch = curl_init('https://api.openai.com/v1/assistants');
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $apiKey,
                'Content-Type: application/json',
                'OpenAI-Beta: assistants=v2'
            ],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 60,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_SSL_VERIFYPEER => true
        ]);
        
        $t0 = microtime(true);
        $resp = curl_exec($ch);
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);
        $t1 = microtime(true);
        
        $this->debugHttp[] = [
            'stage' => 'assistant.create',
            'status_code' => $status,
            'ms' => (int) round(($t1 - $t0) * 1000),
            'endpoint' => 'https://api.openai.com/v1/assistants',
            'method' => 'POST',
            'request' => [
                'headers' => [
                    'Authorization' => 'Bearer ***',
                    'Content-Type' => 'application/json',
                    'OpenAI-Beta' => 'assistants=v2'
                ],
                'payload' => $payload
            ],
            'response' => json_decode($resp, true) ?: $resp
        ];
        
        if ($resp === false || $status === 0) {
            $errorMsg = '⚠️ Alerta: No se recibió ninguna respuesta válida del servidor OpenAI (HTTP 0). '
                      . 'Detalles técnicos: ' . ($curlError ?: 'Sin detalles de cURL');
            $this->fail(502, $errorMsg);
        }
        
        if ($status < 200 || $status >= 300) {
            $this->fail(502, 'Error al crear Assistant: HTTP ' . $status . ' - ' . $resp);
        }
        
        $result = json_decode($resp, true);
        $assistantId = $result['id'] ?? null;
        
        if (!$assistantId) {
            $this->fail(502, 'OpenAI no devolvió assistant_id válido');
        }
        
        // Guardar assistant_id para reutilización
        file_put_contents($assistantIdFile, $assistantId);
        
        $this->mark('assistant.create.done');
        
        return $assistantId;
    }
    
    private function createThread(string $apiKey): string
    {
        $this->mark('thread.create.start');
        
        $ch = curl_init('https://api.openai.com/v1/threads');
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode([]),
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $apiKey,
                'Content-Type: application/json',
                'OpenAI-Beta: assistants=v2'
            ],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 60,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_SSL_VERIFYPEER => true
        ]);
        
        $t0 = microtime(true);
        $resp = curl_exec($ch);
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);
        $t1 = microtime(true);
        
        $this->debugHttp[] = [
            'stage' => 'thread.create',
            'status_code' => $status,
            'ms' => (int) round(($t1 - $t0) * 1000),
            'endpoint' => 'https://api.openai.com/v1/threads',
            'method' => 'POST',
            'request' => [
                'headers' => [
                    'Authorization' => 'Bearer ***',
                    'Content-Type' => 'application/json',
                    'OpenAI-Beta' => 'assistants=v2'
                ],
                'payload' => []
            ],
            'response' => json_decode($resp, true) ?: $resp
        ];
        
        if ($resp === false || $status === 0) {
            $errorMsg = '⚠️ Alerta: No se recibió ninguna respuesta válida del servidor OpenAI (HTTP 0). '
                      . 'Detalles técnicos: ' . ($curlError ?: 'Sin detalles de cURL');
            $this->fail(502, $errorMsg);
        }
        
        if ($status < 200 || $status >= 300) {
            $this->fail(502, 'Error al crear Thread: HTTP ' . $status . ' - ' . $resp);
        }
        
        $result = json_decode($resp, true);
        $threadId = $result['id'] ?? null;
        
        if (!$threadId) {
            $this->fail(502, 'OpenAI no devolvió thread_id válido');
        }
        
        $this->mark('thread.create.done');
        
        return $threadId;
    }
    
    private function addMessage(string $apiKey, string $threadId): void
    {
        $this->mark('message.add.start');
        
        // Mensaje incluyendo el JSON previo y referencia al documento
        $jsonPrevioStr = json_encode($this->jsonPrevio, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        
        $payload = [
            'role' => 'user',
            'content' => "Please generate a technical sheet and summary for this document.\n\nDocument file_id: {$this->fileId}\n\nCurrent JSON:\n{$jsonPrevioStr}",
            'attachments' => [
                [
                    'file_id' => $this->fileId,
                    'tools' => [['type' => 'code_interpreter']]
                ]
            ]
        ];
        
        $ch = curl_init("https://api.openai.com/v1/threads/{$threadId}/messages");
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $apiKey,
                'Content-Type: application/json',
                'OpenAI-Beta: assistants=v2'
            ],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 60,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_SSL_VERIFYPEER => true
        ]);
        
        $t0 = microtime(true);
        $resp = curl_exec($ch);
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);
        $t1 = microtime(true);
        
        $this->debugHttp[] = [
            'stage' => 'message.add',
            'status_code' => $status,
            'ms' => (int) round(($t1 - $t0) * 1000),
            'endpoint' => "https://api.openai.com/v1/threads/{$threadId}/messages",
            'method' => 'POST',
            'request' => [
                'headers' => [
                    'Authorization' => 'Bearer ***',
                    'Content-Type' => 'application/json',
                    'OpenAI-Beta' => 'assistants=v2'
                ],
                'payload' => $payload
            ],
            'response' => json_decode($resp, true) ?: $resp,
            'file_id_document' => $this->fileId,
            'json_previo_length' => strlen($jsonPrevioStr)
        ];
        
        if ($resp === false || $status === 0) {
            $errorMsg = '⚠️ Alerta: No se recibió ninguna respuesta válida del servidor OpenAI (HTTP 0). '
                      . 'Detalles técnicos: ' . ($curlError ?: 'Sin detalles de cURL');
            $this->fail(502, $errorMsg);
        }
        
        if ($status < 200 || $status >= 300) {
            $this->fail(502, 'Error al agregar mensaje: HTTP ' . $status . ' - ' . $resp);
        }
        
        $this->mark('message.add.done');
    }
    
    private function createRun(string $apiKey, string $threadId): string
    {
        $this->mark('run.create.start');
        
        $payload = [
            'assistant_id' => $this->assistantId
        ];
        
        $ch = curl_init("https://api.openai.com/v1/threads/{$threadId}/runs");
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $apiKey,
                'Content-Type: application/json',
                'OpenAI-Beta: assistants=v2'
            ],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 60,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_SSL_VERIFYPEER => true
        ]);
        
        $t0 = microtime(true);
        $resp = curl_exec($ch);
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);
        $t1 = microtime(true);
        
        $this->debugHttp[] = [
            'stage' => 'run.create',
            'status_code' => $status,
            'ms' => (int) round(($t1 - $t0) * 1000),
            'endpoint' => "https://api.openai.com/v1/threads/{$threadId}/runs",
            'method' => 'POST',
            'request' => [
                'headers' => [
                    'Authorization' => 'Bearer ***',
                    'Content-Type' => 'application/json',
                    'OpenAI-Beta' => 'assistants=v2'
                ],
                'payload' => $payload
            ],
            'response' => json_decode($resp, true) ?: $resp
        ];
        
        if ($resp === false || $status === 0) {
            $errorMsg = '⚠️ Alerta: No se recibió ninguna respuesta válida del servidor OpenAI (HTTP 0). '
                      . 'Detalles técnicos: ' . ($curlError ?: 'Sin detalles de cURL');
            $this->fail(502, $errorMsg);
        }
        
        if ($status < 200 || $status >= 300) {
            $this->fail(502, 'Error al crear Run: HTTP ' . $status . ' - ' . $resp);
        }
        
        $result = json_decode($resp, true);
        $runId = $result['id'] ?? null;
        
        if (!$runId) {
            $this->fail(502, 'OpenAI no devolvió run_id válido');
        }
        
        $this->mark('run.create.done');
        
        return $runId;
    }
    
    private function pollRun(string $apiKey, string $threadId, string $runId): void
    {
        $this->mark('run.poll.start');
        
        // Esperar 3 segundos antes del primer polling (dar tiempo al modelo a iniciar)
        sleep(3);
        
        $maxAttempts = 30; // 30 intentos x 2 segundos = 60 segundos máximo
        $attempt = 0;
        
        while ($attempt < $maxAttempts) {
            $attempt++;
            
            $ch = curl_init("https://api.openai.com/v1/threads/{$threadId}/runs/{$runId}");
            curl_setopt_array($ch, [
                CURLOPT_HTTPHEADER => [
                    'Authorization: Bearer ' . $apiKey,
                    'OpenAI-Beta: assistants=v2'
                ],
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 60,
                CURLOPT_CONNECTTIMEOUT => 10,
                CURLOPT_SSL_VERIFYPEER => true
            ]);
            
            $resp = curl_exec($ch);
            $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlError = curl_error($ch);
            curl_close($ch);
            
            // Detectar HTTP 0 (fallo de red/comunicación)
            if ($resp === false || $status === 0) {
                $errorMsg = '⚠️ Alerta: No se recibió ninguna respuesta válida del servidor OpenAI (HTTP 0). '
                          . 'Esto indica un fallo en la comunicación HTTP, no un error de la API. '
                          . 'Detalles técnicos: ' . ($curlError ?: 'Sin detalles de cURL');
                
                $this->debugHttp[] = [
                    'stage' => 'run.poll',
                    'attempt' => $attempt,
                    'error' => 'HTTP 0',
                    'curl_error' => $curlError,
                    'details' => 'No se recibió respuesta HTTP válida'
                ];
                
                $this->fail(502, $errorMsg);
            }
            
            if ($status < 200 || $status >= 300) {
                $this->fail(502, 'Error al consultar estado del Run: HTTP ' . $status . ' - ' . $resp);
            }
            
            $result = json_decode($resp, true);
            $runStatus = $result['status'] ?? '';
            
            $this->debugHttp[] = [
                'stage' => 'run.poll',
                'attempt' => $attempt,
                'status' => $runStatus,
                'http_code' => $status
            ];
            
            if ($runStatus === 'completed') {
                $this->mark('run.poll.completed');
                return;
            }
            
            if (in_array($runStatus, ['failed', 'cancelled', 'expired'])) {
                $failureReason = $result['last_error']['message'] ?? 'Sin detalles';
                $this->fail(502, 'Run terminó con estado: ' . $runStatus . '. Razón: ' . $failureReason);
            }
            
            // Esperar 2 segundos antes del siguiente intento
            if ($attempt < $maxAttempts) {
                sleep(2);
            }
        }
        
        $this->fail(504, 'Timeout esperando a que el Run complete (60s). El modelo está tardando más de lo esperado.');
    }
    
    private function getResponse(string $apiKey, string $threadId): array
    {
        $this->mark('response.get.start');
        
        $ch = curl_init("https://api.openai.com/v1/threads/{$threadId}/messages");
        curl_setopt_array($ch, [
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $apiKey,
                'OpenAI-Beta: assistants=v2'
            ],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 60,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_SSL_VERIFYPEER => true
        ]);
        
        $t0 = microtime(true);
        $resp = curl_exec($ch);
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);
        $t1 = microtime(true);
        
        $this->debugHttp[] = [
            'stage' => 'response.get',
            'status_code' => $status,
            'ms' => (int) round(($t1 - $t0) * 1000),
            'endpoint' => "https://api.openai.com/v1/threads/{$threadId}/messages",
            'method' => 'GET',
            'request' => [
                'headers' => [
                    'Authorization' => 'Bearer ***',
                    'OpenAI-Beta' => 'assistants=v2'
                ]
            ],
            'response' => json_decode($resp, true) ?: $resp
        ];
        
        if ($resp === false || $status === 0) {
            $errorMsg = '⚠️ Alerta: No se recibió ninguna respuesta válida del servidor OpenAI (HTTP 0). '
                      . 'Detalles técnicos: ' . ($curlError ?: 'Sin detalles de cURL');
            $this->fail(502, $errorMsg);
        }
        
        if ($status < 200 || $status >= 300) {
            $this->fail(502, 'Error al obtener mensajes: HTTP ' . $status . ' - ' . $resp);
        }
        
        $result = json_decode($resp, true);
        $messages = $result['data'] ?? [];
        
        // El primer mensaje es la respuesta del Assistant (orden inverso)
        foreach ($messages as $msg) {
            if ($msg['role'] === 'assistant') {
                $content = $msg['content'][0]['text']['value'] ?? '';
                
                // Extraer JSON del contenido
                $jsonData = $this->extractJSON($content);
                
                if (!$jsonData) {
                    $this->fail(502, 'No se pudo extraer JSON válido de la respuesta del Assistant');
                }
                
                $this->mark('response.get.done');
                return $jsonData;
            }
        }
        
        $this->fail(502, 'No se encontró respuesta del Assistant');
    }
    
    private function extractJSON(string $content): ?array
    {
        // Buscar JSON en el contenido (puede estar entre ```json o directamente)
        $content = trim($content);
        
        // Remover markdown code blocks si existen
        if (preg_match('/```json\s*(\{.*?\})\s*```/s', $content, $matches)) {
            $jsonStr = $matches[1];
        } elseif (preg_match('/```\s*(\{.*?\})\s*```/s', $content, $matches)) {
            $jsonStr = $matches[1];
        } elseif (preg_match('/(\{.*?\})/s', $content, $matches)) {
            $jsonStr = $matches[1];
        } else {
            return null;
        }
        
        $jsonData = json_decode($jsonStr, true);
        
        if (!is_array($jsonData)) {
            return null;
        }
        
        // Validar que tenga las claves requeridas de F2A
        $requiredKeys = ['file_id', 'nombre_archivo', 'idiomas_presentes'];
        foreach ($requiredKeys as $key) {
            if (!array_key_exists($key, $jsonData)) {
                return null;
            }
        }
        
        // Validar que tenga los nuevos campos de F2D
        $newKeys = ['ficha_tecnica', 'resumen_tecnico'];
        $hasNewKey = false;
        foreach ($newKeys as $key) {
            if (array_key_exists($key, $jsonData)) {
                $hasNewKey = true;
                break;
            }
        }
        
        if (!$hasNewKey) {
            return null;
        }
        
        return $jsonData;
    }
    
    private function saveResults(array $jsonResult): void
    {
        $docsDir = $this->config['docs_dir'] ?? '';
        if (!$docsDir) return;
        
        $docDir = $docsDir . DIRECTORY_SEPARATOR . $this->docBasename;
        if (!is_dir($docDir)) {
            @mkdir($docDir, 0755, true);
        }
        
        // Guardar JSON final (sobrescribe el de F2C)
        $jsonPath = $docDir . DIRECTORY_SEPARATOR . $this->docBasename . '.json';
        file_put_contents($jsonPath, json_encode($jsonResult, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        
        // Guardar log completo
        $logPath = $docDir . DIRECTORY_SEPARATOR . $this->docBasename . '_2D.log';
        $logData = [
            'timestamp' => date('Y-m-d H:i:s'),
            'phase' => '2D',
            'status' => 'SUCCESS',
            'doc_basename' => $this->docBasename,
            'file_id' => $this->fileId,
            'assistant_id' => $this->assistantId,
            'json_previo' => $this->jsonPrevio,
            'json_result' => $jsonResult,
            'timeline' => $this->timeline,
            'debug_http' => $this->debugHttp
        ];
        
        file_put_contents($logPath, json_encode($logData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        
        $this->mark('files.saved');
    }
    
    private function getFileidPath(): string
    {
        $docsDir = $this->config['docs_dir'] ?? '';
        return $docsDir . DIRECTORY_SEPARATOR . $this->docBasename . DIRECTORY_SEPARATOR . $this->docBasename . '.fileid';
    }
    
    private function getJsonPath(): string
    {
        $docsDir = $this->config['docs_dir'] ?? '';
        return $docsDir . DIRECTORY_SEPARATOR . $this->docBasename . DIRECTORY_SEPARATOR . $this->docBasename . '.json';
    }
    
    private function getAssistantIdPath(): string
    {
        $docsDir = $this->config['docs_dir'] ?? '';
        return $docsDir . DIRECTORY_SEPARATOR . $this->docBasename . DIRECTORY_SEPARATOR . $this->docBasename . '_2D.assistant_id';
    }
    
    private function getFilePaths(): array
    {
        $docsDir = $this->config['docs_dir'] ?? '';
        $docDir = $docsDir . DIRECTORY_SEPARATOR . $this->docBasename;
        
        return [
            'json_file' => $docDir . DIRECTORY_SEPARATOR . $this->docBasename . '.json',
            'log_file' => $docDir . DIRECTORY_SEPARATOR . $this->docBasename . '_2D.log',
            'assistant_id_file' => $docDir . DIRECTORY_SEPARATOR . $this->docBasename . '_2D.assistant_id'
        ];
    }
    
    private function fail(int $code, string $msg): never
    {
        http_response_code($code);
        echo json_encode([
            'output' => ['tex' => "\xEF\xBB\xBF"],
            'debug' => ['http' => $this->debugHttp, 'error' => $msg],
            'timeline' => $this->timeline,
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
}

// ========== EJECUCIÓN ==========

$input = json_decode(file_get_contents('php://input'), true) ?? [];
$docBasename = $input['doc_basename'] ?? '';

if (!$docBasename) {
    http_response_code(400);
    echo json_encode(['error' => 'doc_basename requerido']);
    exit;
}

$proxy = new Phase2DProxy($docBasename, $input);
$result = $proxy->execute($input);

echo json_encode($result, JSON_UNESCAPED_UNICODE);

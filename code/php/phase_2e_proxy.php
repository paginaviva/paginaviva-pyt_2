<?php
/**
 * phase_2e_proxy.php
 * Proxy para Fase 2E: Auditoría y verificación final del JSON usando Assistants API
 * 
 * Propósito: Validar, corrigir y refinar el JSON-F2D completo contra el documento original
 * NO añade nuevos campos, solo verifica y optimiza los existentes (24 campos)
 */

require_once __DIR__ . '/lib_apio.php';

header('Content-Type: application/json; charset=utf-8');

class Phase2EProxy
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
        
        // 2. EJECUCIÓN: Crear/reutilizar Assistant y ejecutar auditoría
        $this->mark('execution.start');
        $jsonResult = $this->runAssistantAudit();
        $this->mark('execution.done');
        
        // 3. POST-EJECUCIÓN: Guardar JSON-FINAL verificado y log
        $this->mark('post_execution.start');
        $this->saveResults($jsonResult);
        $this->mark('post_execution.done');
        
        return [
            'output' => [
                'tex' => "\xEF\xBB\xBFAuditoría y verificación completada",
                'json_data' => $jsonResult,
                'json_previo' => $this->jsonPrevio  // Para comparación en frontend
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
        
        // Verificar que existe el JSON de F2D (24 campos)
        $jsonFile = $this->getJsonFilePath();
        if (!file_exists($jsonFile)) {
            $this->fail(400, 'Debe completar Fase 2D primero. No se encontró: ' . basename($jsonFile));
        }
        
        // Leer JSON previo (F2D)
        $jsonContent = file_get_contents($jsonFile);
        $this->jsonPrevio = json_decode($jsonContent, true);
        
        if (!$this->jsonPrevio || !is_array($this->jsonPrevio)) {
            $this->fail(400, 'El archivo JSON de F2D está corrupto o vacío');
        }
        
        // Verificar que tiene 24 campos (JSON-F2D completo)
        $expectedFields = 24;
        $actualFields = count($this->jsonPrevio);
        if ($actualFields < $expectedFields) {
            $this->fail(400, "El JSON debe tener al menos {$expectedFields} campos. Tiene: {$actualFields}. Complete F2D primero.");
        }
        
        $this->mark('validation.done');
    }
    
    private function runAssistantAudit(): array
    {
        $apiKey = $this->config['apio_key'] ?? '';
        if (!$apiKey) {
            $this->fail(500, 'API key de OpenAI no configurada');
        }
        
        // Obtener modelo (gpt-4o por defecto, configurable)
        $model = $this->input['model'] ?? 'gpt-4o';
        
        // 1. Obtener o crear Assistant
        $assistantId = $this->getOrCreateAssistant($apiKey, $model);
        
        // 2. Crear Thread
        $threadId = $this->createThread($apiKey);
        
        // 3. Agregar mensaje con file_id y JSON previo
        $this->addMessage($apiKey, $threadId);
        
        // 4. Crear Run
        $runId = $this->createRun($apiKey, $threadId, $assistantId);
        
        // 5. Polling del Run
        $this->pollRun($apiKey, $threadId, $runId);
        
        // 6. Obtener mensajes del thread
        $messages = $this->getMessages($apiKey, $threadId);
        
        // 7. Extraer JSON de la respuesta
        $jsonResult = $this->extractJSON($messages);
        
        return $jsonResult;
    }
    
    private function getOrCreateAssistant(string $apiKey, string $model): string
    {
        $this->mark('assistant.check.start');
        
        // F2E NO persiste assistant_id (usa siempre FILE_ID fresco)
        // Crear nuevo assistant cada vez para garantizar auditoría limpia
        
        $this->mark('assistant.create.start');
        
        global $PROMPTS;
        $instructions = $PROMPTS[2]['p_audit_final_verification']['prompt'] ?? '';
        
        if (empty($instructions)) {
            $this->fail(500, 'Prompt p_audit_final_verification no encontrado en prompts.php');
        }
        
        // Reemplazar placeholders (se harán en el mensaje, pero incluir referencia)
        $instructions = str_replace('{FILE_ID}', 'the file_id provided in the message', $instructions);
        $instructions = str_replace('{JSON_PREVIO}', 'the JSON-F2D provided in the message', $instructions);
        
        $payload = [
            'model' => $model,  // gpt-4o (soporta code_interpreter)
            'name' => 'Technical Documentation Auditor',
            'description' => 'Audits and verifies JSON against original Cofem documents',
            'instructions' => $instructions,
            'tools' => [
                ['type' => 'code_interpreter']  // code_interpreter (consistencia con F2C/F2D)
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
            CURLOPT_TIMEOUT => 30,
        ]);
        
        $t0 = microtime(true);
        $resp = curl_exec($ch);
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_error($ch);
        curl_close($ch);
        $t1 = microtime(true);
        
        $this->debugHttp[] = [
            'stage' => 'assistant.create',
            'endpoint' => 'https://api.openai.com/v1/assistants',
            'method' => 'POST',
            'status_code' => $status,
            'ms' => (int) round(($t1 - $t0) * 1000),
            'model' => $model
        ];
        
        if ($status < 200 || $status >= 300) {
            $this->fail(502, 'Error al crear Assistant: HTTP ' . $status . ' - ' . ($err ?: $resp));
        }
        
        $result = json_decode($resp, true);
        $assistantId = $result['id'] ?? null;
        
        if (!$assistantId) {
            $this->fail(502, 'OpenAI Assistants API no devolvió assistant_id válido');
        }
        
        $this->assistantId = $assistantId;
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
            CURLOPT_TIMEOUT => 30,
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
        
        if ($status !== 200) {
            $this->fail(502, 'Error al crear Thread');
        }
        
        $result = json_decode($resp, true);
        $threadId = $result['id'] ?? null;
        
        if (!$threadId) {
            $this->fail(502, 'No se recibió thread_id');
        }
        
        $this->mark('thread.create.done');
        return $threadId;
    }
    
    private function addMessage(string $apiKey, string $threadId): void
    {
        $this->mark('message.add.start');
        
        global $PROMPTS;
        $promptTemplate = $PROMPTS[2]['p_audit_final_verification']['prompt'] ?? '';
        
        // Reemplazar placeholders
        $prompt = str_replace('{FILE_ID}', $this->fileId, $promptTemplate);
        $prompt = str_replace('{JSON_PREVIO}', json_encode($this->jsonPrevio, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), $prompt);
        
        $payload = [
            'role' => 'user',
            'content' => $prompt,
            'attachments' => [
                [
                    'file_id' => $this->fileId,  // Solo FILE_ID del documento original
                    'tools' => [['type' => 'code_interpreter']]  // Obligatorio en API v2
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
            CURLOPT_TIMEOUT => 30,
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
            'attachments_count' => 1
        ];
        
        if ($status !== 200) {
            $this->fail(502, 'Error al agregar mensaje con attachment');
        }
        
        $this->mark('message.add.done');
    }
    
    private function createRun(string $apiKey, string $threadId, string $assistantId): string
    {
        $this->mark('run.create.start');
        
        $payload = [
            'assistant_id' => $assistantId
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
            CURLOPT_TIMEOUT => 30,
        ]);
        
        $t0 = microtime(true);
        $resp = curl_exec($ch);
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        $t1 = microtime(true);
        
        $this->debugHttp[] = [
            'stage' => 'run.create',
            'status_code' => $status,
            'ms' => (int) round(($t1 - $t0) * 1000)
        ];
        
        if ($status !== 200) {
            $this->fail(502, 'Error al crear Run');
        }
        
        $result = json_decode($resp, true);
        $runId = $result['id'] ?? null;
        
        if (!$runId) {
            $this->fail(502, 'No se recibió run_id');
        }
        
        $this->mark('run.create.done');
        return $runId;
    }
    
    private function pollRun(string $apiKey, string $threadId, string $runId): void
    {
        $this->mark('polling.start');
        
        $maxAttempts = 30;  // 30 × 2s = 60s timeout
        $interval = 2;
        
        sleep(3);  // Sleep inicial
        
        for ($i = 0; $i < $maxAttempts; $i++) {
            $ch = curl_init("https://api.openai.com/v1/threads/{$threadId}/runs/{$runId}");
            curl_setopt_array($ch, [
                CURLOPT_HTTPHEADER => [
                    'Authorization: Bearer ' . $apiKey,
                    'OpenAI-Beta: assistants=v2'
                ],
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 30,
            ]);
            
            $resp = curl_exec($ch);
            $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            if ($status !== 200) {
                $this->fail(502, 'Error al consultar run status');
            }
            
            $run = json_decode($resp, true);
            $runStatus = $run['status'] ?? 'unknown';
            
            $this->mark("polling.attempt.{$i}.{$runStatus}");
            
            if ($runStatus === 'completed') {
                $this->mark('polling.completed');
                return;
            }
            
            if (in_array($runStatus, ['failed', 'expired', 'cancelled', 'incomplete'])) {
                $lastError = $run['last_error']['message'] ?? 'Unknown error';
                $this->fail(502, "Run failed with status '{$runStatus}': {$lastError}");
            }
            
            sleep($interval);
        }
        
        $this->fail(504, 'Polling timeout excedido (60s)');
    }
    
    private function getMessages(string $apiKey, string $threadId): array
    {
        $this->mark('messages.list.start');
        
        $ch = curl_init("https://api.openai.com/v1/threads/{$threadId}/messages");
        curl_setopt_array($ch, [
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $apiKey,
                'OpenAI-Beta: assistants=v2'
            ],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
        ]);
        
        $t0 = microtime(true);
        $resp = curl_exec($ch);
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        $t1 = microtime(true);
        
        $this->debugHttp[] = [
            'stage' => 'messages.list',
            'status_code' => $status,
            'ms' => (int) round(($t1 - $t0) * 1000)
        ];
        
        if ($status !== 200) {
            $this->fail(502, 'Error al obtener mensajes del thread');
        }
        
        $result = json_decode($resp, true);
        $messages = $result['data'] ?? [];
        
        $this->mark('messages.list.done');
        return $messages;
    }
    
    private function extractJSON(array $messages): array
    {
        $this->mark('json.extract.start');
        
        // Buscar el último mensaje del assistant
        foreach ($messages as $msg) {
            if (($msg['role'] ?? '') === 'assistant') {
                $content = $msg['content'][0]['text']['value'] ?? '';
                
                // Intentar extraer JSON del contenido
                $jsonText = '';
                
                // Intento 1: JSON entre triple backticks con etiqueta json
                if (preg_match('/```json\s*(\{.*?\})\s*```/s', $content, $matches)) {
                    $jsonText = $matches[1];
                }
                // Intento 2: JSON entre triple backticks sin etiqueta
                elseif (preg_match('/```\s*(\{.*?\})\s*```/s', $content, $matches)) {
                    $jsonText = $matches[1];
                }
                // Intento 3: JSON directo (sin backticks)
                elseif (preg_match('/(\{.*\})/s', $content, $matches)) {
                    $jsonText = $matches[1];
                }
                else {
                    $this->fail(502, 'No se encontró JSON en la respuesta del assistant');
                }
                
                $jsonData = json_decode($jsonText, true);
                
                if (!$jsonData || !is_array($jsonData)) {
                    $this->fail(502, 'JSON inválido recibido del assistant: ' . json_last_error_msg());
                }
                
                // Validar que tiene 24 campos (JSON-FINAL completo)
                $requiredFields = [
                    'file_id', 'nombre_archivo', 'nombre_producto', 'codigo_referencia_cofem',
                    'tipo_documento', 'tipo_informacion_contenida', 'fecha_emision_revision',
                    'idiomas_presentes', 'normas_detectadas', 'certificaciones_detectadas',
                    'manuales_relacionados', 'otros_productos_relacionados', 'accesorios_relacionados',
                    'uso_formacion_tecnicos', 'razon_uso_formacion',
                    'codigo_encontrado', 'nombre_encontrado', 'familia_catalogo', 'nivel_confianza_identificacion',
                    'grupos_de_soluciones', 'familia', 'categoria', 'incidencias_taxonomia',
                    'ficha_tecnica', 'resumen_tecnico'
                ];
                
                foreach ($requiredFields as $field) {
                    if (!array_key_exists($field, $jsonData)) {
                        $this->fail(502, "Campo requerido faltante en JSON-FINAL: {$field}");
                    }
                }
                
                $this->mark('json.validated');
                return $jsonData;
            }
        }
        
        $this->fail(502, 'No se encontró mensaje del assistant en el thread');
    }
    
    private function saveResults(array $jsonData): void
    {
        $docsDir = $this->config['docs_dir'] ?? '';
        if (!$docsDir) {
            $this->fail(500, 'docs_dir no configurado');
        }
        
        $docDir = $docsDir . DIRECTORY_SEPARATOR . $this->docBasename;
        if (!is_dir($docDir)) {
            @mkdir($docDir, 0755, true);
        }
        
        // Guardar JSON-FINAL (sobrescribe el de F2D)
        $jsonPath = $docDir . DIRECTORY_SEPARATOR . $this->docBasename . '.json';
        $savedJson = file_put_contents(
            $jsonPath,
            json_encode($jsonData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
        );
        
        // Guardar log específico de F2E
        $logPath = $docDir . DIRECTORY_SEPARATOR . $this->docBasename . '_2E.log';
        $logData = [
            'timestamp' => date('Y-m-d H:i:s'),
            'phase' => '2E',
            'status' => 'SUCCESS',
            'doc_basename' => $this->docBasename,
            'file_id' => $this->fileId,
            'assistant_id' => $this->assistantId,
            'model' => $this->input['model'] ?? 'gpt-4o',
            'fields_count' => count($jsonData),
            'timeline' => $this->timeline,
            'debug_http' => $this->debugHttp,
            'json_final_saved' => $savedJson !== false
        ];
        
        file_put_contents($logPath, json_encode($logData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        
        $this->mark('files.saved');
    }
    
    private function getFilePaths(): array
    {
        $docsDir = $this->config['docs_dir'] ?? '';
        $docDir = $docsDir . DIRECTORY_SEPARATOR . $this->docBasename;
        
        return [
            'json_file' => $docDir . DIRECTORY_SEPARATOR . $this->docBasename . '.json',
            'log_file' => $docDir . DIRECTORY_SEPARATOR . $this->docBasename . '_2E.log'
        ];
    }
    
    private function getFileidPath(): string
    {
        $docsDir = $this->config['docs_dir'] ?? '';
        return $docsDir . DIRECTORY_SEPARATOR . $this->docBasename . DIRECTORY_SEPARATOR . $this->docBasename . '.fileid';
    }
    
    private function getJsonFilePath(): string
    {
        $docsDir = $this->config['docs_dir'] ?? '';
        return $docsDir . DIRECTORY_SEPARATOR . $this->docBasename . DIRECTORY_SEPARATOR . $this->docBasename . '.json';
    }
    
    private function fail(int $code, string $message): void
    {
        http_response_code($code);
        echo json_encode([
            'error' => $message,
            'debug' => [
                'http' => $this->debugHttp,
                'error' => $message
            ],
            'timeline' => $this->timeline
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        exit;
    }
}

// ======= EJECUCIÓN =======
try {
    $input = json_decode(file_get_contents('php://input'), true) ?? [];
    $docBasename = $input['doc_basename'] ?? '';
    
    if (!$docBasename) {
        http_response_code(400);
        echo json_encode(['error' => 'doc_basename requerido en el payload']);
        exit;
    }
    
    $proxy = new Phase2EProxy($docBasename, $input);
    $result = $proxy->execute($input);
    
    echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'error' => 'Error interno: ' . $e->getMessage(),
        'debug' => ['exception' => $e->getTraceAsString()]
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
}

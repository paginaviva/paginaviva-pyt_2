<?php
/**
 * phase_base.php
 * Clase base abstracta para todas las fases del sistema
 * Proporciona estructura común: Pre-ejecución, Ejecución, Post-ejecución
 */

declare(strict_types=1);

abstract class PhaseBase
{
    protected array $timeline = [];
    protected array $debugHttp = [];
    protected string $docBasename;
    protected string $phaseName;
    protected array $config;
    
    public function __construct(string $docBasename, string $phaseName)
    {
        $this->docBasename = $docBasename;
        $this->phaseName = $phaseName;
        
        require_once __DIR__ . '/lib_apio.php';
        $this->config = apio_load_config();
        
        $this->mark('init');
    }
    
    // ========== PRE-EJECUCIÓN (común) ==========
    
    protected function mark(string $stage): void
    {
        $this->timeline[] = [
            'ts' => (int) floor(microtime(true) * 1000),
            'stage' => $stage
        ];
    }
    
    protected function validateCommonInput(array $input): void
    {
        if (empty($this->docBasename)) {
            $this->fail(400, 'doc_basename requerido');
        }
    }
    
    // ========== MÉTODOS ABSTRACTOS (específicos por fase) ==========
    
    /**
     * Cada fase define su prompt específico
     */
    abstract protected function getPhasePrompt(array $input): string;
    
    /**
     * Cada fase define validaciones específicas adicionales
     */
    abstract protected function validatePhaseInput(array $input): void;
    
    /**
     * Cada fase puede transformar el input antes de enviarlo a OpenAI
     */
    abstract protected function prepareOpenAIPayload(array $input, string $prompt): array;
    
    /**
     * Cada fase puede procesar el resultado de OpenAI
     */
    abstract protected function processOpenAIResult(string $rawText): string;
    
    // ========== EJECUCIÓN (común) ==========
    
    public function execute(array $input): array
    {
        $this->mark('pre_execution.start');
        
        // 1. Validaciones comunes y específicas
        $this->validateCommonInput($input);
        $this->validatePhaseInput($input);
        
        $this->mark('pre_execution.done');
        $this->mark('execution.start');
        
        // 2. Preparar prompt y payload
        $prompt = $this->getPhasePrompt($input);
        $payload = $this->prepareOpenAIPayload($input, $prompt);
        
        // 3. Llamar OpenAI API
        $apiKey = $this->config['apio_key'] ?? '';
        $result = $this->callOpenAI($payload, $apiKey);
        
        $this->mark('execution.done');
        $this->mark('post_execution.start');
        
        // 4. Procesar resultado específico de la fase
        $processedText = $this->processOpenAIResult($result['text']);
        
        // 5. Guardar archivos (.txt y .log)
        $this->saveFiles($processedText, $result);
        
        $this->mark('post_execution.done');
        
        return [
            'output' => ['tex' => "\xEF\xBB\xBF" . $processedText],
            'debug' => ['http' => $this->debugHttp],
            'timeline' => $this->timeline,
            'files_saved' => $this->getFilePaths()
        ];
    }
    
    // ========== POST-EJECUCIÓN (común) ==========
    
    protected function saveFiles(string $text, array $metadata): void
    {
        $docsDir = $this->config['docs_dir'] ?? '';
        if (!$docsDir) return;
        
        $docDir = $docsDir . DIRECTORY_SEPARATOR . $this->docBasename;
        if (!is_dir($docDir)) {
            @mkdir($docDir, 0755, true);
        }
        
        // Guardar .txt con nombre de fase
        $txtPath = $docDir . DIRECTORY_SEPARATOR . $this->docBasename . '_' . $this->phaseName . '.txt';
        file_put_contents($txtPath, $text);
        
        // Guardar .log con información completa
        $logPath = $docDir . DIRECTORY_SEPARATOR . $this->docBasename . '_' . $this->phaseName . '.log';
        $logData = [
            'timestamp' => date('Y-m-d H:i:s'),
            'phase' => $this->phaseName,
            'status' => 'SUCCESS',
            'doc_basename' => $this->docBasename,
            'timeline' => $this->timeline,
            'debug_http' => $this->debugHttp,
            'metadata' => $metadata
        ];
        
        file_put_contents($logPath, json_encode($logData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        
        $this->mark('files.saved');
    }
    
    protected function getFilePaths(): array
    {
        $docsDir = $this->config['docs_dir'] ?? '';
        $docDir = $docsDir . DIRECTORY_SEPARATOR . $this->docBasename;
        
        return [
            'txt_file' => $docDir . DIRECTORY_SEPARATOR . $this->docBasename . '_' . $this->phaseName . '.txt',
            'log_file' => $docDir . DIRECTORY_SEPARATOR . $this->docBasename . '_' . $this->phaseName . '.log'
        ];
    }
    
    protected function callOpenAI(array $payload, string $apiKey): array
    {
        $this->mark('openai.request.start');
        
        $ch = curl_init('https://api.openai.com/v1/chat/completions');
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $apiKey,
                'Content-Type: application/json',
            ],
            CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 120,
        ]);
        
        $t0 = microtime(true);
        $resp = curl_exec($ch);
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        $t1 = microtime(true);
        
        $this->debugHttp[] = [
            'stage' => 'openai_request',
            'status_code' => $status,
            'ms' => (int) round(($t1 - $t0) * 1000),
            'payload_preview' => $payload
        ];
        
        if ($status < 200 || $status >= 300) {
            $this->fail(502, 'OpenAI API error: HTTP ' . $status);
        }
        
        $result = json_decode($resp, true);
        $text = $result['choices'][0]['message']['content'] ?? '';
        
        $this->mark('openai.request.done');
        
        return [
            'text' => $text,
            'raw_response' => $result,
            'usage' => $result['usage'] ?? null
        ];
    }
    
    protected function fail(int $code, string $msg): never
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

<?php
/**
 * phase_1c_proxy.php
 * Proxy específico para Fase 1C: Subir archivo .txt a OpenAI Files API
 * 
 * Propósito: Tomar el archivo .txt generado en F1B y subirlo a OpenAI
 * para obtener un file_id que se usará en fases posteriores
 */

declare(strict_types=1);

// F1C NO usa phase_base porque no llama a Chat Completions
// sino a Files API, por lo que tiene su propia lógica

require_once __DIR__ . '/lib_apio.php';

header('Content-Type: application/json; charset=utf-8');

class Phase1CProxy
{
    private array $timeline = [];
    private array $debugHttp = [];
    private string $docBasename;
    private array $config;
    
    public function __construct(string $docBasename)
    {
        $this->docBasename = $docBasename;
        $this->config = apio_load_config();
        $this->mark('init');
    }
    
    private function mark(string $stage): void
    {
        $this->timeline[] = [
            'ts' => (int) floor(microtime(true) * 1000),
            'stage' => $stage
        ];
    }
    
    public function execute(array $input): array
    {
        // 1. PRE-EJECUCIÓN: Validar entrada
        $this->mark('pre_execution.start');
        $this->validateInput();
        $this->mark('pre_execution.done');
        
        // 2. EJECUCIÓN: Subir archivo a OpenAI Files API
        $this->mark('execution.start');
        $fileId = $this->uploadToOpenAI();
        $this->mark('execution.done');
        
        // 3. POST-EJECUCIÓN: Guardar file_id y log
        $this->mark('post_execution.start');
        $this->saveFileId($fileId);
        $this->mark('post_execution.done');
        
        return [
            'output' => [
                'tex' => "\xEF\xBB\xBFArchivo subido exitosamente a OpenAI",
                'file_id' => $fileId
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
        
        // Verificar que existe el archivo .txt de F1B
        $txtFile = $this->getTxtFilePath();
        if (!file_exists($txtFile)) {
            $this->fail(400, 'Debe completar Fase 1B primero. No se encontró: ' . basename($txtFile));
        }
        
        // Verificar tamaño del archivo (límite de OpenAI: 512 MB, pero validamos 20MB para eficiencia)
        $fileSize = filesize($txtFile);
        if ($fileSize > 20 * 1024 * 1024) {
            $this->fail(413, 'El archivo .txt excede el tamaño permitido (20 MB)');
        }
        
        $this->mark('validation.done');
    }
    
    private function uploadToOpenAI(): string
    {
        $apiKey = $this->config['apio_key'] ?? '';
        if (!$apiKey) {
            $this->fail(500, 'API key de OpenAI no configurada');
        }
        
        $txtFile = $this->getTxtFilePath();
        
        $this->mark('files.upload.start');
        
        // Crear CURLFile para subida multipart/form-data
        $ch = curl_init('https://api.openai.com/v1/files');
        $postFields = [
            'purpose' => 'assistants', // Propósito requerido por OpenAI
            'file' => new CURLFile($txtFile, 'text/plain', basename($txtFile)),
        ];
        
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $postFields,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $apiKey
            ],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 60,
        ]);
        
        $t0 = microtime(true);
        $resp = curl_exec($ch);
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_error($ch);
        curl_close($ch);
        $t1 = microtime(true);
        
        // Guardar debug
        $this->debugHttp[] = [
            'stage' => 'files.upload',
            'status_code' => $status,
            'ms' => (int) round(($t1 - $t0) * 1000),
            'endpoint' => 'https://api.openai.com/v1/files',
            'method' => 'POST',
            'headers' => ['Authorization' => 'Bearer ***', 'Content-Type' => 'multipart/form-data'],
            'file_info' => [
                'filename' => basename($txtFile),
                'size' => filesize($txtFile),
                'purpose' => 'assistants'
            ]
        ];
        
        if ($resp === false || $status < 200 || $status >= 300) {
            $this->fail(502, 'Error al subir archivo a OpenAI: ' . ($err ?: ('HTTP ' . $status . ' - ' . $resp)));
        }
        
        $result = json_decode($resp, true);
        $fileId = $result['id'] ?? null;
        
        if (!$fileId) {
            $this->fail(502, 'OpenAI Files API no devolvió file_id válido. Respuesta: ' . $resp);
        }
        
        // Guardar respuesta completa para debug
        $this->debugHttp[] = [
            'stage' => 'files.upload.response',
            'status_code' => 200,
            'file_id' => $fileId,
            'raw_response' => $result
        ];
        
        $this->mark('files.upload.done');
        
        return $fileId;
    }
    
    private function saveFileId(string $fileId): void
    {
        $docsDir = $this->config['docs_dir'] ?? '';
        if (!$docsDir) return;
        
        $docDir = $docsDir . DIRECTORY_SEPARATOR . $this->docBasename;
        if (!is_dir($docDir)) {
            @mkdir($docDir, 0755, true);
        }
        
        // Guardar file_id en un archivo .fileid
        $fileIdPath = $docDir . DIRECTORY_SEPARATOR . $this->docBasename . '_1C.fileid';
        file_put_contents($fileIdPath, $fileId);
        
        // Guardar log completo
        $logPath = $docDir . DIRECTORY_SEPARATOR . $this->docBasename . '_1C.log';
        $logData = [
            'timestamp' => date('Y-m-d H:i:s'),
            'phase' => '1C',
            'status' => 'SUCCESS',
            'doc_basename' => $this->docBasename,
            'file_id' => $fileId,
            'source_file' => $this->getTxtFilePath(),
            'timeline' => $this->timeline,
            'debug_http' => $this->debugHttp
        ];
        
        file_put_contents($logPath, json_encode($logData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        
        $this->mark('files.saved');
    }
    
    private function getTxtFilePath(): string
    {
        $docsDir = $this->config['docs_dir'] ?? '';
        return $docsDir . DIRECTORY_SEPARATOR . $this->docBasename . DIRECTORY_SEPARATOR . $this->docBasename . '_1B.txt';
    }
    
    private function getFilePaths(): array
    {
        $docsDir = $this->config['docs_dir'] ?? '';
        $docDir = $docsDir . DIRECTORY_SEPARATOR . $this->docBasename;
        
        return [
            'fileid_file' => $docDir . DIRECTORY_SEPARATOR . $this->docBasename . '_1C.fileid',
            'log_file' => $docDir . DIRECTORY_SEPARATOR . $this->docBasename . '_1C.log'
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

$proxy = new Phase1CProxy($docBasename);
$result = $proxy->execute($input);

echo json_encode($result, JSON_UNESCAPED_UNICODE);

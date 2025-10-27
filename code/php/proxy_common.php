<?php
/**
 * proxy_common.php
 * Utilidades comunes para proxys de fases.
 *
 * Requisitos: PHP 8.1+, cURL.
 * Salida estandarizada: { output: { tex }, debug: { http: [] }, timeline: [] }
 */

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

final class ProxyRuntime
{
    public array $timeline = [];
    public array $debugHttp = [];
    public string $apiKey;

    public function __construct()
    {
        $this->apiKey = getenv('OPENAI_API_KEY') ?: '';
        $this->mark('init');
        if ($this->apiKey === '') {
            $this->fail(500, 'Falta OPENAI_API_KEY en el entorno.');
        }
    }

    /* =================== PRE-EJECUCIÓN =================== */

    public function mark(string $stage): void
    {
        $this->timeline[] = [
            'ts' => (int) floor(microtime(true) * 1000),
            'stage' => $stage
        ];
    }

    public function readInput(): array
    {
        $raw = file_get_contents('php://input');
        $ct  = $_SERVER['CONTENT_TYPE'] ?? '';
        if (stripos($ct, 'application/json') !== false) {
            $in = json_decode($raw ?: '[]', true) ?? [];
        } else {
            $in = $_POST ?: [];
        }
        $this->mark('input.read');
        return $in;
    }

    public function validateUrl(string $url, array $allowedHosts = []): void
    {
        if ($url === '' || !filter_var($url, FILTER_VALIDATE_URL)) {
            $this->fail(400, 'URL no válida.');
        }
        if ($allowedHosts) {
            $host = parse_url($url, PHP_URL_HOST) ?: '';
            if (!in_array($host, $allowedHosts, true)) {
                $this->fail(400, 'Dominio no permitido.');
            }
        }
        $this->mark('input.validated');
    }

    public function fail(int $code, string $msg): never
    {
        http_response_code($code);
        echo json_encode([
            'output' => ['tex' => "\xEF\xBB\xBF"],
            'debug'  => ['http' => $this->debugHttp, 'error' => $msg],
            'timeline' => $this->timeline,
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    /* =================== EJECUCIÓN (UTILIDADES) =================== */

    public function fetchToTmp(string $url, string $accept = 'application/octet-stream', string $ext = ''): string
    {
        $this->mark('fetch.start');
        $path = tempnam(sys_get_temp_dir(), 'pxy_');
        if ($ext !== '') {
            $np = $path . '.' . ltrim($ext, '.');
            @rename($path, $np);
            $path = $np;
        }

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS      => 5,
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_HTTPHEADER     => ['Accept: ' . $accept],
        ]);
        $t0 = microtime(true);
        $bin = curl_exec($ch);
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_error($ch);
        curl_close($ch);
        $t1 = microtime(true);

        $this->debugHttp[] = [
            'stage' => 'fetch',
            'status_code' => $status,
            'ms' => (int) round(($t1 - $t0) * 1000),
            'headers' => ['accept' => $accept],
            'url' => $url,
        ];

        if ($bin === false || $status < 200 || $status >= 300) {
            @unlink($path);
            $this->fail(502, 'Fallo al descargar: ' . ($err ?: ('HTTP ' . $status)));
        }
        file_put_contents($path, $bin);
        $this->mark('fetch.done');
        return $path;
    }

    public function openaiUploadFile(string $localPath, string $mime = 'application/octet-stream', string $purpose = 'assistants'): string
    {
        $this->mark('files.upload.start');
        $ch = curl_init('https://api.openai.com/v1/files');
        $postFields = [
            'purpose' => $purpose,
            'file'    => new CURLFile($localPath, $mime, basename($localPath)),
        ];
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $postFields,
            CURLOPT_HTTPHEADER     => ['Authorization: Bearer ' . $this->apiKey],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 60,
        ]);
        $t0 = microtime(true);
        $resp = curl_exec($ch);
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_error($ch);
        curl_close($ch);
        $t1 = microtime(true);

        $this->debugHttp[] = [
            'stage' => 'files.create',
            'status_code' => $status,
            'ms' => (int) round(($t1 - $t0) * 1000),
            'headers' => ['Authorization' => 'Bearer ***', 'Content-Type' => 'multipart/form-data'],
            'payload_preview' => ['purpose' => $purpose, 'filename' => basename($localPath)],
        ];

        if ($resp === false || $status < 200 || $status >= 300) {
            $this->fail(502, 'Fallo en Files API: ' . ($err ?: ('HTTP ' . $status . ' ' . $resp)));
        }
        $j = json_decode($resp, true);
        $id = $j['id'] ?? null;
        if (!$id) {
            $this->fail(502, 'Files API no devolvió id válido.');
        }
        $this->mark('files.upload.done');
        return $id;
    }

    public function openaiResponses(array $payload): array
    {
        $this->mark('responses.create.start');
        $ch = curl_init('https://api.openai.com/v1/responses');
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_HTTPHEADER     => [
                'Authorization: Bearer ' . $this->apiKey,
                'Content-Type: application/json',
            ],
            CURLOPT_POSTFIELDS     => json_encode($payload, JSON_UNESCAPED_UNICODE),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 120,
        ]);
        $t0 = microtime(true);
        $resp = curl_exec($ch);
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_error($ch);
        curl_close($ch);
        $t1 = microtime(true);

        $this->debugHttp[] = [
            'stage' => 'responses.create',
            'status_code' => $status,
            'ms' => (int) round(($t1 - $t0) * 1000),
            'headers' => ['Authorization' => 'Bearer ***', 'Content-Type' => 'application/json'],
        ];

        if ($resp === false || $status < 200 || $status >= 300) {
            $this->fail(502, 'Fallo en Responses API: ' . ($err ?: ('HTTP ' . $status . ' ' . $resp)));
        }
        $j = json_decode($resp, true);
        if (!is_array($j)) {
            $this->fail(502, 'Responses API devolvió un cuerpo no JSON.');
        }
        $this->mark('responses.create.done');
        return $j;
    }

    public function extractOutputText(array $res): string
    {
        $txt = $res['output_text'] ?? '';
        if ($txt !== '') {
            return (string) $txt;
        }
        if (isset($res['output']) && is_array($res['output'])) {
            $buf = '';
            foreach ($res['output'] as $blk) {
                if (!empty($blk['content']) && is_array($blk['content'])) {
                    foreach ($blk['content'] as $c) {
                        if (($c['type'] ?? '') === 'output_text' && isset($c['text'])) {
                            $buf .= (string) $c['text'];
                        }
                    }
                }
            }
            return $buf;
        }
        return '';
    }

    /* =================== POST-EJECUCIÓN =================== */

    public function respondOk(string $text): never
    {
        $BOM = "\xEF\xBB\xBF";
        echo json_encode([
            'output'   => ['tex' => $BOM . $text],
            'debug'    => ['http' => $this->debugHttp],
            'timeline' => $this->timeline,
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
}

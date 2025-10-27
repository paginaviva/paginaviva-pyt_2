<?php
/**
 * phase1b_proxy.php
 * Proxy específico para la Fase 1B: extraer texto de un PDF.
 */

declare(strict_types=1);
require_once __DIR__ . '/proxy_common.php';

$rt = new ProxyRuntime();

/* ====== PRE-EJECUCIÓN: entrada y validación mínima ====== */
$in = $rt->readInput();

$pdfUrl      = trim((string) ($in['pdf_url'] ?? ''));
$model       = trim((string) ($in['model'] ?? 'gpt-4o-mini'));
$temperature = (float) ($in['temperature'] ?? 0.0);
$topP        = (float) ($in['top_p'] ?? 1.0);
$maxTokens   = (int)   ($in['max_tokens'] ?? 1500);
$docBasename = trim((string) ($in['doc_basename'] ?? '')); // Nuevo: NB del archivo

// Validar NB del archivo (requerido para guardar archivos)
if ($docBasename === '') {
    $rt->fail(400, 'doc_basename requerido para identificar el documento.');
}

// Lista blanca opcional. Ajusta dominios si procede.
$allowedHosts = []; // p. ej., ['tu-dominio.com']
$rt->validateUrl($pdfUrl, $allowedHosts);

/* ====== EJECUCIÓN: descargar → subir a Files → llamar Responses ====== */

// 1) Descargar PDF al servidor
$pdfPath = $rt->fetchToTmp($pdfUrl, 'application/pdf', 'pdf');
if (filesize($pdfPath) > 25 * 1024 * 1024) {
    @unlink($pdfPath);
    $rt->fail(413, 'El PDF supera el tamaño permitido (25 MB).');
}

// 2) Subir a OpenAI Files - 'assistants' es el único que acepta PDFs
$fileId = $rt->openaiUploadFile($pdfPath, 'application/pdf', 'assistants');
@unlink($pdfPath);

// 3) Prompt de extracción (tal como lo definiste)
$extractPrompt = <<<PROMPT
Read the contents of the provided PDF file.
Extract the raw textual content exactly as it appears in the document, without summarizing or interpreting.
Apply minimal Markdown formatting to preserve structural fidelity and readability.
Follow these rules strictly:
TEXT EXTRACTION:
- Extract only the actual text from the PDF; ignore all non-textual elements.
- Do not perform OCR.
- Preserve the original order, spacing, and line breaks.
FORMATTING RULES:
- Use Markdown formatting only when necessary to reflect the original layout.
- If a section of text represents a table, grid, or matrix, reproduce it as a Markdown table — keeping the exact row and column structure and cell contents.
- Maintain all bullet points, numbered lists, and headings in proper Markdown syntax if clearly present in the document.
- Where an image appears, insert the placeholder `[IMAGE]` in its original position.
INTEGRITY AND ENCODING:
- Do not rephrase, summarize, or interpret the text.
- Do not add or remove punctuation, characters, or symbols.
- Return the result as plain UTF-8 Markdown text.
- The output must contain only the extracted content — no metadata, no explanations, no commentary.
PROMPT;

// 4) Payload con estructura de objeto para 'file' según error de API
$payload = [
    'model' => $model,
    'max_completion_tokens' => 4000, // Aumentar límite significativamente
    'messages' => [
        [
            'role' => 'user',
            'content' => [
                [
                    'type' => 'text',
                    'text' => $extractPrompt
                ],
                [
                    'type' => 'file',
                    'file' => [
                        'file_id' => $fileId
                    ]
                ]
            ]
        ]
    ]
];

// 5) Llamada a Chat Completions y extracción del texto
$res = $rt->openaiChatCompletions($payload);
$txt = $rt->extractOutputText($res);

// 6) Guardar archivos .txt y .log en /docs/{docBasename}/
require_once __DIR__ . '/lib_apio.php';
$cfg = apio_load_config();
$docsDir = $cfg['docs_dir'] ?? '';

if ($docsDir && $docBasename && $txt !== '') {
    $docDir = $docsDir . DIRECTORY_SEPARATOR . $docBasename;
    
    // Crear directorio si no existe
    if (!is_dir($docDir)) {
        @mkdir($docDir, 0755, true);
    }
    
    // Guardar archivo .txt
    $txtPath = $docDir . DIRECTORY_SEPARATOR . $docBasename . '.txt';
    $savedTxt = file_put_contents($txtPath, $txt);
    
    // Crear archivo .log con información completa del proceso
    $logPath = $docDir . DIRECTORY_SEPARATOR . $docBasename . '.log';
    $logData = [
        'timestamp' => date('Y-m-d H:i:s'),
        'phase' => '1B',
        'status' => 'SUCCESS',
        'doc_basename' => $docBasename,
        'pdf_url' => $pdfUrl,
        'openai_params' => [
            'model' => $model,
            'temperature' => $temperature,
            'max_completion_tokens' => 4000,
            'top_p' => $topP
        ],
        'results' => [
            'txt_file' => $txtPath,
            'txt_size' => strlen($txt),
            'characters_extracted' => strlen($txt),
            'saved_successfully' => $savedTxt !== false
        ],
        'timeline' => $rt->timeline,
        'debug_http' => $rt->debugHttp,
        'openai_response_sample' => [
            'usage' => $res['usage'] ?? null,
            'model_used' => $res['model'] ?? $model,
            'finish_reason' => $res['choices'][0]['finish_reason'] ?? 'unknown'
        ]
    ];
    
    file_put_contents($logPath, json_encode($logData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    
    // Agregar información de archivos guardados al debug
    $rt->debugHttp[] = [
        'stage' => 'save_files',
        'status_code' => 200,
        'headers' => ['info' => 'files_saved'],
        'files_created' => [
            'txt_file' => $txtPath,
            'log_file' => $logPath,
            'txt_saved' => $savedTxt !== false,
            'log_saved' => is_file($logPath)
        ]
    ];
}

/* ====== POST-EJECUCIÓN: responder en formato estándar ====== */
$rt->mark('done');
$rt->respondOk($txt);

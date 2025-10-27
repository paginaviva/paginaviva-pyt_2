<?php
/**
 * phase1b_proxy.php
 * Proxy específico para la Fase 1B: extraer texto de un PDF.
 */

declare(strict_types=1);
require_once __DIR__ . '/proxy_common.php';

// Configurar API key desde config.json
require_once __DIR__ . '/lib_apio.php';
$cfg = apio_load_config();
putenv('OPENAI_API_KEY=' . ($cfg['apio_key'] ?? ''));

$rt = new ProxyRuntime();

/* ====== PRE-EJECUCIÓN: entrada y validación mínima ====== */
$in = $rt->readInput();

$pdfUrl      = trim((string) ($in['pdf_url'] ?? ''));
$model       = trim((string) ($in['model'] ?? 'gpt-4o-mini'));
$temperature = (float) ($in['temperature'] ?? 0.0);
$topP        = (float) ($in['top_p'] ?? 1.0);
$maxTokens   = (int)   ($in['max_tokens'] ?? 1500);

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

// 2) Subir a OpenAI Files
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

// 4) Payload de Responses para 1B
$payload = [
    'model' => $model,
    'temperature' => $temperature,
    'top_p' => $topP,
    'max_output_tokens' => $maxTokens,
    'input' => [[
        'role' => 'user',
        'content' => [
            ['type' => 'input_text', 'text' => $extractPrompt],
        ],
    ]],
    'tools' => [
        ['type' => 'file_search'],
    ],
    'attachments' => [
        [
            'file_id' => $fileId,
            'tools'   => [['type' => 'file_search']],
        ],
    ],
];

// 5) Llamada a Responses y extracción del texto
$res = $rt->openaiResponses($payload);
$txt = $rt->extractOutputText($res);

/* ====== POST-EJECUCIÓN: responder en formato estándar ====== */
$rt->mark('done');
$rt->respondOk($txt);

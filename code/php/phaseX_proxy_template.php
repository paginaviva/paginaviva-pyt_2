<?php
/**
 * phaseX_proxy.php - Plantilla para otras fases
 * Ejemplo de cómo crear proxies para diferentes fases reutilizando proxy_common.php
 */

declare(strict_types=1);
require_once __DIR__ . '/proxy_common.php';

// Configurar API key desde config.json
require_once __DIR__ . '/lib_apio.php';
$cfg = apio_load_config();
putenv('OPENAI_API_KEY=' . ($cfg['apio_key'] ?? ''));

$rt = new ProxyRuntime();

/* ====== PRE-EJECUCIÓN: entrada y validación específica ====== */
$in = $rt->readInput();

// Parámetros específicos de esta fase
$textContent = trim((string) ($in['text_content'] ?? ''));
$analysisType = trim((string) ($in['analysis_type'] ?? 'summary'));
$model = trim((string) ($in['model'] ?? 'gpt-4o-mini'));
$temperature = (float) ($in['temperature'] ?? 0.0);
$maxTokens = (int) ($in['max_tokens'] ?? 1500);

// Validaciones específicas
if ($textContent === '') {
    $rt->fail(400, 'Contenido de texto requerido.');
}

/* ====== EJECUCIÓN: lógica específica de la fase ====== */

// Ejemplo: preparar prompt específico según tipo de análisis
$prompts = [
    'summary' => 'Proporciona un resumen conciso del siguiente texto:',
    'keywords' => 'Extrae las palabras clave principales del siguiente texto:',
    'sentiment' => 'Analiza el sentimiento del siguiente texto:',
];

$prompt = $prompts[$analysisType] ?? $prompts['summary'];

// Payload específico para esta fase
$payload = [
    'model' => $model,
    'temperature' => $temperature,
    'max_output_tokens' => $maxTokens,
    'input' => [[
        'role' => 'user',
        'content' => [
            ['type' => 'input_text', 'text' => $prompt . "\n\n" . $textContent],
        ],
    ]],
    // Agregar herramientas específicas si se necesitan
    // 'tools' => [['type' => 'code_interpreter']],
];

// Llamada a OpenAI usando función común
$res = $rt->openaiResponses($payload);
$txt = $rt->extractOutputText($res);

/* ====== POST-EJECUCIÓN: responder en formato estándar ====== */
$rt->mark('done');
$rt->respondOk($txt);

/* 
NOTAS PARA OTRAS FASES:

1. FASE 2 (Análisis): 
   - Recibe texto extraído de F1B
   - Puede hacer análisis específicos (resumen, keywords, etc.)
   - Usar herramientas como code_interpreter si necesita cálculos

2. FASE 3 (Comparación):
   - Recibe múltiples textos
   - Puede usar file_search con vector stores
   - Comparar documentos usando embeddings

3. FASE N (Personalizada):
   - Definir parámetros específicos
   - Usar fetchToTmp() si necesita descargar recursos
   - Usar openaiUploadFile() si necesita subir archivos
   - Mantener mismo formato de respuesta estándar

PATRONES COMUNES:
- Siempre usar $rt->readInput() para entrada
- Validar parámetros específicos de la fase
- Usar funciones del proxy común
- Terminar con $rt->respondOk($result)
- Mantener formato JSON estándar: {output: {tex}, debug: {http}, timeline}
*/
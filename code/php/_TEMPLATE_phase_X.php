<?php
/**
 * _TEMPLATE_phase_X.php
 * PLANTILLA DE REFERENCIA para crear nuevas fases
 * 
 * INSTRUCCIONES:
 * 1. Copiar este archivo y renombrar a phase_[NUMERO]_proxy.php
 * 2. Completar los métodos abstractos con la lógica específica de la fase
 * 3. NO modificar la estructura base, solo implementar los métodos
 * 
 * EJEMPLO DE USO:
 * - Para crear Fase 1C: copiar a phase_1c_proxy.php
 * - Implementar getPhasePrompt() con el prompt de F1C
 * - Implementar validatePhaseInput() con validaciones específicas
 * - Implementar prepareOpenAIPayload() con estructura de datos de F1C
 * - Implementar processOpenAIResult() con transformaciones específicas
 * 
 * IMPORTANTE: Este archivo NO se ejecuta, es solo una guía de referencia
 */

declare(strict_types=1);

require_once __DIR__ . '/phase_base.php';

class PhaseXProxy extends PhaseBase
{
    public function __construct(string $docBasename)
    {
        // Cambiar 'X' por el número/letra de la fase (ejemplo: '1C', '2A', etc.)
        parent::__construct($docBasename, 'X');
    }
    
    /**
     * PASO 1: Definir el prompt específico de esta fase
     * 
     * @param array $input Parámetros de entrada del usuario
     * @return string Prompt completo para OpenAI
     */
    protected function getPhasePrompt(array $input): string
    {
        // IMPLEMENTAR: Retornar el prompt específico de esta fase
        return <<<PROMPT
[AQUÍ VA EL PROMPT DE LA FASE]
Ejemplo:
- Analiza el siguiente texto...
- Extrae los elementos clave...
- Resume en formato markdown...
PROMPT;
    }
    
    /**
     * PASO 2: Validaciones específicas de esta fase
     * 
     * @param array $input Parámetros de entrada
     * @throws Llama $this->fail() si hay error
     */
    protected function validatePhaseInput(array $input): void
    {
        // IMPLEMENTAR: Validaciones únicas de esta fase
        // Ejemplo: verificar que existan archivos de fases anteriores
        
        // Verificar que existe el archivo de la fase anterior
        // $previousFile = $this->config['docs_dir'] . '/' . $this->docBasename . '/' . $this->docBasename . '_1B.txt';
        // if (!file_exists($previousFile)) {
        //     $this->fail(400, 'Debe completar Fase 1B primero');
        // }
        
        // Validar parámetros específicos
        // if (empty($input['specific_param'])) {
        //     $this->fail(400, 'specific_param es requerido');
        // }
    }
    
    /**
     * PASO 3: Preparar payload para OpenAI API
     * 
     * @param array $input Parámetros de entrada
     * @param string $prompt Prompt generado
     * @return array Payload completo para OpenAI
     */
    protected function prepareOpenAIPayload(array $input, string $prompt): array
    {
        // IMPLEMENTAR: Estructura específica del payload
        // Puede incluir:
        // - Cargar archivos de fases anteriores
        // - Agregar contexto adicional
        // - Configurar herramientas específicas
        
        // Ejemplo: Cargar texto de fase anterior
        // $previousFile = $this->config['docs_dir'] . '/' . $this->docBasename . '/' . $this->docBasename . '_1B.txt';
        // $previousText = file_exists($previousFile) ? file_get_contents($previousFile) : '';
        
        return [
            'model' => $input['model'] ?? 'gpt-4o-mini',
            'messages' => [
                [
                    'role' => 'user',
                    'content' => $prompt
                    // Si necesitas agregar contexto:
                    // 'content' => $prompt . "\n\nTexto a procesar:\n" . $previousText
                ]
            ],
            'temperature' => floatval($input['temperature'] ?? 0.1),
            'max_tokens' => intval($input['max_tokens'] ?? 4000),
            'top_p' => floatval($input['top_p'] ?? 1.0)
            
            // Si la fase necesita herramientas específicas:
            // 'tools' => [
            //     ['type' => 'code_interpreter']
            // ]
        ];
    }
    
    /**
     * PASO 4: Procesar resultado de OpenAI
     * 
     * @param string $rawText Texto crudo de OpenAI
     * @return string Texto procesado final
     */
    protected function processOpenAIResult(string $rawText): string
    {
        // IMPLEMENTAR: Transformaciones específicas del resultado
        // Por defecto, retornar sin cambios
        // Puede incluir:
        // - Limpieza de formato
        // - Extracción de secciones específicas
        // - Validación de estructura
        // - Conversión de formato
        
        // Ejemplo: Remover marcadores o limpiar formato
        // $processed = str_replace('```markdown', '', $rawText);
        // $processed = str_replace('```', '', $processed);
        // return trim($processed);
        
        return $rawText;
    }
}

// ========== EJECUCIÓN ESTÁNDAR (NO MODIFICAR) ==========

header('Content-Type: application/json; charset=utf-8');

$input = json_decode(file_get_contents('php://input'), true) ?? [];
$docBasename = $input['doc_basename'] ?? '';

if (!$docBasename) {
    http_response_code(400);
    echo json_encode(['error' => 'doc_basename requerido']);
    exit;
}

$proxy = new PhaseXProxy($docBasename);
$result = $proxy->execute($input);

echo json_encode($result, JSON_UNESCAPED_UNICODE);


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
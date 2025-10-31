# Fase 3A - Extracción de Terminología SEO

**Documentación técnica completa**  
**Actualizado: 31 de octubre de 2025**

---

## 📋 Contenido

1. [Visión General](#visión-general)
2. [Arquitectura](#arquitectura)
3. [Componentes](#componentes)
4. [Flujo Completo](#flujo-completo)
5. [Estructuras de Datos](#estructuras-de-datos)
6. [Dependencias](#dependencias)

---

## Visión General

### Propósito

**Fase 3A** extrae terminología SEO específica del documento original (FILE_ID) para crear un JSON-SEO independiente con:
- **kw**: Keyword principal (1 producto/sistema específico)
- **kw_lt**: Long-tail variations (3-5 búsquedas específicas)
- **terminos_semanticos**: Términos técnicos relacionados (6-10 palabras)

### Diferencia Clave con F2E

| Característica | F2E | F3A |
|----------------|-----|-----|
| Input | FILE_ID + JSON-F2D | **Solo FILE_ID** |
| Propósito | Auditar metadatos | Extraer terminología SEO |
| Assistant | Persistente (.assistant_id) | **FRESH** (sin persistencia) |
| Output | JSON-FINAL (24 campos) | JSON-SEO (3 campos) |
| Archivo | Sobrescribe .json | Crea nuevo _SEO.json |

### Características Clave

- **API**: OpenAI Assistants API v2
- **Tool**: `file_search` (analiza solo documento)
- **Model**: gpt-4o (default, configurable)
- **Assistant**: **Fresh cada vez** (no .assistant_id persistente)
- **Input**: FILE_ID únicamente
- **Output**: JSON-SEO (kw, kw_lt, terminos_semanticos)
- **Timeout**: 60s (polling)

---

## Arquitectura

### Filosofía del Fresh Assistant

**¿Por qué Fresh?**

F3A crea un **nuevo assistant** en cada ejecución (sin guardar `.assistant_id`) para garantizar:
1. **Análisis limpio** sin contexto previo contaminado
2. **Instrucciones frescas** desde prompt original
3. **Sin memoria** de documentos anteriores

**Método Clave**:
```php
private function createFreshAssistant(): array
{
    // NO busca ni reutiliza .assistant_id existente
    // Siempre crea nuevo assistant
    return $this->apiCall('POST', '/assistants', [
        'model' => $this->model,
        'name' => 'F3A-SEO-Terminology',
        'tools' => [['type' => 'file_search']],
        'instructions' => $PROMPTS[3]['p_extract_terminology']['prompt']
    ]);
}
```

### Flujo

```
phase_3a_proxy.php
  ↓
1. Validar file_id (F1C) existe
  ↓
2. createFreshAssistant() ← SIN .assistant_id
   tools: [{'type': 'file_search'}]
  ↓
3. createThread()
4. addMessage() con FILE_ID
  ↓
5. createRun() y pollRun()
  ↓
6. extractJSON() → validar 3 campos (kw, kw_lt, terminos_semanticos)
  ↓
7. saveResults()
   ├── {NB}_SEO.json (NUEVO archivo independiente)
   └── {NB}_3A.log
   
   ⚠️ NO guarda {NB}_3A.assistant_id (fresh cada vez)
```

---

## Componentes

### 1. Clase: Phase3AProxy

**Ubicación**: `/code/php/phase_3a_proxy.php`  
**Tamaño**: ~616 líneas

#### Propiedades

```php
private array $timeline = [];
private array $debugHttp = [];
private string $docBasename;
private array $config;
private array $input;
private string $fileId;            // Del archivo .fileid (F1C)
private ?string $assistantId = null;
private string $model = 'gpt-4o';  // Default model
```

#### Constructor

```php
public function __construct(string $docBasename, array $config, array $input = [])
{
    $this->docBasename = $docBasename;
    $this->config = $config;
    $this->input = $input;
    $this->mark('init');
    
    // Si el usuario especifica un modelo, usarlo
    if (!empty($input['model'])) {
        $this->model = $input['model'];
    }
}
```

#### Método Principal

```php
public function execute(array $input): array
{
    // 1. PRE-EJECUCIÓN
    $this->mark('pre_execution.start');
    $this->validateInput();
    $this->mark('pre_execution.done');
    
    // 2. EJECUCIÓN: Extraer terminología
    $this->mark('execution.start');
    $jsonResult = $this->runAssistantAnalysis();
    $this->mark('execution.done');
    
    // 3. POST-EJECUCIÓN: Guardar JSON-SEO
    $this->mark('post_execution.start');
    $this->saveResults($jsonResult);
    $this->mark('post_execution.done');
    
    return [
        'output' => [
            'tex' => "Extracción de terminología SEO completada",
            'json_data' => $jsonResult
        ],
        'debug' => ['http' => $this->debugHttp],
        'timeline' => $this->timeline,
        'files_saved' => $this->getFilePaths()
    ];
}
```

### 2. Prompt: p_extract_terminology

```php
$PROMPTS[3]['p_extract_terminology'] = [
    'prompt' => <<<'PROMPT'
ROLE:
You are an SEO and technical terminology specialist for Cofem fire detection systems.

OBJECTIVE:
Extract from the attached document:
1. Main keyword (kw): The most specific product/system name
2. Long-tail keywords (kw_lt): 3-5 specific search variations
3. Semantic terms (terminos_semanticos): 6-10 related technical terms

RULES:
1. kw must be ONE specific product/system (e.g., "central analógica direccionable CLV")
2. kw_lt must be realistic search queries (e.g., "central contra incendios CLV", "detector direccionable Cofem")
3. terminos_semanticos must be technical domain terms (e.g., "lazo analógico", "detector inteligente")
4. All terms in Spanish from Spain (RAE)
5. Base extraction EXCLUSIVELY on document content
6. Do not invent terms not present in document

OUTPUT FORMAT (strict JSON):
{
  "kw": "...",
  "kw_lt": ["...", "...", "..."],
  "terminos_semanticos": ["...", "...", "...", "...", "..."]
}
PROMPT
];
```

### 3. Validación de Entrada

```php
private function validateInput(): void
{
    if (empty($this->docBasename)) {
        $this->fail(400, 'doc_basename requerido');
    }
    
    // Verificar .fileid existe (F1C)
    $fileidFile = $this->getFileidPath();
    if (!file_exists($fileidFile)) {
        $this->fail(400, 'Debe completar Fase 1C primero');
    }
    
    $this->fileId = trim(file_get_contents($fileidFile));
    if (empty($this->fileId)) {
        $this->fail(400, 'El archivo .fileid está vacío');
    }
    
    // ⚠️ NO requiere .json (no usa JSON-FINAL de F2E)
    
    $this->mark('validation.done');
}
```

### 4. Fresh Assistant Creation

```php
private function createFreshAssistant(): array
{
    $this->mark('assistant.create.start');
    
    // Cargar prompt
    global $PROMPTS;
    $promptData = $PROMPTS[3]['p_extract_terminology'] ?? null;
    if (!$promptData) {
        $this->fail(500, 'Prompt p_extract_terminology no encontrado');
    }
    
    // Crear nuevo assistant (NO reutiliza)
    $data = [
        'model' => $this->model,
        'name' => 'F3A-SEO-Terminology-' . date('Ymd-His'),
        'description' => 'Extrae keywords y terminología SEO del documento',
        'instructions' => $promptData['prompt'],
        'tools' => [['type' => 'file_search']],
        'temperature' => 0.3,
        'top_p' => 1.0
    ];
    
    $result = $this->apiCall('POST', '/assistants', $data);
    
    $this->mark('assistant.create.done');
    
    return $result;
}
```

### 5. Análisis con Assistant

```php
private function runAssistantAnalysis(): array
{
    // 1. Crear fresh assistant (sin .assistant_id)
    $assistantData = $this->createFreshAssistant();
    $this->assistantId = $assistantData['id'];
    
    // 2. Crear Thread
    $threadId = $this->createThread();
    
    // 3. Agregar mensaje con FILE_ID únicamente
    $this->addMessage($threadId);
    
    // 4. Ejecutar Run
    $runId = $this->createRun($threadId);
    
    // 5. Polling hasta completed
    $runResult = $this->pollRun($threadId, $runId);
    
    // 6. Obtener mensajes
    $messages = $this->getMessages($threadId);
    
    // 7. Extraer JSON-SEO
    $jsonSEO = $this->extractJSON($messages);
    
    // 8. Validar estructura (kw, kw_lt, terminos_semanticos)
    $this->validateSEOStructure($jsonSEO);
    
    return $jsonSEO;
}
```

### 6. Validación JSON-SEO

```php
private function validateSEOStructure(array $json): void
{
    $required = ['kw', 'kw_lt', 'terminos_semanticos'];
    
    foreach ($required as $field) {
        if (!isset($json[$field])) {
            $this->fail(500, "JSON-SEO inválido: falta campo '{$field}'");
        }
    }
    
    // Validar kw es string
    if (!is_string($json['kw']) || empty($json['kw'])) {
        $this->fail(500, 'JSON-SEO: kw debe ser string no vacío');
    }
    
    // Validar kw_lt es array
    if (!is_array($json['kw_lt']) || count($json['kw_lt']) < 3) {
        $this->fail(500, 'JSON-SEO: kw_lt debe tener al menos 3 elementos');
    }
    
    // Validar terminos_semanticos es array
    if (!is_array($json['terminos_semanticos']) || count($json['terminos_semanticos']) < 6) {
        $this->fail(500, 'JSON-SEO: terminos_semanticos debe tener al menos 6 elementos');
    }
}
```

---

## Flujo Completo

### Timeline Detallada

```
+0ms      init
+5ms      pre_execution.start
+10ms     validation.done (file_id leído)
+15ms     pre_execution.done
+20ms     execution.start
+25ms     assistant.create.start ← FRESH
+525ms    assistant.create.done (nuevo assistant creado)
+530ms    thread.create
+830ms    thread.create.done
+835ms    message.add (solo FILE_ID, sin JSON)
+1035ms   message.add.done
+1040ms   run.create
+1190ms   run.create.done
+1195ms   polling.start
+4195ms   polling.attempt.0.in_progress
+6195ms   polling.attempt.1.in_progress
+10195ms  polling.attempt.2.completed
+10200ms  polling.completed
+10205ms  messages.list
+10405ms  messages.list.done
+10410ms  json.extract.start
+10415ms  json.extract.done
+10420ms  execution.done
+10425ms  post_execution.start
+10430ms  files.saved (JSON-SEO + log, NO .assistant_id)
+10435ms  post_execution.done
```

**Nota**: Assistant creation es más lento (500ms) que F2E (30ms) porque no reutiliza.

---

## Estructuras de Datos

### Input

**Solo FILE_ID** (no JSON):
```
file_id: file-abcXYZ123
```

### Output JSON-SEO

```json
{
  "kw": "central analógica direccionable CLV",
  "kw_lt": [
    "central contra incendios CLV Cofem",
    "detector direccionable analógico",
    "sistema detección incendios analógico",
    "central CLV 2 lazos",
    "panel control incendios Cofem"
  ],
  "terminos_semanticos": [
    "lazo analógico",
    "detector inteligente",
    "protocolo direccionable",
    "algoritmos autoajustables",
    "verificación de alarma",
    "doble sensor",
    "aislador de cortocircuito",
    "fuente de alimentación conmutada",
    "batería de respaldo",
    "puerto RS-232"
  ]
}
```

### Respuesta del Proxy

```json
{
  "output": {
    "tex": "Extracción de terminología SEO completada",
    "json_data": {
      "kw": "...",
      "kw_lt": [...],
      "terminos_semanticos": [...]
    }
  },
  "debug": {
    "http": [...]
  },
  "timeline": [...],
  "files_saved": {
    "json_seo": "/docs/DOC001/DOC001_SEO.json",
    "log": "/docs/DOC001/DOC001_3A.log"
  }
}
```

---

## Dependencias

### Archivos Requeridos

| Archivo | Generado por | Contenido |
|---------|--------------|-----------|
| `{NB}.fileid` | F1C | file_id |
| `prompts.php` | Manual | Prompt extracción |

**NO requiere**:
- ❌ `{NB}.json` (JSON-FINAL de F2E)
- ❌ `{NB}_2E.assistant_id`

### Archivos Generados

| Archivo | Acción | Contenido |
|---------|--------|-----------|
| `{NB}_SEO.json` | **CREA NUEVO** | JSON-SEO (kw, kw_lt, terminos_semanticos) |
| `{NB}_3A.log` | Crea | Log completo con metadata |

**NO genera**:
- ❌ `{NB}_3A.assistant_id` (fresh cada vez)

---

## Troubleshooting

### kw Demasiado Genérico

**Síntoma**: kw = "detector de incendios" (muy amplio)

**Causa**: Prompt no enfatiza especificidad.

**Solución**: Mejorar prompt
```
kw must include MODEL NAME or SPECIFIC SYSTEM (e.g., "CLV-2", "detector fotoeléctrico DN420")
Avoid generic terms like "detector" or "central"
```

### kw_lt Tiene Menos de 3 Elementos

**Síntoma**: kw_lt = ["central incendios"]

**Causa**: Documento técnico sin suficientes variaciones.

**Debug**: Revisar documento fuente, puede ser muy especializado.

**Solución**:
1. Aceptar menos elementos (cambiar validación)
2. O mejorar prompt para generar variaciones sintéticas

### terminos_semanticos Repite kw

**Síntoma**: kw = "CLV" y terminos_semanticos = ["CLV", ...]

**Solución prompt**:
```
terminos_semanticos must be RELATED but DIFFERENT from kw
Examples: "lazo", "algoritmo", "protocolo", NOT the product name itself
```

### Assistant Creation Lento (>1s)

**Causa**: Fresh assistant requiere POST `/assistants` cada vez.

**Solución**: Es comportamiento esperado. Si se necesita velocidad:
1. Cambiar a assistant persistente (como F2E)
2. O cachear assistant_id temporalmente (10min TTL)

### Error: "Debe completar Fase 1C primero"

**Solución**: Ejecutar F1C (upload FILE_ID) antes de F3A.

---

## Comparación F2E vs F3A

| Aspecto | F2E | F3A |
|---------|-----|-----|
| **Input** | FILE_ID + JSON-F2D | Solo FILE_ID |
| **Assistant** | Persistente (.assistant_id) | **Fresh** (sin persistir) |
| **Tool** | code_interpreter | file_search |
| **Output archivo** | .json (sobrescribe) | _SEO.json (nuevo) |
| **Output campos** | 24 (metadatos) | 3 (SEO) |
| **Propósito** | Auditar precisión | Extraer terminología |
| **Velocidad** | Más rápida (reusa) | Más lenta (crea) |
| **Limpieza contexto** | Reutiliza memoria | Siempre limpio |

---

**Fin de documentación Fase 3A**  
**Versión**: 1.0  
**Fecha**: 31 de octubre de 2025

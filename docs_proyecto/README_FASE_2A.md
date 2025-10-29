# Fase 2A - Extracción de Metadatos Técnicos Básicos

**Documentación técnica completa**  
**Actualizado: 29 de octubre de 2025**

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

**Fase 2A** inicia el análisis técnico del documento. Usa **OpenAI Assistants API v2** con **file_search** para extraer 8 campos de metadatos básicos del archivo `.txt` y guardarlos en formato JSON estructurado.

### Objetivos

1. **Analizar** archivo `.txt` usando Assistants API
2. **Extraer** 8 campos de metadatos técnicos
3. **Validar** JSON contra schema definido
4. **Persistir** datos en `.json` y `.assistant_id`
5. **Preparar** para ampliación en F2B

### Campos Extraídos (8)

| Campo | Tipo | Descripción |
|-------|------|-------------|
| `file_id` | string | ID del archivo en OpenAI |
| `nombre_archivo` | string | Nombre del archivo original |
| `nombre_producto` | string | Denominación técnica/comercial |
| `codigo_referencia_cofem` | string | Código Cofem si existe |
| `tipo_documento` | string | Clasificación del documento |
| `tipo_informacion_contenida` | string | Naturaleza del contenido |
| `fecha_emision_revision` | string | Fecha YYYY-MM-DD |
| `idiomas_presentes` | array | Lista de idiomas detectados |

---

## Arquitectura

### Patrón OpenAI Assistants API v2

```
phase_2a_proxy.php
  ↓
1. getOrCreateAssistant()
   ├── Verificar .assistant_id existe
   ├── Si NO: crear nuevo assistant con prompt
   └── Si SÍ: reutilizar assistant_id
  ↓
2. createThread()
   └── POST /v1/threads
  ↓
3. addMessage()
   └── POST /v1/threads/{thread_id}/messages
       con attachments=[{file_id, tools: file_search}]
  ↓
4. createRun()
   └── POST /v1/threads/{thread_id}/runs
  ↓
5. pollRun() [60s timeout, 2s interval]
   └── GET /v1/threads/{thread_id}/runs/{run_id}
       hasta status = 'completed'
  ↓
6. getResponse()
   └── GET /v1/threads/{thread_id}/messages
       extraer último mensaje assistant
  ↓
7. extractJSON()
   └── Parsear y validar JSON
  ↓
8. saveFiles()
   ├── {NB}.json (8 campos)
   ├── {NB}_2A.log (metadatos)
   └── {NB}_2A.assistant_id (persistencia)
```

### Diferencias con F1B/F1C

| Característica | F1B | F1C | F2A |
|----------------|-----|-----|-----|
| API | Chat Completions | Files | **Assistants** |
| Input | PDF | .txt local | .txt (file_id) |
| Output | Texto | file_id | **JSON** |
| Polling | NO | NO | **SÍ** |
| Persistencia | .txt, .log | .fileid, .log | **.json, .assistant_id, .log** |

---

## Componentes

### 1. Proxy: phase_2a_proxy.php

**Clase**: `Phase2AProxy`  
**Tamaño**: ~673 líneas

#### Propiedades

```php
private array $timeline = [];
private array $debugHttp = [];
private string $docBasename;
private array $config;
private array $input;
private string $fileId;            // Del archivo .fileid (F1C)
private ?string $assistantId = null;
```

#### Método Principal

```php
public function execute(array $input): array
{
    // PRE-EJECUCIÓN
    $this->mark('pre_execution.start');
    $this->validateInput();  // Verificar .fileid existe
    $this->mark('pre_execution.done');
    
    // EJECUCIÓN
    $this->mark('execution.start');
    $jsonResult = $this->runAssistantAnalysis();
    $this->mark('execution.done');
    
    // POST-EJECUCIÓN
    $this->mark('post_execution.start');
    $this->saveResults($jsonResult);
    $this->mark('post_execution.done');
    
    return [...];
}
```

#### Validación

```php
private function validateInput(): void
{
    if (empty($this->docBasename)) {
        $this->fail(400, 'doc_basename requerido');
    }
    
    // Verificar .fileid existe (generado en F1C)
    $fileidFile = $this->getFileidPath();
    if (!file_exists($fileidFile)) {
        $this->fail(400, 'Debe completar Fase 1C primero');
    }
    
    // Leer file_id
    $this->fileId = trim(file_get_contents($fileidFile));
    if (empty($this->fileId)) {
        $this->fail(400, 'El archivo .fileid está vacío');
    }
    
    $this->mark('validation.done');
}
```

#### Obtener/Crear Assistant

```php
private function getOrCreateAssistant(string $apiKey, ?string $modelOverride = null): string
{
    $this->mark('assistant.check.start');
    
    // Verificar si existe
    $assistantIdFile = $this->getAssistantIdPath();
    if (file_exists($assistantIdFile)) {
        $assistantId = trim(file_get_contents($assistantIdFile));
        if ($assistantId) {
            $this->mark('assistant.reused');
            return $assistantId;
        }
    }
    
    // Crear nuevo
    $this->mark('assistant.create.start');
    
    global $PROMPTS;
    $instructions = $PROMPTS[2]['p_extract_metadata_technical']['prompt'];
    $instructions = str_replace('{FILE_ID}', 'the file_id provided in the message', $instructions);
    
    $model = $modelOverride ?? 'gpt-4o';
    
    $payload = [
        'model' => $model,
        'name' => 'Technical Metadata Extractor',
        'description' => 'Extracts technical metadata from text files',
        'instructions' => $instructions,
        'tools' => [['type' => 'file_search']]  // file_search para .txt
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
    
    $resp = curl_exec($ch);
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($status !== 200) {
        $this->fail(502, 'Error al crear Assistant');
    }
    
    $result = json_decode($resp, true);
    $assistantId = $result['id'] ?? null;
    
    // Guardar para reutilización
    file_put_contents($assistantIdFile, $assistantId);
    
    $this->mark('assistant.create.done');
    return $assistantId;
}
```

#### Polling de Run

```php
private function pollRun(string $apiKey, string $threadId, string $runId): void
{
    $this->mark('polling.start');
    
    $maxAttempts = 30;  // 30 × 2s = 60s
    $interval = 2;
    
    sleep(3);  // Sleep inicial (run tarda ~2-3s mínimo)
    
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
        
        if (in_array($runStatus, ['failed', 'expired', 'cancelled'])) {
            $this->fail(502, "Run failed: {$runStatus}");
        }
        
        sleep($interval);
    }
    
    $this->fail(504, 'Polling timeout excedido (60s)');
}
```

#### Extraer y Validar JSON

```php
private function extractJSON(array $messages): array
{
    $this->mark('json.extract.start');
    
    // Buscar último mensaje del assistant
    foreach ($messages as $msg) {
        if ($msg['role'] === 'assistant') {
            $content = $msg['content'][0]['text']['value'] ?? '';
            
            // Extraer JSON del texto
            if (preg_match('/```json\s*(\{.*?\})\s*```/s', $content, $matches)) {
                $jsonText = $matches[1];
            } elseif (preg_match('/(\{.*\})/s', $content, $matches)) {
                $jsonText = $matches[1];
            } else {
                $this->fail(502, 'No se encontró JSON en la respuesta');
            }
            
            $jsonData = json_decode($jsonText, true);
            
            if (!$jsonData) {
                $this->fail(502, 'JSON inválido: ' . json_last_error_msg());
            }
            
            // Validar campos requeridos
            $required = ['file_id', 'nombre_archivo', 'idiomas_presentes'];
            foreach ($required as $field) {
                if (!isset($jsonData[$field])) {
                    $this->fail(502, "Campo requerido faltante: {$field}");
                }
            }
            
            $this->mark('json.extract.done');
            return $jsonData;
        }
    }
    
    $this->fail(502, 'No se encontró mensaje del assistant');
}
```

#### Guardar Resultados

```php
private function saveResults(array $jsonData): void
{
    $docsDir = $this->config['docs_dir'] ?? '';
    $docDir = $docsDir . DIRECTORY_SEPARATOR . $this->docBasename;
    
    if (!is_dir($docDir)) {
        @mkdir($docDir, 0755, true);
    }
    
    // Guardar JSON
    $jsonPath = $docDir . DIRECTORY_SEPARATOR . $this->docBasename . '.json';
    file_put_contents($jsonPath, json_encode($jsonData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    
    // Guardar LOG
    $logPath = $docDir . DIRECTORY_SEPARATOR . $this->docBasename . '_2A.log';
    $logData = [
        'timestamp' => date('Y-m-d H:i:s'),
        'phase' => '2A',
        'status' => 'SUCCESS',
        'doc_basename' => $this->docBasename,
        'assistant_id' => $this->assistantId,
        'fields_count' => count($jsonData),
        'timeline' => $this->timeline,
        'debug_http' => $this->debugHttp
    ];
    file_put_contents($logPath, json_encode($logData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    
    $this->mark('files.saved');
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
+25ms     assistant.check.start
+30ms     assistant.reused (o assistant.create 200-500ms)
+35ms     thread.create (100-300ms)
+340ms    thread.create.done
+345ms    message.add (100-200ms)
+545ms    message.add.done
+550ms    run.create (50-150ms)
+700ms    run.create.done
+705ms    polling.start
+3705ms   polling.attempt.0.in_progress
+5705ms   polling.attempt.1.in_progress
+7705ms   polling.attempt.2.completed
+7710ms   polling.completed
+7715ms   messages.list (100-200ms)
+7915ms   messages.list.done
+7920ms   json.extract.start
+7925ms   json.extract.done
+7930ms   execution.done
+7935ms   post_execution.start
+7940ms   files.saved
+7945ms   post_execution.done
```

---

## Estructuras de Datos

### Prompt Template (prompts.php)

```php
$PROMPTS[2]['p_extract_metadata_technical'] = [
    'id' => 'p_extract_metadata_technical',
    'title' => 'Extraer metadatos técnicos',
    'prompt' => <<<'PROMPT'
OBJECTIVE:
Access the file indicated by its file_id, perform a precautionary check, and generate a base JSON object containing technical information.

INSTRUCTIONS:
1. Use exclusively the file_id provided: {FILE_ID}
2. Perform precautionary validation
3. Read and analyse the complete content
4. Extract and populate the following JSON keys:
   - file_id
   - nombre_archivo
   - nombre_producto
   - codigo_referencia_cofem
   - tipo_documento
   - tipo_informacion_contenida
   - fecha_emision_revision (YYYY-MM-DD)
   - idiomas_presentes (array)
5. If missing: use "" for text, [] for arrays
6. Output must be ONLY valid JSON, no comments

MANDATORY OUTPUT FORMAT:
{
  "file_id": "",
  "nombre_archivo": "",
  "nombre_producto": "",
  "codigo_referencia_cofem": "",
  "tipo_documento": "",
  "tipo_informacion_contenida": "",
  "fecha_emision_revision": "",
  "idiomas_presentes": []
}
PROMPT
];
```

### Output JSON (8 campos)

```json
{
  "file_id": "file-XXXXXXXXXXXXXXXXXXXXXXXX",
  "nombre_archivo": "DOC001.txt",
  "nombre_producto": "Central de Detección de Incendios",
  "codigo_referencia_cofem": "CLVR-8Z",
  "tipo_documento": "Manual Técnico",
  "tipo_informacion_contenida": "Instalación y Mantenimiento",
  "fecha_emision_revision": "2024-03-15",
  "idiomas_presentes": ["es", "en"]
}
```

### Respuesta del Proxy

```json
{
  "output": {
    "tex": "\uFEFFAnálisis técnico completado",
    "json_data": {...8 campos...}
  },
  "debug": {
    "http": [
      {"stage": "assistant.reused", "assistant_id": "asst_..."},
      {"stage": "thread.create", "status_code": 200, "ms": 240},
      {"stage": "message.add", "status_code": 200, "ms": 200},
      {"stage": "run.create", "status_code": 200, "ms": 150},
      {"stage": "polling.attempt.0.in_progress", ...},
      {"stage": "polling.attempt.2.completed", ...},
      {"stage": "messages.list", "status_code": 200, "ms": 200}
    ]
  },
  "timeline": [...],
  "files_saved": {
    "json_file": "/docs/DOC001/DOC001.json",
    "log_file": "/docs/DOC001/DOC001_2A.log",
    "assistant_id_file": "/docs/DOC001/DOC001_2A.assistant_id"
  }
}
```

---

## Dependencias

### Archivos Requeridos

| Archivo | Generado por | Contenido |
|---------|--------------|-----------|
| `{NB}.fileid` | Fase 1C | file_id de OpenAI |
| `config.json` | Manual | API key, paths |
| `prompts.php` | Manual | Prompt F2A |

### Archivos Generados

| Archivo | Usado por | Contenido |
|---------|-----------|-----------|
| `{NB}.json` | F2B | 8 campos JSON |
| `{NB}_2A.log` | Debug | Log completo |
| `{NB}_2A.assistant_id` | F2A (reutilización) | Assistant ID |

### Componentes

```
phase_2a_proxy.php
  ├── lib_apio.php                  → Config, paths
  ├── config/prompts.php            → Prompt template
  └── OpenAI Assistants API v2
      ├── POST /v1/assistants       → Crear assistant
      ├── POST /v1/threads          → Crear thread
      ├── POST /v1/threads/{id}/messages → Agregar mensaje
      ├── POST /v1/threads/{id}/runs → Ejecutar
      ├── GET /v1/threads/{id}/runs/{id} → Polling
      └── GET /v1/threads/{id}/messages → Obtener respuesta
```

---

## Troubleshooting

### Error: "Debe completar Fase 1C primero"

**Solución**: Ejecutar F1C para generar `.fileid`

### Polling Timeout (60s excedido)

**Causa**: Documento muy largo o modelo lento.

**Solución**:
```php
$maxAttempts = 60;  // 120s timeout
```

### JSON Inválido en Respuesta

**Causa**: Assistant devolvió texto sin JSON.

**Solución**: Mejorar prompt con ejemplos explícitos de JSON

### Assistant Reutilizado con Modelo Incorrecto

**Causa**: `.assistant_id` guardado con modelo anterior.

**Solución**: Eliminar `.assistant_id` para forzar recreación:
```bash
rm /docs/{NB}/{NB}_2A.assistant_id
```

---

**Fin de documentación Fase 2A**  
**Versión**: 1.0  
**Fecha**: 29 de octubre de 2025

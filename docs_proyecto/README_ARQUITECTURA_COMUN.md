# Arquitectura Común para Todas las Fases

**Documento técnico de referencia para desarrolladores e IA**  
**Actualizado: 29 de octubre de 2025**

---

## 📋 Índice

1. [Visión General](#visión-general)
2. [Arquitectura Proxy-Frontend](#arquitectura-proxy-frontend)
3. [Componentes Comunes](#componentes-comunes)
4. [Flujo de Datos Estándar](#flujo-de-datos-estándar)
5. [Estructura de Archivos](#estructura-de-archivos)
6. [Convenciones y Estándares](#convenciones-y-estándares)
7. [Guía de Implementación](#guía-de-implementación)

---

## Visión General

### Propósito

El sistema implementa una arquitectura común y reutilizable para todas las fases de procesamiento de documentos PDF mediante inteligencia artificial. Esta arquitectura garantiza:

- **Consistencia**: Todas las fases siguen el mismo patrón de ejecución
- **Mantenibilidad**: Cambios centralizados se propagan a todas las fases
- **Escalabilidad**: Nuevas fases se implementan con mínimo esfuerzo
- **Observabilidad**: Timeline y debug HTTP estandarizados
- **Robustez**: Manejo uniforme de errores y timeouts

### Patrón Arquitectónico

```
┌─────────────┐        ┌──────────────┐        ┌─────────────┐
│  Frontend   │ -----> │ Proxy (PHP)  │ -----> │  OpenAI API │
│  (HTML/JS)  │ <----- │  + Runtime   │ <----- │  (Assistants)│
└─────────────┘        └──────────────┘        └─────────────┘
      │                       │                       │
      ├── Timeline UI         ├── ProxyRuntime        ├── Assistants v2
      ├── Debug HTTP          ├── Polling Logic       ├── code_interpreter
      └── Error Handling      └── File Management     └── File Attachments
```

**Separación de responsabilidades:**
- **Frontend**: Interfaz de usuario, visualización, navegación
- **Proxy**: Lógica de negocio, validación, coordinación OpenAI
- **Runtime**: Utilidades comunes, HTTP, timeline, debug

---

## Arquitectura Proxy-Frontend

### 1. Componente Frontend

**Archivo tipo**: `phase_XX.php`  
**Ubicación**: `/code/php/phase_XX.php`  
**Tecnologías**: PHP (estructura), HTML5, CSS3, JavaScript (ES6+)

#### Responsabilidades

1. **Renderizado de interfaz**:
   - Mostrar datos de entrada (JSON previo, documento, parámetros)
   - Botón de ejecución con estado (disabled durante procesamiento)
   - Secciones de resultados con formato apropiado

2. **Comunicación con Proxy**:
   ```javascript
   const response = await fetch('/code/php/phase_XX_proxy.php', {
       method: 'POST',
       headers: { 'Content-Type': 'application/json' },
       body: JSON.stringify(payload)
   });
   ```

3. **Visualización en tiempo real**:
   - Timeline de ejecución (milisegundos con etiquetas)
   - Debug HTTP (requests, responses, status codes)
   - Resultados finales con opciones de descarga/copia

4. **Navegación**:
   - Botones "Continuar a Fase XX" (siguiente fase)
   - Botones "Ver Archivos Generados" (lista de documentos)
   - Breadcrumb o menú de contexto

#### Estructura HTML Estándar

```html
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Fase XX - [Descripción]</title>
    <link rel="stylesheet" href="/code/css/phase_common.css">
</head>
<body>
    <?php require_once 'header.php'; ?>
    
    <div class="container">
        <!-- Sección de entrada -->
        <section id="inputSection">
            <h2>Datos de Entrada</h2>
            <div id="jsonPrevio"></div>
        </section>
        
        <!-- Botón de ejecución -->
        <button id="btnExecute">Ejecutar Fase XX</button>
        
        <!-- Timeline -->
        <section id="statusPanel">
            <h3>Estado de Ejecución</h3>
            <div id="statusIndicator"></div>
            <div id="timelineContent"></div>
        </section>
        
        <!-- Debug HTTP -->
        <section id="debugHttpPanel">
            <h3>Debug HTTP</h3>
            <div id="debugHttpContent"></div>
        </section>
        
        <!-- Resultados -->
        <section id="resultsSection" style="display:none;">
            <h2>Resultados</h2>
            <div id="resultsContent"></div>
        </section>
        
        <!-- Navegación -->
        <div class="actions">
            <button id="btnNext">Continuar a Fase [XX+1]</button>
            <button id="btnViewFiles">Ver Archivos Generados</button>
        </div>
    </div>
    
    <script src="/code/js/phase_common.js"></script>
    <script src="/code/js/phase_XX.js"></script>
</body>
</html>
```

#### JavaScript: phase_common.js

**Funciones comunes disponibles para todas las fases:**

```javascript
// Timeline rendering
function renderTimeline(timelineArray, containerId)

// Debug HTTP rendering
function renderDebugHttp(debugHttpArray, containerId)

// Error handling
function handleError(error, containerId)

// Status indicator
function updateStatus(message, type) // type: 'info'|'success'|'error'|'loading'

// Text utilities
function copyToClipboard(text)
function downloadAsFile(text, filename)

// JSON formatting
function formatJSON(jsonObject)
```

### 2. Componente Proxy

**Archivo tipo**: `phase_XX_proxy.php`  
**Ubicación**: `/code/php/phase_XX_proxy.php`  
**Tecnología**: PHP 8.1+

#### Responsabilidades

1. **Validación de entrada**:
   - Verificar campos requeridos
   - Validar tipos de datos
   - Comprobar existencia de archivos/documentos

2. **Orquestación OpenAI**:
   - Crear o recuperar Assistant ID
   - Preparar mensajes y attachments
   - Ejecutar thread y polling
   - Extraer y validar resultados JSON

3. **Gestión de archivos**:
   - Guardar `.json` con campos actualizados
   - Guardar `.log` con metadatos completos
   - Guardar `.assistant_id` para reutilización

4. **Respuesta estandarizada**:
   ```json
   {
     "output": {
       "tex": "...",
       "json_path": "/docs/{NB}/...",
       "fields_added": ["campo1", "campo2"]
     },
     "debug": {
       "http": [...],
       "assistant_id": "asst_...",
       "thread_id": "thread_...",
       "run_id": "run_..."
     },
     "timeline": [...]
   }
   ```

#### Estructura PHP Estándar

```php
<?php
declare(strict_types=1);
require_once __DIR__ . '/proxy_common.php';

class PhaseXXProxy
{
    private ProxyRuntime $runtime;
    private array $config;
    private string $docBasename;
    
    public function __construct()
    {
        $this->runtime = new ProxyRuntime();
        require_once __DIR__ . '/lib_apio.php';
        $this->config = apio_load_config();
    }
    
    // ========== PRE-EJECUCIÓN ==========
    
    public function validateInput(array $input): void
    {
        // Validar campos requeridos
        if (empty($input['doc_basename'])) {
            $this->runtime->fail(400, 'doc_basename requerido');
        }
        
        $this->docBasename = $input['doc_basename'];
        $this->runtime->mark('input.validated');
    }
    
    // ========== EJECUCIÓN ==========
    
    public function execute(array $input): array
    {
        $this->validateInput($input);
        
        // 1. Obtener o crear Assistant
        $assistantId = $this->getOrCreateAssistant();
        
        // 2. Crear thread y mensaje
        $threadId = $this->createThread();
        $this->addMessage($threadId, $input);
        
        // 3. Ejecutar run con polling
        $runId = $this->createRun($threadId, $assistantId);
        $result = $this->pollRun($threadId, $runId);
        
        // 4. Extraer y validar JSON
        $jsonData = $this->extractJSON($result);
        
        // 5. Guardar archivos
        $this->saveFiles($jsonData);
        
        return $jsonData;
    }
    
    // ========== POST-EJECUCIÓN ==========
    
    private function saveFiles(array $jsonData): void
    {
        $docDir = $this->config['docs_dir'] . '/' . $this->docBasename;
        
        // Guardar JSON
        $jsonPath = "{$docDir}/{$this->docBasename}.json";
        file_put_contents($jsonPath, json_encode($jsonData, JSON_PRETTY_PRINT));
        
        // Guardar LOG
        $logPath = "{$docDir}/{$this->docBasename}_XX.log";
        $logData = [
            'timestamp' => date('Y-m-d H:i:s'),
            'phase' => 'XX',
            'status' => 'SUCCESS',
            'timeline' => $this->runtime->timeline,
            'debug' => $this->runtime->debugHttp
        ];
        file_put_contents($logPath, json_encode($logData, JSON_PRETTY_PRINT));
        
        // Guardar assistant_id
        $assistantPath = "{$docDir}/{$this->docBasename}_XX.assistant_id";
        file_put_contents($assistantPath, $this->assistantId);
    }
}

// Ejecución
$proxy = new PhaseXXProxy();
$input = json_decode(file_get_contents('php://input'), true) ?? [];
$result = $proxy->execute($input);

echo json_encode([
    'output' => ['tex' => "\xEF\xBB\xBF", 'json' => $result],
    'debug' => ['http' => $proxy->runtime->debugHttp],
    'timeline' => $proxy->runtime->timeline
], JSON_UNESCAPED_UNICODE);
```

---

## Componentes Comunes

### 1. proxy_common.php

**Clase**: `ProxyRuntime`  
**Ubicación**: `/code/php/proxy_common.php`  
**Propósito**: Utilidades HTTP, timeline, debug, OpenAI API

#### Métodos Principales

##### Timeline Management

```php
public function mark(string $stage): void
```
- **Propósito**: Registrar un evento en la línea de tiempo
- **Parámetros**: 
  - `$stage`: Nombre descriptivo del evento (e.g., 'init', 'fetch.start', 'openai.done')
- **Almacena**: `['ts' => timestamp_ms, 'stage' => stage]`
- **Uso**: Permite visualizar duración entre eventos en frontend

##### Input Handling

```php
public function readInput(): array
```
- **Propósito**: Leer y parsear input JSON desde `php://input` o `$_POST`
- **Retorna**: Array con datos de entrada
- **Manejo**: Detecta Content-Type y parsea apropiadamente

##### Validation

```php
public function validateUrl(string $url, array $allowedHosts = []): void
```
- **Propósito**: Validar URLs antes de fetch
- **Parámetros**:
  - `$url`: URL a validar
  - `$allowedHosts`: Lista blanca de dominios permitidos (opcional)
- **Falla**: Llama `fail()` si URL inválida o host no permitido

##### HTTP Operations

```php
public function fetchToTmp(string $url, string $accept = 'application/octet-stream', string $ext = ''): string
```
- **Propósito**: Descargar archivo remoto a temporal local
- **Parámetros**:
  - `$url`: URL del archivo
  - `$accept`: Header Accept (MIME type)
  - `$ext`: Extensión de archivo (pdf, csv, txt)
- **Retorna**: Path absoluto del archivo temporal
- **Debug**: Registra status code, duración, headers

```php
public function openaiUploadFile(string $localPath, string $mime = 'application/octet-stream', string $purpose = 'assistants'): string
```
- **Propósito**: Subir archivo local a OpenAI Files API
- **Parámetros**:
  - `$localPath`: Path del archivo local
  - `$mime`: MIME type (application/pdf, text/csv, text/plain)
  - `$purpose`: Propósito OpenAI ('assistants')
- **Retorna**: file_id de OpenAI
- **Debug**: Registra upload duration, file name, purpose

##### OpenAI API Calls

```php
public function openaiChatCompletions(array $payload): array
```
- **Propósito**: Llamar OpenAI Chat Completions API
- **Parámetros**: `$payload` con estructura completa del request
- **Retorna**: Respuesta JSON parseada
- **Timeout**: 120 segundos
- **Debug**: Status code, duración, headers (token oculto)

```php
public function extractOutputText(array $res): string
```
- **Propósito**: Extraer texto de respuesta OpenAI (múltiples formatos)
- **Soporta**:
  - `output_text` directo
  - `output[].content[].text` (Responses API)
  - `choices[].message.content` (Chat Completions)
  - `content` directo
  - `text` directo
- **Debug**: Registra método de extracción usado
- **Retorna**: String de texto extraído

##### Error Handling

```php
public function fail(int $code, string $msg): never
```
- **Propósito**: Terminar ejecución con error HTTP
- **Parámetros**:
  - `$code`: HTTP status code (400, 500, 502, etc.)
  - `$msg`: Mensaje de error descriptivo
- **Respuesta**:
  ```json
  {
    "output": {"tex": "\uFEFF"},
    "debug": {"http": [...], "error": "mensaje"},
    "timeline": [...]
  }
  ```

##### Success Response

```php
public function respondOk(string $text): never
```
- **Propósito**: Responder con éxito
- **Parámetros**: `$text` - contenido exitoso
- **Respuesta**:
  ```json
  {
    "output": {"tex": "\uFEFF{text}"},
    "debug": {"http": [...]},
    "timeline": [...]
  }
  ```

#### Propiedades Públicas

```php
public array $timeline = [];      // Timeline events
public array $debugHttp = [];     // HTTP debug info
public string $apiKey;            // OpenAI API key
```

### 2. lib_apio.php

**Tipo**: Biblioteca de utilidades  
**Ubicación**: `/code/php/lib_apio.php`  
**Propósito**: Configuración, paths, logging

#### Funciones Principales

```php
function apio_load_config(): array
```
- **Propósito**: Cargar `config/config.json`
- **Retorna**: Array con toda la configuración
- **Cache**: Carga una sola vez por request
- **Uso**: `$cfg = apio_load_config();`

```php
function apio_resolve_path(string $relative): string
```
- **Propósito**: Convertir path relativo a absoluto
- **Base**: `project_root` desde config
- **Ejemplo**: `apio_resolve_path('docs/file.pdf')`

```php
function apio_log_phase(string $phase, string $docBasename, array $data): void
```
- **Propósito**: Logging estructurado para fases
- **Parámetros**:
  - `$phase`: Nombre de fase (1B, 2A, etc.)
  - `$docBasename`: Identificador documento
  - `$data`: Array con información a loguear
- **Archivo**: `/tmp/logs/{fase}_{doc}_{timestamp}.log`

### 3. prompts.php

**Tipo**: Configuración de prompts  
**Ubicación**: `/config/prompts.php`  
**Estructura**: Array PHP con prompts por fase

```php
$PROMPTS = [
    1 => [
        'p_extract_text' => [
            'id' => 'p_extract_text',
            'title' => 'Extraer texto bruto',
            'prompt' => '...',
            'placeholders' => ['PDF_BASE64', 'FILE_ID'],
            'output_format' => 'markdown'
        ]
    ],
    2 => [
        'p_extract_metadata_technical' => [...],
        'p_expand_metadata_technical' => [...],
        'p_add_taxonomy_fields' => [...],
        'p_generate_technical_sheet' => [...]
    ]
];
```

**Acceso desde proxy:**
```php
require_once __DIR__ . '/../../config/prompts.php';
$prompt = $PROMPTS[2]['p_extract_metadata_technical']['prompt'];
$prompt = str_replace('{FILE_ID}', $fileId, $prompt);
```

### 4. config.json

**Ubicación**: `/config/config.json`  
**Formato**: JSON

#### Secciones Principales

```json
{
  "apio_key": "sk-proj-...",
  "apio_url": "https://api.openai.com/v1/chat/completions",
  
  "project_root": "/home/plazzaxy/public_html/ed_cfle",
  "docs_dir": "/home/.../docs",
  "tmp_dir": "/home/.../tmp",
  "config_dir": "/home/.../config",
  
  "file_id_productos": "file-...",
  "file_id_taxonomia": "file-...",
  
  "apio_models": ["gpt-4o", "gpt-4o-mini"],
  "apio_defaults": {
    "model": "gpt-4o",
    "temperature": 0,
    "max_tokens": 1500
  },
  
  "cleanup": {
    "incomplete_hours": 6,
    "keep_days": 30
  },
  
  "public_base": "https://cfle.plazza.xyz"
}
```

---

## Flujo de Datos Estándar

### 1. Ciclo de Vida Completo

```
┌─────────────────────────────────────────────────────────────┐
│ FRONTEND (phase_XX.php)                                      │
├─────────────────────────────────────────────────────────────┤
│ 1. Usuario carga página con ?doc=NB_archivo                 │
│ 2. Frontend muestra JSON previo (si existe)                 │
│ 3. Usuario hace clic en "Ejecutar Fase XX"                  │
│ 4. JavaScript prepara payload:                              │
│    {                                                         │
│      "doc_basename": "NB_archivo",                          │
│      "json_previo": {...},                                  │
│      "parametros": {...}                                    │
│    }                                                         │
│ 5. fetch() POST a phase_XX_proxy.php                        │
└─────────────────────────────────────────────────────────────┘
                           ↓
┌─────────────────────────────────────────────────────────────┐
│ PROXY (phase_XX_proxy.php)                                   │
├─────────────────────────────────────────────────────────────┤
│ PRE-EJECUCIÓN:                                               │
│ 6. Leer input JSON (ProxyRuntime::readInput)               │
│ 7. Validar campos requeridos                                │
│ 8. Verificar archivo .txt existe en /docs/NB/              │
│ 9. Cargar JSON previo (si aplica)                          │
│                                                              │
│ EJECUCIÓN:                                                   │
│ 10. Obtener/crear Assistant ID                              │
│     - Verificar archivo .assistant_id                       │
│     - Si no existe: crear nuevo assistant con prompt       │
│     - Guardar assistant_id para reutilización              │
│ 11. Preparar attachments (archivos OpenAI)                  │
│     - file_id del documento.txt                             │
│     - file_id de CSVs (si aplica: F2C)                     │
│ 12. Crear Thread                                             │
│ 13. Agregar mensaje con attachments                         │
│ 14. Crear Run                                                │
│ 15. Polling (hasta 60s, cada 2s):                          │
│     - Verificar status: queued → in_progress → completed   │
│     - Si completed: obtener mensajes                        │
│     - Si failed/expired: error                              │
│ 16. Extraer último mensaje del thread                       │
│ 17. Parsear JSON desde mensaje                              │
│ 18. Validar campos nuevos contra schema                     │
│                                                              │
│ POST-EJECUCIÓN:                                              │
│ 19. Guardar .json con campos actualizados                   │
│ 20. Guardar .log con metadatos completos                    │
│ 21. Guardar .assistant_id (si nuevo)                        │
│ 22. Responder con output + debug + timeline                 │
└─────────────────────────────────────────────────────────────┘
                           ↓
┌─────────────────────────────────────────────────────────────┐
│ FRONTEND (JavaScript)                                        │
├─────────────────────────────────────────────────────────────┤
│ 23. Recibir respuesta JSON                                   │
│ 24. Renderizar timeline                                      │
│ 25. Renderizar debug HTTP                                    │
│ 26. Mostrar resultados:                                      │
│     - JSON formateado                                        │
│     - Campos nuevos destacados                              │
│     - Botones de acción (copiar, descargar)                │
│ 27. Habilitar botón "Continuar a Fase XX+1"                │
└─────────────────────────────────────────────────────────────┘
```

### 2. Formato de Respuesta Estándar

**Respuesta exitosa:**
```json
{
  "output": {
    "tex": "\uFEFF",
    "json_data": {
      "file_id": "...",
      "nombre_archivo": "...",
      ...nuevos campos...
    },
    "json_path": "/docs/NB/NB.json",
    "fields_added": ["campo1", "campo2"]
  },
  "debug": {
    "http": [
      {
        "stage": "assistants.create",
        "status_code": 200,
        "ms": 1234,
        "headers": {...},
        "response_preview": {...}
      },
      ...
    ],
    "assistant_id": "asst_...",
    "thread_id": "thread_...",
    "run_id": "run_...",
    "polling_attempts": 5
  },
  "timeline": [
    {"ts": 1698765432000, "stage": "init"},
    {"ts": 1698765433000, "stage": "input.read"},
    {"ts": 1698765434000, "stage": "assistants.get"},
    ...
  ]
}
```

**Respuesta de error:**
```json
{
  "output": {
    "tex": "\uFEFF"
  },
  "debug": {
    "http": [...],
    "error": "Descripción del error"
  },
  "timeline": [...]
}
```

### 3. Estados de Timeline

**Eventos comunes en todas las fases:**

| Stage | Descripción | Cuándo |
|-------|-------------|--------|
| `init` | Inicialización proxy | Constructor ProxyRuntime |
| `input.read` | Lectura de input | readInput() |
| `input.validated` | Validación completa | validateInput() |
| `file.check` | Verificar archivo .txt | Antes de OpenAI |
| `assistants.get` | Recuperar assistant_id | getOrCreateAssistant() |
| `assistants.create` | Crear nuevo assistant | Primera ejecución |
| `threads.create` | Crear thread | createThread() |
| `messages.create` | Agregar mensaje | addMessage() |
| `runs.create` | Iniciar run | createRun() |
| `polling.start` | Iniciar polling | pollRun() |
| `polling.attempt.N` | Intento N de polling | Cada 2 segundos |
| `polling.completed` | Run completado | Status = completed |
| `messages.list` | Listar mensajes | Después de run |
| `json.extract` | Extraer JSON | extractJSON() |
| `json.validate` | Validar campos | validateJSON() |
| `files.save` | Guardar archivos | saveFiles() |
| `done` | Finalización exitosa | respondOk() |

---

## Estructura de Archivos

### Organización por Documento

```
/docs/
  {NB_archivo}/                    # Directorio por documento
    {NB_archivo}.pdf               # PDF original
    {NB_archivo}.txt               # Texto extraído (F1B)
    {NB_archivo}.json              # JSON progresivo (F2A→F2B→F2C→F2D)
    
    {NB_archivo}.log               # Log F1B
    {NB_archivo}_1C.log            # Log F1C
    {NB_archivo}_2A.log            # Log F2A
    {NB_archivo}_2B.log            # Log F2B
    {NB_archivo}_2C.log            # Log F2C
    {NB_archivo}_2D.log            # Log F2D
    
    {NB_archivo}_2A.assistant_id   # Assistant ID F2A
    {NB_archivo}_2B.assistant_id   # Assistant ID F2B
    {NB_archivo}_2C.assistant_id   # Assistant ID F2C
    {NB_archivo}_2D.assistant_id   # Assistant ID F2D
```

### Evolución del JSON

**F2A (8 campos):**
```json
{
  "file_id": "file-...",
  "nombre_archivo": "documento.txt",
  "nombre_producto": "...",
  "codigo_referencia_cofem": "...",
  "tipo_documento": "...",
  "tipo_informacion_contenida": "...",
  "fecha_emision_revision": "YYYY-MM-DD",
  "idiomas_presentes": ["es", "en"]
}
```

**F2B (14 campos = 8 + 6 nuevos):**
```json
{
  ...todos los de F2A...,
  "normas_detectadas": [{...}],
  "certificaciones_detectadas": [{...}],
  "manuales_relacionados": [{...}],
  "otros_productos_relacionados": [{...}],
  "accesorios_relacionados": [{...}],
  "uso_formacion_tecnicos": false,
  "razon_uso_formacion": ""
}
```

**F2C (22 campos = 14 + 8 nuevos):**
```json
{
  ...todos los de F2B...,
  "codigo_encontrado": "...",
  "nombre_encontrado": "...",
  "familia_catalogo": "...",
  "nivel_confianza_identificacion": "...",
  "grupos_de_soluciones": "...",
  "familia": "...",
  "categoria": "...",
  "incidencias_taxonomia": []
}
```

**F2D (24 campos = 22 + 2 nuevos):**
```json
{
  ...todos los de F2C...,
  "ficha_tecnica": "...",
  "resumen_tecnico": "..."
}
```

---

## Convenciones y Estándares

### 1. Nomenclatura

#### Archivos

| Tipo | Patrón | Ejemplo |
|------|--------|---------|
| Frontend | `phase_{fase}.php` | `phase_2a.php` |
| Proxy | `phase_{fase}_proxy.php` | `phase_2a_proxy.php` |
| Log | `{NB}_{fase}.log` | `DOC001_2A.log` |
| Assistant ID | `{NB}_{fase}.assistant_id` | `DOC001_2A.assistant_id` |
| JSON | `{NB}.json` | `DOC001.json` |
| TXT | `{NB}.txt` | `DOC001.txt` |

#### Variables

```php
// Snake_case para variables
$doc_basename = '...';
$json_previo = [...];
$assistant_id = '...';

// camelCase para métodos
public function getOrCreateAssistant(): string
public function validateInput(array $input): void

// UPPERCASE para constantes
const POLLING_TIMEOUT = 60;
const POLLING_INTERVAL = 2;
```

### 2. HTTP Status Codes

| Code | Uso | Ejemplo |
|------|-----|---------|
| 200 | Éxito | Proceso completado |
| 400 | Error de cliente | Parámetro faltante |
| 413 | Payload muy grande | PDF > 25 MB |
| 500 | Error de servidor | Error interno PHP |
| 502 | Error de gateway | OpenAI API falla |
| 504 | Timeout | Polling excede 60s |

### 3. Tipos MIME

```php
'application/pdf'                  // PDF files
'text/plain; charset=utf-8'        // TXT files
'text/csv; charset=utf-8'          // CSV files
'application/json; charset=utf-8'  // JSON responses
```

### 4. Character Encoding

**SIEMPRE UTF-8 con BOM en respuestas:**
```php
$BOM = "\xEF\xBB\xBF";
echo json_encode(['output' => ['tex' => $BOM . $text]], JSON_UNESCAPED_UNICODE);
```

### 5. OpenAI Assistants API v2

**Estructura de attachments obligatoria:**
```php
$attachments = [
    [
        'file_id' => 'file-...',
        'tools' => [['type' => 'code_interpreter']]  // OBLIGATORIO en API v2
    ]
];
```

**NUNCA usar `file_search`** para archivos CSV (no soportado):
```php
// ❌ INCORRECTO
'tools' => [['type' => 'file_search']]

// ✅ CORRECTO
'tools' => [['type' => 'code_interpreter']]
```

### 6. Polling Pattern

```php
const POLLING_TIMEOUT = 60;    // Máximo 60 segundos
const POLLING_INTERVAL = 2;    // Cada 2 segundos
const MAX_ATTEMPTS = 30;       // 30 intentos × 2s = 60s

sleep(3);  // Sleep inicial de 3s (run suele tardar 2-3s mínimo)

for ($i = 0; $i < MAX_ATTEMPTS; $i++) {
    $run = $this->getRun($threadId, $runId);
    $status = $run['status'];
    
    if ($status === 'completed') {
        return $this->getMessages($threadId);
    }
    
    if (in_array($status, ['failed', 'expired', 'cancelled'])) {
        $this->fail(502, "Run failed: {$status}");
    }
    
    sleep(POLLING_INTERVAL);
}

$this->fail(504, 'Polling timeout excedido');
```

---

## Guía de Implementación

### Implementar Nueva Fase

#### Paso 1: Crear Prompt en prompts.php

```php
// config/prompts.php
$PROMPTS[N] = [
    'p_nueva_fase' => [
        'id' => 'p_nueva_fase',
        'title' => 'Descripción de la fase',
        'prompt' => <<<'PROMPT'
OBJECTIVE:
...

INSTRUCTIONS:
...

OUTPUT SCHEMA:
...
PROMPT
        ,
        'placeholders' => ['FILE_ID', 'JSON_PREVIO'],
        'output_format' => 'json'
    ]
];
```

#### Paso 2: Crear Proxy (phase_XX_proxy.php)

```php
<?php
declare(strict_types=1);
require_once __DIR__ . '/proxy_common.php';

class PhaseXXProxy
{
    private ProxyRuntime $runtime;
    private array $config;
    private string $docBasename;
    private string $assistantId = '';
    
    public function __construct()
    {
        $this->runtime = new ProxyRuntime();
        require_once __DIR__ . '/lib_apio.php';
        $this->config = apio_load_config();
    }
    
    public function validateInput(array $input): void
    {
        // Validar campos específicos
    }
    
    public function execute(array $input): array
    {
        $this->validateInput($input);
        
        $assistantId = $this->getOrCreateAssistant();
        $threadId = $this->createThread();
        $this->addMessage($threadId, $input);
        $runId = $this->createRun($threadId, $assistantId);
        $result = $this->pollRun($threadId, $runId);
        $jsonData = $this->extractJSON($result);
        $this->saveFiles($jsonData);
        
        return $jsonData;
    }
    
    private function getOrCreateAssistant(): string
    {
        $docDir = $this->config['docs_dir'] . '/' . $this->docBasename;
        $assistantFile = "{$docDir}/{$this->docBasename}_XX.assistant_id";
        
        if (file_exists($assistantFile)) {
            $this->assistantId = trim(file_get_contents($assistantFile));
            $this->runtime->mark('assistants.reuse');
            return $this->assistantId;
        }
        
        // Cargar prompt
        require_once __DIR__ . '/../../config/prompts.php';
        $prompt = $PROMPTS[N]['p_nueva_fase']['prompt'];
        
        // Crear assistant
        $payload = [
            'model' => 'gpt-4o',
            'name' => 'Phase XX Processor',
            'instructions' => $prompt,
            'tools' => [['type' => 'code_interpreter']]
        ];
        
        $ch = curl_init('https://api.openai.com/v1/assistants');
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $this->runtime->apiKey,
                'Content-Type: application/json',
                'OpenAI-Beta: assistants=v2'
            ],
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30
        ]);
        
        $resp = curl_exec($ch);
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($status !== 200) {
            $this->runtime->fail(502, 'Failed to create assistant');
        }
        
        $result = json_decode($resp, true);
        $this->assistantId = $result['id'];
        
        // Guardar para reutilización
        if (!is_dir($docDir)) {
            @mkdir($docDir, 0755, true);
        }
        file_put_contents($assistantFile, $this->assistantId);
        
        $this->runtime->mark('assistants.created');
        return $this->assistantId;
    }
    
    // Implementar: createThread(), addMessage(), createRun(), pollRun(), extractJSON(), saveFiles()
}

// Ejecutar
$proxy = new PhaseXXProxy();
$input = json_decode(file_get_contents('php://input'), true) ?? [];

try {
    $result = $proxy->execute($input);
    echo json_encode([
        'output' => ['tex' => "\xEF\xBB\xBF", 'json' => $result],
        'debug' => ['http' => $proxy->runtime->debugHttp],
        'timeline' => $proxy->runtime->timeline
    ], JSON_UNESCAPED_UNICODE);
} catch (Exception $e) {
    $proxy->runtime->fail(500, $e->getMessage());
}
```

#### Paso 3: Crear Frontend (phase_XX.php)

```php
<?php
session_start();
require_once 'lib_apio.php';
require_once 'header.php';

$cfg = apio_load_config();
$docBasename = $_GET['doc'] ?? '';

if (!$docBasename) {
    die('Documento no especificado');
}

$docDir = $cfg['docs_dir'] . '/' . $docBasename;
$jsonPath = "{$docDir}/{$docBasename}.json";

$jsonPrevio = [];
if (file_exists($jsonPath)) {
    $jsonPrevio = json_decode(file_get_contents($jsonPath), true) ?? [];
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Fase XX - Descripción</title>
    <link rel="stylesheet" href="/code/css/phase_common.css">
</head>
<body>
    <div class="container">
        <h1>Fase XX: Descripción</h1>
        
        <!-- JSON Previo -->
        <section>
            <h2>Datos de Entrada (JSON Previo)</h2>
            <pre id="jsonPrevio"><?= json_encode($jsonPrevio, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) ?></pre>
        </section>
        
        <!-- Botón ejecutar -->
        <button id="btnExecute">Ejecutar Fase XX</button>
        
        <!-- Timeline -->
        <section id="statusPanel">
            <h3>Timeline de Ejecución</h3>
            <div id="statusIndicator"></div>
            <div id="timelineContent"></div>
        </section>
        
        <!-- Debug HTTP -->
        <section id="debugHttpPanel">
            <h3>Debug HTTP</h3>
            <div id="debugHttpContent"></div>
        </section>
        
        <!-- Resultados -->
        <section id="resultsSection" style="display:none;">
            <h2>Resultados</h2>
            <div id="resultsContent"></div>
        </section>
        
        <!-- Navegación -->
        <div class="actions">
            <button id="btnNext">Continuar a Fase [XX+1]</button>
            <button id="btnViewFiles">Ver Archivos</button>
        </div>
    </div>
    
    <script src="/code/js/phase_common.js"></script>
    <script>
    document.addEventListener('DOMContentLoaded', () => {
        const docBasename = '<?= $docBasename ?>';
        const jsonPrevio = <?= json_encode($jsonPrevio) ?>;
        
        document.getElementById('btnExecute').addEventListener('click', async () => {
            updateStatus('Ejecutando Fase XX...', 'loading');
            
            try {
                const response = await fetch('/code/php/phase_XX_proxy.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        doc_basename: docBasename,
                        json_previo: jsonPrevio
                    })
                });
                
                const data = await response.json();
                
                renderTimeline(data.timeline || [], 'timelineContent');
                renderDebugHttp(data.debug?.http || [], 'debugHttpContent');
                
                if (data.output?.json) {
                    document.getElementById('resultsContent').innerHTML = 
                        '<pre>' + JSON.stringify(data.output.json, null, 2) + '</pre>';
                    document.getElementById('resultsSection').style.display = 'block';
                    updateStatus('Fase XX completada', 'success');
                } else {
                    throw new Error(data.debug?.error || 'Error desconocido');
                }
            } catch (error) {
                handleError(error, 'resultsContent');
                updateStatus('Error en Fase XX', 'error');
            }
        });
        
        document.getElementById('btnNext').addEventListener('click', () => {
            window.location.href = `/code/php/phase_[XX+1].php?doc=${docBasename}`;
        });
        
        document.getElementById('btnViewFiles').addEventListener('click', () => {
            window.location.href = '/code/php/docs_list.php';
        });
    });
    </script>
</body>
</html>
```

#### Paso 4: Actualizar Fase Anterior

Agregar botón "Continuar a Fase XX" en `phase_[XX-1].php`:

```javascript
document.getElementById('btnNextPhase').addEventListener('click', () => {
    window.location.href = `/code/php/phase_XX.php?doc=${docBasename}`;
});
```

---

## Dependencias Entre Componentes

### Diagrama de Dependencias

```
config.json
    ↓
lib_apio.php ←── proxy_common.php ←── phase_XX_proxy.php
    ↓                                         ↓
prompts.php                            phase_XX.php
    ↓                                         ↓
OpenAI API                              phase_common.js
```

### Tabla de Dependencias

| Componente | Depende de | Proporciona a |
|------------|------------|---------------|
| `config.json` | - | Configuración global |
| `lib_apio.php` | `config.json` | Utilidades, paths, config |
| `proxy_common.php` | `lib_apio.php` | ProxyRuntime, HTTP utils |
| `prompts.php` | - | Templates de prompts |
| `phase_XX_proxy.php` | `proxy_common.php`, `lib_apio.php`, `prompts.php` | Lógica de fase |
| `phase_XX.php` | `lib_apio.php`, `header.php` | Interfaz de usuario |
| `phase_common.js` | - | Funciones JS comunes |

---

## Notas Finales

### Buenas Prácticas

1. **Reutilizar Assistant IDs**: Evita crear assistants duplicados, ahorra costos
2. **Validar JSON siempre**: Usar schemas estrictos para validar campos
3. **Logging exhaustivo**: Timeline + Debug HTTP permiten debugging eficiente
4. **Manejo de errores**: Siempre usar `fail()` con mensajes descriptivos
5. **UTF-8 con BOM**: Garantiza compatibilidad de caracteres especiales
6. **Polling eficiente**: Sleep inicial de 3s, luego cada 2s hasta 60s máximo

### Errores Comunes a Evitar

1. ❌ Usar `file_search` para CSVs → ✅ Usar `code_interpreter`
2. ❌ Omitir campo `tools` en attachments v2 → ✅ Incluir siempre
3. ❌ No guardar assistant_id → ✅ Persistir para reutilización
4. ❌ Polling sin timeout → ✅ Máximo 60s con 30 intentos
5. ❌ No validar JSON extraído → ✅ Validar contra schema
6. ❌ No registrar timeline → ✅ mark() en cada etapa

### Recursos Adicionales

- **OpenAI Assistants API v2**: https://platform.openai.com/docs/api-reference/assistants
- **JSON Schema**: https://json-schema.org/
- **PHP cURL**: https://www.php.net/manual/en/book.curl.php
- **Fetch API**: https://developer.mozilla.org/en-US/docs/Web/API/Fetch_API

---

**Fin del documento de Arquitectura Común**  
**Versión**: 1.0  
**Fecha**: 29 de octubre de 2025

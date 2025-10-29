# Fase 1B - Extracción de Texto de PDF

**Documentación técnica completa para desarrolladores e IA**  
**Actualizado: 29 de octubre de 2025**

---

## 📋 Índice

1. [Visión General](#visión-general)
2. [Arquitectura de la Fase](#arquitectura-de-la-fase)
3. [Componentes Detallados](#componentes-detallados)
4. [Flujo de Ejecución](#flujo-de-ejecución)
5. [Estructuras de Datos](#estructuras-de-datos)
6. [Dependencias](#dependencias)
7. [Troubleshooting](#troubleshooting)

---

## Visión General

### Propósito

La **Fase 1B** es el punto de entrada del procesamiento de documentos PDF. Su función es extraer el texto completo de un archivo PDF y guardarlo como `.txt` para su procesamiento posterior en las fases siguientes.

### Objetivos

1. **Extracción precisa**: Obtener todo el texto del PDF preservando estructura
2. **Formato Markdown**: Aplicar formateo mínimo para mantener legibilidad
3. **Persistencia**: Guardar texto extraído en `/docs/{NB}/ {NB}.txt`
4. **Logging**: Registrar proceso completo en `/docs/{NB}/{NB}.log`
5. **Preparación**: Habilitar flujo hacia Fase 1C

### Características Clave

- **API utilizada**: OpenAI Chat Completions API (NO Assistants API)
- **Modelo**: Configurable (gpt-4o-mini, gpt-4o, gpt-5-mini)
- **Formato salida**: Markdown UTF-8
- **Límite PDF**: 25 MB
- **Timeout**: 120 segundos

---

## Arquitectura de la Fase

### Diagrama de Componentes

```
┌────────────────────┐
│  upload_form.php   │  (Fase 1A: Subida PDF)
└────────────────────┘
          ↓
    Archivo PDF subido
          ↓
┌────────────────────┐
│   phase_1b.php     │  Frontend
│   (interfaz)       │
└────────────────────┘
          ↓ POST
┌────────────────────┐
│ phase_1b_proxy.php │  Proxy Backend
│  + ProxyRuntime    │
└────────────────────┘
          ↓
┌────────────────────┐
│   OpenAI Files     │  1. Upload PDF
│        API         │
└────────────────────┘
          ↓
┌────────────────────┐
│  OpenAI Chat       │  2. Extract text
│  Completions API   │
└────────────────────┘
          ↓
┌────────────────────┐
│  /docs/{NB}/       │  3. Save files
│    {NB}.txt        │
│    {NB}.log        │
└────────────────────┘
```

### Patrón de Diseño

**Proxy Pattern**: Separación entre interfaz de usuario (frontend) y lógica de negocio (proxy).

- **Frontend**: Recolecta parámetros, muestra resultados
- **Proxy**: Orquesta llamadas a OpenAI, guarda archivos
- **Runtime**: Utilidades HTTP, timeline, debug

---

## Componentes Detallados

### 1. Frontend: phase_1b.php

**Ubicación**: `/code/php/phase_1b.php`  
**Tamaño**: ~350 líneas  
**Tecnologías**: PHP, HTML5, JavaScript ES6

#### Responsabilidades

1. **Recibir documento desde URL**:
   ```php
   $docBasename = $_GET['doc'] ?? '';
   ```

2. **Verificar PDF existe**:
   ```php
   $pdfPath = "{$docsDir}/{$docBasename}/{$docBasename}.pdf";
   if (!file_exists($pdfPath)) {
       die('PDF no encontrado');
   }
   ```

3. **Mostrar configuración OpenAI**:
   - Selector de modelo (gpt-4o-mini, gpt-4o, gpt-5-mini)
   - Temperature (0.0 - 2.0, default 0.0)
   - Max tokens (500 - 16000, default 4000)
   - Top P (0.0 - 1.0, default 1.0)

4. **Ejecutar extracción**:
   ```javascript
   const response = await fetch('/code/php/phase_1b_proxy.php', {
       method: 'POST',
       headers: { 'Content-Type': 'application/json' },
       body: JSON.stringify({
           pdf_url: pdfPublicUrl,
           model: selectedModel,
           temperature: tempValue,
           max_tokens: maxTokens,
           top_p: topP,
           doc_basename: docBasename
       })
   });
   ```

5. **Visualizar resultados**:
   - Timeline de ejecución
   - Debug HTTP con detalles de requests
   - Texto extraído con opciones de copia/descarga
   - Metadatos (caracteres, tamaño, finish_reason)

#### Estructura HTML

```html
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Fase 1B - Extracción de Texto PDF</title>
    <link rel="stylesheet" href="/code/css/phase_common.css">
</head>
<body>
    <?php require_once 'header.php'; ?>
    
    <div class="container">
        <h1>Fase 1B: Extracción de Texto PDF</h1>
        <p>Documento: <strong><?= htmlspecialchars($docBasename) ?></strong></p>
        
        <!-- Configuración OpenAI -->
        <section class="config-section">
            <h2>Configuración de Extracción</h2>
            
            <div class="form-group">
                <label for="modelSelect">Modelo:</label>
                <select id="modelSelect">
                    <option value="gpt-4o-mini" selected>gpt-4o-mini (rápido, económico)</option>
                    <option value="gpt-4o">gpt-4o (balanceado)</option>
                    <option value="gpt-5-mini">gpt-5-mini (avanzado)</option>
                </select>
            </div>
            
            <div class="form-group">
                <label for="temperatureInput">Temperature: <span id="tempValue">0.0</span></label>
                <input type="range" id="temperatureInput" min="0" max="2" step="0.1" value="0">
            </div>
            
            <div class="form-group">
                <label for="maxTokensInput">Max Tokens: <span id="tokensValue">4000</span></label>
                <input type="range" id="maxTokensInput" min="500" max="16000" step="500" value="4000">
            </div>
            
            <div class="form-group">
                <label for="topPInput">Top P: <span id="topPValue">1.0</span></label>
                <input type="range" id="topPInput" min="0" max="1" step="0.05" value="1">
            </div>
        </section>
        
        <!-- Botón de ejecución -->
        <button id="btnExtract" class="btn-primary">
            🚀 Generar .TXT con OpenAI
        </button>
        
        <!-- Timeline de ejecución -->
        <section id="statusPanel" class="status-panel">
            <h3>⏱️ Timeline de Ejecución</h3>
            <div id="statusIndicator" class="status-indicator"></div>
            <div id="timelineContent" class="timeline-content"></div>
        </section>
        
        <!-- Debug HTTP -->
        <section id="debugHttpPanel" class="debug-panel">
            <h3>🔍 Debug HTTP</h3>
            <div id="debugHttpContent" class="debug-content"></div>
        </section>
        
        <!-- Resultados -->
        <section id="resultsSection" class="results-section" style="display:none;">
            <h2>📄 Texto Extraído</h2>
            <div class="results-metadata" id="resultsMetadata"></div>
            <div class="results-actions">
                <button id="btnCopy">📋 Copiar</button>
                <button id="btnDownload">💾 Descargar</button>
                <button id="btnViewAsFile">📂 Ver como Archivo</button>
            </div>
            <pre id="resultsContent" class="results-text"></pre>
        </section>
        
        <!-- Navegación -->
        <section class="actions-section">
            <button id="btnContinue" class="btn-success" style="display:none;">
                ➡️ Continuar a Fase 1C
            </button>
            <button id="btnViewFiles" class="btn-secondary">
                📁 Ver Archivos Generados
            </button>
        </section>
    </div>
    
    <script src="/code/js/phase_common.js"></script>
    <script src="/code/js/phase_1b.js"></script>
</body>
</html>
```

#### JavaScript: phase_1b.js

**Funciones principales:**

```javascript
// Actualizar valores de sliders en tiempo real
document.getElementById('temperatureInput').addEventListener('input', (e) => {
    document.getElementById('tempValue').textContent = e.target.value;
});

// Ejecutar extracción
document.getElementById('btnExtract').addEventListener('click', async () => {
    updateStatus('Iniciando extracción...', 'loading');
    
    const payload = {
        pdf_url: pdfPublicUrl,
        model: document.getElementById('modelSelect').value,
        temperature: parseFloat(document.getElementById('temperatureInput').value),
        max_tokens: parseInt(document.getElementById('maxTokensInput').value),
        top_p: parseFloat(document.getElementById('topPInput').value),
        doc_basename: docBasename
    };
    
    try {
        const response = await fetch('/code/php/phase_1b_proxy.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        });
        
        const data = await response.json();
        
        // Renderizar timeline
        renderTimeline(data.timeline || [], 'timelineContent');
        
        // Renderizar debug HTTP
        renderDebugHttp(data.debug?.http || [], 'debugHttpContent');
        
        // Mostrar resultados
        if (data.output?.tex) {
            const text = data.output.tex.replace(/^\uFEFF/, ''); // Quitar BOM
            document.getElementById('resultsContent').textContent = text;
            
            // Metadatos
            const metadata = `
                📊 Caracteres: ${text.length.toLocaleString()} | 
                📏 Palabras: ${text.split(/\s+/).length.toLocaleString()} |
                ✅ Estado: Completado
            `;
            document.getElementById('resultsMetadata').textContent = metadata;
            
            document.getElementById('resultsSection').style.display = 'block';
            document.getElementById('btnContinue').style.display = 'inline-block';
            
            updateStatus('✅ Extracción completada exitosamente', 'success');
        } else {
            throw new Error(data.debug?.error || 'No se recibió texto');
        }
    } catch (error) {
        handleError(error, 'resultsContent');
        updateStatus('❌ Error en extracción', 'error');
    }
});

// Copiar al portapapeles
document.getElementById('btnCopy').addEventListener('click', () => {
    const text = document.getElementById('resultsContent').textContent;
    copyToClipboard(text);
});

// Descargar como archivo
document.getElementById('btnDownload').addEventListener('click', () => {
    const text = document.getElementById('resultsContent').textContent;
    downloadAsFile(text, `${docBasename}.txt`);
});

// Navegar a Fase 1C
document.getElementById('btnContinue').addEventListener('click', () => {
    window.location.href = `/code/php/phase_1c.php?doc=${docBasename}`;
});
```

---

### 2. Proxy: phase_1b_proxy.php

**Ubicación**: `/code/php/phase_1b_proxy.php`  
**Tamaño**: ~195 líneas  
**Tecnología**: PHP 8.1+

#### Estructura de Clase

```php
<?php
declare(strict_types=1);
require_once __DIR__ . '/proxy_common.php';

$rt = new ProxyRuntime();
```

**NO usa clase**, ejecuta directamente con objeto `ProxyRuntime`.

#### Pre-Ejecución

```php
// 1. Leer input JSON
$in = $rt->readInput();

// 2. Extraer parámetros
$pdfUrl      = trim((string) ($in['pdf_url'] ?? ''));
$model       = trim((string) ($in['model'] ?? 'gpt-4o-mini'));
$temperature = (float) ($in['temperature'] ?? 0.0);
$topP        = (float) ($in['top_p'] ?? 1.0);
$maxTokens   = (int)   ($in['max_tokens'] ?? 1500);
$docBasename = trim((string) ($in['doc_basename'] ?? ''));

// 3. Validar doc_basename (requerido para guardar archivos)
if ($docBasename === '') {
    $rt->fail(400, 'doc_basename requerido para identificar el documento.');
}

// 4. Validar URL del PDF
$allowedHosts = []; // Opcional: ['cfle.plazza.xyz']
$rt->validateUrl($pdfUrl, $allowedHosts);
```

#### Ejecución

**Paso 1: Descargar PDF**

```php
$pdfPath = $rt->fetchToTmp($pdfUrl, 'application/pdf', 'pdf');

if (filesize($pdfPath) > 25 * 1024 * 1024) {
    @unlink($pdfPath);
    $rt->fail(413, 'El PDF supera el tamaño permitido (25 MB).');
}
```

**Paso 2: Subir a OpenAI Files API**

```php
$fileId = $rt->openaiUploadFile($pdfPath, 'application/pdf', 'assistants');
@unlink($pdfPath); // Eliminar archivo temporal
```

**Paso 3: Preparar prompt de extracción**

```php
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
```

**Paso 4: Llamar Chat Completions API**

```php
$payload = [
    'model' => $model,
    'max_completion_tokens' => 4000,
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

$res = $rt->openaiChatCompletions($payload);
$txt = $rt->extractOutputText($res);
```

#### Post-Ejecución

**Guardar archivos:**

```php
require_once __DIR__ . '/lib_apio.php';
$cfg = apio_load_config();
$docsDir = $cfg['docs_dir'] ?? '';

if ($docsDir && $docBasename && $txt !== '') {
    $docDir = $docsDir . DIRECTORY_SEPARATOR . $docBasename;
    
    // Crear directorio
    if (!is_dir($docDir)) {
        @mkdir($docDir, 0755, true);
    }
    
    // Guardar .txt
    $txtPath = $docDir . DIRECTORY_SEPARATOR . $docBasename . '.txt';
    $savedTxt = file_put_contents($txtPath, $txt);
    
    // Guardar .log
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
}
```

**Responder:**

```php
$rt->mark('done');
$rt->respondOk($txt);
```

---

## Flujo de Ejecución

### Timeline Completa

```
Tiempo  | Stage                    | Descripción
--------|--------------------------|------------------------------------------
+0ms    | init                     | Inicialización ProxyRuntime
+5ms    | input.read               | Leer payload JSON desde php://input
+10ms   | input.validated          | Validar URL y parámetros
+15ms   | fetch.start              | Iniciar descarga de PDF
+850ms  | fetch.done               | PDF descargado a /tmp/pxy_XXXXX.pdf
+860ms  | files.upload.start       | Iniciar upload a OpenAI Files API
+2100ms | files.upload.done        | Recibido file_id: file-XXXXXXXX
+2110ms | chat.completions.start   | Iniciar llamada Chat Completions API
+8500ms | chat.completions.done    | Respuesta recibida con texto extraído
+8510ms | save_files               | Guardar .txt y .log en /docs/{NB}/
+8520ms | done                     | Proceso completado
```

### Diagrama de Flujo

```
START
  ↓
[Leer Input JSON]
  ↓
[Validar doc_basename]
  ↓
[Validar pdf_url]
  ↓
[Descargar PDF a /tmp]
  ↓
[Verificar tamaño < 25MB] → NO → ERROR 413
  ↓ SÍ
[Subir PDF a OpenAI Files API]
  ↓
[Recibir file_id]
  ↓
[Preparar prompt de extracción]
  ↓
[Construir payload con file_id]
  ↓
[Llamar Chat Completions API]
  ↓
[Extraer texto de respuesta]
  ↓
[Crear directorio /docs/{NB}/] → Si no existe
  ↓
[Guardar {NB}.txt]
  ↓
[Guardar {NB}.log]
  ↓
[Responder con texto + timeline + debug]
  ↓
END
```

---

## Estructuras de Datos

### Input (Payload al Proxy)

```json
{
  "pdf_url": "https://cfle.plazza.xyz/docs/DOC001/DOC001.pdf",
  "model": "gpt-4o-mini",
  "temperature": 0.0,
  "top_p": 1.0,
  "max_tokens": 4000,
  "doc_basename": "DOC001"
}
```

### Output (Respuesta del Proxy)

```json
{
  "output": {
    "tex": "\uFEFF# Contenido Extraído\n\nTexto del PDF..."
  },
  "debug": {
    "http": [
      {
        "stage": "fetch",
        "status_code": 200,
        "ms": 835,
        "headers": {"accept": "application/pdf"},
        "url": "https://cfle.plazza.xyz/docs/DOC001/DOC001.pdf"
      },
      {
        "stage": "files.create",
        "status_code": 200,
        "ms": 1240,
        "headers": {
          "Authorization": "Bearer ***",
          "Content-Type": "multipart/form-data"
        },
        "payload_preview": {
          "purpose": "assistants",
          "filename": "DOC001.pdf"
        }
      },
      {
        "stage": "chat.completions",
        "status_code": 200,
        "ms": 6390,
        "headers": {
          "Authorization": "Bearer ***",
          "Content-Type": "application/json"
        }
      },
      {
        "stage": "save_files",
        "status_code": 200,
        "headers": {"info": "files_saved"},
        "files_created": {
          "txt_file": "/docs/DOC001/DOC001.txt",
          "log_file": "/docs/DOC001/DOC001.log",
          "txt_saved": true,
          "log_saved": true
        }
      }
    ]
  },
  "timeline": [
    {"ts": 1698765432000, "stage": "init"},
    {"ts": 1698765432005, "stage": "input.read"},
    {"ts": 1698765432010, "stage": "input.validated"},
    {"ts": 1698765432015, "stage": "fetch.start"},
    {"ts": 1698765432850, "stage": "fetch.done"},
    {"ts": 1698765432860, "stage": "files.upload.start"},
    {"ts": 1698765434100, "stage": "files.upload.done"},
    {"ts": 1698765434110, "stage": "chat.completions.start"},
    {"ts": 1698765440500, "stage": "chat.completions.done"},
    {"ts": 1698765440510, "stage": "save_files"},
    {"ts": 1698765440520, "stage": "done"}
  ]
}
```

### Archivo .log Generado

```json
{
  "timestamp": "2025-10-29 14:30:45",
  "phase": "1B",
  "status": "SUCCESS",
  "doc_basename": "DOC001",
  "pdf_url": "https://cfle.plazza.xyz/docs/DOC001/DOC001.pdf",
  "openai_params": {
    "model": "gpt-4o-mini",
    "temperature": 0.0,
    "max_completion_tokens": 4000,
    "top_p": 1.0
  },
  "results": {
    "txt_file": "/home/.../docs/DOC001/DOC001.txt",
    "txt_size": 15487,
    "characters_extracted": 15487,
    "saved_successfully": true
  },
  "timeline": [...],
  "debug_http": [...],
  "openai_response_sample": {
    "usage": {
      "prompt_tokens": 1250,
      "completion_tokens": 3840,
      "total_tokens": 5090
    },
    "model_used": "gpt-4o-mini-2024-07-18",
    "finish_reason": "stop"
  }
}
```

---

## Dependencias

### Dependencias Directas

| Componente | Depende de | Proporciona |
|------------|------------|-------------|
| `phase_1b.php` | `lib_apio.php`, `header.php` | Interfaz de usuario |
| `phase_1b_proxy.php` | `proxy_common.php`, `lib_apio.php` | Lógica de extracción |
| `phase_1b.js` | `phase_common.js` | Funcionalidad JavaScript |

### Dependencias de Configuración

```
config.json
  ├── docs_dir         → Para guardar archivos
  ├── public_base      → Para construir pdf_url
  └── apio_key         → API key de OpenAI

prompts.php
  └── p_extract_text   → Prompt de extracción (NO usado en F1B, solo referencia)
```

### Dependencias de OpenAI

| API | Endpoint | Propósito |
|-----|----------|-----------|
| Files API | `POST /v1/files` | Subir PDF |
| Chat Completions API | `POST /v1/chat/completions` | Extraer texto |

### Flujo de Dependencias

```
upload_form.php (F1A)
  ↓ Guarda PDF
phase_1b.php (Frontend)
  ↓ Carga PDF URL
phase_1b_proxy.php (Proxy)
  ↓ Usa
proxy_common.php (ProxyRuntime)
  ↓ Usa
OpenAI APIs
  ↓ Devuelve texto
phase_1b_proxy.php
  ↓ Guarda
/docs/{NB}/{NB}.txt
/docs/{NB}/{NB}.log
  ↓ Consumido por
phase_1c.php (siguiente fase)
```

---

## Troubleshooting

### Error: "doc_basename requerido"

**Causa**: No se envió `doc_basename` en el payload.

**Solución**:
```javascript
// Asegurar que se incluye en el payload
const payload = {
    ...otherParams,
    doc_basename: docBasename  // REQUERIDO
};
```

### Error: "El PDF supera el tamaño permitido (25 MB)"

**Causa**: PDF descargado es mayor a 25 MB.

**Solución**:
1. Comprimir PDF antes de subir
2. Aumentar límite en proxy:
   ```php
   if (filesize($pdfPath) > 50 * 1024 * 1024) { // Aumentar a 50MB
   ```

### Error: "Fallo en Files API: HTTP 400"

**Causa**: Formato de archivo no válido o corrupto.

**Solución**:
1. Verificar que el archivo es realmente un PDF válido
2. Verificar MIME type correcto:
   ```php
   $fileId = $rt->openaiUploadFile($pdfPath, 'application/pdf', 'assistants');
   ```

### Error: "Chat Completions API devolvió un cuerpo no JSON"

**Causa**: OpenAI API devolvió error HTML en lugar de JSON.

**Solución**:
1. Verificar API key válida en `config.json`
2. Revisar debug HTTP para ver respuesta exacta:
   ```json
   {
     "stage": "chat.completions",
     "status_code": 401,
     "response": "<!DOCTYPE html>..."
   }
   ```

### Texto Extraído Vacío

**Causa**: PDF no tiene texto seleccionable (es una imagen escaneada).

**Solución**:
- F1B **NO hace OCR**. Requiere PDF con texto seleccionable.
- Para PDFs escaneados, usar herramienta OCR externa antes de subir.

### Timeout en Chat Completions

**Causa**: PDF muy grande o procesamiento lento.

**Solución**:
1. Aumentar timeout:
   ```php
   curl_setopt($ch, CURLOPT_TIMEOUT, 180); // 3 minutos
   ```
2. Reducir `max_completion_tokens`
3. Usar modelo más rápido (`gpt-4o-mini`)

---

## Notas de Implementación

### Buenas Prácticas

1. **Validar siempre doc_basename**: Es la clave para organizar archivos
2. **Limpiar archivos temporales**: Usar `@unlink()` después de upload
3. **Logging exhaustivo**: Guardar timeline + debug HTTP en .log
4. **UTF-8 con BOM**: Garantizar compatibilidad de caracteres
5. **Max tokens generoso**: 4000 por defecto para PDFs largos

### Limitaciones Conocidas

1. **No hace OCR**: Solo extrae texto seleccionable
2. **Límite 25 MB**: Archivos mayores fallarán
3. **No preserva formato complejo**: Tablas complejas pueden perder estructura
4. **Imágenes**: Se reemplazan con `[IMAGE]`

### Diferencias con Otras Fases

| Característica | F1B | F2A-F2D |
|----------------|-----|---------|
| API usada | Chat Completions | Assistants API |
| Persistencia Assistant | NO | SÍ (.assistant_id) |
| Attachments | 1 archivo (PDF) | 1-3 archivos (.txt + CSVs) |
| Output | Texto plano | JSON estructurado |
| Polling | NO | SÍ (60s timeout) |

---

**Fin de documentación Fase 1B**  
**Versión**: 1.0  
**Fecha**: 29 de octubre de 2025

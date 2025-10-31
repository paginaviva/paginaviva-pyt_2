# Fase 3B - Optimización y Redacción Final SEO

**Documentación técnica completa**  
**Actualizado: 31 de octubre de 2025**

---

## 📋 Contenido

1. [Visión General](#visión-general)
2. [Arquitectura TRÍADA](#arquitectura-tríada)
3. [Componentes](#componentes)
4. [Flujo Completo](#flujo-completo)
5. [Estructuras de Datos](#estructuras-de-datos)
6. [Formato HTML](#formato-html)
7. [Dependencias](#dependencias)

---

## Visión General

### Propósito

**Fase 3B** es la fase FINAL que optimiza el JSON-FINAL con SEO y genera la **descripción larga HTML** del producto usando principios **E-E-A-T** (Experience, Expertise, Authoritativeness, Trustworthiness).

Integra la **TRÍADA completa**:
1. **FILE_ID** (documento original)
2. **JSON-FINAL** (24 campos de F2E)
3. **JSON-SEO** (kw, kw_lt, terminos_semanticos de F3A)

### Objetivos

1. **Optimizar** campos textuales del JSON-FINAL con terminología SEO
2. **Crear** `descripcion_larga_producto` en **formato HTML**
3. **Integrar** keywords y términos semánticos naturalmente
4. **Aplicar** E-E-A-T: experiencia técnica, autoridad, confiabilidad
5. **Formatear** con HTML semántico: `<p>`, `<strong>`, `<span>`, emoticonos 🔹
6. **Preservar** estructura técnica existente

### Características Clave

- **API**: OpenAI Assistants API v2
- **Tool**: `code_interpreter` (consistencia con F2D/F2E)
- **Model**: gpt-4o (default, configurable)
- **Assistant**: **Fresh cada vez** (sin persistencia)
- **Input**: TRÍADA (FILE_ID + JSON-FINAL + JSON-SEO)
- **Output**: JSON-FINAL optimizado + descripcion_larga_producto (HTML)
- **Formato**: HTML con `<p>`, `<strong>`, `<span class="highlight">`, emoticonos
- **Timeout**: 60s (polling)

---

## Arquitectura TRÍADA

### ¿Qué es la TRÍADA?

La **TRÍADA** es el sistema de 3 inputs simultáneos que alimentan el prompt de F3B:

```
TRÍADA = FILE_ID + JSON-FINAL + JSON-SEO
```

#### 1. FILE_ID (de F1C)
- **Fuente**: `{NB}.fileid`
- **Propósito**: Documento original como referencia de verdad
- **Uso**: Verificar precisión técnica, extraer detalles no presentes en JSON

#### 2. JSON-FINAL (de F2E)
- **Fuente**: `{NB}.json` (24 campos auditados)
- **Propósito**: Metadatos técnicos verificados
- **Uso**: Base estructural para optimización

#### 3. JSON-SEO (de F3A)
- **Fuente**: `{NB}_SEO.json` (kw, kw_lt, terminos_semanticos)
- **Propósito**: Terminología SEO específica del documento
- **Uso**: Integración natural en textos optimizados

### Inyección de TRÍADA en Prompt

```php
$instructions = str_replace(
    ['{FILE_ID}', '{JSON_FINAL}', '{JSON_SEO}'],
    [
        $this->fileId,                              // "file-abc123"
        json_encode($this->jsonFinal, JSON_UNESCAPED_UNICODE),  // {...24 campos...}
        json_encode($this->jsonSEO, JSON_UNESCAPED_UNICODE)     // {kw, kw_lt, ...}
    ],
    $promptTemplate
);
```

### Flujo

```
phase_3b_proxy.php
  ↓
1. Validar .fileid, .json, _SEO.json existen
  ↓
2. loadTriad()
   ├── Leer FILE_ID de .fileid
   ├── Leer JSON-FINAL de .json
   └── Leer JSON-SEO de _SEO.json
  ↓
3. createFreshAssistant()
   ├── Inyectar TRÍADA en prompt
   ├── tools: [{'type': 'code_interpreter'}]
   └── SIN .assistant_id persistente
  ↓
4. createThread()
5. addMessage() con FILE_ID adjunto
  ↓
6. createRun() y pollRun()
  ↓
7. extractJSON() → validar 24+ campos (incluye descripcion_larga_producto HTML)
  ↓
8. saveResults()
   ├── {NB}.json (SOBRESCRIBE con JSON optimizado + HTML)
   └── {NB}_3B.log
```

---

## Componentes

### 1. Clase: Phase3BProxy

**Ubicación**: `/code/php/phase_3b_proxy.php`  
**Tamaño**: ~702 líneas

#### Propiedades

```php
private array $timeline = [];
private array $debugHttp = [];
private string $docBasename;
private array $config;
private array $input;
private string $fileId;            // Del .fileid (F1C)
private array $jsonFinal;          // Del .json (F2E)
private array $jsonSEO;            // Del _SEO.json (F3A)
private ?string $assistantId = null;
private string $model = 'gpt-4o';  // Default model
```

#### Constructor

```php
public function __construct(string $docBasename, array $input = [])
{
    $this->docBasename = $docBasename;
    $this->input = $input;
    $this->config = apio_load_config();
    $this->model = $input['model'] ?? 'gpt-4o';
    $this->mark('init');
    
    $this->loadPrompts();  // Cargar PROMPTS global
}
```

#### Método Principal

```php
public function execute(array $input): array
{
    // 1. PRE-EJECUCIÓN: Validar y cargar TRÍADA
    $this->mark('pre_execution.start');
    $this->validateInput();
    $this->loadTriad();
    $this->mark('pre_execution.done');
    
    // 2. EJECUCIÓN: Optimizar con Assistant
    $this->mark('execution.start');
    $jsonOptimized = $this->runAssistantOptimization();
    $this->mark('execution.done');
    
    // 3. POST-EJECUCIÓN: Guardar JSON optimizado
    $this->mark('post_execution.start');
    $this->saveResults($jsonOptimized);
    $this->mark('post_execution.done');
    
    return [
        'output' => [
            'tex' => "Optimización SEO completada",
            'json_data' => $jsonOptimized
        ],
        'debug' => ['http' => $this->debugHttp],
        'timeline' => $this->timeline,
        'files_saved' => $this->getFilePaths()
    ];
}
```

### 2. Validación de TRÍADA

```php
private function validateInput(): void
{
    if (empty($this->docBasename)) {
        $this->fail(400, 'doc_basename requerido');
    }
    
    // Verificar FILE_ID existe (F1C)
    $fileidFile = $this->getFileidPath();
    if (!file_exists($fileidFile)) {
        $this->fail(400, 'Debe completar Fase 1C primero');
    }
    
    // Verificar JSON-FINAL existe (F2E)
    $jsonFinalFile = $this->getJsonFinalPath();
    if (!file_exists($jsonFinalFile)) {
        $this->fail(400, 'Debe completar Fase 2E primero');
    }
    
    // Verificar JSON-SEO existe (F3A)
    $jsonSeoFile = $this->getJsonSeoPath();
    if (!file_exists($jsonSeoFile)) {
        $this->fail(400, 'Debe completar Fase 3A primero');
    }
    
    $this->mark('validation.done');
}
```

### 3. Carga de TRÍADA

```php
private function loadTriad(): void
{
    $this->mark('triad.load.start');
    
    // 1. FILE_ID
    $this->fileId = trim(file_get_contents($this->getFileidPath()));
    if (empty($this->fileId)) {
        $this->fail(400, 'El archivo .fileid está vacío');
    }
    
    // 2. JSON-FINAL
    $jsonFinalContent = file_get_contents($this->getJsonFinalPath());
    $this->jsonFinal = json_decode($jsonFinalContent, true);
    if (!is_array($this->jsonFinal)) {
        $this->fail(400, 'JSON-FINAL no es válido o está corrupto');
    }
    
    // 3. JSON-SEO
    $jsonSeoContent = file_get_contents($this->getJsonSeoPath());
    $this->jsonSEO = json_decode($jsonSeoContent, true);
    if (!is_array($this->jsonSEO)) {
        $this->fail(400, 'JSON-SEO no es válido o está corrupto');
    }
    
    // Validar JSON-SEO tiene claves necesarias
    if (!isset($this->jsonSEO['kw']) && !isset($this->jsonSEO['kw_lt'])) {
        $this->fail(400, 'JSON-SEO no contiene claves válidas');
    }
    
    $this->mark('triad.loaded');
}
```

### 4. Prompt: p_optimize_final_content

```php
$PROMPTS[3]['p_optimize_final_content'] = [
    'prompt' => <<<'PROMPT'
ROLE:
You are an expert SEO copywriter specializing in Cofem fire detection systems.
Your task is to optimize the JSON-FINAL and create a comprehensive HTML product description 
using E-E-A-T principles (Experience, Expertise, Authoritativeness, Trustworthiness).

INPUTS PROVIDED (TRÍADA):
1. Original document: {FILE_ID}
2. Technical metadata (JSON-FINAL): {JSON_FINAL}
3. SEO terminology (JSON-SEO): {JSON_SEO}

OBJECTIVE:
1. Optimize existing text fields in JSON-FINAL by naturally integrating SEO keywords
2. Create new field: "descripcion_larga_producto" (HTML format, 800-1200 words)

INSTRUCTIONS FOR descripcion_larga_producto:

1. FORMAT: Use semantic HTML tags
   - <p>: Paragraphs
   - <strong>: Important keywords (kw, kw_lt)
   - <span class="highlight">: Technical terms (terminos_semanticos)
   - Emoticonos: 🔹 (bullets), 1️⃣2️⃣3️⃣ (steps), ✅ (benefits)

2. STRUCTURE (800-1200 words):
   - Introduction (150 words): What is the product, main kw
   - Technical Features (300 words): Specifications with semantic terms
   - Benefits and Applications (250 words): Real-world use cases
   - Why Choose This Product (200 words): E-E-A-T authority
   - Conclusion (100 words): CTA and kw_lt integration

3. SEO INTEGRATION:
   - Use "kw" from JSON-SEO in <h2> or first <strong>
   - Integrate "kw_lt" naturally in subheadings
   - Distribute "terminos_semanticos" with <span class="highlight">
   - Keyword density: 1.5-2.5% for main kw
   - LSI keywords: Use terminos_semanticos as semantic variations

4. E-E-A-T PRINCIPLES:
   - Experience: Mention real scenarios, applications, industry context
   - Expertise: Use precise technical language from document
   - Authoritativeness: Reference standards (EN 54, certifications)
   - Trustworthiness: Factual data, no exaggerations

5. LANGUAGE:
   - Spanish from Spain (RAE standards)
   - Avoid present continuous, colloquialisms
   - Technical but accessible tone

6. DO NOT:
   - Change technical values or specifications
   - Remove or rename existing fields
   - Generate fake data not in FILE_ID

OUTPUT FORMAT:
Return complete JSON-FINAL with all 24 existing fields + new field:
{
  ... (24 campos existentes optimizados) ...,
  "descripcion_larga_producto": "<p>...</p><p><strong>...</strong></p>..."
}
PROMPT
];
```

### 5. Fresh Assistant Creation con TRÍADA

```php
private function createFreshAssistant(): array
{
    $this->mark('assistant.create.start');
    
    global $PROMPTS;
    $promptTemplate = $PROMPTS[3]['p_optimize_final_content']['prompt'] ?? '';
    
    if (empty($promptTemplate)) {
        $this->fail(500, 'Prompt p_optimize_final_content no encontrado');
    }
    
    // Inyectar TRÍADA en prompt
    $instructions = str_replace(
        ['{FILE_ID}', '{JSON_FINAL}', '{JSON_SEO}'],
        [
            $this->fileId,
            json_encode($this->jsonFinal, JSON_UNESCAPED_UNICODE),
            json_encode($this->jsonSEO, JSON_UNESCAPED_UNICODE)
        ],
        $promptTemplate
    );
    
    $payload = [
        'model' => $this->model,
        'name' => 'F3B SEO Writer - ' . $this->docBasename,
        'instructions' => $instructions,
        'tools' => [['type' => 'code_interpreter']]
    ];
    
    // POST /assistants (sin persistencia)
    $ch = curl_init('https://api.openai.com/v1/assistants');
    // ... curl setup ...
    
    $assistant = json_decode($resp, true);
    $this->mark('assistant.created');
    
    return $assistant;
}
```

### 6. Optimización con Assistant

```php
private function runAssistantOptimization(): array
{
    // 1. Crear fresh assistant (TRÍADA inyectada)
    $assistantData = $this->createFreshAssistant();
    $this->assistantId = $assistantData['id'];
    
    // 2. Crear Thread
    $threadId = $this->createThread();
    
    // 3. Agregar mensaje con FILE_ID adjunto
    $this->addMessage($threadId);
    
    // 4. Ejecutar Run
    $runId = $this->createRun($threadId);
    
    // 5. Polling hasta completed
    $runResult = $this->pollRun($threadId, $runId);
    
    // 6. Obtener mensajes
    $messages = $this->getMessages($threadId);
    
    // 7. Extraer JSON optimizado
    $jsonOptimized = $this->extractJSON($messages);
    
    // 8. Validar descripcion_larga_producto existe y es HTML
    $this->validateHTMLDescription($jsonOptimized);
    
    return $jsonOptimized;
}
```

---

## Flujo Completo

### Timeline Detallada

```
+0ms      init
+5ms      pre_execution.start
+10ms     validation.done (.fileid, .json, _SEO.json verificados)
+15ms     triad.load.start
+20ms     triad.loaded (FILE_ID, JSON-FINAL, JSON-SEO en memoria)
+25ms     pre_execution.done
+30ms     execution.start
+35ms     assistant.create.start (inyecta TRÍADA)
+535ms    assistant.created (nuevo assistant con TRÍADA)
+540ms    thread.create
+840ms    thread.created
+845ms    message.add (FILE_ID adjunto)
+1045ms   message.added
+1050ms   run.create
+1200ms   run.created
+1205ms   polling.start
+5205ms   polling.attempt.0.in_progress
+9205ms   polling.attempt.1.in_progress
+15205ms  polling.attempt.2.in_progress (HTML largo, más tiempo)
+21205ms  polling.attempt.3.completed
+21210ms  polling.completed
+21215ms  messages.list
+21415ms  messages.list.done
+21420ms  json.extract.start (parsear HTML en campo)
+21425ms  json.extract.done
+21430ms  execution.done
+21435ms  post_execution.start
+21440ms  files.saved (JSON optimizado + log)
+21445ms  post_execution.done
```

**Nota**: F3B es más lento (~21s) que F3A (~10s) por generar HTML extenso (800-1200 palabras).

---

## Estructuras de Datos

### Input TRÍADA

```javascript
// 1. FILE_ID (string)
"file-abc123XYZ"

// 2. JSON-FINAL (24 campos de F2E)
{
  "file_id": "file-abc123XYZ",
  "nombre_archivo": "CLVR.pdf",
  "titulo_seo": "...",
  ... 20 campos más ...,
  "ficha_tecnica": "...",
  "resumen_tecnico": "..."
}

// 3. JSON-SEO (3 campos de F3A)
{
  "kw": "central analógica direccionable CLV",
  "kw_lt": ["central incendios CLV", "detector direccionable", ...],
  "terminos_semanticos": ["lazo analógico", "algoritmo inteligente", ...]
}
```

### Output JSON Optimizado (25 campos)

```json
{
  "file_id": "file-abc123XYZ",
  "nombre_archivo": "CLVR.pdf",
  "titulo_seo": "Central Analógica Direccionable CLV - Sistema Detección Incendios",
  "meta_descripcion": "Central CLV de Cofem: sistema de detección de incendios con 2 lazos analógicos direccionables...",
  ... 20 campos optimizados con SEO ...,
  "ficha_tecnica": "...(texto optimizado)...",
  "resumen_tecnico": "...(optimizado con kw_lt)...",
  
  "descripcion_larga_producto": "<p>La <strong>central analógica direccionable CLV</strong> de Cofem representa...</p><p>🔹 <span class=\"highlight\">Lazo analógico</span> con capacidad para...</p><p>1️⃣ Verificación de alarma automática</p><p>2️⃣ <strong>Detector direccionable</strong> con algoritmos...</p><p>✅ Cumple normativas EN 54-2 y EN 54-4</p>"
}
```

---

## Formato HTML

### Elementos Permitidos

```html
<!-- Párrafos -->
<p>Texto descriptivo con keywords naturalmente integradas.</p>

<!-- Énfasis en keywords principales -->
<p>La <strong>central analógica direccionable CLV</strong> ofrece...</p>

<!-- Términos técnicos destacados -->
<p>Utiliza <span class="highlight">lazo analógico</span> para...</p>

<!-- Listas con emoticonos -->
<p>🔹 Característica 1</p>
<p>🔹 Característica 2</p>

<!-- Pasos numerados -->
<p>1️⃣ Conexión de detectores</p>
<p>2️⃣ Configuración de zonas</p>
<p>3️⃣ Programación de algoritmos</p>

<!-- Beneficios con checkmarks -->
<p>✅ Mayor seguridad</p>
<p>✅ Mantenimiento reducido</p>
```

### Ejemplo Real de Salida

```html
<p>La <strong>central analógica direccionable CLV</strong> de Cofem es un sistema avanzado de detección de incendios diseñado para edificios comerciales, industriales y residenciales de gran envergadura. Con capacidad para gestionar hasta 2 <span class="highlight">lazos analógicos</span> y 250 dispositivos por lazo, esta central representa la tecnología más avanzada en protección contra incendios.</p>

<p><strong>Características Técnicas Principales</strong></p>

<p>🔹 <span class="highlight">Protocolo direccionable</span> que permite identificación única de cada detector</p>
<p>🔹 <span class="highlight">Algoritmos autoajustables</span> con compensación de deriva y suciedad</p>
<p>🔹 Fuente de alimentación conmutada de 27,6V con batería de respaldo</p>
<p>🔹 Pantalla LCD retroiluminada de 2 líneas × 40 caracteres</p>

<p><strong>Aplicaciones y Beneficios</strong></p>

<p>El <strong>sistema de detección incendios CLV</strong> es ideal para:</p>

<p>1️⃣ Edificios de oficinas con múltiples plantas</p>
<p>2️⃣ Centros comerciales y espacios públicos</p>
<p>3️⃣ Instalaciones industriales con requisitos de seguridad elevados</p>

<p>✅ Reduce falsas alarmas mediante verificación automática</p>
<p>✅ Cumple normativas europeas EN 54-2 y EN 54-4</p>
<p>✅ Mantenimiento simplificado con diagnóstico remoto</p>

<p><strong>¿Por Qué Elegir la Central CLV?</strong></p>

<p>Cofem, con más de 30 años de experiencia en sistemas de detección de incendios, garantiza la máxima fiabilidad. La <strong>central contra incendios CLV</strong> integra tecnología de <span class="highlight">detector inteligente</span> que se adapta automáticamente a las condiciones ambientales, minimizando el mantenimiento y maximizando la seguridad.</p>

<p>Proteja su inversión con un sistema certificado, probado en miles de instalaciones y respaldado por un equipo técnico especializado. Solicite más información sobre la <strong>central analógica direccionable CLV</strong> hoy mismo.</p>
```

---

## Dependencias

### Archivos Requeridos (TRÍADA)

| Archivo | Generado por | Contenido | Orden |
|---------|--------------|-----------|-------|
| `{NB}.fileid` | F1C | file_id | 1º |
| `{NB}.json` | F2E | 24 campos auditados | 3º (requiere F1C, F2D) |
| `{NB}_SEO.json` | F3A | kw, kw_lt, terminos_semanticos | 4º (requiere F1C) |
| `prompts.php` | Manual | Prompt optimización | - |

### Archivos Generados

| Archivo | Acción | Contenido |
|---------|--------|-----------|
| `{NB}.json` | **SOBRESCRIBE** | 25 campos (24 optimizados + descripcion_larga_producto HTML) |
| `{NB}_3B.log` | Crea | Log completo con TRÍADA inyectada y metadata |

**NO genera**:
- ❌ `{NB}_3B.assistant_id` (fresh cada vez, como F3A)

---

## Troubleshooting

### descripcion_larga_producto Sin HTML

**Síntoma**: Texto plano sin tags `<p>`, `<strong>`

**Causa**: Prompt no enfatiza HTML output.

**Solución**:
```
CRITICAL: descripcion_larga_producto MUST be HTML format.
Use <p>, <strong>, <span class="highlight"> tags.
DO NOT return plain text or Markdown.
```

### Keywords Forzadas o Artificiales

**Síntoma**: "central analógica direccionable CLV central analógica..." (repetición)

**Causa**: Sobre-optimización SEO.

**Solución en prompt**:
```
Integrate keywords NATURALLY. Avoid keyword stuffing.
Maintain readability and professional tone over SEO density.
```

### descripcion_larga_producto Demasiado Corta (<500 palabras)

**Causa**: Assistant no generó suficiente contenido.

**Solución**:
```
MANDATORY: descripcion_larga_producto must be 800-1200 words.
Include all sections: Introduction, Features, Benefits, Why Choose, Conclusion.
```

### HTML con Tags No Permitidos

**Síntoma**: `<div>`, `<h1>`, `<ul>` en output

**Solución**:
```
ALLOWED HTML TAGS ONLY:
- <p>: Paragraphs
- <strong>: Keywords
- <span class="highlight">: Technical terms
DO NOT USE: <div>, <h1-h6>, <ul>, <li>, <a>, <img>
```

### TRÍADA Incompleta (falta JSON-SEO)

**Error**: "Debe completar Fase 3A primero"

**Solución**: Ejecutar F3A antes de F3B para generar `{NB}_SEO.json`.

### JSON-FINAL No Contiene descripcion_larga_producto

**Causa**: Assistant no agregó el campo nuevo.

**Debug**:
```php
if (!isset($json['descripcion_larga_producto'])) {
    error_log("F3B: Missing descripcion_larga_producto");
    // Validar que prompt solicita campo explícitamente
}
```

---

## Comparación F2E vs F3A vs F3B

| Aspecto | F2E | F3A | F3B |
|---------|-----|-----|-----|
| **Input** | FILE_ID + JSON-F2D | Solo FILE_ID | **TRÍADA** |
| **Assistant** | Persistente | Fresh | Fresh |
| **Tool** | code_interpreter | file_search | code_interpreter |
| **Output archivo** | .json (sobrescribe) | _SEO.json (nuevo) | .json (sobrescribe) |
| **Output campos** | 24 (auditados) | 3 (SEO) | **25 (24+HTML)** |
| **Propósito** | Auditar precisión | Extraer términos | **Optimizar+HTML** |
| **Formato especial** | Ninguno | Ninguno | **HTML semántico** |
| **Velocidad** | ~10s | ~10s | **~21s** (HTML largo) |
| **Dependencias** | F1C, F2D | F1C | **F1C, F2E, F3A** |

---

**Fin de documentación Fase 3B**  
**Versión**: 1.0  
**Fecha**: 31 de octubre de 2025

# Fase 1C - Subida de Texto a OpenAI Files API

**Documentación técnica completa para desarrolladores e IA**  
**Actualizado: 29 de octubre de 2025**

---

## 📋 Índice

1. [Visión General](#visión-general)
2. [Arquitectura](#arquitectura)
3. [Componentes](#componentes)
4. [Flujo de Ejecución](#flujo-de-ejecución)
5. [Estructuras de Datos](#estructuras-de-datos)
6. [Troubleshooting](#troubleshooting)

---

## Visión General

### Propósito

La **Fase 1C** es un paso de preparación que toma el archivo `.txt` generado en F1B y lo sube a OpenAI Files API para obtener un `file_id`. Este `file_id` es requerido por todas las fases posteriores (F2A-F2D) que usan Assistants API.

### Objetivos

1. **Validar** existencia del archivo `.txt` de F1B
2. **Subir** archivo a OpenAI Files API con purpose='assistants'
3. **Persistir** `file_id` en archivo `.fileid`
4. **Habilitar** acceso desde fases F2A-F2D

### Características Clave

- **API**: OpenAI Files API (`POST /v1/files`)
- **Input**: Archivo `.txt` local (generado en F1B)
- **Output**: `file_id` de OpenAI
- **Límite**: 20 MB (configurado, máximo OpenAI es 512 MB)
- **Purpose**: 'assistants' (requerido para Assistants API)

---

## Arquitectura

### Diagrama de Flujo

```
phase_1b.php
  ↓ Genera
/docs/{NB}/{NB}.txt
  ↓
phase_1c.php (Frontend)
  ↓ POST
phase_1c_proxy.php (Proxy)
  ↓ Leer archivo local
{NB}.txt
  ↓ Upload
OpenAI Files API
  ↓ Devuelve
file_id
  ↓ Guardar
/docs/{NB}/{NB}.fileid
  ↓ Usado por
phase_2a_proxy.php
phase_2b_proxy.php
phase_2c_proxy.php
phase_2d_proxy.php
```

### Diferencia con F1B

| Característica | F1B | F1C |
|----------------|-----|-----|
| API | Chat Completions | Files API |
| Input | PDF desde URL | .txt local |
| Output | Texto extraído | file_id |
| Archivo guardado | .txt, .log | .fileid, .log |
| Procesamiento IA | Extracción texto | Solo upload |

---

## Componentes

### 1. Proxy: phase_1c_proxy.php

**Clase**: `Phase1CProxy`  
**Tamaño**: ~250 líneas

#### Constructor

```php
public function __construct(string $docBasename)
{
    $this->docBasename = $docBasename;
    $this->config = apio_load_config();
    $this->mark('init');
}
```

#### Método Principal: execute()

```php
public function execute(array $input): array
{
    // 1. PRE-EJECUCIÓN
    $this->mark('pre_execution.start');
    $this->validateInput();  // Verificar .txt existe
    $this->mark('pre_execution.done');
    
    // 2. EJECUCIÓN
    $this->mark('execution.start');
    $fileId = $this->uploadToOpenAI();  // Subir a Files API
    $this->mark('execution.done');
    
    // 3. POST-EJECUCIÓN
    $this->mark('post_execution.start');
    $this->saveFileId($fileId);  // Guardar .fileid y .log
    $this->mark('post_execution.done');
    
    return [
        'output' => [
            'tex' => "\xEF\xBB\xBFArchivo subido exitosamente a OpenAI",
            'file_id' => $fileId
        ],
        'debug' => ['http' => $this->debugHttp],
        'timeline' => $this->timeline,
        'files_saved' => $this->getFilePaths()
    ];
}
```

#### Validación de Entrada

```php
private function validateInput(): void
{
    if (empty($this->docBasename)) {
        $this->fail(400, 'doc_basename requerido');
    }
    
    // Verificar .txt existe
    $txtFile = $this->getTxtFilePath();
    if (!file_exists($txtFile)) {
        $this->fail(400, 'Debe completar Fase 1B primero. No se encontró: ' . basename($txtFile));
    }
    
    // Verificar tamaño (límite: 20 MB)
    $fileSize = filesize($txtFile);
    if ($fileSize > 20 * 1024 * 1024) {
        $this->fail(413, 'El archivo .txt excede el tamaño permitido (20 MB)');
    }
    
    $this->mark('validation.done');
}
```

#### Upload a OpenAI

```php
private function uploadToOpenAI(): string
{
    $apiKey = $this->config['apio_key'] ?? '';
    if (!$apiKey) {
        $this->fail(500, 'API key de OpenAI no configurada');
    }
    
    $txtFile = $this->getTxtFilePath();
    $this->mark('files.upload.start');
    
    // Crear request multipart/form-data
    $ch = curl_init('https://api.openai.com/v1/files');
    $postFields = [
        'purpose' => 'assistants',  // OBLIGATORIO
        'file' => new CURLFile($txtFile, 'text/plain', basename($txtFile)),
    ];
    
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $postFields,
        CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $apiKey],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 60,
    ]);
    
    $t0 = microtime(true);
    $resp = curl_exec($ch);
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_error($ch);
    curl_close($ch);
    $t1 = microtime(true);
    
    // Debug HTTP
    $this->debugHttp[] = [
        'stage' => 'files.upload',
        'status_code' => $status,
        'ms' => (int) round(($t1 - $t0) * 1000),
        'endpoint' => 'https://api.openai.com/v1/files',
        'method' => 'POST',
        'headers' => ['Authorization' => 'Bearer ***', 'Content-Type' => 'multipart/form-data'],
        'file_info' => [
            'filename' => basename($txtFile),
            'size' => filesize($txtFile),
            'purpose' => 'assistants'
        ]
    ];
    
    if ($resp === false || $status < 200 || $status >= 300) {
        $this->fail(502, 'Error al subir archivo a OpenAI: ' . ($err ?: ('HTTP ' . $status . ' - ' . $resp)));
    }
    
    $result = json_decode($resp, true);
    $fileId = $result['id'] ?? null;
    
    if (!$fileId) {
        $this->fail(502, 'OpenAI Files API no devolvió file_id válido');
    }
    
    $this->mark('files.upload.done');
    return $fileId;
}
```

#### Guardar file_id

```php
private function saveFileId(string $fileId): void
{
    $docsDir = $this->config['docs_dir'] ?? '';
    $docDir = $docsDir . DIRECTORY_SEPARATOR . $this->docBasename;
    
    if (!is_dir($docDir)) {
        @mkdir($docDir, 0755, true);
    }
    
    // Guardar .fileid
    $fileIdPath = $docDir . DIRECTORY_SEPARATOR . $this->docBasename . '.fileid';
    file_put_contents($fileIdPath, $fileId);
    
    // Guardar .log
    $logPath = $docDir . DIRECTORY_SEPARATOR . $this->docBasename . '_1C.log';
    $logData = [
        'timestamp' => date('Y-m-d H:i:s'),
        'phase' => '1C',
        'status' => 'SUCCESS',
        'doc_basename' => $this->docBasename,
        'file_id' => $fileId,
        'source_file' => $this->getTxtFilePath(),
        'timeline' => $this->timeline,
        'debug_http' => $this->debugHttp
    ];
    
    file_put_contents($logPath, json_encode($logData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    $this->mark('files.saved');
}
```

### 2. Frontend: phase_1c.php

**Ubicación**: `/code/php/phase_1c.php`

#### Funcionalidad Principal

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

// Verificar archivo .txt existe
$txtPath = "{$cfg['docs_dir']}/{$docBasename}/{$docBasename}.txt";
if (!file_exists($txtPath)) {
    die('Debe completar Fase 1B primero');
}

$txtContent = file_get_contents($txtPath);
$txtSize = filesize($txtPath);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Fase 1C - Subida a OpenAI Files</title>
    <link rel="stylesheet" href="/code/css/phase_common.css">
</head>
<body>
    <div class="container">
        <h1>Fase 1C: Subir Texto a OpenAI Files API</h1>
        <p>Documento: <strong><?= htmlspecialchars($docBasename) ?></strong></p>
        
        <!-- Información del archivo -->
        <section class="file-info">
            <h2>Archivo a Subir</h2>
            <p>📄 Archivo: <code><?= $docBasename ?>.txt</code></p>
            <p>📏 Tamaño: <?= number_format($txtSize) ?> bytes</p>
            <p>📝 Caracteres: <?= number_format(strlen($txtContent)) ?></p>
        </section>
        
        <!-- Botón de ejecución -->
        <button id="btnUpload" class="btn-primary">
            ☁️ Subir a OpenAI Files API
        </button>
        
        <!-- Timeline -->
        <section id="statusPanel">
            <h3>Timeline</h3>
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
            <h2>Archivo Subido</h2>
            <div id="resultsContent"></div>
        </section>
        
        <!-- Navegación -->
        <div class="actions">
            <button id="btnContinue" style="display:none;">
                ➡️ Continuar a Fase 2A
            </button>
        </div>
    </div>
    
    <script src="/code/js/phase_common.js"></script>
    <script>
    document.addEventListener('DOMContentLoaded', () => {
        const docBasename = '<?= $docBasename ?>';
        
        document.getElementById('btnUpload').addEventListener('click', async () => {
            updateStatus('Subiendo archivo a OpenAI...', 'loading');
            
            try {
                const response = await fetch('/code/php/phase_1c_proxy.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ doc_basename: docBasename })
                });
                
                const data = await response.json();
                
                renderTimeline(data.timeline || [], 'timelineContent');
                renderDebugHttp(data.debug?.http || [], 'debugHttpContent');
                
                if (data.output?.file_id) {
                    document.getElementById('resultsContent').innerHTML = `
                        <p>✅ Archivo subido exitosamente</p>
                        <p><strong>File ID:</strong> <code>${data.output.file_id}</code></p>
                    `;
                    document.getElementById('resultsSection').style.display = 'block';
                    document.getElementById('btnContinue').style.display = 'inline-block';
                    updateStatus('✅ Upload completado', 'success');
                } else {
                    throw new Error(data.debug?.error || 'No se recibió file_id');
                }
            } catch (error) {
                handleError(error, 'resultsContent');
                updateStatus('❌ Error en upload', 'error');
            }
        });
        
        document.getElementById('btnContinue').addEventListener('click', () => {
            window.location.href = `/code/php/phase_2a.php?doc=${docBasename}`;
        });
    });
    </script>
</body>
</html>
```

---

## Flujo de Ejecución

### Timeline Típica

```
Tiempo  | Stage                    | Descripción
--------|--------------------------|------------------------------------------
+0ms    | init                     | Inicialización Phase1CProxy
+5ms    | pre_execution.start      | Inicio validación
+8ms    | validation.done          | .txt existe y tamaño válido
+10ms   | pre_execution.done       | Validación completa
+12ms   | execution.start          | Inicio upload
+15ms   | files.upload.start       | Inicio POST a /v1/files
+1250ms | files.upload.done        | Recibido file_id
+1255ms | execution.done           | Upload completado
+1260ms | post_execution.start     | Inicio guardado
+1265ms | files.saved              | .fileid y .log guardados
+1270ms | post_execution.done      | Proceso completado
```

### Diagrama de Secuencia

```
Frontend                Proxy                OpenAI Files API
   |                      |                          |
   |---POST {doc}-------->|                          |
   |                      |                          |
   |                      |--Verificar .txt existe   |
   |                      |                          |
   |                      |--Leer archivo local      |
   |                      |                          |
   |                      |--POST /v1/files--------->|
   |                      |  (multipart/form-data)   |
   |                      |                          |
   |                      |<-----file_id-------------|
   |                      |                          |
   |                      |--Guardar .fileid         |
   |                      |--Guardar .log            |
   |                      |                          |
   |<--{file_id, debug}---|                          |
   |                      |                          |
   |--Mostrar file_id     |                          |
   |--Habilitar Fase 2A   |                          |
```

---

## Estructuras de Datos

### Input (Payload al Proxy)

```json
{
  "doc_basename": "DOC001"
}
```

### Output (Respuesta del Proxy)

```json
{
  "output": {
    "tex": "\uFEFFArchivo subido exitosamente a OpenAI",
    "file_id": "file-XXXXXXXXXXXXXXXXXXXXXXXX"
  },
  "debug": {
    "http": [
      {
        "stage": "files.upload",
        "status_code": 200,
        "ms": 1235,
        "endpoint": "https://api.openai.com/v1/files",
        "method": "POST",
        "headers": {
          "Authorization": "Bearer ***",
          "Content-Type": "multipart/form-data"
        },
        "file_info": {
          "filename": "DOC001.txt",
          "size": 15487,
          "purpose": "assistants"
        }
      },
      {
        "stage": "files.upload.response",
        "status_code": 200,
        "file_id": "file-XXXXXXXXXXXXXXXXXXXXXXXX",
        "raw_response": {
          "id": "file-XXXXXXXXXXXXXXXXXXXXXXXX",
          "object": "file",
          "bytes": 15487,
          "created_at": 1698765432,
          "filename": "DOC001.txt",
          "purpose": "assistants"
        }
      }
    ]
  },
  "timeline": [
    {"ts": 1698765432000, "stage": "init"},
    {"ts": 1698765432005, "stage": "pre_execution.start"},
    {"ts": 1698765432008, "stage": "validation.done"},
    {"ts": 1698765432010, "stage": "pre_execution.done"},
    {"ts": 1698765432012, "stage": "execution.start"},
    {"ts": 1698765432015, "stage": "files.upload.start"},
    {"ts": 1698765433250, "stage": "files.upload.done"},
    {"ts": 1698765433255, "stage": "execution.done"},
    {"ts": 1698765433260, "stage": "post_execution.start"},
    {"ts": 1698765433265, "stage": "files.saved"},
    {"ts": 1698765433270, "stage": "post_execution.done"}
  ],
  "files_saved": {
    "fileid_file": "/docs/DOC001/DOC001.fileid",
    "log_file": "/docs/DOC001/DOC001_1C.log"
  }
}
```

### Archivo .fileid Generado

**Ubicación**: `/docs/{NB}/{NB}.fileid`  
**Contenido**: Solo el file_id (una línea)

```
file-XXXXXXXXXXXXXXXXXXXXXXXX
```

### Archivo .log Generado

**Ubicación**: `/docs/{NB}/{NB}_1C.log`

```json
{
  "timestamp": "2025-10-29 15:45:12",
  "phase": "1C",
  "status": "SUCCESS",
  "doc_basename": "DOC001",
  "file_id": "file-XXXXXXXXXXXXXXXXXXXXXXXX",
  "source_file": "/home/.../docs/DOC001/DOC001.txt",
  "timeline": [...],
  "debug_http": [...]
}
```

---

## Dependencias

### Archivos Requeridos

| Archivo | Generado por | Propósito |
|---------|--------------|-----------|
| `{NB}.txt` | Fase 1B | Archivo a subir |
| `config.json` | Manual | API key, paths |

### Archivos Generados

| Archivo | Usado por | Propósito |
|---------|-----------|-----------|
| `{NB}.fileid` | Fases 2A-2D | file_id de OpenAI |
| `{NB}_1C.log` | Debug/audit | Log del proceso |

### Componentes de Código

```
phase_1c_proxy.php
  ├── lib_apio.php          → Configuración
  └── (NO usa proxy_common.php porque no hace HTTP complexo)

phase_1c.php
  ├── lib_apio.php          → Configuración
  ├── header.php            → Header común
  └── phase_common.js       → Funciones JavaScript
```

---

## Troubleshooting

### Error: "Debe completar Fase 1B primero"

**Causa**: No existe `{NB}.txt`

**Solución**:
1. Ejecutar Fase 1B primero
2. Verificar que el archivo se guardó correctamente:
   ```bash
   ls -lh /docs/{NB}/{NB}.txt
   ```

### Error: "El archivo .txt excede el tamaño permitido (20 MB)"

**Causa**: Archivo muy grande.

**Solución**:
1. Aumentar límite en proxy:
   ```php
   if ($fileSize > 50 * 1024 * 1024) { // 50 MB
   ```
2. O dividir documento en partes más pequeñas

### Error 400: "Invalid file format"

**Causa**: MIME type incorrecto o archivo corrupto.

**Solución**:
1. Verificar que es UTF-8 válido:
   ```bash
   file -i /docs/{NB}/{NB}.txt
   ```
2. Asegurar MIME type correcto:
   ```php
   new CURLFile($txtFile, 'text/plain', basename($txtFile))
   ```

### Error 401: "Incorrect API key"

**Causa**: API key inválida en `config.json`

**Solución**:
```json
{
  "apio_key": "sk-proj-VALID_KEY_HERE"
}
```

### No se guarda .fileid

**Causa**: Permisos de escritura en `/docs/{NB}/`

**Solución**:
```bash
chmod 755 /docs/{NB}/
chown www-data:www-data /docs/{NB}/
```

---

## Notas de Implementación

### Diferencias con Proxy Común

F1C **NO usa** `ProxyRuntime` de `proxy_common.php` porque:
1. Solo hace una llamada HTTP simple (upload)
2. No necesita polling
3. No usa Chat Completions/Assistants API
4. Implementa su propia clase `Phase1CProxy`

### Purpose 'assistants' es Obligatorio

```php
'purpose' => 'assistants'  // REQUERIDO para usar con Assistants API
```

Otros values posibles:
- `'fine-tune'` - Para fine-tuning
- `'assistants'` - Para Assistants API (nuestro caso)
- `'batch'` - Para batch processing

### Persistencia del file_id

El archivo `.fileid` debe persistir porque:
1. **F2A-F2D** lo leen para attachments
2. **Evita re-uploads** innecesarios
3. **OpenAI cobra** por almacenamiento de archivos

### Límites de OpenAI Files API

| Característica | Límite |
|----------------|--------|
| Tamaño máximo | 512 MB |
| Tipos soportados | .txt, .csv, .pdf, .json, etc. |
| Almacenamiento | Ilimitado (con costo) |
| Retención | Hasta eliminación manual |

---

**Fin de documentación Fase 1C**  
**Versión**: 1.0  
**Fecha**: 29 de octubre de 2025

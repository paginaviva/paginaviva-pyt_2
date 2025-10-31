# Fase 2E - Auditoría y Verificación Final (QA-Final)

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

**Fase 2E** es la fase final de auditoría que verifica, corrige y refina el JSON-F2D (24 campos) contra el documento original. **NO añade nuevos campos**, solo valida y optimiza los existentes para producir el **JSON-FINAL** listo para publicación.

### Objetivos

1. **Auditar** todos los 24 campos contra documento original (FILE_ID)
2. **Verificar** precisión factual, valores numéricos, unidades técnicas
3. **Corregir** inexactitudes, omisiones o expresiones vagas
4. **Refinar** claridad técnica y consistencia con texto fuente
5. **Preservar** `resumen_tecnico` sin modificarlo (campo intocable)
6. **Producir** JSON-FINAL verificado y optimizado

### Características Clave

- **API**: OpenAI Assistants API v2
- **Tool**: `code_interpreter` (consistencia con F2D)
- **Model**: gpt-4o (configurable)
- **Input**: FILE_ID + JSON-F2D (24 campos)
- **Output**: JSON-FINAL (24 campos auditados)
- **Campo intocable**: `resumen_tecnico`
- **Timeout**: 60s (polling)

---

## Arquitectura

### Diferencia con F2D

| Característica | F2D | F2E |
|----------------|-----|-----|
| Propósito | Generar textos técnicos | **Auditar y verificar** |
| Campos entrada | 22 | 24 |
| Campos salida | 24 (22+2 nuevos) | 24 (mismos, auditados) |
| Prompt | p_generate_technical_sheet | **p_audit_final_verification** |
| Modifica datos | SÍ (genera ficha_tecnica/resumen_tecnico) | SÍ (corrige inexactitudes) |
| Campo preservado | Ninguno | **`resumen_tecnico`** |

### Flujo

```
phase_2e_proxy.php
  ↓
1. Validar file_id (F1C)
2. Validar .json existe (F2D con 24 campos)
3. Leer JSON-F2D
  ↓
4. getOrCreateAssistant() con prompt auditoría
   tools: [{'type': 'code_interpreter'}]
  ↓
5. createThread()
6. addMessage() con FILE_ID + JSON-F2D en prompt
  ↓
7. createRun() y pollRun()
  ↓
8. extractJSON() → validar 24 campos + preservar resumen_tecnico
  ↓
9. saveResults()
   ├── {NB}.json (SOBRESCRIBE con 24 campos auditados = JSON-FINAL)
   ├── {NB}_2E.log
   └── {NB}_2E.assistant_id
```

---

## Componentes

### 1. Clase: Phase2EProxy

**Ubicación**: `/code/php/phase_2e_proxy.php`  
**Tamaño**: ~633 líneas

#### Propiedades

```php
private array $timeline = [];
private array $debugHttp = [];
private string $docBasename;
private array $config;
private array $input;
private string $fileId;            // Del archivo .fileid (F1C)
private array $jsonPrevio;         // JSON-F2D (24 campos)
private ?string $assistantId = null;
```

#### Método Principal

```php
public function execute(array $input): array
{
    // 1. PRE-EJECUCIÓN
    $this->mark('pre_execution.start');
    $this->validateInput();
    $this->mark('pre_execution.done');
    
    // 2. EJECUCIÓN: Auditoría con Assistant
    $this->mark('execution.start');
    $jsonResult = $this->runAssistantAudit();
    $this->mark('execution.done');
    
    // 3. POST-EJECUCIÓN: Guardar JSON-FINAL
    $this->mark('post_execution.start');
    $this->saveResults($jsonResult);
    $this->mark('post_execution.done');
    
    return [
        'output' => [
            'tex' => "Auditoría y verificación completada",
            'json_data' => $jsonResult,
            'json_previo' => $this->jsonPrevio  // Para comparación
        ],
        'debug' => ['http' => $this->debugHttp],
        'timeline' => $this->timeline,
        'files_saved' => $this->getFilePaths()
    ];
}
```

### 2. Prompt: p_audit_final_verification

```php
$PROMPTS[2]['p_audit_final_verification'] = [
    'prompt' => <<<'PROMPT'
ROLE:
Act as a technical documentation auditor specialised in Cofem fire detection systems.
Your task is to verify, correct, and refine the full JSON-F2D using the original document.
All writing must comply strictly with Real Academia Española (RAE) standards.

OBJECTIVE:
Perform final technical review and consistency audit of JSON-F2D by cross-checking 
each value with the content of the original document.

INSTRUCTIONS:
1. Use exclusively:
   - Original Cofem document: {FILE_ID}
   - JSON-F2D from previous phase: {JSON_PREVIO}

2. Compare EVERY field in JSON-F2D with original text:
   - Verify factual accuracy, numerical values, technical units
   - Correct or complete inaccuracies, omissions, vague expressions
   - Reformulate phrases for technical clarity
   - Add missing data if present in document

3. You must NOT modify, rewrite, or regenerate:
   - `resumen_tecnico` (this field must remain exactly as received)

4. Maintain strict editorial and linguistic standards:
   - Write exclusively in Spanish from Spain (RAE)
   - Avoid present continuous, colloquial terms, Latin American expressions
   - Use precise and consistent technical language
   - Ensure proper use of technical symbols, units, formatting

5. Do not remove, rename, or reorder any fields

6. If a field cannot be verified, retain current value without modification

7. Output must be single clean valid JSON object (JSON-FINAL)

MANDATORY OUTPUT SCHEMA (JSON-FINAL):
{
  ...24 campos con valores auditados...
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
    
    // Verificar .json existe (F2D con 24 campos)
    $jsonFile = $this->getJsonFilePath();
    if (!file_exists($jsonFile)) {
        $this->fail(400, 'Debe completar Fase 2D primero');
    }
    
    // Leer JSON-F2D
    $this->jsonPrevio = json_decode(file_get_contents($jsonFile), true);
    if (!$this->jsonPrevio) {
        $this->fail(400, 'El archivo JSON de F2D está corrupto');
    }
    
    $this->mark('validation.done');
}
```

### 4. Auditoría con Assistant

```php
private function runAssistantAudit(): array
{
    // 1. Obtener o crear assistant F2E
    $assistantData = $this->getOrCreateAssistant();
    $this->assistantId = $assistantData['id'];
    
    // 2. Crear Thread
    $threadId = $this->createThread();
    
    // 3. Agregar mensaje con FILE_ID + JSON-F2D
    $this->addMessage($threadId);
    
    // 4. Ejecutar Run
    $runId = $this->createRun($threadId);
    
    // 5. Polling hasta completed
    $runResult = $this->pollRun($threadId, $runId);
    
    // 6. Obtener mensajes
    $messages = $this->getMessages($threadId);
    
    // 7. Extraer JSON-FINAL
    $jsonFinal = $this->extractJSON($messages);
    
    // 8. Validar que resumen_tecnico NO cambió
    $this->validateResumenPreserved($jsonFinal);
    
    return $jsonFinal;
}
```

### 5. Validación Especial: Preservar resumen_tecnico

```php
private function validateResumenPreserved(array $jsonFinal): void
{
    $resumenOriginal = $this->jsonPrevio['resumen_tecnico'] ?? '';
    $resumenNuevo = $jsonFinal['resumen_tecnico'] ?? '';
    
    if ($resumenOriginal !== $resumenNuevo) {
        // Advertencia en log pero no falla
        error_log(
            "F2E WARNING: resumen_tecnico modificado para {$this->docBasename}.\n" .
            "  Original ({$resumenOriginal})\n" .
            "  Nuevo ({$resumenNuevo})"
        );
    }
}
```

---

## Flujo Completo

### Timeline Detallada

```
+0ms      init
+5ms      pre_execution.start
+10ms     validation.done (file_id + JSON-F2D leídos)
+15ms     pre_execution.done
+20ms     execution.start
+25ms     assistant.get_or_create.start
+30ms     assistant.reused (o assistant.create 200-500ms)
+35ms     thread.create (100-300ms)
+340ms    thread.create.done
+345ms    message.add (100-200ms, inyecta FILE_ID + JSON-F2D)
+545ms    message.add.done
+550ms    run.create (50-150ms)
+700ms    run.create.done
+705ms    polling.start
+3705ms   polling.attempt.0.in_progress
+5705ms   polling.attempt.1.in_progress
+9705ms   polling.attempt.2.completed
+9710ms   polling.completed
+9715ms   messages.list (100-200ms)
+9915ms   messages.list.done
+9920ms   json.extract.start
+9925ms   json.extract.done
+9930ms   execution.done
+9935ms   post_execution.start
+9940ms   files.saved
+9945ms   post_execution.done
```

---

## Estructuras de Datos

### Input JSON-F2D (24 campos)

```json
{
  "file_id": "file-XXX",
  "nombre_archivo": "CLVR.pdf",
  ... 20 campos más ...,
  "ficha_tecnica": "...",
  "resumen_tecnico": "Central analógica direccionable..."
}
```

### Output JSON-FINAL (24 campos auditados)

```json
{
  "file_id": "file-XXX",
  "nombre_archivo": "CLVR.pdf",
  ... valores corregidos/refinados ...,
  "ficha_tecnica": "...texto mejorado...",
  "resumen_tecnico": "Central analógica direccionable..."  ← SIN CAMBIOS
}
```

### Respuesta del Proxy

```json
{
  "output": {
    "tex": "Auditoría y verificación completada",
    "json_data": { ...JSON-FINAL con 24 campos... },
    "json_previo": { ...JSON-F2D original para comparación... }
  },
  "debug": {
    "http": [...]
  },
  "timeline": [...],
  "files_saved": {
    "json": "/docs/DOC001/DOC001.json",
    "log": "/docs/DOC001/DOC001_2E.log",
    "assistant_id": "/docs/DOC001/DOC001_2E.assistant_id"
  }
}
```

---

## Dependencias

### Archivos Requeridos

| Archivo | Generado por | Contenido |
|---------|--------------|-----------|
| `{NB}.fileid` | F1C | file_id |
| `{NB}.json` | F2D | 24 campos |
| `prompts.php` | Manual | Prompt auditoría |

### Archivos Generados

| Archivo | Acción | Contenido |
|---------|--------|-----------|
| `{NB}.json` | **SOBRESCRIBE** | 24 campos auditados (JSON-FINAL) |
| `{NB}_2E.log` | Crea | Log completo con metadata |
| `{NB}_2E.assistant_id` | Crea | Assistant ID para reutilización |

### Nota Importante

⚠️ **F2E sobrescribe `{NB}.json`** con JSON-FINAL. Este es el archivo definitivo que se usará en F3A/F3B.

---

## Troubleshooting

### resumen_tecnico fue Modificado

**Causa**: Assistant no respetó instrucción de preservar campo.

**Solución en Prompt**: Enfatizar más
```
CRITICAL: You must NOT modify the field "resumen_tecnico".
Copy it EXACTLY as received in JSON_PREVIO without ANY changes.
```

### Campos Numéricos Cambiados Sin Justificación

**Causa**: Assistant interpretó mal o "corrigió" valores correctos.

**Debug**: Comparar JSON-F2D vs JSON-FINAL:
```php
$cambios = array_diff_assoc($jsonPrevio, $jsonFinal);
error_log("Campos modificados: " . json_encode($cambios));
```

### JSON-FINAL Idéntico a JSON-F2D

**Causa**: Documento ya estaba perfecto o Assistant no encontró mejoras.

**Esto es válido**: Si no hay errores, no debe cambiar nada.

### Error: "Debe completar Fase 2D primero"

**Solución**: Ejecutar F2D antes de F2E.

---

**Fin de documentación Fase 2E**  
**Versión**: 1.0  
**Fecha**: 31 de octubre de 2025

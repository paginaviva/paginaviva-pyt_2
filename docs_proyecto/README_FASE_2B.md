# Fase 2B - Ampliación de Metadatos Técnicos

**Documentación técnica completa**  
**Actualizado: 29 de octubre de 2025**

---

## Visión General

### Propósito

**Fase 2B** amplía el JSON de F2A agregando 6 campos adicionales de metadatos técnicos avanzados (normas, certificaciones, manuales relacionados, productos, accesorios, uso formación). Usa el mismo file_id y expande el JSON de 8 a 14 campos.

### Campos Añadidos (6 nuevos → total 14)

| Campo | Tipo | Descripción |
|-------|------|-------------|
| `normas_detectadas` | array | Normas técnicas (EN 54-2, UNE, etc.) |
| `certificaciones_detectadas` | array | Certificaciones (AENOR, CE, LPCB) |
| `manuales_relacionados` | array | Manuales referenciados |
| `otros_productos_relacionados` | array | Otros productos Cofem |
| `accesorios_relacionados` | array | Accesorios identificados |
| `uso_formacion_tecnicos` | boolean | Aplicable para formación |
| `razon_uso_formacion` | string | Justificación (si true) |

Cada array contiene objetos con estructura:
```json
{
  "nombre": "",
  "referencia": "",
  "informacion_complementaria": ""
}
```

---

## Arquitectura

### Diferencia con F2A

| Característica | F2A | F2B |
|----------------|-----|-----|
| Input | file_id | file_id + JSON_F2A |
| Campos entrada | 0 | 8 (de F2A) |
| Campos salida | 8 | 14 (8+6 nuevos) |
| Prompt | p_extract_metadata_technical | p_expand_metadata_technical |
| Assistant | Nuevo/reutilizado | Nuevo/reutilizado (distinto de F2A) |

### Flujo

```
phase_2b_proxy.php
  ↓
1. Validar .fileid existe (F1C)
2. Validar .json existe (F2A)
3. Leer JSON previo (8 campos)
  ↓
4. getOrCreateAssistant() con prompt F2B
  ↓
5. createThread()
6. addMessage() con file_id + JSON previo en prompt
  ↓
7. createRun() y pollRun()
  ↓
8. extractJSON() → validar 14 campos
  ↓
9. saveFiles()
   ├── {NB}.json (SOBRESCRIBE con 14 campos)
   ├── {NB}_2B.log
   └── {NB}_2B.assistant_id
```

---

## Componentes Clave

### Prompt (prompts.php)

```php
$PROMPTS[2]['p_expand_metadata_technical'] = [
    'prompt' => <<<'PROMPT'
OBJECTIVE:
Use the content of the document associated with the file_id to expand the JSON received from the previous block with additional technical information.

INSTRUCTIONS:
1. Use exclusively the content accessible through: {FILE_ID}
2. The JSON data generated in the previous block is:
   {JSON_PREVIO}
   - Do not delete or rename any of its keys
   - Add only the new keys listed below
3. Analyse the full text
4. Extract requested information (technical, precise, without inventing)
5. Add the following fields:
   - normas_detectadas: list [{nombre, referencia, informacion_complementaria}]
   - certificaciones_detectadas: list [...]
   - manuales_relacionados: list [...]
   - otros_productos_relacionados: list [...]
   - accesorios_relacionados: list [...]
   - uso_formacion_tecnicos: boolean
   - razon_uso_formacion: string (if true)
6. If missing: use [] for lists, false for boolean, "" for string
7. Maintain snake_case naming
8. Output must be ONLY complete JSON with all original + new fields

MANDATORY OUTPUT SCHEMA:
{
  ...8 campos de F2A...,
  "normas_detectadas": [{...}],
  "certificaciones_detectadas": [{...}],
  "manuales_relacionados": [{...}],
  "otros_productos_relacionados": [{...}],
  "accesorios_relacionados": [{...}],
  "uso_formacion_tecnicos": false,
  "razon_uso_formacion": ""
}
PROMPT
];
```

### Validación de Entrada

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
    
    // Verificar .json existe (F2A)
    $jsonFile = $this->getJsonFilePath();
    if (!file_exists($jsonFile)) {
        $this->fail(400, 'Debe completar Fase 2A primero');
    }
    
    // Leer JSON previo
    $this->jsonPrevio = json_decode(file_get_contents($jsonFile), true);
    if (!$this->jsonPrevio) {
        $this->fail(400, 'El archivo JSON de F2A está corrupto');
    }
    
    $this->mark('validation.done');
}
```

### Agregar Mensaje con JSON Previo

```php
private function addMessage(string $apiKey, string $threadId): void
{
    $this->mark('message.add.start');
    
    // Construir prompt con JSON previo
    global $PROMPTS;
    $promptTemplate = $PROMPTS[2]['p_expand_metadata_technical']['prompt'];
    $prompt = str_replace('{FILE_ID}', $this->fileId, $promptTemplate);
    $prompt = str_replace('{JSON_PREVIO}', json_encode($this->jsonPrevio, JSON_PRETTY_PRINT), $prompt);
    
    $payload = [
        'role' => 'user',
        'content' => $prompt,
        'attachments' => [
            [
                'file_id' => $this->fileId,
                'tools' => [['type' => 'file_search']]
            ]
        ]
    ];
    
    $ch = curl_init("https://api.openai.com/v1/threads/{$threadId}/messages");
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
        $this->fail(502, 'Error al agregar mensaje');
    }
    
    $this->mark('message.add.done');
}
```

### Validación JSON con 14 Campos

```php
private function extractJSON(array $messages): array
{
    // ...extraer JSON del mensaje assistant...
    
    $jsonData = json_decode($jsonText, true);
    
    // Validar campos requeridos de F2A
    $requiredF2A = ['file_id', 'nombre_archivo', 'nombre_producto'];
    foreach ($requiredF2A as $field) {
        if (!isset($jsonData[$field])) {
            $this->fail(502, "Campo F2A faltante: {$field}");
        }
    }
    
    // Validar campos nuevos de F2B
    $requiredF2B = [
        'normas_detectadas',
        'certificaciones_detectadas',
        'manuales_relacionados',
        'otros_productos_relacionados',
        'accesorios_relacionados',
        'uso_formacion_tecnicos',
        'razon_uso_formacion'
    ];
    foreach ($requiredF2B as $field) {
        if (!isset($jsonData[$field])) {
            $this->fail(502, "Campo F2B faltante: {$field}");
        }
    }
    
    // Validar tipos
    if (!is_array($jsonData['normas_detectadas'])) {
        $this->fail(502, 'normas_detectadas debe ser array');
    }
    if (!is_bool($jsonData['uso_formacion_tecnicos'])) {
        $this->fail(502, 'uso_formacion_tecnicos debe ser boolean');
    }
    
    $this->mark('json.validated');
    return $jsonData;
}
```

---

## Estructuras de Datos

### JSON Input (8 campos de F2A)

```json
{
  "file_id": "file-XXX",
  "nombre_archivo": "DOC001.txt",
  "nombre_producto": "Central CLVR-8Z",
  "codigo_referencia_cofem": "CLVR-8Z",
  "tipo_documento": "Manual Técnico",
  "tipo_informacion_contenida": "Instalación",
  "fecha_emision_revision": "2024-03-15",
  "idiomas_presentes": ["es"]
}
```

### JSON Output (14 campos: 8 previos + 6 nuevos)

```json
{
  "file_id": "file-XXX",
  "nombre_archivo": "DOC001.txt",
  "nombre_producto": "Central CLVR-8Z",
  "codigo_referencia_cofem": "CLVR-8Z",
  "tipo_documento": "Manual Técnico",
  "tipo_informacion_contenida": "Instalación",
  "fecha_emision_revision": "2024-03-15",
  "idiomas_presentes": ["es"],
  
  "normas_detectadas": [
    {
      "nombre": "EN 54-2",
      "referencia": "EN 54-2:1997+A1:2006",
      "informacion_complementaria": "Sistemas de detección y alarma de incendio"
    },
    {
      "nombre": "UNE 23007-14",
      "referencia": "UNE 23007-14:2014",
      "informacion_complementaria": "Centrales de señalización y control"
    }
  ],
  
  "certificaciones_detectadas": [
    {
      "nombre": "AENOR",
      "referencia": "AENOR-CERT-12345",
      "informacion_complementaria": "Certificación conformidad EN 54"
    },
    {
      "nombre": "CE",
      "referencia": "0370-CPR-1234",
      "informacion_complementaria": "Marcado CE conforme CPR"
    }
  ],
  
  "manuales_relacionados": [
    {
      "nombre": "Manual de instalación CLVR",
      "referencia": "MI-CLVR-2024",
      "informacion_complementaria": "Versión 3.0"
    }
  ],
  
  "otros_productos_relacionados": [
    {
      "nombre": "Fuente FALM",
      "referencia": "FALM-24/5",
      "informacion_complementaria": "Fuente alimentación 24V 5A"
    }
  ],
  
  "accesorios_relacionados": [
    {
      "nombre": "Teclado remoto",
      "referencia": "TEC-CLVR",
      "informacion_complementaria": "Teclado LCD para control remoto"
    }
  ],
  
  "uso_formacion_tecnicos": true,
  "razon_uso_formacion": "Contiene información técnica detallada sobre instalación, configuración y mantenimiento aplicable a la formación de técnicos instaladores"
}
```

---

## Dependencias

### Archivos Requeridos

| Archivo | Generado por | Contenido |
|---------|--------------|-----------|
| `{NB}.fileid` | F1C | file_id |
| `{NB}.json` | F2A | 8 campos JSON |
| `prompts.php` | Manual | Prompt F2B |

### Archivos Generados

| Archivo | Acción | Contenido |
|---------|--------|-----------|
| `{NB}.json` | **SOBRESCRIBE** | 14 campos (8+6) |
| `{NB}_2B.log` | Crea | Log F2B |
| `{NB}_2B.assistant_id` | Crea | Assistant F2B |

### Nota Importante: Sobrescritura de JSON

⚠️ **F2B sobrescribe `{NB}.json`** con 14 campos. El JSON de F2A (8 campos) es reemplazado. Esto es intencional para mantener un único archivo JSON progresivo.

---

## Troubleshooting

### Error: "Debe completar Fase 2A primero"

**Causa**: No existe `{NB}.json`

**Solución**: Ejecutar F2A primero

### JSON Devuelto Solo Tiene Campos Nuevos (sin campos F2A)

**Causa**: Assistant no incluyó campos previos.

**Solución**: Mejorar prompt:
```
CRITICAL: The output must include ALL fields from the previous JSON plus the new fields. Do not omit any existing field.
```

### Campos de Arrays Vacíos

**Causa**: Documento no contiene normas/certificaciones.

**Esto es correcto**: Si no hay información, debe devolver `[]`.

### Boolean como String

**Causa**: JSON devuelto con `"true"` en lugar de `true`

**Solución**: Validar y convertir:
```php
$jsonData['uso_formacion_tecnicos'] = filter_var(
    $jsonData['uso_formacion_tecnicos'], 
    FILTER_VALIDATE_BOOLEAN, 
    FILTER_NULL_ON_FAILURE
) ?? false;
```

---

**Fin de documentación Fase 2B**  
**Versión**: 1.0  
**Fecha**: 29 de octubre de 2025

# Fase 2C - Clasificación Taxonómica con Catálogos CSV

**Documentación técnica completa**  
**Actualizado: 29 de octubre de 2025**

---

## Visión General

### Propósito

**Fase 2C** realiza clasificación taxonómica correlacionando el producto identificado con dos catálogos CSV de Cofem:
1. **Productos_Cofem.csv**: Catálogo de productos con códigos y familias
2. **Taxonomía_Cofem.csv**: Clasificación oficial (Grupos de Soluciones, Familia, Categoría)

Expande el JSON de 14 a 22 campos (8 campos nuevos: 4 intermedios + 4 finales).

### Campos Añadidos (8 nuevos → total 22)

**Campos intermedios (correlación con Productos_Cofem.csv):**
- `codigo_encontrado`: Código del producto identificado
- `nombre_encontrado`: Nombre del producto identificado
- `familia_catalogo`: Familia del catálogo de productos
- `nivel_confianza_identificacion`: Alta/Media/Baja

**Campos finales (taxonomía oficial):**
- `grupos_de_soluciones`: Clasificación nivel 1
- `familia`: Clasificación nivel 2
- `categoria`: Clasificación nivel 3
- `incidencias_taxonomia`: Array de incidencias ([] si OK)

---

## Arquitectura

### Diferencia Crítica: code_interpreter vs file_search

⚠️ **IMPORTANTE**: F2C usa **code_interpreter** (NO file_search) porque:
- `file_search` **NO soporta archivos CSV**
- `code_interpreter` puede leer y procesar CSVs con pandas/Python

| Característica | F2A/F2B | F2C |
|----------------|---------|-----|
| Tool | `file_search` | **`code_interpreter`** |
| Archivos | 1 (.txt) | **3 (.txt + 2 CSVs)** |
| Model | `gpt-4o` o custom | **`gpt-4o`** (gpt-4o-mini NO soporta code_interpreter) |
| Attachments | Simple | **Requiere tools en cada attachment (API v2)** |

### Flujo con Múltiples Attachments

```
phase_2c_proxy.php
  ↓
1. Validar file_id (documento.txt)
2. Validar file_id_productos (config.json)
3. Validar file_id_taxonomia (config.json)
4. Leer JSON F2B (14 campos)
  ↓
5. getOrCreateAssistant() 
   tools: [{'type': 'code_interpreter'}]  ← CRÍTICO
  ↓
6. createThread()
7. addMessage() con 3 attachments:
   [
     {file_id: documento.txt, tools: [{type: 'code_interpreter'}]},
     {file_id: Productos_Cofem.csv, tools: [{type: 'code_interpreter'}]},
     {file_id: Taxonomia_Cofem.csv, tools: [{type: 'code_interpreter'}]}
   ]
  ↓
8. createRun() y pollRun()
  ↓
9. extractJSON() → validar 22 campos
  ↓
10. saveFiles()
    ├── {NB}.json (SOBRESCRIBE con 22 campos)
    ├── {NB}_2C.log
    └── {NB}_2C.assistant_id
```

---

## Componentes Clave

### Configuración: config.json

```json
{
  "file_id_productos": "file-TGC6KXtXdnV2qCZyXFXmRU",
  "file_id_taxonomia": "file-PQ9KQbkw7tYumKWL8UtvL5"
}
```

Estos file_ids son **permanentes** y apuntan a los CSVs subidos manualmente a OpenAI.

### Prompt: prompts.php

```php
$PROMPTS[2]['p_add_taxonomy_fields'] = [
    'prompt' => <<<'PROMPT'
## OBJECTIVE:
Use the content of the original technical document ({FILE_ID}), together with Productos_Cofem.csv and Taxonomía_Cofem.csv to expand the JSON with taxonomic fields.

## INSTRUCTIONS
1. Use file identifiers:
   - Main document: {FILE_ID}
   - Products: {FILE_ID_PRODUCTOS}
   - Taxonomy: {FILE_ID_TAXONOMIA}
2. JSON from previous block: {JSON_PREVIO}
   - Do not delete or rename any keys
   - Add only new fields
3. Analyse all three sources

## STEP 4 — Product Identification (Productos_Cofem.csv)
1. Compare `codigo_referencia_cofem` and `nombre_producto` against CSV
2. Match priority:
   1) Exact match by code
   2) Exact match by name
   3) Partial/semantic match (if unambiguous)
3. Add intermediate fields:
   - codigo_encontrado
   - nombre_encontrado
   - familia_catalogo
   - nivel_confianza_identificacion

## STEP 5 — Taxonomic Classification (Taxonomía_Cofem.csv)
1. Use familia_catalogo from step 4
2. Search in Taxonomía_Cofem.csv for:
   - Grupos de Soluciones
   - Familia
   - Categoría
3. Correlation rules:
   - If exact match: use taxonomy from CSV
   - If conflict: taxonomy file prevails
   - If no match: leave empty + add incidence
4. Add final fields:
   - grupos_de_soluciones
   - familia
   - categoria
   - incidencias_taxonomia

## MANDATORY OUTPUT SCHEMA
{
  ...14 campos de F2B...,
  "codigo_encontrado": "",
  "nombre_encontrado": "",
  "familia_catalogo": "",
  "nivel_confianza_identificacion": "",
  "grupos_de_soluciones": "",
  "familia": "",
  "categoria": "",
  "incidencias_taxonomia": []
}
PROMPT
];
```

### Crear Assistant con code_interpreter

```php
private function getOrCreateAssistant(string $apiKey): string
{
    // ... verificar .assistant_id ...
    
    // Crear nuevo
    global $PROMPTS;
    $instructions = $PROMPTS[2]['p_add_taxonomy_fields']['prompt'];
    
    // Reemplazar placeholders
    $instructions = str_replace('{FILE_ID}', 'the main document file_id', $instructions);
    $instructions = str_replace('{FILE_ID_PRODUCTOS}', 'the products catalog file_id', $instructions);
    $instructions = str_replace('{FILE_ID_TAXONOMIA}', 'the taxonomy file_id', $instructions);
    
    $payload = [
        'model' => 'gpt-4o',  // OBLIGATORIO: gpt-4o-mini NO soporta code_interpreter
        'name' => 'Taxonomy Classifier',
        'description' => 'Classifies products using CSV catalogs',
        'instructions' => $instructions,
        'tools' => [
            ['type' => 'code_interpreter']  // ← code_interpreter (NO file_search)
        ]
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
    
    // ... ejecutar y guardar assistant_id ...
}
```

### Agregar Mensaje con 3 Attachments

```php
private function addMessage(string $apiKey, string $threadId): void
{
    $this->mark('message.add.start');
    
    global $PROMPTS;
    $promptTemplate = $PROMPTS[2]['p_add_taxonomy_fields']['prompt'];
    
    // Reemplazar placeholders
    $prompt = str_replace('{FILE_ID}', $this->fileId, $promptTemplate);
    $prompt = str_replace('{FILE_ID_PRODUCTOS}', $this->fileIdProductos, $prompt);
    $prompt = str_replace('{FILE_ID_TAXONOMIA}', $this->fileIdTaxonomia, $prompt);
    $prompt = str_replace('{JSON_PREVIO}', json_encode($this->jsonPrevio, JSON_PRETTY_PRINT), $prompt);
    
    $payload = [
        'role' => 'user',
        'content' => $prompt,
        'attachments' => [
            [
                'file_id' => $this->fileId,  // documento.txt
                'tools' => [['type' => 'code_interpreter']]  // ← OBLIGATORIO en API v2
            ],
            [
                'file_id' => $this->fileIdProductos,  // Productos_Cofem.csv
                'tools' => [['type' => 'code_interpreter']]  // ← OBLIGATORIO
            ],
            [
                'file_id' => $this->fileIdTaxonomia,  // Taxonomía_Cofem.csv
                'tools' => [['type' => 'code_interpreter']]  // ← OBLIGATORIO
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
        $this->fail(502, 'Error al agregar mensaje con attachments');
    }
    
    $this->mark('message.add.done');
}
```

### Validación JSON con 22 Campos

```php
private function extractJSON(array $messages): array
{
    // ... extraer JSON ...
    
    $jsonData = json_decode($jsonText, true);
    
    // Validar campos F2B (14)
    // ...
    
    // Validar campos nuevos F2C (8)
    $requiredF2C = [
        'codigo_encontrado',
        'nombre_encontrado',
        'familia_catalogo',
        'nivel_confianza_identificacion',
        'grupos_de_soluciones',
        'familia',
        'categoria',
        'incidencias_taxonomia'
    ];
    
    foreach ($requiredF2C as $field) {
        if (!isset($jsonData[$field])) {
            $this->fail(502, "Campo F2C faltante: {$field}");
        }
    }
    
    // Validar incidencias_taxonomia es array
    if (!is_array($jsonData['incidencias_taxonomia'])) {
        $this->fail(502, 'incidencias_taxonomia debe ser array');
    }
    
    $this->mark('json.validated');
    return $jsonData;
}
```

---

## Estructuras de Datos

### CSV: Productos_Cofem.csv

**Estructura esperada:**
```csv
codigo,nombre,familia,descripcion
CLVR-8Z,Central CLVR 8 Zonas,Centrales,Central de detección...
FALM-24/5,Fuente FALM,Fuentes,Fuente de alimentación...
...
```

### CSV: Taxonomía_Cofem.csv

**Estructura esperada:**
```csv
familia,grupos_de_soluciones,categoria
Centrales,Detección de Incendios,Centrales Analógicas
Detectores,Detección de Incendios,Detectores Ópticos
...
```

### JSON Output (22 campos)

```json
{
  ...14 campos de F2B...,
  
  "codigo_encontrado": "CLVR-8Z",
  "nombre_encontrado": "Central CLVR 8 Zonas",
  "familia_catalogo": "Centrales",
  "nivel_confianza_identificacion": "Alta",
  
  "grupos_de_soluciones": "Detección de Incendios",
  "familia": "Centrales",
  "categoria": "Centrales Analógicas",
  "incidencias_taxonomia": []
}
```

**Si NO hay match:**
```json
{
  ...14 campos...,
  
  "codigo_encontrado": "",
  "nombre_encontrado": "",
  "familia_catalogo": "",
  "nivel_confianza_identificacion": "",
  
  "grupos_de_soluciones": "",
  "familia": "",
  "categoria": "",
  "incidencias_taxonomia": [
    "No match found in Productos_Cofem.csv or Taxonomía_Cofem.csv for CLVR-8Z"
  ]
}
```

---

## Errores Comunes y Soluciones

### Error HTTP 400: "file_search does not support .csv files"

**Causa**: Intentar usar `file_search` con CSVs.

**Solución**: Usar `code_interpreter`:
```php
'tools' => [['type' => 'code_interpreter']]  // ✅ CORRECTO
'tools' => [['type' => 'file_search']]       // ❌ INCORRECTO para CSV
```

### Error HTTP 400: "Missing required parameter 'attachments[0].tools'"

**Causa**: API v2 requiere campo `tools` en cada attachment.

**Solución**:
```php
'attachments' => [
    [
        'file_id' => $fileId,
        'tools' => [['type' => 'code_interpreter']]  // ← OBLIGATORIO
    ]
]
```

### Error: "gpt-4o-mini does not support code_interpreter"

**Causa**: Modelo no soporta code_interpreter.

**Solución**: Usar `gpt-4o` (NO mini):
```php
'model' => 'gpt-4o'  // ✅ CORRECTO
```

### JSON Devuelto Sin Campos Intermedios

**Causa**: Assistant omitió step 4 (identificación en Productos_Cofem.csv).

**Solución**: Mejorar prompt con estructura clara:
```
## STEP 4 — Product Identification
(must execute before step 5)

## STEP 5 — Taxonomic Classification
(depends on step 4 results)
```

### Incidencias Taxonomía Siempre Llenas

**Causa**: Assistant no encuentra matches aunque existan.

**Debug**:
1. Verificar CSVs tienen encoding UTF-8
2. Verificar nombres/códigos exactos (sin espacios extra)
3. Verificar estructura columnas CSV

**Solución**:
```bash
# Verificar encoding
file -i Productos_Cofem.csv

# Limpiar espacios
sed -i 's/[[:space:]]*$//' Productos_Cofem.csv
```

---

## Dependencias

### Archivos Requeridos

| Archivo | Generado por | Contenido |
|---------|--------------|-----------|
| `{NB}.fileid` | F1C | file_id documento |
| `{NB}.json` | F2B | 14 campos |
| `config.json` | Manual | file_id_productos, file_id_taxonomia |
| `Productos_Cofem.csv` | Manual (subido a OpenAI) | Catálogo productos |
| `Taxonomía_Cofem.csv` | Manual (subido a OpenAI) | Taxonomía oficial |

### Archivos Generados

| Archivo | Acción | Contenido |
|---------|--------|-----------|
| `{NB}.json` | SOBRESCRIBE | 22 campos |
| `{NB}_2C.log` | Crea | Log F2C |
| `{NB}_2C.assistant_id` | Crea | Assistant F2C |

---

## Subir CSVs a OpenAI (Manual)

```bash
# Usando curl
curl -X POST https://api.openai.com/v1/files \
  -H "Authorization: Bearer $OPENAI_API_KEY" \
  -F purpose="assistants" \
  -F file="@Productos_Cofem.csv"

# Respuesta:
{
  "id": "file-TGC6KXtXdnV2qCZyXFXmRU",
  "object": "file",
  "bytes": 12345,
  "created_at": 1698765432,
  "filename": "Productos_Cofem.csv",
  "purpose": "assistants"
}

# Agregar file_id a config.json
{
  "file_id_productos": "file-TGC6KXtXdnV2qCZyXFXmRU"
}
```

---

**Fin de documentación Fase 2C**  
**Versión**: 1.0  
**Fecha**: 29 de octubre de 2025

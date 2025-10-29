# Fase 2D - Generación de Ficha Técnica y Resumen

**Documentación técnica completa**  
**Actualizado: 29 de octubre de 2025**

---

## Visión General

### Propósito

**Fase 2D** es la fase final del pipeline de análisis. Genera dos campos de texto técnico derivados del documento:
1. **ficha_tecnica**: Ficha técnica estructurada para técnicos instaladores
2. **resumen_tecnico**: Resumen conciso (máx. 300 caracteres)

Ambos textos deben cumplir estrictamente con directrices RAE (Real Academia Española).

### Campos Añadidos (2 nuevos → total 24)

| Campo | Tipo | Límites | Descripción |
|-------|------|---------|-------------|
| `ficha_tecnica` | string | Sin límite | Ficha técnica completa en español de España |
| `resumen_tecnico` | string | **Máx. 300 chars** | Resumen conciso RAE-compliant |

---

## Arquitectura

### Tool Usado: code_interpreter

Como F2C, usa **code_interpreter** (NO file_search) porque:
- Mantiene consistencia con fase anterior
- Permite análisis más profundo del contenido
- Soporta procesamiento de texto estructurado

| Característica | F2C | F2D |
|----------------|-----|-----|
| Tool | code_interpreter | code_interpreter |
| Archivos | 3 (.txt + 2 CSVs) | **1 (.txt)** |
| Model | gpt-4o | gpt-4o |
| Campos entrada | 14 → 22 | 22 → 24 |
| Output | JSON estructurado | **JSON + texto técnico** |

### Flujo

```
phase_2d_proxy.php
  ↓
1. Validar file_id (documento.txt)
2. Validar .json existe (F2C con 22 campos)
3. Leer JSON F2C
  ↓
4. getOrCreateAssistant() con prompt RAE
   tools: [{'type': 'code_interpreter'}]
  ↓
5. createThread()
6. addMessage() con 1 attachment:
   [{file_id: documento.txt, tools: [{type: 'code_interpreter'}]}]
  ↓
7. createRun() y pollRun()
  ↓
8. extractJSON() → validar 24 campos + longitud resumen ≤300
  ↓
9. saveFiles()
   ├── {NB}.json (SOBRESCRIBE con 24 campos)
   ├── {NB}_2D.log
   └── {NB}_2D.assistant_id
```

---

## Componentes Clave

### Directrices RAE en Prompt

```php
$PROMPTS[2]['p_generate_technical_sheet'] = [
    'prompt' => <<<'PROMPT'
ROLE:
Act as a technical writer specialised in Cofem fire detection products.
Your writing must comply strictly with Real Academia Española (RAE) rules.

OBJECTIVE:
Analyse the document and add two new fields:
- `ficha_tecnica`: structured technical sheet
- `resumen_tecnico`: concise summary (max 300 characters)

INSTRUCTIONS:
1. Use exclusively file_id: {FILE_ID}
2. JSON from previous block: {JSON_PREVIO}
3. Generate Technical Sheet following:
   - Write exclusively in Spanish from Spain (RAE)
   - Avoid present continuous tense
   - Do not use Latin American words, expressions, or syntax
   - Use formal, precise, objective technical tone
   - Structure with bullet points or short sections
   - Include when available:
     * Functional description
     * Technical specifications
     * Compatible models/variants
     * Installation conditions
     * Maintenance requirements
     * Applicable standards/certifications
   - Do not invent or fill in missing data
4. Generate Technical Summary with:
   - Write exclusively in Spanish from Spain (RAE)
   - Avoid present continuous and Latin American expressions
   - Maximum length: 300 characters
   - Concise, factual, technical tone
   - Summarise product nature, function, essential characteristics
   - Consistent with ficha_tecnica
   - No subjective or promotional language
5. If cannot generate: assign ""
6. Maintain snake_case naming
7. Output: ONLY complete valid JSON

MANDATORY OUTPUT SCHEMA:
{
  ...22 campos de F2C...,
  "ficha_tecnica": "",
  "resumen_tecnico": ""
}
PROMPT
];
```

### Validación Específica F2D

```php
private function extractJSON(array $messages): array
{
    // ... extraer JSON ...
    
    $jsonData = json_decode($jsonText, true);
    
    // Validar campos F2C (22)
    // ...
    
    // Validar campos nuevos F2D (2)
    if (!isset($jsonData['ficha_tecnica'])) {
        $this->fail(502, 'Campo ficha_tecnica faltante');
    }
    if (!isset($jsonData['resumen_tecnico'])) {
        $this->fail(502, 'Campo resumen_tecnico faltante');
    }
    
    // Validar tipos
    if (!is_string($jsonData['ficha_tecnica'])) {
        $this->fail(502, 'ficha_tecnica debe ser string');
    }
    if (!is_string($jsonData['resumen_tecnico'])) {
        $this->fail(502, 'resumen_tecnico debe ser string');
    }
    
    // Validar longitud resumen
    $resumenLength = mb_strlen($jsonData['resumen_tecnico'], 'UTF-8');
    if ($resumenLength > 300) {
        $this->fail(502, "resumen_tecnico excede 300 caracteres ({$resumenLength})");
    }
    
    $this->mark('json.validated');
    return $jsonData;
}
```

### Frontend: Visualización Especial

```html
<!-- phase_2d.php -->
<section id="resultsSection" style="display:none;">
    <h2>Ficha Técnica Generada</h2>
    
    <!-- Ficha Técnica con pre-wrap -->
    <div class="technical-sheet">
        <h3>📋 Ficha Técnica</h3>
        <pre style="white-space: pre-wrap; font-family: sans-serif; line-height: 1.6;" id="fichaTecnica"></pre>
    </div>
    
    <!-- Resumen Técnico con contador -->
    <div class="technical-summary">
        <h3>📝 Resumen Técnico</h3>
        <p id="resumenTecnico"></p>
        <small id="resumenLength"></small>
    </div>
    
    <!-- JSON Completo -->
    <details>
        <summary>Ver JSON Completo (24 campos)</summary>
        <pre id="jsonCompleto"></pre>
    </details>
</section>

<script>
document.getElementById('btnExecute').addEventListener('click', async () => {
    // ... fetch proxy ...
    
    if (data.output?.json) {
        const json = data.output.json;
        
        // Mostrar ficha técnica
        document.getElementById('fichaTecnica').textContent = json.ficha_tecnica;
        
        // Mostrar resumen con longitud
        const resumen = json.resumen_tecnico;
        document.getElementById('resumenTecnico').textContent = resumen;
        document.getElementById('resumenLength').textContent = 
            `Caracteres: ${resumen.length}/300`;
        
        // JSON completo
        document.getElementById('jsonCompleto').textContent = 
            JSON.stringify(json, null, 2);
        
        document.getElementById('resultsSection').style.display = 'block';
    }
});
</script>
```

---

## Estructuras de Datos

### JSON Output (24 campos: 22 previos + 2 nuevos)

```json
{
  ...22 campos de F2C...,
  
  "ficha_tecnica": "# Central de Detección de Incendios CLVR-8Z\n\n## Descripción Funcional\nCentral analógica direccionable de 8 zonas conforme a normativa EN 54-2 y EN 54-4.\n\n## Especificaciones Técnicas\n• Zonas: 8 zonas direccionables\n• Detectores: Hasta 127 detectores por zona\n• Alimentación: 230V AC / 24V DC\n• Consumo: 150mA en reposo, 2A en alarma\n• Temperatura operación: -5°C a +50°C\n\n## Modelos Compatibles\n• Detectores serie DT-XXX\n• Pulsadores manuales PM-XXX\n• Sirenas SA-XXX\n\n## Condiciones de Instalación\n• Montaje en pared o armario rack 19\"\n• Altura recomendada: 1.5m del suelo\n• Ventilación: Separación mínima 10cm laterales\n\n## Mantenimiento\n• Inspección visual: Mensual\n• Prueba funcional: Trimestral\n• Sustitución batería: Cada 4 años\n\n## Normativa Aplicable\n• EN 54-2:1997+A1:2006 - Equipos de control e indicación\n• EN 54-4:1997+A1:2002 - Equipos de suministro de alimentación\n• UNE 23007-14:2014 - Diseño, instalación y mantenimiento",
  
  "resumen_tecnico": "Central analógica direccionable CLVR-8Z conforme EN 54-2/4. 8 zonas, hasta 127 detectores por zona. Alimentación 230V AC, consumo 150mA reposo. Montaje pared/rack. Certificación AENOR, marcado CE."
}
```

### Validación de Longitud

```javascript
// Frontend: verificar antes de mostrar
const resumen = json.resumen_tecnico;
if (resumen.length > 300) {
    console.warn(`Resumen excede 300 caracteres: ${resumen.length}`);
    // Truncar si es necesario
    const truncated = resumen.substring(0, 297) + '...';
    document.getElementById('resumenTecnico').textContent = truncated;
} else {
    document.getElementById('resumenTecnico').textContent = resumen;
}
```

---

## Directrices RAE

### Evitar Presente Continuo

❌ **INCORRECTO** (presente continuo):
```
"La central está permitiendo la conexión de hasta 127 detectores"
"El sistema está funcionando a 230V"
```

✅ **CORRECTO** (presente simple):
```
"La central permite la conexión de hasta 127 detectores"
"El sistema funciona a 230V"
```

### Evitar Latinoamericanismos

❌ **INCORRECTO** (expresiones latinoamericanas):
```
"computadora" → "ordenador"
"aplicación" (app) → "aplicación" (OK) o "programa"
"celular" → "móvil"
"plata" (dinero) → "dinero"
```

✅ **CORRECTO** (español de España):
```
"ordenador", "aplicación", "móvil", "dinero"
```

### Vocabulario Técnico Formal

✅ **CORRECTO**:
```
"alimentación eléctrica" (no "corriente")
"equipo de control" (no "controlador")
"detector de incendios" (no "sensor")
"pulsador manual" (no "botón")
```

---

## Errores Comunes y Soluciones

### Resumen Excede 300 Caracteres

**Causa**: Assistant generó texto muy largo.

**Solución en Proxy**:
```php
if (mb_strlen($jsonData['resumen_tecnico'], 'UTF-8') > 300) {
    // Opción 1: Rechazar
    $this->fail(502, 'resumen_tecnico excede 300 caracteres');
    
    // Opción 2: Truncar (NO recomendado, mejor rechazar)
    $jsonData['resumen_tecnico'] = mb_substr($jsonData['resumen_tecnico'], 0, 297, 'UTF-8') . '...';
}
```

**Solución en Prompt**: Enfatizar límite
```
CRITICAL: resumen_tecnico MUST be maximum 300 characters including spaces.
Count characters before responding.
```

### Ficha Técnica Usa Presente Continuo

**Causa**: Prompt no enfatiza suficiente directriz RAE.

**Solución**: Agregar ejemplos explícitos en prompt:
```
EXAMPLES of RAE-compliant present simple:
✅ "La central permite..." (NOT "está permitiendo")
✅ "El sistema funciona..." (NOT "está funcionando")
✅ "El detector identifica..." (NOT "está identificando")
```

### Texto Usa Vocabulario Latinoamericano

**Causa**: Modelo tiene sesgo hacia español latinoamericano.

**Solución**: Enfatizar en prompt:
```
PROHIBITED WORDS (use Spain alternatives):
❌ computadora → ✅ ordenador
❌ celular → ✅ móvil
❌ aplicación (app) → ✅ programa
```

### Ficha Técnica Vacía

**Causa**: Documento no tiene información suficiente.

**Esto es correcto**: Si no hay datos, debe devolver `""`.

**Verificar**: No está inventando información:
```php
if ($jsonData['ficha_tecnica'] === '') {
    // Log warning pero no fallar
    error_log("F2D: ficha_tecnica vacía para {$this->docBasename}");
}
```

---

## Dependencias

### Archivos Requeridos

| Archivo | Generado por | Contenido |
|---------|--------------|-----------|
| `{NB}.fileid` | F1C | file_id |
| `{NB}.json` | F2C | 22 campos |
| `prompts.php` | Manual | Prompt RAE |

### Archivos Generados

| Archivo | Acción | Contenido |
|---------|--------|-----------|
| `{NB}.json` | SOBRESCRIBE | 24 campos (FINAL) |
| `{NB}_2D.log` | Crea | Log F2D |
| `{NB}_2D.assistant_id` | Crea | Assistant F2D |

---

## Testing y Validación

### Validar Cumplimiento RAE

```bash
# Buscar presente continuo en ficha_tecnica
grep -E "(está|están|estoy) \w+ndo" {NB}.json

# Buscar latinoamericanismos
grep -iE "(computadora|celular|plata|aplicación)" {NB}.json
```

### Validar Longitud Resumen

```bash
# Extraer resumen_tecnico y contar caracteres
jq -r '.resumen_tecnico | length' {NB}.json
```

### Validar Estructura JSON

```bash
# Verificar 24 campos
jq 'keys | length' {NB}.json  # Debe ser 24
```

---

## Ejemplo Completo de JSON Final

```json
{
  "file_id": "file-XXX",
  "nombre_archivo": "DOC001.txt",
  "nombre_producto": "Central CLVR-8Z",
  "codigo_referencia_cofem": "CLVR-8Z",
  "tipo_documento": "Manual Técnico",
  "tipo_informacion_contenida": "Instalación y Mantenimiento",
  "fecha_emision_revision": "2024-03-15",
  "idiomas_presentes": ["es"],
  
  "normas_detectadas": [
    {
      "nombre": "EN 54-2",
      "referencia": "EN 54-2:1997+A1:2006",
      "informacion_complementaria": "Equipos de control e indicación"
    }
  ],
  "certificaciones_detectadas": [
    {
      "nombre": "AENOR",
      "referencia": "AENOR-CERT-12345",
      "informacion_complementaria": "Certificación EN 54"
    }
  ],
  "manuales_relacionados": [],
  "otros_productos_relacionados": [],
  "accesorios_relacionados": [],
  "uso_formacion_tecnicos": true,
  "razon_uso_formacion": "Contiene información técnica detallada aplicable a formación de instaladores",
  
  "codigo_encontrado": "CLVR-8Z",
  "nombre_encontrado": "Central CLVR 8 Zonas",
  "familia_catalogo": "Centrales",
  "nivel_confianza_identificacion": "Alta",
  
  "grupos_de_soluciones": "Detección de Incendios",
  "familia": "Centrales",
  "categoria": "Centrales Analógicas",
  "incidencias_taxonomia": [],
  
  "ficha_tecnica": "# Central de Detección CLVR-8Z\n\n## Descripción\nCentral analógica...",
  "resumen_tecnico": "Central analógica CLVR-8Z conforme EN 54-2. 8 zonas, 127 detectores/zona. 230V AC, montaje pared/rack. Certificación AENOR."
}
```

---

## Fin del Pipeline

**F2D es la última fase del procesamiento**. Después de ejecutar F2D:

✅ **JSON completo** con 24 campos  
✅ **Ficha técnica** RAE-compliant  
✅ **Resumen** de máx. 300 caracteres  
✅ **Logs completos** de todas las fases  
✅ **Assistant IDs** persistidos para reutilización  

El documento está **completamente procesado** y listo para:
- Indexación en base de datos
- Búsqueda y clasificación
- Uso en sistemas de formación
- Generación de catálogos

---

**Fin de documentación Fase 2D**  
**Versión**: 1.0  
**Fecha**: 29 de octubre de 2025

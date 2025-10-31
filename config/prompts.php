<?php
// prompts.php - plantillas P por fase (edición manual).
// Incluye p_extract_text según tu especificación para fase 1.

$PROMPTS = [];

/**
 * Prompt p_extract_text (fase 1)
 */
$PROMPTS[1] = [
    'p_extract_text' => [
        'id' => 'p_extract_text',
        'title' => 'Extraer texto bruto (p_extract_text)',
        'prompt' => <<<'PROMPT'
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
PROMPT
        ,
        'placeholders' => ['PDF_BASE64','PDF_CHUNK_BASE64','IMAGES_LIST'],
        'output_format' => 'markdown'
    ]
];

/**
 * Prompt p_extract_metadata_technical (fase 2A)
 */
$PROMPTS[2] = [
    'p_extract_metadata_technical' => [
        'id' => 'p_extract_metadata_technical',
        'title' => 'Extraer metadatos técnicos (p_extract_metadata_technical)',
        'prompt' => <<<'PROMPT'
OBJECTIVE:
Access the file indicated by its file_id, perform a precautionary check of its validity, and generate a base JSON object containing the technical information extracted from the text.

INSTRUCTIONS:
1. Use exclusively the file_id provided: {FILE_ID}
2. Perform a precautionary validation:
   - Confirm the file exists and is accessible.
   - Confirm it corresponds to a .txt file.
   - Confirm its content decodes correctly as UTF-8.
3. Read and analyse the complete content of the file.
4. Extract and populate the following JSON keys:
   - file_id: identifier of the file.
   - nombre_archivo: literal name of the file.
   - nombre_producto: main technical or commercial designation detected.
   - codigo_referencia_cofem: Cofem reference if present.
   - tipo_documento: technical classification of the document.
   - tipo_informacion_contenida: nature of the content.
   - fecha_emision_revision: most recent date found in the document, formatted as YYYY-MM-DD.
   - idiomas_presentes: list of languages detected in the document.
5. If a piece of information is missing or not identifiable:
   - Use an empty string "" for any textual field.
   - Use an empty list [] for idiomas_presentes.
6. The output must comply with the following JSON Schema:
{
  "type": "object",
  "additionalProperties": false,
  "properties": {
    "file_id": { "type": "string" },
    "nombre_archivo": { "type": "string" },
    "nombre_producto": { "type": "string" },
    "codigo_referencia_cofem": { "type": "string" },
    "tipo_documento": { "type": "string" },
    "tipo_informacion_contenida": { "type": "string" },
    "fecha_emision_revision": {
      "type": "string",
      "pattern": "^\\d{4}-\\d{2}-\\d{2}$"
    },
    "idiomas_presentes": {
      "type": "array",
      "items": { "type": "string" }
    }
  },
  "required": [
    "file_id",
    "nombre_archivo",
    "idiomas_presentes"
  ]
}
7. Do not omit or rename any key.
8. The response must be **only** the final JSON object, valid according to the schema, without comments or text outside the JSON.

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
        ,
        'placeholders' => ['FILE_ID'],
        'output_format' => 'json'
    ]
];

/**
 * Prompt p_expand_metadata_technical (fase 2B)
 */
$PROMPTS[2]['p_expand_metadata_technical'] = [
    'id' => 'p_expand_metadata_technical',
    'title' => 'Ampliar metadatos técnicos (p_expand_metadata_technical)',
    'prompt' => <<<'PROMPT'
OBJECTIVE:
Use the content of the document associated with the provided file_id to expand the JSON received from the previous block with additional, verified technical information.

INSTRUCTIONS:
1. Use exclusively the content accessible through the specified file_id: {FILE_ID}.
2. The JSON data generated in the previous block is:
   {JSON_PREVIO}
   - Do not delete or rename any of its keys.
   - Add only the new keys listed below.
3. Analyse the full text of the document (content available within the files linked to the assistant_id).
4. Extract the requested information, writing it in a technical and precise manner, without inventing or filling in missing data.
5. Add the following fields to the received JSON:
   - normas_detectadas: list of technical standards mentioned (for example, EN 54-2, UNE 23007-14, etc.).
     Each element must be an object with the following keys:
     {
       "nombre": "",
       "referencia": "",
       "informacion_complementaria": ""
     }
   - certificaciones_detectadas: list of certifications or approvals (AENOR, CE, LPCB, etc.), using the same structure as above.
   - manuales_relacionados: list of manuals detected or referenced in the document, including name, reference, and, where applicable, complementary information.
   - otros_productos_relacionados: list of other Cofem products mentioned in the document, including name, reference, and complementary information where applicable.
   - accesorios_relacionados: list of accessories identified or referenced in the document, including name, reference, and complementary information where applicable.
   - uso_formacion_tecnicos: boolean value (true/false) indicating whether the document's content is applicable or useful for technician training.
     If the value is true, also add the key:
     "razon_uso_formacion" with a brief and technical explanation of why the document can be used for training purposes.
6. If any of the above fields are not present or cannot be identified in the document:
   - Assign an empty list [] to list-type fields.
   - Assign false to uso_formacion_tecnicos and an empty string "" to razon_uso_formacion.
7. Maintain key naming in snake_case (all lowercase, using underscores).
8. Do not modify or delete any existing field from the received JSON.
9. The final response must consist only of the complete JSON object, containing all original and newly added fields, without any text or comments outside the JSON.

MANDATORY OUTPUT SCHEMA:
{
  "file_id": "",
  "nombre_archivo": "",
  "nombre_producto": "",
  "codigo_referencia_cofem": "",
  "tipo_documento": "",
  "tipo_informacion_contenida": "",
  "fecha_emision_revision": "",
  "idiomas_presentes": [],
  "normas_detectadas": [
    {
      "nombre": "",
      "referencia": "",
      "informacion_complementaria": ""
    }
  ],
  "certificaciones_detectadas": [],
  "manuales_relacionados": [],
  "otros_productos_relacionados": [],
  "accesorios_relacionados": [],
  "uso_formacion_tecnicos": false,
  "razon_uso_formacion": ""
}
PROMPT
    ,
    'placeholders' => ['FILE_ID', 'JSON_PREVIO'],
    'output_format' => 'json'
];

// Puedes añadir aquí más plantillas por fase (3..10) siguiendo la estructura.

/**
 * FASE 2C: Añadir campos taxonómicos desde Productos_Cofem.csv y Taxonomia Cofem.csv
 */
$PROMPTS[2]['p_add_taxonomy_fields'] = [
    'prompt' => <<<'PROMPT'
## OBJECTIVE:
Use the content of the original technical document associated with the `{FILE_ID}`, together with the information from two auxiliary CSV files — Productos_Cofem.csv and Taxonomía_Cofem.csv — to expand the JSON received from the previous block by adding standardized and verifiable taxonomic and classification fields.

## INSTRUCTIONS
1. Use exclusively the contents accessible through the following file identifiers:
   - Main technical document file: {FILE_ID}
   - Product reference file: {FILE_ID_PRODUCTOS} (corresponding to *Productos_Cofem.csv*)
   - Master taxonomy file: {FILE_ID_TAXONOMIA} (corresponding to *Taxonomía_Cofem.csv*)

2. The JSON data generated in the previous block is:
   {JSON_PREVIO}
   - Do not delete or rename any existing keys.
   - Add only the new fields described below.

3. Analyse all three sources of information:
   - The complete text of the technical document (linked to `{FILE_ID}`).
   - The records from *Productos_Cofem.csv*.
   - The records from *Taxonomía_Cofem.csv*.

## ROLE: `IntegradorTaxonomico`
Executes the correlation and enrichment process defined in steps 4 and 5.
Acts deterministically and verifiably, without altering the textual form of any source.

## STEP 4 — Product Identification (source: Productos_Cofem.csv)
Objective: Locate the exact or most reliable product record and extract related information.

Procedure:
1. Compare the JSON fields `codigo_referencia_cofem` and `nombre_producto` against the corresponding columns in *Productos_Cofem.csv*.
   Do not normalise or modify the text (keep accents, case, and spacing exactly as in the sources).

2. Apply the following match priority:
   1 Exact match by Cofem code/reference (`codigo_referencia_cofem`).
   2 Exact match by product name (`nombre_producto`).
   3 Partial or semantically close match, provided it is objectively verifiable and unambiguous.

3. If multiple valid matches exist:
   - Prefer the record with a non-empty familia.
   - If a tie remains, select the first encountered record and register an informational note in `incidencias_taxonomia`.

4. Add directly to the JSON the following intermediate fields:
   ```json
   "codigo_encontrado": "",
   "nombre_encontrado": "",
   "familia_catalogo": "",
   "nivel_confianza_identificacion": ""
   ```
   - Populate these with data from the most reliable match.
   - If no valid match is found, leave them empty (`""`) and record the corresponding incidence.

## STEP 5 — Taxonomic Classification (source: Taxonomía_Cofem.csv)
Objective: Retrieve the official Cofem taxonomy for the identified product or its family.

Procedure:
1. Use the values from step 4 (`familia_catalogo`, `codigo_encontrado`, `nombre_encontrado`) as lookup inputs.

2. Search within *Taxonomía_Cofem.csv* for the corresponding official entries:
   - `Grupos de Soluciones`
   - `Familia`
   - `Categoría`

3. Correlation rules:
   - If the family from *Productos_Cofem.csv* matches exactly one in *Taxonomía_Cofem.csv*, adopt the taxonomy values from the latter.
   - If both files disagree, the taxonomy file always prevails.
   - If no valid correlation exists, leave the three taxonomy fields empty and record the standard incidence message.

4. Add to the JSON the definitive taxonomy fields:
   ```json
   "grupos_de_soluciones": "",
   "familia": "",
   "categoria": "",
   "incidencias_taxonomia": []
   ```
   - When a valid match is found, keep `incidencias_taxonomia` as an empty list `[]`.
   - When no match is found, include:
     ```
     "No match found in Productos_Cofem.csv or Taxonomía_Cofem.csv for [Code/Name]"
     ```

## RULES AND OUTPUT
- Maintain snake_case naming (lowercase with underscores).
- Never modify or remove any existing key from the received JSON.
- Populate all new values precisely and verifiably, without inferring or fabricating information.
- The final response must consist only of the full JSON object, without explanations, comments, or additional text.

## MANDATORY OUTPUT SCHEMA
```json
{
  "file_id": "",
  "nombre_archivo": "",
  "nombre_producto": "",
  "codigo_referencia_cofem": "",
  "tipo_documento": "",
  "tipo_informacion_contenida": "",
  "fecha_emision_revision": "",
  "idiomas_presentes": [],
  "normas_detectadas": [],
  "certificaciones_detectadas": [],
  "manuales_relacionados": [],
  "otros_productos_relacionados": [],
  "accesorios_relacionados": [],
  "uso_formacion_tecnicos": false,
  "razon_uso_formacion": "",
  "codigo_encontrado": "",
  "nombre_encontrado": "",
  "familia_catalogo": "",
  "nivel_confianza_identificacion": "",
  "grupos_de_soluciones": "",
  "familia": "",
  "categoria": "",
  "incidencias_taxonomia": []
}
```
PROMPT
];

/**
 * FASE 2D: Generar ficha técnica y resumen técnico
 */
$PROMPTS[2]['p_generate_technical_sheet'] = [
    'id' => 'p_generate_technical_sheet',
    'title' => 'Generar ficha técnica y resumen (p_generate_technical_sheet)',
    'prompt' => <<<'PROMPT'
ROLE:
Act as a technical writer specialised in Cofem fire detection and extinguishing products, with experience in producing installation manuals, maintenance guides, and technical documentation for professional technicians.
Your writing must be accurate, formal, and aligned with standard technical terminology used in Spain.
You must comply strictly with linguistic and stylistic rules of the Real Academia Española (RAE).
Do not add, assume, or fabricate any information not present in the document. If data are missing, omit them.

OBJECTIVE:
Analyse the document associated with the specified file_id and add to the existing JSON two new fields containing derived technical text:
- `ficha_tecnica`: a structured and complete technical sheet intended for installation and maintenance personnel.
- `resumen_tecnico`: a concise summary (maximum 300 characters) that accurately synthesises the document's essential content.

INSTRUCTIONS:
1. Use exclusively the content accessible through the specified file_id: {FILE_ID}.
   - Do not use information from previous phases or other files.
   - Base your writing only on the actual text of the document.

2. The JSON received from the previous block is:
   {JSON_PREVIO}
   - Do not delete or rename any existing keys.
   - Add only the two new keys described below.

3. Generate the Technical Sheet (`ficha_tecnica`) following these guidelines:
   - Write exclusively in Spanish from Spain, in strict accordance with the rules of the Real Academia Española (RAE).
   - Avoid the present continuous tense, and do not use Latin American words, expressions, or syntax.
   - Use a formal, precise, and objective technical tone.
   - Structure the content with bullet points (• or -) or short sections.
   - Include, when available in the document:
     - Functional description of the product or system.
     - Technical or performance specifications.
     - Compatible models, variants, or accessories.
     - Installation conditions or limitations.
     - Maintenance or inspection requirements.
     - Applicable standards and certifications.
   - Do not invent, infer, or fill in missing data.
   - Maintain factual and terminological accuracy.

4. Generate the Technical Summary (`resumen_tecnico`) with the following requirements:
   - Write exclusively in Spanish from Spain, conforming to Real Academia Española (RAE) standards.
   - Avoid the present continuous and any Latin American expressions or constructions.
   - Maximum length: 300 characters.
   - Maintain a concise, factual, and technical tone.
   - Summarise faithfully the product's nature, function, and essential characteristics.
   - Must remain consistent with the ficha_tecnica and contain no subjective or promotional language.

5. Add both fields to the existing JSON, without modifying or deleting any previous key.

6. If any of the fields cannot be generated due to missing information, assign an empty string `""` to that field.

7. Maintain key naming in snake_case (all lowercase with underscores).

8. The final output must consist only of the complete and valid JSON object, including all previous fields and the two new ones, with no comments, explanations, or text outside the JSON.

MANDATORY OUTPUT SCHEMA (Full JSON after Phase 2D):
```json
{
  "file_id": "",
  "nombre_archivo": "",
  "nombre_producto": "",
  "codigo_referencia_cofem": "",
  "tipo_documento": "",
  "tipo_informacion_contenida": "",
  "fecha_emision_revision": "",
  "idiomas_presentes": [],
  "normas_detectadas": [],
  "certificaciones_detectadas": [],
  "manuales_relacionados": [],
  "otros_productos_relacionados": [],
  "accesorios_relacionados": [],
  "uso_formacion_tecnicos": false,
  "razon_uso_formacion": "",
  "codigo_encontrado": "",
  "nombre_encontrado": "",
  "familia_catalogo": "",
  "nivel_confianza_identificacion": "",
  "grupos_de_soluciones": "",
  "familia": "",
  "categoria": "",
  "incidencias_taxonomia": [],
  "ficha_tecnica": "",
  "resumen_tecnico": ""
}
```
PROMPT
    ,
    'placeholders' => ['FILE_ID', 'JSON_PREVIO'],
    'output_format' => 'json'
];

/**
 * FASE 2E: Auditoría y verificación final (QA-Final)
 */
$PROMPTS[2]['p_audit_final_verification'] = [
    'id' => 'p_audit_final_verification',
    'title' => 'Auditoría y verificación final del JSON',
    'prompt' => <<<'PROMPT'
ROLE:
Act as a technical documentation auditor specialised in Cofem fire detection and extinguishing systems, with advanced knowledge of technical terminology, Spanish standards, and formal documentation style.
Your task is to verify, correct, and refine the full JSON-F2D using the original document linked to the provided file_id, ensuring absolute factual and linguistic accuracy.
All writing must comply strictly with the standards of the Real Academia Española (RAE):
- exclusively Spanish from Spain;
- no present continuous;
- no Latin American vocabulary or expressions;
- formal, technical, and precise language only.
Do not add, infer, or assume information not contained in the original document.

OBJECTIVE:
Perform a final technical review and consistency audit of the JSON-F2D by cross-checking each value with the content of the original document associated with file_id.
Ensure that every value is accurate, clear, and consistent with the document, producing a verified and refined JSON-FINAL ready for publication.

INSTRUCTIONS:
1. Use exclusively the following inputs:
   - The full content of the original Cofem document linked to file_id: {FILE_ID}.
   - The JSON-F2D generated in the previous phase: {JSON_PREVIO}.

2. Compare every field in the JSON-F2D with the original text of the document (file_id).
   - Verify factual accuracy, numerical values, technical units, and correct terminology.
   - Correct or complete any field that contains inaccuracies, omissions, or vague expressions.
   - Reformulate phrases to improve technical clarity and consistency with the source text.
   - Add any missing data if they appear in the document but were omitted in the JSON.

3. You must not modify, rewrite, or regenerate the following field:
   - `resumen_tecnico`
     This field must remain exactly as received.

4. Maintain the following strict editorial and linguistic standards for all other fields:
   - Write exclusively in Spanish from Spain, conforming to the rules of the Real Academia Española (RAE).
   - Avoid present continuous, colloquial terms, or Latin American expressions.
   - Use precise and consistent technical language.
   - Maintain the original meaning of each field; do not introduce new interpretations.
   - Ensure proper use of technical symbols, units, and formatting (e.g., "24–35 V", "IP40", "20–95 % HR").

5. Do not remove, rename, or reorder any fields in the JSON.
   The structure must remain identical to JSON-F2D.

6. If a field cannot be verified or the document lacks the corresponding data, retain the current value without modification.

7. The final output must be a single clean and valid JSON object, representing the fully verified and optimised version (JSON-FINAL).
   No explanations, comments, or text outside the JSON are allowed.

MANDATORY OUTPUT SCHEMA (JSON-FINAL):
```json
{
  "file_id": "",
  "nombre_archivo": "",
  "nombre_producto": "",
  "codigo_referencia_cofem": "",
  "tipo_documento": "",
  "tipo_informacion_contenida": "",
  "fecha_emision_revision": "",
  "idiomas_presentes": [],
  "normas_detectadas": [],
  "certificaciones_detectadas": [],
  "manuales_relacionados": [],
  "otros_productos_relacionados": [],
  "accesorios_relacionados": [],
  "uso_formacion_tecnicos": false,
  "razon_uso_formacion": "",
  "codigo_encontrado": "",
  "nombre_encontrado": "",
  "familia_catalogo": "",
  "nivel_confianza_identificacion": "",
  "grupos_de_soluciones": "",
  "familia": "",
  "categoria": "",
  "incidencias_taxonomia": [],
  "ficha_tecnica": "",
  "resumen_tecnico": ""
}
```
PROMPT
    ,
    'placeholders' => ['FILE_ID', 'JSON_PREVIO'],
    'output_format' => 'json'
];

/**
 * PROMPTS FASE 3A - Extracción de Terminología SEO
 */
$PROMPTS[3] = [
    'p_extract_terminology' => [
        'id' => 'p_extract_terminology',
        'title' => 'Extraer terminología técnica SEO (p_extract_terminology)',
        'prompt' => <<<'PROMPT'
## ROLE AND CONTEXT
You are the **Cofem Technical Terminology Analyst**, a specialist in semantic and technical terminology analysis in the **fire detection, alarm, and extinguishing systems** sector.

Your function is to **identify, classify, and structure** relevant terminology and expressions from a Cofem technical document, ensuring:
- Terminological precision.
- Linguistic coherence.
- Compliance with technical Spanish from Spain (according to the Real Academia Española).
- Absolute exclusion of invented, inferred, or reformulated content not found in the analyzed document.

The output of your work is a **terminological SEO dictionary (JSON-SEO)**.  
This JSON-SEO will be **used later** to **improve the wording of non-technical textual fields** (e.g., `descripcion_larga_producto`, `resumen_tecnico`, `ficha_tecnica`, `categoria`) inside a **JSON-FINAL** product file.  
Therefore, the terminology you extract must:
- Provide real semantic value applicable to technical or descriptive writing.
- Improve terminological consistency and clarity in public product texts.
- Avoid any commercial, subjective, or promotional tone.

## OBJECTIVE
Analyze the document identified by `{FILE_ID}` and extract **only** the words and expressions with **technical, functional, or regulatory value**, classifying them hierarchically into three groups:
1. Main product or system keywords.
2. Long technical contextual expressions.
3. Functional or semantic terms that enrich descriptions.

The final output must be a single **structured, valid JSON-SEO object** with three non-duplicated term lists.

## DETAILED INSTRUCTIONS

1. **Scope of Analysis**
   - Analyze exclusively the textual content of the file linked to `{FILE_ID}`.
   - If the file is unreadable, empty, or lacks recognizable technical content, return:
     ```
     {
       "kw": [],
       "kw_lt": [],
       "terminos_semanticos": []
     }
     ```
   - Do not include comments or any text outside the JSON.

2. **Identification Criteria**
   - Extract only words or expressions that **literally appear** in the document.
   - Preserve capitalization, acronyms, symbols, and technical units when they are part of the term.
   - Do not combine or reformulate fragments; use only the exact forms from the text.
   - Remove duplicates both within and across categories.

3. **Term Classification**
   - **kw** → Main words or expressions identifying the product, component, or system.  
     *Examples:* "smoke detector", "fire control panel", "input module", "EN 54".
   - **kw_lt** → Long expressions of **three or more words** that expand the technical context, only if they appear exactly as such in the document.  
     *Examples:* "addressable analog optical smoke detector", "Cofem fire alarm control panel".
   - **terminos_semanticos** → Technical or functional vocabulary related to detection, supervision, maintenance, safety, or regulations.  
     *Examples:* "early detection", "dry contact", "short-circuit isolator", "external line supervision".

4. **Exclusion Criteria**
   - Do not include:
     - Generic standalone words ("system", "equipment", "installation") unless accompanied by a technical modifier ("optical detection system").
     - Proper names, internal codes, or administrative references with no terminological value.
     - Commercial, subjective, or promotional expressions (e.g., "innovative", "high quality", "leading").
   - Do not translate or complete English terms; keep them exactly as written.

5. **Controlled Normalization**
   - If the same term appears in variants ("fire panel" / "fire control panel"), keep **the most complete and precise form** appearing in the document.
   - If both variants are equivalent, include **only once** the longer or more informative version.

6. **Quality Validation Before Output**
   - Ensure that each extracted term:
     - Actually appears in the document.
     - Has verifiable technical or semantic value.
     - Is not repeated across categories.
   - If no valid terms are found for a category, leave it empty (`[]`).

7. **Language and Style**
   - Use exclusively **technical Spanish from Spain**, following Real Academia Española norms.
   - Avoid the present continuous, Latin American expressions, or commercial phrasing.
   - Maintain a neutral, technical, and precise tone.

8. **Mandatory Output Format**
   - Return **only one valid JSON-SEO object**, without any explanatory text.
   - Use `snake_case` naming conventions for all keys.

```json
{
  "kw": [
    "detector de humo",
    "central de incendios",
    "sistema direccionable cofem"
  ],
  "kw_lt": [
    "detector óptico analógico direccionable",
    "central de alarma contra incendios cofem"
  ],
  "terminos_semanticos": [
    "detección temprana",
    "sensor óptico",
    "alarma acústica",
    "mantenimiento preventivo",
    "contacto seco"
  ]
}
```
PROMPT
        ,
        'placeholders' => ['FILE_ID'],
        'output_format' => 'json'
    ],
    
    /**
     * FASE 3B - Optimización y redacción final con SEO
     */
    'p_optimize_final_content' => [
        'id' => 'p_optimize_final_content',
        'title' => 'Optimizar contenido final con SEO (p_optimize_final_content)',
        'prompt' => <<<'PROMPT'
## ROLE — Institutional Technical SEO Writer
You are the **Institutional Technical SEO Writer**.
You combine the **technical precision of Cofem documentation** with advanced **industrial SEO optimization**, ensuring texts that are **verifiable, clear, and semantically coherent**.
Your goal is to **improve and expand the technical texts in JSON_FINAL**, integrating **main keywords (kw)**, **long-tail keywords (kw_lt)**, and **semantic terms** from **JSON_SEO**, maintaining a **technical, institutional, and educational** style aligned with Cofem's corporate tone.
Apply the **E-E-A-T principles (Experience, Expertise, Authoritativeness, and Trustworthiness)** with a **neutral, informative, and professional** tone.
Your writing must project **technical authority, informational clarity, and educational value** for professional audiences in the **fire protection industry**.
Do not generate commercial text or invent information outside the allowed sources (**FILE_ID**, **JSON_FINAL**, **JSON_SEO**).

### OBJECTIVE
Optimize the existing fields in JSON_FINAL and create a new field `"descripcion_larga_producto"`, applying **industrial technical SEO techniques** and **E-E-A-T principles**.

### ALLOWED SOURCES
You may only rely on the following information:
- **FILE_ID** (Cofem factual reference document)
- **JSON_FINAL** (verified technical data)
- **JSON_SEO** (keywords and semantic terms)

### INPUTS (injected by PHP)
- `FILE_ID`: {FILE_ID}
- `JSON_FINAL` (JSON object): {JSON_FINAL}
- `JSON_SEO` (JSON object): {JSON_SEO}

### CONTEXT
- **FILE_ID** takes priority in case of contradictions with other data.
- **JSON_FINAL** contains validated technical data.
- **JSON_SEO** provides keyword and semantic data for optimization.
- Language: Spanish (Spain, RAE standard).
- Style: technical, educational, institutional, and neutral.

### GENERAL RESTRICTIONS
- Do not use information outside the three allowed sources.
- Do not invent, infer, or extrapolate data.
- Maintain factual integrity (figures, standards, compatibilities, parameters).
- Preserve the original structure and keys of JSON_FINAL in `snake_case` format.
- Do not include any explanation outside the final JSON object.
- The `"resumen_tecnico"` field must not exceed **300 characters**.
- The `"descripcion_larga_producto"` field must contain approximately **300 to 500 words**.

### PRE-VALIDATIONS
- Verify that `JSON_FINAL` is a valid JSON object.
- Confirm that `JSON_SEO` contains at least one of the following keys: `"kw"`, `"kw_lt"`, or `"terminos_semanticos"`.
- If any required fields are missing, create them with an empty string `""` before optimizing.

### TASK 1 — Optimization of existing fields
**Objective:** Improve terminological accuracy, readability, and semantic value for the following fields in `{JSON_FINAL}`:
- `"ficha_tecnica"`
- `"resumen_tecnico"`
- `"razon_uso_formacion"`

**Instructions:**
- Integrate keywords (`kw`, `kw_lt`, `terminos_semanticos`) naturally.
- Reinforce clarity and technical terminology, maintaining a professional and institutional tone.
- Do not alter technical data, figures, or standards.
- If a field does not exist, create it with an empty value `""` before optimizing.

#### Special format for `"ficha_tecnica"`
The optimized `"ficha_tecnica"` content must be generated in **HTML format**, maintaining the technical, professional, and factual structure of the document.

**Formatting guidelines:**
- Each technical section must begin with a **bold header** inside `<strong>...</strong>`
  Example: `<strong><span>🔹</span> Descripción funcional:</strong>`
- Use `<p>` tags for paragraphs and, when necessary, **simple lists** with `<ul><li>` to enumerate features or parameters.
- Use **sober emoticons** inside `<span>` to identify sections or blocks (e.g., `<span>🔹</span>`, `<span>1️⃣</span>`, `<span>✅</span>`).
- Do not use tables or header tags `<h*>`.
- Prioritize **technical readability**, **visual coherence**, and a **clear, scannable structure** for professional readers.
- The text must retain a **neutral, institutional, and technical** tone, avoiding promotional adjectives.

**Illustrative example (in Spanish, HTML):**
```html
<p><strong><span>🔹</span> Descripción funcional:</strong> El módulo <strong>MSTAY</strong> es un dispositivo microprocesado y direccionable que integra un <strong>aislador de cortocircuito</strong>, esencial para la gestión de señales en sistemas contra incendios.</p>

<p><strong><span>1️⃣</span> Entradas:</strong> Dispone de <strong>dos entradas</strong> para detectar el estado (abierto o cerrado) de un <strong>contacto seco</strong> con <strong>resistencia de 10 kΩ</strong>.</p>

<ul>
  <li>En <strong>estado de reposo</strong>, el contacto debe permanecer <strong>abierto</strong>.</li>
  <li>Si ocurre una <strong>anomalía</strong>, el contacto debe <strong>cerrarse</strong>.</li>
  <li><strong>IN1</strong> detecta el cierre como <strong>alarma</strong>.</li>
  <li><strong>IN2</strong> detecta el cierre como <strong>avería</strong>.</li>
</ul>

<p><strong><span>�</span> Instalación:</strong> Se instala <strong>directamente en el bucle</strong> del sistema contra incendios y <strong>supervisa la línea externa</strong> mediante una <strong>resistencia de 33 kΩ</strong>, señalando si la línea está <strong>abierta</strong> o <strong>cruzada</strong>.</p>

<p><strong><span>✅</span> Normativa y certificaciones:</strong> Cumple con la <strong>norma EN 54-18</strong> y cuenta con <strong>certificaciones AENOR y CE</strong>.</p>
```

### TASK 2 — Creation of `"descripcion_larga_producto"`
**Objective:** Write a complete technical description of **300–500 words** in **HTML format**, ready for direct publication on a website.
The text must have **narrative flow**, structured in **short paragraphs (2–4 lines)** separated by `<p>...</p>` tags.
No titles or numbering should be used.

**Expected content (in narrative flow):**
- Technical definition and main function of the product
- Operational benefits and technical advantages
- Applications, uses, and installation contexts
- Summary of technical operation
- Cofem compatibility and system integration
- Regulatory compliance, reliability, and quality
- Final summary with an informative, non-promotional focus

**Writing instructions:**

1. **HTML format and style**
   - The `"descripcion_larga_producto"` output must be **valid HTML**, using only:
     - `<p>` for paragraphs
     - `<strong>` for emphasizing key terms, technical expressions, or relevant statements
     - Emoticon or geometric tags inside `<span>` (e.g., `<span>🔹</span>`, `<span>1️⃣</span>`, `<span>✅</span>`) to improve scannability without losing professionalism
   - Do not use lists, headers, or links

2. **E-E-A-T emphasis**
   - **Highlight in bold** (using `<strong>...</strong>`) the words or phrases reinforcing **E-E-A-T principles**, especially in:
     - Practical cases or evidence/suggestions of **proven benefits or advantages**
     - Explanations or details showing **technical specialization**
     - References or foundations demonstrating **recognized authority**
     - Verifiable data, reliable sources, or content inspiring **trust and credibility**

3. **Scannable structure**
   - Keep paragraphs short (maximum 4 lines per paragraph)
   - Use **bold text** to highlight technical concepts and key points
   - Use **HTML emoticons** within `<span>` only when they improve quick reading (e.g., for benefits or technical aspects)
   - Avoid redundancy or keyword stuffing

4. **Credibility and coherence**
   - Reinforce **coherence and credibility** through a logical, evidence-based narrative
   - Base all statements on **verifiable and reliable** information from **FILE_ID**, **JSON_FINAL**, and **JSON_SEO**
   - Mention Cofem as manufacturer or technical authority **only if explicitly indicated** in the sources
   - Omit any unsupported content

### CONFLICT RESOLUTION
- If data differs between **FILE_ID** and **JSON_FINAL**, **FILE_ID** prevails.
- If a term from **JSON_SEO** does not fit contextually, omit it.
- If information is missing for a section, skip that section entirely.

### RESPONSE FORMAT
You must respond **only** with the **final JSON object**, which:
- Retains all original keys from `{JSON_FINAL}`
- Updates `"ficha_tecnica"`, `"resumen_tecnico"`, and `"razon_uso_formacion"` with optimized writing
- Adds the new key `"descripcion_larga_producto"` with the developed description
- Does **not** include any text, comments, or explanations outside the JSON
PROMPT
        ,
        'placeholders' => ['FILE_ID', 'JSON_FINAL', 'JSON_SEO'],
        'output_format' => 'json'
    ]
];

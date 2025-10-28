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
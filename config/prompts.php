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

// Puedes añadir aquí más plantillas por fase (3..10) siguiendo la estructura.
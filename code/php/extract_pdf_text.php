<?php
// extract_pdf_text.php - Extractor de texto simple y confiable para PDFs

/**
 * Extrae texto de PDF usando el método más simple disponible
 * @param string $pdfPath - Ruta al archivo PDF
 * @return array - ['success' => bool, 'text' => string, 'method' => string, 'error' => string]
 */
function simple_extract_pdf_text($pdfPath) {
    if (!file_exists($pdfPath)) {
        return ['success' => false, 'error' => 'Archivo PDF no encontrado', 'text' => '', 'method' => 'none'];
    }
    
    $extractedText = '';
    $method = 'none';
    
    // MÉTODO 1: Intentar con command line pdftotext (más confiable)
    if (function_exists('exec')) {
        $output = [];
        $return_var = 0;
        $tempTxt = sys_get_temp_dir() . '/pdf_extract_' . uniqid() . '.txt';
        
        exec("pdftotext -layout " . escapeshellarg($pdfPath) . " " . escapeshellarg($tempTxt) . " 2>&1", $output, $return_var);
        
        if ($return_var === 0 && file_exists($tempTxt) && filesize($tempTxt) > 10) {
            $extractedText = file_get_contents($tempTxt);
            unlink($tempTxt);
            if (strlen(trim($extractedText)) > 10) {
                return ['success' => true, 'text' => trim($extractedText), 'method' => 'pdftotext', 'error' => ''];
            }
        }
    }
    
    // MÉTODO 2: Usar file_get_contents básico (some PDFs have readable text)
    $rawContent = file_get_contents($pdfPath);
    if ($rawContent) {
        // Buscar texto legible en el contenido crudo
        if (preg_match_all('/\(([^)]+)\)/', $rawContent, $matches)) {
            $possibleText = implode(' ', $matches[1]);
            if (strlen($possibleText) > 50) {
                return ['success' => true, 'text' => $possibleText, 'method' => 'raw_extraction', 'error' => ''];
            }
        }
    }
    
    // MÉTODO 3: Crear texto básico con información del PDF
    $basicInfo = "PDF: " . basename($pdfPath) . "\n";
    $basicInfo .= "Tamaño: " . number_format(filesize($pdfPath) / 1024, 1) . " KB\n";
    $basicInfo .= "Fecha: " . date('Y-m-d H:i:s', filemtime($pdfPath)) . "\n\n";
    $basicInfo .= "CONTENIDO: Este PDF requiere procesamiento manual o herramientas especializadas para extraer el texto.\n";
    $basicInfo .= "Sugerencia: Copia y pega manualmente el contenido del PDF si es necesario.\n";
    
    return ['success' => false, 'text' => $basicInfo, 'method' => 'fallback', 'error' => 'No se pudo extraer texto automáticamente'];
}

/**
 * Procesa PDF y genera archivo .txt en el mismo directorio
 * @param string $pdfPath - Ruta al PDF
 * @param string $docBasename - Nombre base del documento 
 * @return array - Resultado del procesamiento
 */
function process_pdf_to_txt($pdfPath, $docBasename) {
    $result = simple_extract_pdf_text($pdfPath);
    
    // Generar archivo .txt
    $txtPath = dirname($pdfPath) . DIRECTORY_SEPARATOR . $docBasename . '.txt';
    
    // Preparar contenido con BOM UTF-8
    $bomUtf8 = "\xEF\xBB\xBF";
    $content = $bomUtf8 . $result['text'];
    
    if (file_put_contents($txtPath, $content, LOCK_EX) === false) {
        return ['success' => false, 'error' => 'No se pudo escribir archivo .txt', 'txt_path' => ''];
    }
    
    return [
        'success' => $result['success'],
        'txt_path' => $txtPath,
        'text_preview' => substr($result['text'], 0, 500),
        'text_length' => strlen($result['text']),
        'extraction_method' => $result['method'],
        'error' => $result['error']
    ];
}
?>
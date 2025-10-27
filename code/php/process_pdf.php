<?php
// process_pdf.php - Procesamiento de PDF con extracción de texto
require_once __DIR__ . '/lib_apio.php';

/**
 * Procesar PDF subido - extrae información y texto
 */
function process_uploaded_pdf($fullpath) {
    if (!is_file($fullpath)) {
        return ['ok' => false, 'error' => 'Fichero no encontrado'];
    }

    $cfg = apio_load_config();
    $dpi = intval($cfg['dpi_images'] ?? 300);

    $size = filesize($fullpath);
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime = finfo_file($finfo, $fullpath);
    finfo_close($finfo);

    // Extraer texto del PDF
    $textResult = extract_text_from_pdf($fullpath);

    return [
        'ok' => true,
        'filesize' => $size,
        'mime' => $mime,
        'dpi' => $dpi,
        'text_extraction' => $textResult
    ];
}

/**
 * Extraer texto de PDF usando herramientas disponibles en el servidor
 */
function extract_text_from_pdf($pdfPath) {
    $docDir = dirname($pdfPath);
    $basename = pathinfo($pdfPath, PATHINFO_FILENAME);
    $txtPath = $docDir . DIRECTORY_SEPARATOR . $basename . '.txt';
    
    // Método 1: Intentar con pdftotext (más común en servidores)
    $output = [];
    $returnVar = 0;
    
    exec("pdftotext -layout -enc UTF-8 " . escapeshellarg($pdfPath) . " " . escapeshellarg($txtPath) . " 2>&1", $output, $returnVar);
    
    if ($returnVar === 0 && is_file($txtPath) && filesize($txtPath) > 0) {
        return [
            'ok' => true, 
            'method' => 'pdftotext',
            'txt_file' => $txtPath,
            'text_preview' => substr(file_get_contents($txtPath), 0, 500)
        ];
    }
    
    // Método 2: Intentar con Imagick (si está disponible)
    if (extension_loaded('imagick')) {
        try {
            $imagick = new Imagick($pdfPath);
            $text = '';
            $numPages = $imagick->getNumberImages();
            
            for ($i = 0; $i < min($numPages, 10); $i++) { // Máximo 10 páginas
                $imagick->setIteratorIndex($i);
                $imagick->setImageFormat('txt');
                $pageText = $imagick->getImageBlob();
                $text .= $pageText . "\n\n";
            }
            
            if (strlen(trim($text)) > 50) {
                file_put_contents($txtPath, $text);
                return [
                    'ok' => true,
                    'method' => 'imagick',
                    'txt_file' => $txtPath,
                    'text_preview' => substr($text, 0, 500),
                    'pages_processed' => $numPages
                ];
            }
            
        } catch (Exception $e) {
            // Continuar con siguiente método
        }
    }
    
    // Método 3: Crear archivo TXT básico con información del PDF
    $basicInfo = "PDF: " . basename($pdfPath) . "\n";
    $basicInfo .= "Tamaño: " . filesize($pdfPath) . " bytes\n";
    $basicInfo .= "Fecha: " . date('Y-m-d H:i:s', filemtime($pdfPath)) . "\n\n";
    $basicInfo .= "[TEXTO NO EXTRAÍDO AUTOMÁTICAMENTE]\n";
    $basicInfo .= "El servidor no pudo extraer el texto de este PDF.\n";
    $basicInfo .= "Herramientas intentadas: pdftotext, imagick\n";
    
    file_put_contents($txtPath, $basicInfo);
    
    return [
        'ok' => false,
        'method' => 'fallback',
        'txt_file' => $txtPath,
        'error' => 'No se pudo extraer texto automáticamente',
        'text_preview' => $basicInfo
    ];
}
?>
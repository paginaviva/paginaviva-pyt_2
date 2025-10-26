<?php
// process_pdf.php - ejemplo de procesado que usa config (dpi_images, etc.)
require_once __DIR__ . '/lib_apio.php';

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

    return [
        'ok' => true,
        'filesize' => $size,
        'mime' => $mime,
        'dpi' => $dpi
    ];
}
?>
<?php
// docs_list.php - lista los documentos organizados en subdirectorios bajo docs_dir
require_once __DIR__ . '/lib_apio.php';
$config = apio_load_config();
$docsDir = rtrim($config['docs_dir'], "/\\");

// comprobar existencia
if (!is_dir($docsDir)) {
    echo '<p>No hay carpeta de documentos (docs_dir no existe).</p>';
    return;
}

// leer subdirectorios (cada subdir = SDIR con un documento)
$dirs = array_filter(scandir($docsDir), function($d) use ($docsDir) {
    if ($d === '.' || $d === '..') return false;
    return is_dir($docsDir . DIRECTORY_SEPARATOR . $d);
});
if (empty($dirs)) {
    echo '<p>No hay documentos subidos todavía.</p>';
    return;
}

echo '<h2>Lista de documentos</h2>';
echo '<ul>';
foreach ($dirs as $d) {
    $sdir = $docsDir . DIRECTORY_SEPARATOR . $d;
    // Buscar un PDF con basename igual a $d.* preferiblemente
    $candidates = glob($sdir . DIRECTORY_SEPARATOR . $d . '.*');
    $fileLink = '';
    if (!empty($candidates)) {
        // elegir el primer candidato
        $file = basename($candidates[0]);
        // construir ruta web relativa si possible (intentar con public base)
        $publicBase = rtrim($config['public_base'] ?? '', '/');
        if ($publicBase) {
            // if docs_dir is under document root, try to build public URL
            // Best effort: user may serve /docs from a public path; try relative mapping via code_dir
            // Fallback: provide file:// path on server
            $relative = str_replace($_SERVER['DOCUMENT_ROOT'] ?? '', '', $sdir . DIRECTORY_SEPARATOR . $file);
            $url = $publicBase . $relative;
        } else {
            $url = 'docs/' . rawurlencode($d) . '/' . rawurlencode($file);
        }
        $fileLink = '<a href="' . htmlspecialchars($url, ENT_QUOTES) . '" target="_blank" rel="noopener noreferrer">' . htmlspecialchars($file) . '</a>';
    } else {
        // no encuentra archivo; listar carpeta vacía
        $fileLink = '<em>(sin archivo)</em>';
    }

    echo '<li><strong>' . htmlspecialchars($d) . '</strong>: ' . $fileLink . '</li>';
}
echo '</ul>';
?>
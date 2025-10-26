<?php
// cleanup.php - limpia tmp_dir de partes incompletas y docs_dir de carpetas antiguas
require_once __DIR__ . '/lib_apio.php';
$config = apio_load_config();

$tmpDir = $config['tmp_dir'];
$docsDir = $config['docs_dir'];
$incompleteHours = intval($config['cleanup']['incomplete_hours'] ?? 6);
$keepDays = intval($config['cleanup']['keep_days'] ?? 30);
$now = time();

// 1) eliminar directorios de tmp con fecha de modificación > incompleteHours
if (is_dir($tmpDir)) {
    foreach (scandir($tmpDir) as $entry) {
        if ($entry === '.' || $entry === '..') continue;
        $path = $tmpDir . DIRECTORY_SEPARATOR . $entry;
        if (is_dir($path)) {
            $mtime = filemtime($path);
            if ($mtime !== false && ($now - $mtime) > ($incompleteHours * 3600)) {
                // eliminar contenido y directorio
                $files = glob($path . DIRECTORY_SEPARATOR . '*');
                foreach ($files as $f) { @unlink($f); }
                @rmdir($path);
            }
        }
    }
}

// 2) eliminar documentos (subdirs) con última modificación > keepDays
if (is_dir($docsDir)) {
    foreach (scandir($docsDir) as $entry) {
        if ($entry === '.' || $entry === '..') continue;
        $path = $docsDir . DIRECTORY_SEPARATOR . $entry;
        if (is_dir($path)) {
            $mtime = filemtime($path);
            if ($mtime !== false && ($now - $mtime) > ($keepDays * 24 * 3600)) {
                // eliminar archivos y directorio
                $files = glob($path . DIRECTORY_SEPARATOR . '*');
                foreach ($files as $f) { @unlink($f); }
                @rmdir($path);
            }
        }
    }
}

echo json_encode(['ok' => true, 'tmp' => $tmpDir, 'docs' => $docsDir]);
?>
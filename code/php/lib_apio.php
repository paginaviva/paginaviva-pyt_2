<?php
// lib_apio.php - helper utilities incl. carga centralizada de config.json
// Resuelve project_root y normaliza rutas absolutas/relativas.

// Devuelve la ruta al directorio 'code' (este archivo está en code/php/)
function apio_get_code_dir() {
    return dirname(__DIR__); // .../code
}

// Devuelve el proyecto root (prefer config.project_root, si no existe, padre de 'code')
function apio_get_project_root() {
    static $root = null;
    if ($root !== null) return $root;

    // Intentar cargar config.json si está disponible en ../config/
    $cfgPath = apio_get_code_dir() . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'config.json';
    $cfgPath = realpath($cfgPath) ?: $cfgPath;

    if (is_file($cfgPath)) {
        $raw = file_get_contents($cfgPath);
        $parsed = json_decode($raw, true);
        if (is_array($parsed) && !empty($parsed['project_root'])) {
            $root = rtrim($parsed['project_root'], "/\\");
            return $root;
        }
    }

    // Fallback: project root = parent of code dir
    $root = dirname(apio_get_code_dir());
    return $root;
}

// Resuelve ruta: si $path es absoluta (empieza por / o con letra:), devuelve tal cual,
// si es relativa, la interpreta respecto a project_root.
function apio_resolve_path($path) {
    if (!$path) return $path;
    // Windows absolute drive letter or Unix absolute
    if (DIRECTORY_SEPARATOR === '\\') {
        // Windows
        if (preg_match('/^[A-Za-z]:[\\\\\\/]/', $path)) return $path;
    } else {
        if (strpos($path, '/') === 0) return $path;
    }
    // Relativo => resolver respecto a project_root
    $root = apio_get_project_root();
    return rtrim($root, "/\\") . DIRECTORY_SEPARATOR . ltrim($path, "/\\");
}

function apio_get_config_path() {
    // Default location: ../config/config.json from code dir
    $p = apio_get_code_dir() . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'config.json';
    return realpath($p) ?: $p;
}

function apio_load_config() {
    static $cfg = null;
    if ($cfg !== null) return $cfg;

    $cfgPath = apio_get_config_path();
    if (!is_file($cfgPath)) {
        // defaults safe
        $defaultRoot = apio_get_project_root();
        $cfg = [
            'project_root' => $defaultRoot,
            'tmp_dir' => $defaultRoot . DIRECTORY_SEPARATOR . 'tmp',
            'docs_dir' => $defaultRoot . DIRECTORY_SEPARATOR . 'docs',
            'upload_max_filesize' => 10 * 1024 * 1024,
            'max_document_size' => 20 * 1024 * 1024,
            'public_base' => ''
        ];
        return $cfg;
    }

    $raw = @file_get_contents($cfgPath);
    $parsed = @json_decode($raw, true);
    if (!is_array($parsed)) $parsed = [];

    // project_root explicit or derive
    $projectRoot = !empty($parsed['project_root']) ? rtrim($parsed['project_root'], "/\\") : apio_get_project_root();

    // tmp_dir/docs_dir: if absolute use them, if relative resolve against project_root
    $tmp = $parsed['tmp_dir'] ?? 'tmp';
    $docs = $parsed['docs_dir'] ?? 'docs';

    // Resolve
    $tmpResolved = (strpos($tmp, DIRECTORY_SEPARATOR) === 0 || preg_match('/^[A-Za-z]:[\\\\\\/]/', $tmp)) ? $tmp : $projectRoot . DIRECTORY_SEPARATOR . ltrim($tmp, "/\\");
    $docsResolved = (strpos($docs, DIRECTORY_SEPARATOR) === 0 || preg_match('/^[A-Za-z]:[\\\\\\/]/', $docs)) ? $docs : $projectRoot . DIRECTORY_SEPARATOR . ltrim($docs, "/\\");

    // normalize
    $parsed['project_root'] = $projectRoot;
    $parsed['tmp_dir'] = rtrim($tmpResolved, "/\\");
    $parsed['docs_dir'] = rtrim($docsResolved, "/\\");
    if (empty($parsed['upload_max_filesize'])) $parsed['upload_max_filesize'] = 10 * 1024 * 1024;
    if (empty($parsed['max_document_size'])) $parsed['max_document_size'] = 20 * 1024 * 1024;
    if (!isset($parsed['public_base'])) $parsed['public_base'] = '';

    $cfg = $parsed;
    return $cfg;
}

// Helper: sanitize a basename (sin extensión)
function apio_safe_basename($name) {
    $name = preg_replace('/\.[^.]+$/', '', $name); // remove extension
    $name = preg_replace('/[^A-Za-z0-9._-]/', '_', $name);
    $name = trim($name, "._-");
    if ($name === '') $name = 'doc';
    return $name;
}
?>
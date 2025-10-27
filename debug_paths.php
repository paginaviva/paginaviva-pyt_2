<?php
// debug_paths.php - Diagnóstico simple de rutas
header('Content-Type: text/plain; charset=utf-8');

echo "=== DIAGNÓSTICO DE RUTAS ED_CFLE ===\n\n";

// Cargar configuración
require_once __DIR__ . '/code/php/lib_apio.php';

try {
    $config = apio_load_config();
    
    echo "CONFIGURACIÓN:\n";
    echo "- project_root: " . $config['project_root'] . "\n";
    echo "- tmp_dir: " . $config['tmp_dir'] . "\n";
    echo "- docs_dir: " . $config['docs_dir'] . "\n\n";
    
    echo "CONTEXTO PHP:\n";
    echo "- Directorio actual: " . getcwd() . "\n";
    echo "- Directorio script: " . __DIR__ . "\n";
    echo "- Usuario PHP: " . get_current_user() . "\n";
    echo "- UID: " . getmyuid() . "\n\n";
    
    $paths = [
        'tmp_dir' => $config['tmp_dir'],
        'docs_dir' => $config['docs_dir']
    ];
    
    foreach ($paths as $name => $path) {
        echo "VERIFICACIÓN $name: $path\n";
        echo "  file_exists(): " . (file_exists($path) ? 'SÍ' : 'NO') . "\n";
        echo "  is_dir(): " . (is_dir($path) ? 'SÍ' : 'NO') . "\n";
        echo "  is_readable(): " . (is_readable($path) ? 'SÍ' : 'NO') . "\n";
        echo "  is_writable(): " . (is_writable($path) ? 'SÍ' : 'NO') . "\n";
        
        if (file_exists($path)) {
            $perms = fileperms($path);
            echo "  Permisos: " . sprintf('%o', $perms & 0777) . "\n";
        }
        
        // Prueba real de escritura
        $testFile = $path . '/.test_write';
        if (@file_put_contents($testFile, 'test') !== false) {
            echo "  Escritura real: SÍ\n";
            @unlink($testFile);
        } else {
            echo "  Escritura real: NO\n";
        }
        
        echo "\n";
    }
    
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}

echo "=== FIN DIAGNÓSTICO ===\n";
?>
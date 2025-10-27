<?php
// check_permissions.php - Diagnóstico de permisos del servidor
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h2>Diagnóstico de Permisos - Ed CFLE</h2>\n";
echo "<pre>\n";

// Cargar configuración
require_once 'code/php/lib_apio.php';
$config = apio_load_config();

echo "=== CONFIGURACIÓN ===\n";
echo "Project Root: " . $config['project_root'] . "\n";
echo "Tmp Dir: " . $config['tmp_dir'] . "\n";
echo "Docs Dir: " . $config['docs_dir'] . "\n";
echo "\n";

echo "=== INFORMACIÓN DEL SERVIDOR ===\n";
echo "Usuario web: " . get_current_user() . "\n";
echo "UID: " . getmyuid() . "\n";
echo "GID: " . getmygid() . "\n";
echo "Directorio actual: " . getcwd() . "\n";
echo "Document Root: " . ($_SERVER['DOCUMENT_ROOT'] ?? 'No definido') . "\n";
echo "\n";

// Verificar directorios principales
$dirs = [
    'Project Root' => $config['project_root'],
    'Tmp Dir' => $config['tmp_dir'],
    'Docs Dir' => $config['docs_dir']
];

echo "=== VERIFICACIÓN DE DIRECTORIOS ===\n";
foreach ($dirs as $label => $dir) {
    echo "$label: $dir\n";
    
    if (is_dir($dir)) {
        echo "  ✓ Existe\n";
        echo "  ✓ Legible: " . (is_readable($dir) ? 'SÍ' : 'NO') . "\n";
        echo "  ✓ Escribible: " . (is_writable($dir) ? 'SÍ' : 'NO') . "\n";
        
        $perms = fileperms($dir);
        echo "  ✓ Permisos: " . sprintf('%o', $perms & 0777) . "\n";
        
        $owner = fileowner($dir);
        $group = filegroup($dir);
        echo "  ✓ Propietario UID: $owner\n";
        echo "  ✓ Grupo GID: $group\n";
    } else {
        echo "  ✗ NO EXISTE\n";
        
        // Verificar directorio padre
        $parent = dirname($dir);
        echo "  Directorio padre: $parent\n";
        if (is_dir($parent)) {
            echo "    ✓ Padre existe\n";
            echo "    ✓ Padre escribible: " . (is_writable($parent) ? 'SÍ' : 'NO') . "\n";
            $perms = fileperms($parent);
            echo "    ✓ Permisos padre: " . sprintf('%o', $perms & 0777) . "\n";
        } else {
            echo "    ✗ Padre NO EXISTE\n";
        }
    }
    echo "\n";
}

echo "=== PRUEBA DE CREACIÓN ===\n";
$testDir = $config['tmp_dir'] . '/test_' . time();
echo "Intentando crear: $testDir\n";

if (mkdir($testDir, 0755, true)) {
    echo "✓ Creación exitosa\n";
    
    $testFile = $testDir . '/test.txt';
    if (file_put_contents($testFile, 'test') !== false) {
        echo "✓ Escritura de archivo exitosa\n";
        unlink($testFile);
    } else {
        echo "✗ Error escribiendo archivo\n";
    }
    
    rmdir($testDir);
    echo "✓ Eliminación exitosa\n";
} else {
    echo "✗ Error en creación: " . error_get_last()['message'] . "\n";
}

echo "\n=== RECOMENDACIONES ===\n";

// Verificar si los directorios principales no existen
if (!is_dir($config['tmp_dir']) || !is_dir($config['docs_dir'])) {
    echo "PROBLEMA: Directorios principales no existen.\n";
    echo "SOLUCIÓN: Ejecutar en el servidor:\n";
    echo "  mkdir -p " . $config['tmp_dir'] . "\n";
    echo "  mkdir -p " . $config['docs_dir'] . "\n";
    echo "  chown -R www-data:www-data " . $config['tmp_dir'] . "\n";
    echo "  chown -R www-data:www-data " . $config['docs_dir'] . "\n";
    echo "  chmod -R 755 " . $config['tmp_dir'] . "\n";
    echo "  chmod -R 755 " . $config['docs_dir'] . "\n";
    echo "\n";
}

if (is_dir($config['tmp_dir']) && !is_writable($config['tmp_dir'])) {
    echo "PROBLEMA: tmp_dir no es escribible.\n";
    echo "SOLUCIÓN: chmod 755 " . $config['tmp_dir'] . "\n";
    echo "\n";
}

if (is_dir($config['docs_dir']) && !is_writable($config['docs_dir'])) {
    echo "PROBLEMA: docs_dir no es escribible.\n";
    echo "SOLUCIÓN: chmod 755 " . $config['docs_dir'] . "\n";
    echo "\n";
}

echo "</pre>\n";
?>
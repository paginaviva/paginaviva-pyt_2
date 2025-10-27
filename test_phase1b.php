<?php
// test_phase1b.php - Script de prueba para validar Fase 1B
require_once __DIR__ . '/code/php/lib_apio.php';

echo "=== TEST FASE 1B - ED CFLE ===\n\n";

// 1. Verificar configuración
echo "1. CONFIGURACIÓN:\n";
$cfg = apio_load_config();
echo "   ✓ Config cargada: " . (count($cfg) > 0 ? "SÍ" : "NO") . "\n";
echo "   ✓ Debug display: " . (isset($cfg['debug_display']) ? "SÍ" : "NO") . "\n";
echo "   ✓ APIO models: " . (isset($cfg['apio_models']) ? count($cfg['apio_models']) . " modelos" : "NO") . "\n";
echo "   ✓ APIO defaults: " . (isset($cfg['apio_defaults']) ? "SÍ" : "NO") . "\n";
echo "   ✓ Docs dir: " . ($cfg['docs_dir'] ?? 'NO CONFIGURADO') . "\n";

// 2. Verificar funciones de logging
echo "\n2. FUNCIONES DE LOGGING:\n";
echo "   ✓ apio_log_event: " . (function_exists('apio_log_event') ? "DISPONIBLE" : "FALTA") . "\n";
echo "   ✓ apio_log_error: " . (function_exists('apio_log_error') ? "DISPONIBLE" : "FALTA") . "\n";
echo "   ✓ apio_log_success: " . (function_exists('apio_log_success') ? "DISPONIBLE" : "FALTA") . "\n";
echo "   ✓ apio_call_openai: " . (function_exists('apio_call_openai') ? "DISPONIBLE" : "FALTA") . "\n";

// 3. Verificar directorio docs
echo "\n3. ESTRUCTURA DE ARCHIVOS:\n";
$docsDir = $cfg['docs_dir'] ?? '';
echo "   ✓ Docs dir existe: " . (is_dir($docsDir) ? "SÍ" : "NO") . "\n";

if (is_dir($docsDir)) {
    $items = scandir($docsDir);
    $pdfCount = 0;
    foreach ($items as $item) {
        if ($item === '.' || $item === '..') continue;
        $itemPath = $docsDir . DIRECTORY_SEPARATOR . $item;
        if (is_dir($itemPath)) {
            $pdfPath = $itemPath . DIRECTORY_SEPARATOR . $item . '.pdf';
            if (is_file($pdfPath)) {
                $pdfCount++;
                echo "   📄 Documento: $item (" . round(filesize($pdfPath)/1024) . " KB)\n";
            }
        }
    }
    echo "   ✓ Total documentos: $pdfCount\n";
}

// 4. Verificar archivos creados
echo "\n4. ARCHIVOS FASE 1B:\n";
$files = [
    'process_1b.php' => __DIR__ . '/code/php/process_1b.php',
    'phase_1b.php' => __DIR__ . '/code/php/phase_1b.php',
    'phase_1b.js' => __DIR__ . '/code/js/phase_1b.js'
];

foreach ($files as $name => $path) {
    echo "   ✓ $name: " . (file_exists($path) ? "CREADO (" . round(filesize($path)/1024) . " KB)" : "FALTA") . "\n";
}

// 5. Verificar prompts
echo "\n5. PROMPTS:\n";
$promptsPath = __DIR__ . '/config/prompts.php';
if (file_exists($promptsPath)) {
    require_once $promptsPath;
    echo "   ✓ Prompts cargados: SÍ\n";
    echo "   ✓ p_extract_text: " . (isset($PROMPTS[1]['p_extract_text']) ? "DISPONIBLE" : "FALTA") . "\n";
} else {
    echo "   ✗ Prompts: ARCHIVO NO ENCONTRADO\n";
}

// 6. Test básico de logging (si hay documentos)
if ($pdfCount > 0) {
    echo "\n6. TEST DE LOGGING:\n";
    $testDoc = 'test_doc';
    $result = apio_log_event($testDoc, '1B_TEST', 'INFO', 'Test de funcionalidad de logging');
    echo "   ✓ Log test: " . ($result ? "ÉXITO" : "FALLO") . "\n";
}

echo "\n=== FIN DEL TEST ===\n";
?>
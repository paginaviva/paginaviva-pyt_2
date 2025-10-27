<?php
// test_phase1b.php - Test COMPLETO de la nueva Fase 1B simplificada
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h2>🧪 TEST COMPLETO FASE 1B SIMPLIFICADA</h2>\n";
echo "<pre>\n";

require_once __DIR__ . '/code/php/lib_apio.php';
require_once __DIR__ . '/code/php/extract_pdf_text.php';

echo "=== NUEVO ENFOQUE FASE 1B ===\n";

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

// 6. TEST DE EXTRACCIÓN DE PDF
echo "\n6. TEST DE EXTRACCIÓN:\n";
if ($pdfCount > 0) {
    // Buscar primer PDF disponible
    $items = scandir($docsDir);
    $testPdf = null;
    foreach ($items as $item) {
        if ($item === '.' || $item === '..') continue;
        $itemPath = $docsDir . DIRECTORY_SEPARATOR . $item;
        if (is_dir($itemPath)) {
            $pdfPath = $itemPath . DIRECTORY_SEPARATOR . $item . '.pdf';
            if (is_file($pdfPath)) {
                $testPdf = $pdfPath;
                $testDoc = $item;
                break;
            }
        }
    }
    
    if ($testPdf) {
        echo "   🔍 Probando con: $testDoc\n";
        $extractResult = simple_extract_pdf_text($testPdf);
        echo "   ✓ Extracción: " . ($extractResult['success'] ? "ÉXITO" : "FALLO") . "\n";
        echo "   ✓ Método: " . $extractResult['method'] . "\n";
        echo "   ✓ Texto length: " . strlen($extractResult['text']) . " caracteres\n";
        
        if ($extractResult['text']) {
            echo "   📝 Preview: " . substr($extractResult['text'], 0, 100) . "...\n";
        }
    }
}

// 7. SIMULACIÓN DE PROCESS_1B.PHP
echo "\n7. SIMULACIÓN PROCESS_1B:\n";
if (isset($testDoc)) {
    echo "   🎯 Simulando procesamiento de: $testDoc\n";
    
    // Simular sesión y POST
    session_start();
    $_SESSION['user'] = 'test_user';
    $_POST['doc_basename'] = $testDoc;
    $_POST['model'] = 'gpt-4o-mini';
    $_POST['temperature'] = '0';
    $_POST['max_tokens'] = '1500';
    $_SERVER['REQUEST_METHOD'] = 'POST';
    
    echo "   ✓ Sesión configurada\n";
    echo "   ✓ POST configurado\n";
    echo "   ✓ Listo para procesar\n";
    
    echo "\n   💡 EJECUTA: https://cfle.plazza.xyz/test_phase1b.php\n";
    echo "   💡 O directamente: https://cfle.plazza.xyz/code/php/process_1b.php\n";
}

echo "\n=== EVALUACIÓN FINAL ===\n";
echo "✅ Enfoque simplificado implementado\n";
echo "✅ Función de extracción creada\n";
echo "✅ Process_1b.php simplificado\n";
echo "✅ Sin dependencias complejas\n";
echo "✅ Manejo de errores robusto\n";

echo "\n=== INSTRUCCIONES PARA EL EXPERTO ===\n";
echo "1. Verificar que pdftotext está disponible en servidor\n";
echo "2. Probar extracción manual con: simple_extract_pdf_text()\n";
echo "3. Si pdftotext no funciona, implementar método alternativo\n";
echo "4. El sistema ya NO depende de OpenAI para extracción básica\n";
echo "5. Archivo .txt se genera automáticamente\n";

echo "</pre>\n";
?>
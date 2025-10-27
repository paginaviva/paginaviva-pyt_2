<?php
// test_config.php - Script para verificar que todas las URLs y rutas se generen correctamente
require_once __DIR__ . '/code/php/lib_apio.php';

echo "<h1>Verificación de Configuración</h1>\n";

$config = apio_load_config();

echo "<h2>Configuración Cargada:</h2>\n";
echo "<pre>" . print_r($config, true) . "</pre>\n";

echo "<h2>URLs Públicas Generadas:</h2>\n";
$publicBase = rtrim($config['public_base'] ?? '', '/');

$urls = [
    'Login' => $publicBase . '/code/php/login.php',
    'Panel Principal' => $publicBase . '/code/php/index.php',
    'Subir PDF' => $publicBase . '/code/php/upload_form.php',
    'Lista Documentos' => $publicBase . '/code/php/docs_list.php',
    'Logout' => $publicBase . '/code/php/logout.php',
    'CSS' => $publicBase . ($config['css_path'] ?? '/css/styles.css'),
    'Logo' => $publicBase . ($config['logo_path'] ?? '/css/BeeViva_Logo_Colour.avif'),
];

echo "<ul>\n";
foreach ($urls as $name => $url) {
    echo "<li><strong>$name:</strong> <a href=\"$url\">$url</a></li>\n";
}
echo "</ul>\n";

echo "<h2>Rutas del Sistema:</h2>\n";
$paths = [
    'Project Root' => $config['project_root'] ?? 'No configurado',
    'Temporales' => $config['tmp_dir'] ?? 'No configurado',
    'Documentos' => $config['docs_dir'] ?? 'No configurado',
    'Código' => $config['code_dir'] ?? 'No configurado',
    'CSS' => $config['css_dir'] ?? 'No configurado',
];

echo "<ul>\n";
foreach ($paths as $name => $path) {
    $exists = is_dir($path) ? '✅ Existe' : '❌ No existe';
    echo "<li><strong>$name:</strong> $path ($exists)</li>\n";
}
echo "</ul>\n";

echo "<h2>APIs y URLs Externas:</h2>\n";
echo "<ul>\n";
echo "<li><strong>OpenAI API:</strong> " . ($config['apio_url'] ?? 'No configurado') . "</li>\n";
echo "<li><strong>API Key configurada:</strong> " . (isset($config['apio_key']) && !empty($config['apio_key']) && $config['apio_key'] !== 'sk-proj-YOUR_OPENAI_API_KEY_HERE' ? '✅ Sí' : '❌ No') . "</li>\n";
echo "</ul>\n";

echo "<h2>Límites y Configuración:</h2>\n";
echo "<ul>\n";
echo "<li><strong>Max Upload:</strong> " . number_format($config['upload_max_filesize'] ?? 0) . " bytes (" . round(($config['upload_max_filesize'] ?? 0) / 1024 / 1024, 2) . " MB)</li>\n";
echo "<li><strong>Max Document:</strong> " . number_format($config['max_document_size'] ?? 0) . " bytes (" . round(($config['max_document_size'] ?? 0) / 1024 / 1024, 2) . " MB)</li>\n";
echo "<li><strong>DPI Imágenes:</strong> " . ($config['dpi_images'] ?? 'No configurado') . "</li>\n";
echo "<li><strong>Modelo IA:</strong> " . ($config['default_model'] ?? 'No configurado') . "</li>\n";
echo "</ul>\n";

?>
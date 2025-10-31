<?php
/**
 * test_taxonomia_file.php
 * Verifica si el archivo Taxonomia Cofem.csv está cargado correctamente en OpenAI.
 * 
 * Autor: Proyecto Cofem
 * Fecha: 2025-10
 */

// === CONFIGURACIÓN ===
$config_path = '/home/plazzaxy/public_html/ed_cfle/config/config.json';
//$file_id = 'file-PQ9KQbkw7tYumKWL8UtvL5'; // Sustituye por el FILE_ID real obtenido al subir el CSV Taxonomia Cofem.csv
$file_id = 'file-TGC6KXtXdnV2qCZyXFXmRU'; // Sustituye por el FILE_ID real obtenido al subir el CSV Productos Cofem.csv

// === VALIDACIONES PREVIAS ===
if (!function_exists('curl_version')) {
    die("❌ Error: La extensión cURL no está disponible.<br>");
}

if (!file_exists($config_path)) {
    die("❌ Error: No se encontró el archivo de configuración:<br>$config_path<br>");
}

$config_data = json_decode(file_get_contents($config_path), true);

if (json_last_error() !== JSON_ERROR_NONE) {
    die("❌ Error: El archivo config.json contiene un formato JSON inválido.<br>");
}

if (empty($config_data['apio_key'])) {
    die("❌ Error: No se encontró 'apio_key' en el archivo config.json.<br>");
}

$api_key = trim($config_data['apio_key']);

// === CONSULTAR EL ARCHIVO EN OPENAI ===
$ch = curl_init("https://api.openai.com/v1/files/$file_id");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Authorization: Bearer ' . $api_key
]);

$response = curl_exec($ch);

if (curl_errno($ch)) {
    die("❌ Error cURL: " . curl_error($ch) . "<br>");
}

curl_close($ch);

$result = json_decode($response, true);

// === VALIDAR RESPUESTA ===
if (isset($result['id']) && $result['id'] === $file_id) {
    echo "✅ Archivo encontrado correctamente en OpenAI.<br>";
    echo "📄 Nombre del archivo: " . $result['filename'] . "<br>";
    echo "📦 Tamaño: " . number_format($result['bytes'] / 1024, 2) . " KB<br>";
    echo "📅 Fecha de creación: " . date('Y-m-d H:i:s', $result['created_at']) . "<br>";
    echo "🧭 Propósito declarado: " . $result['purpose'] . "<br>";
} else {
    echo "⚠️ No se encontró el archivo en OpenAI o el FILE_ID no es válido.<br>";
    echo "Respuesta de la API:<br>$response<br>";
}

?>

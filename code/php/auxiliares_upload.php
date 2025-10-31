<?php
/**
 * upload_taxonomia.php
 * Subir Taxonomia Cofem.csv a OpenAI y mostrar su FILE_ID
 * Ruta: /home/plazzaxy/public_html/ed_cfle/php/upload_taxonomia.php
 * 
 * Autor: Proyecto Cofem
 * Fecha: 2025-10
 */

// === RUTAS Y CONFIGURACIÓN ===
//$csv_path    = '/home/plazzaxy/public_html/ed_cfle/docs/Taxonomia Cofem.csv';
$csv_path    = '/home/plazzaxy/public_html/ed_cfle/docs/Productos Cofem.csv';
$config_path = '/home/plazzaxy/public_html/ed_cfle/config/config.json';

// === COMPROBACIONES BÁSICAS ===
if (!function_exists('curl_version')) {
    die("❌ Error: La extensión cURL no está disponible en este servidor.<br>");
}

if (!file_exists($config_path)) {
    die("❌ Error: No se encontró el archivo de configuración en:<br>$config_path<br>");
}

if (!file_exists($csv_path)) {
    die("❌ Error: No se encontró el archivo CSV en:<br>$csv_path<br>");
}

// === LECTURA Y VALIDACIÓN DE CONFIG.JSON ===
$config_data = json_decode(file_get_contents($config_path), true);

if (json_last_error() !== JSON_ERROR_NONE) {
    die("❌ Error: El archivo config.json contiene un formato JSON inválido.<br>");
}

if (empty($config_data['apio_key'])) {
    die("❌ Error: No se encontró 'apio_key' en el archivo config.json.<br>");
}

$api_key = trim($config_data['apio_key']);

// === SUBIDA DEL ARCHIVO A OPENAI ===
echo "🔹 Iniciando subida del archivo ...<br>";

$ch = curl_init('https://api.openai.com/v1/files');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);

$post_fields = [
    'purpose' => 'assistants',
    'file' => new CURLFile($csv_path, 'text/csv', basename($csv_path))
];

curl_setopt($ch, CURLOPT_POSTFIELDS, $post_fields);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Authorization: Bearer ' . $api_key
]);

$response = curl_exec($ch);

if (curl_errno($ch)) {
    die("❌ Error cURL: " . curl_error($ch) . "<br>");
}

curl_close($ch);

// === PROCESAR LA RESPUESTA ===
$result = json_decode($response, true);

if (json_last_error() !== JSON_ERROR_NONE) {
    die("❌ Error: No se pudo decodificar la respuesta JSON de la API.<br>Respuesta:<br>$response<br>");
}

if (!isset($result['id'])) {
    die("❌ Error: No se recibió un 'file_id' válido de OpenAI.<br>Respuesta completa:<br>$response<br>");
}

$file_id = $result['id'];

// === MOSTRAR RESULTADO ===
echo "✅ Archivo subido correctamente.<br>";
echo "📄 FILE_ID obtenido: $file_id<br>";
echo "⚙️  Este FILE_ID debe añadirse como constante o variable global para F2C.<br>";

?>

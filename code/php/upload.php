<?php
// upload.php - receptor de subidas, crea SDIR bajo docs_dir (en project_root) y guarda el PDF con su basename
// Reescrito para usar config.json (lib_apio.php). Sobrescribe fichero si ya existe.

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/lib_apio.php';

$config = apio_load_config();
$tmpDir = $config['tmp_dir'];
$docsDir = $config['docs_dir'];
$maxFile = intval($config['upload_max_filesize']);
$maxDoc = intval($config['max_document_size']);

// Asegurar existencia de dirs
foreach ([$tmpDir, $docsDir] as $d) {
    if (!is_dir($d)) {
        if (!mkdir($d, 0755, true) && !is_dir($d)) {
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => "No se puede crear directorio: $d"]);
            exit;
        }
    }
}

function resError($msg, $code = 400) {
    http_response_code($code);
    echo json_encode(['success' => false, 'error' => $msg]);
    exit;
}
function resOk($data = []) {
    echo json_encode(array_merge(['success' => true], $data));
    exit;
}

// 1) Subida completa en $_FILES['file']
if (isset($_FILES['file']) && is_uploaded_file($_FILES['file']['tmp_name'])) {
    $file = $_FILES['file'];

    if ($file['error'] !== UPLOAD_ERR_OK) {
        $msg = 'Error en la subida.';
        switch ($file['error']) {
            case UPLOAD_ERR_INI_SIZE:
            case UPLOAD_ERR_FORM_SIZE:
                $msg = 'Fichero demasiado grande.';
                break;
            case UPLOAD_ERR_PARTIAL:
                $msg = 'Subida parcial.';
                break;
            case UPLOAD_ERR_NO_FILE:
                $msg = 'No se ha enviado ning�n fichero.';
                break;
        }
        resError($msg);
    }

    // Tama�o m�ximo (configurable)
    $filesize = filesize($file['tmp_name']);
    if ($filesize > $maxDoc) {
        resError('El documento excede el tama�o m�ximo permitido.', 413);
    }

    // Obtener doc_basename (POST 'doc_basename' -> 'filename' -> derived from file name)
    if (!empty($_POST['doc_basename'])) {
        $docBasename = preg_replace('/[^A-Za-z0-9._-]/', '_', $_POST['doc_basename']);
    } elseif (!empty($_POST['filename'])) {
        $docBasename = apio_safe_basename($_POST['filename']);
    } else {
        $docBasename = apio_safe_basename($file['name']);
    }
    if (!$docBasename) $docBasename = 'doc';

    // Crear SDIR bajo docsDir - Solo si realmente no existe
    $sdir = $docsDir . DIRECTORY_SEPARATOR . $docBasename;
    
    // Si el directorio no existe, intentar crearlo sin permisos específicos
    if (!is_dir($sdir)) {
        // Intentar crear sin especificar permisos (usa umask del servidor)
        if (!@mkdir($sdir, 0755, true)) {
            // Si falla, verificar si existe (por si otro proceso lo creó)
            if (!is_dir($sdir)) {
                resError('No se puede crear directorio del documento: ' . $docBasename, 500);
            }
        }
    }

    // Nombre final: <docBasename>.<ext>
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!$ext) $ext = 'pdf';
    $finalFilename = $docBasename . '.' . $ext;
    $destPath = $sdir . DIRECTORY_SEPARATOR . $finalFilename;

    // Si existe, sobrescribir: eliminar primero (seg�n requerimiento)
    if (is_file($destPath)) {
        @unlink($destPath);
    }

    if (!move_uploaded_file($file['tmp_name'], $destPath)) {
        resError('No se pudo guardar el fichero en el servidor.', 500);
    }

    // Procesado posterior (opcional)
    $processResult = null;
    $processFile = __DIR__ . '/process_pdf.php';
    if (is_file($processFile)) {
        try {
            require_once $processFile;
            if (function_exists('process_uploaded_pdf')) {
                $processResult = process_uploaded_pdf($destPath);
            } else {
                $processResult = ['ok' => false, 'error' => 'Function process_uploaded_pdf not found'];
            }
        } catch (Throwable $e) {
            $processResult = ['ok' => false, 'error' => $e->getMessage()];
        }
    }

    // Devolver ruta relativa pública si es posible (usar public_base + path relative to doc root)
    $publicBase = rtrim($config['public_base'] ?? '', '/');
    $relativePath = str_replace(rtrim($config['project_root'], "/\\"), '', $destPath);
    $relativePath = ltrim(str_replace(DIRECTORY_SEPARATOR, '/', $relativePath), '/');
    if ($publicBase) {
        $publicUrl = $publicBase . '/' . $relativePath;
    } else {
        $publicUrl = 'docs/' . rawurlencode($docBasename) . '/' . rawurlencode($finalFilename);
    }

    resOk(['filepath' => $destPath, 'public_url' => $publicUrl, 'doc_dir' => $sdir, 'process' => $processResult]);
}

// 2) Legacy: manejo de chunks (guardado en tmpDir y ensamblado en docsDir/<basename>/)
if (isset($_POST['uploadId']) || isset($_POST['chunkIndex']) || isset($_POST['totalChunks'])) {
    $uploadId = isset($_POST['uploadId']) ? preg_replace('/[^A-Za-z0-9._-]/', '_', $_POST['uploadId']) : null;
    $chunkIndex = isset($_POST['chunkIndex']) ? intval($_POST['chunkIndex']) : null;
    $totalChunks = isset($_POST['totalChunks']) ? intval($_POST['totalChunks']) : null;

    if (!$uploadId || $chunkIndex === null || $totalChunks === null) {
        resError('Missing chunk metadata', 400);
    }

    if (!isset($_FILES['file']) || !is_uploaded_file($_FILES['file']['tmp_name'])) {
        resError('No se recibi� el chunk (archivo).', 400);
    }

    $targetDir = $tmpDir . DIRECTORY_SEPARATOR . $uploadId;
    if (!is_dir($targetDir)) {
        if (!@mkdir($targetDir, 0755, true) && !is_dir($targetDir)) {
            resError('No se puede crear directorio temporal para chunks.', 500);
        }
    }

    $partName = sprintf('%05d.part', $chunkIndex);
    $partPath = $targetDir . DIRECTORY_SEPARATOR . $partName;
    if (!move_uploaded_file($_FILES['file']['tmp_name'], $partPath)) {
        resError('No se pudo guardar el chunk.', 500);
    }

    // Si recibimos todos los chunks -> ensamblar
    $received = glob($targetDir . DIRECTORY_SEPARATOR . '*.part');
    if (count($received) === $totalChunks) {
        $origName = isset($_POST['filename']) ? apio_safe_basename($_POST['filename']) : ('file_' . $uploadId);
        $sdir = $docsDir . DIRECTORY_SEPARATOR . $origName;
        if (!is_dir($sdir)) {
            if (!@mkdir($sdir, 0755, true) && !is_dir($sdir)) {
                resError('No se puede crear directorio final para ensamblar.', 500);
            }
        }
        $finalPath = $sdir . DIRECTORY_SEPARATOR . $origName . '.pdf';
        if (is_file($finalPath)) {
            @unlink($finalPath);
        }

        $out = fopen($finalPath, 'wb');
        if (!$out) resError('No se puede crear fichero final.', 500);

        for ($i = 0; $i < $totalChunks; $i++) {
            $p = $targetDir . DIRECTORY_SEPARATOR . sprintf('%05d.part', $i);
            if (!is_file($p)) {
                fclose($out);
                resError("Falta chunk {$i} al ensamblar.", 500);
            }
            $in = fopen($p, 'rb');
            if (!$in) {
                fclose($out);
                resError("No se puede leer chunk {$i}.", 500);
            }
            while (!feof($in)) {
                $buf = fread($in, 4096);
                if ($buf === false) break;
                fwrite($out, $buf);
            }
            fclose($in);
        }
        fclose($out);

        // limpiar tmp
        array_map('unlink', glob($targetDir . DIRECTORY_SEPARATOR . '*.part'));
        @rmdir($targetDir);

        // devolver info
        $publicBase = rtrim($config['public_base'] ?? '', '/');
        $relativePath = str_replace(rtrim($config['project_root'], "/\\"), '', $finalPath);
        $relativePath = ltrim(str_replace(DIRECTORY_SEPARATOR, '/', $relativePath), '/');
        if ($publicBase) {
            $publicUrl = $publicBase . '/' . $relativePath;
        } else {
            $publicUrl = 'docs/' . rawurlencode($origName) . '/' . rawurlencode(basename($finalPath));
        }

        resOk(['assembled' => $finalPath, 'public_url' => $publicUrl]);
    }

    resOk(['receivedChunk' => $chunkIndex, 'totalChunks' => $totalChunks]);
}

resError('No se recibi� ning�n fichero v�lido ni metadata de chunk.', 400);
?>
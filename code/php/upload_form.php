<?php
// upload_form.php - styled upload UI (card style) and injects UPLOAD_URL for upload.js
session_start();
if (!isset($_SESSION['user'])) {
  $projectRoot = dirname(__DIR__, 2);
  $cfg = [];
  $cfgPath = $projectRoot . '/config/config.json';
  if (file_exists($cfgPath)) $cfg = json_decode(file_get_contents($cfgPath), true) ?? [];
  $pb = rtrim($cfg['public_base'] ?? '', '');
  header('Location: ' . ($pb ? $pb . '/code/php/login.php' : '/code/php/login.php'));
  exit;
}

$projectRoot = dirname(__DIR__, 2);
$cfgPath = $projectRoot . '/config/config.json';
$cfg = [];
if (file_exists($cfgPath)) $cfg = json_decode(file_get_contents($cfgPath), true) ?? [];
$pb = rtrim($cfg['public_base'] ?? '', '');

// upload endpoint and script url (concrete)
$uploadEndpoint = $pb ? $pb . '/code/php/upload.php' : '/code/php/upload.php';
$uploadJs = $pb ? $pb . '/code/js/upload.js' : '/code/js/upload.js';
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <title>Fase 1A — Subir PDF</title>
  <?php require_once __DIR__ . '/header.php'; ?>
  <style>
    /* Card style to match requested appearance (applies to forms across project) */
    .form-card {
      max-width:820px;
      margin:18px auto;
      background:#fff;
      border-radius:10px;
      box-shadow:0 2px 8px rgba(0,0,0,0.06);
      padding:18px;
      border:1px solid #eee;
      font-family: Arial, Helvetica, sans-serif;
    }
    .form-row { margin:10px 0; display:flex; align-items:center; gap:12px; }
    label.inline { min-width:160px; color:#333; }
    .btn-primary {
      display:inline-block;
      background:#0b6cff;
      color:#fff;
      padding:8px 14px;
      border-radius:6px;
      border:none;
      cursor:pointer;
      font-weight:600;
    }
    .hint { color:#666; font-size:0.95rem; }
    .status-box { margin-top:14px; padding:14px; border-radius:8px; display:none; }
    .status-success { background:#e8f8ec; border:1px dashed #bfe6c7; color:#1b6b2d; display:flex; gap:12px; align-items:center; }
    .status-error { background:#fff0f0; border:1px solid #f0bcbc; color:#7a1b1b; }
    .status-icon { font-size:20px; font-weight:700; }
    .file-name { font-weight:700; margin-left:6px; }
    .note { margin-top:8px; font-size:0.9rem; color:#666; }
  </style>
</head>
<body>
  <div class="form-card" role="main" aria-labelledby="pageTitle">
    <h1 id="pageTitle" style="margin-top:0;">Fase 1A — Subir PDF</h1>

    <div class="form-row">
      <label class="inline" for="fileInput">Selecciona un PDF:</label>
      <input id="fileInput" type="file" accept="application/pdf" />
      <span id="fileName" class="file-name"></span>
    </div>

    <div class="form-row">
      <button id="startUpload" class="btn-primary">Subir PDF</button>
    </div>

    <div id="statusBox" class="status-box" aria-live="polite">
      <!-- populated by JS -->
    </div>

    <!-- Botón Fase 1B (solo aparece después de subida exitosa) -->
    <div id="phase1bSection" style="display: none; margin-top: 20px; padding: 15px; background: #f0f8ff; border: 2px solid #007cba; border-radius: 8px;">
      <h3 style="margin-top: 0; color: #007cba;">&#129302; Procesar con IA (Fase 1B)</h3>
      <p>El PDF se ha subido correctamente. Ahora puedes procesarlo con Inteligencia Artificial para extraer el texto.</p>
      <button id="generateTxtBtn" class="btn" style="background: #28a745; color: white; padding: 12px 24px; border: none; border-radius: 6px; font-weight: 700; cursor: pointer;">
        &#10024; Generar .TXT (F1B)
      </button>
    </div>

    <p class="note">Nota: el fichero se envía en una única petición al servidor.</p>
  </div>

  <script>
    // Inject concrete upload endpoint for the JS
    const UPLOAD_URL = "<?php echo htmlspecialchars($uploadEndpoint, ENT_QUOTES); ?>";
  </script>
  <script src="<?php echo htmlspecialchars($uploadJs, ENT_QUOTES); ?>" defer></script>
</body>
</html>
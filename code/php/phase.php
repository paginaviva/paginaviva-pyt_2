<?php
// phase.php - view for a given phase; builds concrete actions/URLs using public_base
session_start();
if (!isset($_SESSION['user'])) {
    $cfg = json_decode(file_get_contents(__DIR__ . '/../../config/config.json'), true);
    $pb = rtrim($cfg['public_base'] ?? '', '/');
    header('Location: ' . $pb . '/ed_cfle/code/php/login.php');
    exit;
}
$fase = isset($_GET['fase']) ? intval($_GET['fase']) : 1;
require_once __DIR__ . '/../../config/prompts.php';
$config = json_decode(file_get_contents(__DIR__ . '/../../config/config.json'), true);
$pb = rtrim($config['public_base'] ?? '', '/');
$templates = $PROMPTS[$fase] ?? [];
?>
<!doctype html>
<html lang="es">
<head><meta charset="utf-8"><title>Fase <?=htmlspecialchars($fase)?></title></head>
<body>
  <?php require_once __DIR__ . '/header.php'; ?>
  <div class="container">
  <?php foreach ($templates as $tpl): ?>
    <section class="template">
      <h2><?=htmlspecialchars($tpl['title'])?></h2>
      <pre class="prompt"><?=htmlspecialchars($tpl['prompt'])?></pre>
      <form method="post" action="<?php echo htmlspecialchars($pb . '/ed_cfle/code/php/process_phase.php'); ?>">
        <input type="hidden" name="fase" value="<?=htmlspecialchars($fase)?>">
        <input type="hidden" name="template_id" value="<?=htmlspecialchars($tpl['id'])?>">
        <label>Modelo:
          <select name="model">
            <option value="<?=$config['default_model']?>"><?=$config['default_model']?></option>
          </select>
        </label><br>
        <label>Temperature: <input name="temperature" value="<?=$config['default_params']['temperature']?>"></label><br>
        <label>Max tokens: <input name="max_tokens" value="<?=$config['default_params']['max_tokens']?>"></label><br>
        <label>Document base name: <input name="doc_basename" placeholder="Nombre base del PDF (ej. LYON-REMOTE_FICHA-1)"></label><br>
        <button>Ejecutar fase</button>
      </form>
    </section>
  <?php endforeach; ?>

  <?php if ($fase === 1): ?>
    <section>
      <h2>Fase 1A — Procesar PDF (Extraer TXT y imágenes)</h2>
      <form method="post" action="<?php echo htmlspecialchars($pb . '/ed_cfle/code/php/process_pdf.php'); ?>">
        <label>Nombre base del PDF: <input name="doc_basename" required></label><br>
        <label>Modelo: <input name="model" value="<?=$config['default_model']?>"></label><br>
        <label>Temperature: <input name="temperature" value="<?=$config['default_params']['temperature']?>"></label><br>
        <label>Max tokens: <input name="max_tokens" value="<?=$config['default_params']['max_tokens']?>"></label><br>
        <button type="submit">Procesar con OpenAI</button>
      </form>
      <p>La operación es síncrona: espera hasta que finalice para ver el resultado (puede tardar).</p>
    </section>
  <?php endif; ?>
  </div>
</body>
</html>
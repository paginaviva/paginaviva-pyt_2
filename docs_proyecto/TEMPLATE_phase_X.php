<?php
/**
 * _TEMPLATE_phase_X.php
 * PLANTILLA FRONTEND para crear nuevas fases
 * 
 * INSTRUCCIONES:
 * 1. Copiar este archivo como phase_X.php (ej: phase_2b.php)
 * 2. Buscar y reemplazar los siguientes marcadores:
 *    - {PHASE_NUMBER} → Número de la fase (ej: 2B)
 *    - {PHASE_TITLE} → Título de la fase (ej: Generar Resumen Ejecutivo)
 *    - {PHASE_DESCRIPTION} → Descripción breve de la fase
 *    - {PHASE_EMOJI} → Emoji representativo (ej: 📝, 🔍, 🚀)
 *    - {PROXY_FILE} → Nombre del archivo proxy (ej: phase_2b_proxy.php)
 *    - {PREVIOUS_PHASE_FILE} → Archivo que debe existir del paso anterior
 *    - {PREVIOUS_PHASE_NAME} → Nombre de la fase anterior (ej: Fase 1C)
 * 3. Adaptar la lógica de validación y procesamiento
 * 4. Personalizar los paneles de resultados según el tipo de salida
 * 
 * ARQUITECTURA COMÚN:
 * - Usa phase_common.css para estilos compartidos
 * - Usa phase_common.js para funciones compartidas
 * - Estructura HTML estándar con paneles colapsables
 * - Debug HTTP y Timeline siempre visibles
 */

session_start();
if (empty($_SESSION['user'])) {
    header('Location: login.php');
    exit;
}

require_once __DIR__ . '/lib_apio.php';

require_once __DIR__ . '/lib_apio.php';

$cfg = apio_load_config();
$docBasename = isset($_GET['doc']) ? trim($_GET['doc']) : '';

if (!$docBasename) {
    die('Documento no especificado. Use: phase_{PHASE_NUMBER}.php?doc=NOMBRE_DOCUMENTO');
}

// Verificar que existe el archivo requerido de la fase anterior
$requiredFile = $cfg['docs_dir'] . DIRECTORY_SEPARATOR . $docBasename . DIRECTORY_SEPARATOR . $docBasename . '.{PREVIOUS_PHASE_FILE}';
if (!file_exists($requiredFile)) {
    die('Error: Debe completar la {PREVIOUS_PHASE_NAME} primero.');
}

// Leer información de la fase anterior si es necesario
$previousData = file_get_contents($requiredFile);

// Verificar si ya existe el resultado de esta fase
$outputFile = $cfg['docs_dir'] . DIRECTORY_SEPARATOR . $docBasename . DIRECTORY_SEPARATOR . $docBasename . '.{OUTPUT_EXTENSION}';
$existingOutput = null;
if (file_exists($outputFile)) {
    $existingOutput = file_get_contents($outputFile);
}

// URL del proxy
$proxyUrl = apio_public_from_cfg_path('/code/php/{PROXY_FILE}');

// Configuración de parámetros OpenAI
$apioModels = $cfg['apio_models'] ?? ['gpt-4o', 'gpt-4o-mini', 'gpt-4'];
$apioDefaults = $cfg['apio_defaults'] ?? [];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{PHASE_EMOJI} Fase {PHASE_NUMBER} — {PHASE_TITLE}</title>
    <link rel="stylesheet" href="<?php echo apio_public_from_cfg_path('/css/styles.css'); ?>">
    <link rel="stylesheet" href="<?php echo apio_public_from_cfg_path('/code/css/phase_common.css'); ?>">
</head>
<body>
    <?php require_once __DIR__ . '/header.php'; ?>
    
    <div class="container">
        <h2>{PHASE_EMOJI} Fase {PHASE_NUMBER} &mdash; {PHASE_TITLE}</h2>
        <p>{PHASE_DESCRIPTION}</p>
        
        <div class="file-info">
            <h3>📄 Información del Documento:</h3>
            <div class="info-display">
                <strong>Documento:</strong> <?php echo htmlspecialchars($docBasename); ?><br>
                <strong>Estado:</strong> <?php echo $existingOutput ? '✅ Ya procesado (se puede reprocesar)' : '⏳ Pendiente'; ?>
            </div>
        </div>
        
        <?php if ($existingOutput): ?>
        <div class="existing-json-warning">
            ⚠️ <strong>Nota:</strong> Este documento ya fue procesado. Si lo procesas de nuevo, se sobrescribirá.
        </div>
        <?php endif; ?>
        
        <div class="params-panel">
            <h3>⚙️ Parámetros OpenAI API</h3>
            <form id="phaseForm">
                <input type="hidden" name="doc_basename" value="<?php echo htmlspecialchars($docBasename); ?>">
                <div class="param-row">
                    <div class="param-group">
                        <label for="model">Modelo:</label>
                        <select id="model" name="model">
                            <?php foreach ($apioModels as $model): ?>
                                <option value="<?php echo htmlspecialchars($model); ?>" 
                                    <?php echo ($model === ($apioDefaults['model'] ?? 'gpt-4o')) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($model); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
            </form>
        </div>
        
        <button type="button" id="processBtn" class="process-btn">
            🚀 Ejecutar {PHASE_TITLE}
        </button>
        
        <div id="statusIndicator" class="status-indicator"></div>
        
        <div id="timelinePanel" class="timeline-panel">
            <div class="timeline-header" onclick="togglePanel('timeline')">⏱️ Timeline de Ejecución</div>
            <div class="timeline-content" id="timelineContent"></div>
        </div>
        
        <div id="debugPanel" class="debug-panel">
            <div class="debug-header" onclick="togglePanel('debug')">🔍 Debug HTTP</div>
            <div class="debug-content"><div id="debugContent" class="debug-json"></div></div>
        </div>
        
        <div id="errorRawPanel" class="error-raw-panel">
            <div class="error-raw-header">⚠️ Respuesta Cruda del Servidor</div>
            <div class="error-raw-content">
                <p><strong>El servidor devolvió HTML en lugar de JSON.</strong></p>
                <div id="errorRawContent" class="error-raw-html"></div>
            </div>
        </div>
        
        <div id="resultsPanel" class="results-panel">
            <div class="results-header">✅ Análisis Completado</div>
            <div class="results-content">
                <h3>📄 Resultado:</h3>
                <div class="results-actions">
                    <button class="action-btn" onclick="copyResult()">📋 Copiar</button>
                    <button class="action-btn" onclick="downloadResult()">💾 Descargar</button>
                </div>
                <div id="resultsText" class="results-text"></div>
                
                <div class="next-phase-section">
                    <h3 style="margin-top: 0; color: #28a745;">🚀 Continuar con el Flujo</h3>
                    <button id="viewFilesBtn" class="btn" style="background: #6c757d; color: white; padding: 12px 24px; border: none; border-radius: 6px; font-weight: 700; cursor: pointer;">
                        📁 Ver Archivos Generados
                    </button>
                </div>
            </div>
        </div>
    </div>
    
    <script src="<?php echo apio_public_from_cfg_path('/code/js/phase_common.js'); ?>"></script>
    <script>
        const PROXY_URL = '<?php echo $proxyUrl; ?>';
        const CURRENT_DOC = '<?php echo htmlspecialchars($docBasename); ?>';
        const processBtn = document.getElementById('processBtn');
        let processedResult = null;
        
        document.addEventListener('DOMContentLoaded', () => {
            processBtn.addEventListener('click', handleProcess);
            document.getElementById('viewFilesBtn').onclick = () => viewGeneratedFiles(CURRENT_DOC);
        });
        
        async function handleProcess() {
            if (!CURRENT_DOC) return alert('No hay documento seleccionado');
            
            const modelSelect = document.getElementById('model');
            processBtn.disabled = true;
            showStatus('🔄 Procesando...', 'processing');
            
            document.getElementById('timelinePanel').style.display = 'none';
            document.getElementById('resultsPanel').style.display = 'none';
            document.getElementById('errorRawPanel').style.display = 'none';
            
            try {
                const payload = { doc_basename: CURRENT_DOC };
                if (modelSelect) payload.model = modelSelect.value;
                
                const response = await fetch(PROXY_URL, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(payload)
                });
                
                const responseText = await response.text();
                let result;
                
                try {
                    result = JSON.parse(responseText);
                } catch (e) {
                    return handleError('Respuesta inválida', responseText, null, null);
                }
                
                if (response.ok && result.output) {
                    handleSuccess(result);
                } else {
                    handleError(result.debug?.error || 'Error', null, result.timeline, result.debug?.http);
                }
            } catch (error) {
                handleError('Error de conexión: ' + error.message, null, null, null);
            } finally {
                processBtn.disabled = false;
            }
        }
        
        function handleSuccess(result) {
            showStatus('✅ Completado', 'success');
            if (result.timeline) showTimeline(result.timeline);
            if (result.debug?.http) showDebugHttp(result.debug.http);
            if (result.output) showResults(result.output);
        }
        
        function showResults(output) {
            processedResult = output.text || output;
            document.getElementById('resultsText').textContent = processedResult;
            document.getElementById('resultsPanel').style.display = 'block';
        }
        
        function copyResult() {
            if (processedResult) copyToClipboard(processedResult, 'Copiado');
        }
        
        function downloadResult() {
            if (processedResult) downloadFile(processedResult, `${CURRENT_DOC}_result.txt`);
        }
    </script>
</body>
</html>
<?php
/**
 * phase_2d.php
 * FRONTEND para Fase 2D: Generar ficha técnica y resumen técnico
 * 
 * Propósito: Añadir campos generativos (ficha_tecnica, resumen_tecnico) al JSON de F2C
 */

session_start();
if (empty($_SESSION['user'])) {
    header('Location: login.php');
    exit;
}

require_once __DIR__ . '/lib_apio.php';

$cfg = apio_load_config();
$docBasename = isset($_GET['doc']) ? trim($_GET['doc']) : '';

if (!$docBasename) {
    die('Documento no especificado. Use: phase_2d.php?doc=NOMBRE_DOCUMENTO');
}

// Verificar que existe el JSON de F2C
$jsonFile = $cfg['docs_dir'] . DIRECTORY_SEPARATOR . $docBasename . DIRECTORY_SEPARATOR . $docBasename . '.json';
if (!file_exists($jsonFile)) {
    die('Error: Debe completar la Fase 2C primero.');
}

// Leer JSON de F2C
$jsonF2C = json_decode(file_get_contents($jsonFile), true);
if (!is_array($jsonF2C)) {
    die('Error: El JSON de F2C no es válido.');
}

// Verificar si el JSON ya tiene ficha técnica (F2D completada)
$hasSheet = array_key_exists('ficha_tecnica', $jsonF2C) || 
            array_key_exists('resumen_tecnico', $jsonF2C);

// URL del proxy
$proxyUrl = apio_public_from_cfg_path('/code/php/phase_2d_proxy.php');

// Configuración de parámetros OpenAI
$apioModels = $cfg['apio_models'] ?? ['gpt-4o', 'gpt-4o-mini', 'gpt-4'];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>📄 Fase 2D — Ficha Técnica</title>
    <link rel="stylesheet" href="<?php echo apio_public_from_cfg_path('/css/styles.css'); ?>">
    <link rel="stylesheet" href="<?php echo apio_public_from_cfg_path('/code/css/phase_common.css'); ?>">
</head>
<body>
    <?php require_once __DIR__ . '/header.php'; ?>
    
    <div class="container">
        <h2>📄 Fase 2D &mdash; Ficha Técnica y Resumen</h2>
        <p>
            Esta fase genera <strong>contenido técnico redactado</strong> a partir del documento original:
            una ficha técnica completa y un resumen conciso (máx. 300 caracteres).
        </p>
        
        <div class="file-info">
            <h3>📄 Información del Documento:</h3>
            <div class="info-display">
                <strong>Documento:</strong> <?php echo htmlspecialchars($docBasename); ?><br>
                <strong>Estado JSON:</strong> <?php echo $hasSheet ? '📄 Ficha técnica ya generada (se puede reprocesar)' : '📊 Sin ficha técnica'; ?><br>
                <strong>Producto:</strong> <?php echo htmlspecialchars($jsonF2C['nombre_producto'] ?? '(desconocido)'); ?><br>
                <strong>Familia:</strong> <?php echo htmlspecialchars($jsonF2C['familia'] ?? '(desconocida)'); ?><br>
                <strong>Categoría:</strong> <?php echo htmlspecialchars($jsonF2C['categoria'] ?? '(desconocida)'); ?>
            </div>
        </div>
        
        <?php if ($hasSheet): ?>
        <div class="existing-json-warning">
            ⚠️ <strong>Nota:</strong> Este JSON ya tiene ficha técnica. Si lo procesas de nuevo, se sobrescribirá.
        </div>
        <?php endif; ?>
        
        <div class="params-panel">
            <h3>📊 JSON Actual (F2C - 22 campos)</h3>
            <div class="json-display" style="max-height: 300px; overflow-y: auto;">
                <pre><?php echo htmlspecialchars(json_encode($jsonF2C, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)); ?></pre>
            </div>
        </div>
        
        <div class="params-panel">
            <h3>⚙️ Parámetros OpenAI API</h3>
            <form id="phaseForm">
                <input type="hidden" name="doc_basename" value="<?php echo htmlspecialchars($docBasename); ?>">
                <div class="param-row">
                    <div class="param-group">
                        <label for="model">Modelo:</label>
                        <select id="model" name="model">
                            <?php 
                            $defaultModel = 'gpt-4o'; // Modelo por defecto fijo
                            foreach ($apioModels as $model): 
                            ?>
                                <option value="<?php echo htmlspecialchars($model); ?>" 
                                    <?php echo $model === $defaultModel ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($model); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <button type="button" id="processBtn" onclick="processPhase()">🚀 Generar Ficha Técnica</button>
            </form>
        </div>
        
        <div id="statusPanel" class="status-panel"></div>
        
        <!-- Timeline siempre visible -->
        <div class="timeline-panel">
            <h3>⏱️ Timeline de Ejecución</h3>
            <div id="timeline" class="timeline-display">
                <em>Esperando ejecución...</em>
            </div>
        </div>
        
        <!-- Debug HTTP siempre visible -->
        <div class="debug-panel">
            <h3>🔍 Debug HTTP</h3>
            <div id="debugHttp" class="debug-display">
                <em>Esperando ejecución...</em>
            </div>
        </div>
        
        <div id="resultsPanel" class="results-panel" style="display: none;">
            <h3>✅ Resultados Fase 2D</h3>
            
            <div id="technicalFields"></div>
            
            <h4>📦 JSON Completo (24 campos)</h4>
            <div id="resultsJson" class="json-display"></div>
            
            <div class="result-actions">
                <button onclick="copyResult()">📋 Copiar JSON</button>
                <button onclick="downloadResult()">💾 Descargar JSON</button>
                <button onclick="viewFiles()">📁 Ver Archivos</button>
                <button onclick="location.href='phase.php?doc=<?php echo urlencode($docBasename); ?>'">🏠 Volver al Dashboard</button>
                <button disabled style="opacity: 0.5; cursor: not-allowed;">➡️ Continuar (próximamente)</button>
            </div>
        </div>
    </div>
    
    <script src="<?php echo apio_public_from_cfg_path('/code/js/phase_common.js'); ?>"></script>
    <script>
        const CURRENT_DOC = <?php echo json_encode($docBasename); ?>;
        const PROXY_URL = <?php echo json_encode($proxyUrl); ?>;
        let processedResult = null;
        
        async function processPhase() {
            const form = document.getElementById('phaseForm');
            const formData = new FormData(form);
            const processBtn = document.getElementById('processBtn');
            
            const payload = {
                doc_basename: formData.get('doc_basename'),
                model: formData.get('model')
            };
            
            processBtn.disabled = true;
            showStatus('⏳ Generando ficha técnica y resumen...', 'info');
            document.getElementById('resultsPanel').style.display = 'none';
            
            try {
                const response = await fetch(PROXY_URL, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(payload)
                });
                
                const result = await response.json();
                
                if (!response.ok || result.debug?.error) {
                    const errorMsg = result.debug?.error || `HTTP ${response.status}`;
                    handleError(errorMsg, result.timeline, result.debug?.http, response.status);
                    return;
                }
                
                handleSuccess(result);
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
            if (result.output?.json_data) showResults(result.output.json_data);
        }
        
        function handleError(errorMsg, timeline, debugHttp, httpStatus) {
            showStatus('❌ Error: ' + errorMsg, 'error');
            if (timeline) showTimeline(timeline);
            if (debugHttp) showDebugHttp(debugHttp);
            console.error('Error en F2D:', errorMsg, httpStatus);
        }
        
        function showResults(jsonData) {
            processedResult = JSON.stringify(jsonData, null, 2);
            
            // Mostrar JSON completo
            document.getElementById('resultsJson').innerHTML = '<pre>' + escapeHtml(processedResult) + '</pre>';
            
            // Mostrar ficha técnica y resumen
            const fichaTecnica = jsonData.ficha_tecnica || '';
            const resumenTecnico = jsonData.resumen_tecnico || '';
            
            const technicalFieldsHtml = `
                <div class="field-group">
                    <h4>📋 Ficha Técnica</h4>
                    <div style="background-color: #f8f9fa; padding: 15px; border-radius: 5px; border-left: 4px solid #007bff; white-space: pre-wrap; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; line-height: 1.6;">
                        ${fichaTecnica ? escapeHtml(fichaTecnica) : '<em style="color: #dc3545;">⚠️ No se generó ficha técnica</em>'}
                    </div>
                </div>
                <hr>
                <div class="field-group">
                    <h4>📝 Resumen Técnico (máx. 300 caracteres)</h4>
                    <div style="background-color: #e9ecef; padding: 12px; border-radius: 5px; border-left: 4px solid #28a745; font-style: italic; font-size: 14px;">
                        ${resumenTecnico ? escapeHtml(resumenTecnico) : '<em style="color: #dc3545;">⚠️ No se generó resumen técnico</em>'}
                    </div>
                    <p style="margin-top: 8px; font-size: 12px; color: #6c757d;">
                        <strong>Longitud:</strong> ${resumenTecnico.length} caracteres ${resumenTecnico.length > 300 ? '<span style="color: #dc3545;">⚠️ Excede el límite de 300</span>' : '✓'}
                    </p>
                </div>
            `;
            
            document.getElementById('technicalFields').innerHTML = technicalFieldsHtml;
            document.getElementById('resultsPanel').style.display = 'block';
        }
        
        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }
        
        function copyResult() {
            if (processedResult) copyToClipboard(processedResult, 'JSON copiado al portapapeles');
        }
        
        function downloadResult() {
            if (processedResult) downloadFile(processedResult, `${CURRENT_DOC}_technical_sheet.json`);
        }
        
        function viewFiles() {
            window.open(`docs_list.php?doc=${encodeURIComponent(CURRENT_DOC)}`, '_blank');
        }
    </script>
</body>
</html>

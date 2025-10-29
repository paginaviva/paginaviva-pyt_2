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
                                    <?php echo ($model === $defaultModel) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($model); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
            </form>
            <div class="timeout-notice">
                ⏱️ <strong>Nota:</strong> Esta operación puede tomar hasta <strong>60 segundos</strong>.
            </div>
        </div>
        
        <button type="button" id="processBtn" class="process-btn">
            🚀 Generar Ficha Técnica
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
            <div class="results-header">✅ Ficha Técnica Generada</div>
            <div class="results-content">
                <h3>📄 JSON Final (24 campos):</h3>
                <div class="results-actions">
                    <button class="action-btn" onclick="copyResult()">📋 Copiar JSON</button>
                    <button class="action-btn" onclick="downloadResult()">💾 Descargar JSON</button>
                </div>
                <div id="resultsJson" class="json-display"></div>
                
                <h3 style="margin-top: 30px;">� Contenido Técnico Generado:</h3>
                <div id="technicalFields" class="expanded-fields-display"></div>
                
                <div class="next-phase-section">
                    <h3 style="margin-top: 0; color: #28a745;">🚀 Continuar con el Flujo</h3>
                    <button id="continuePhaseBtn" class="btn" style="background: #28a745; color: white; padding: 12px 24px; border: none; border-radius: 6px; font-weight: 700; cursor: pointer;">
                        🔍 Continuar a Fase 2E (Auditoría Final)
                    </button>
                    <button id="viewFilesBtn" class="btn" style="background: #17a2b8; color: white; padding: 12px 24px; border: none; border-radius: 6px; font-weight: 700; cursor: pointer; margin-left: 10px;">
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
            document.getElementById('continuePhaseBtn').onclick = () => {
                window.location.href = `phase_2e.php?doc=${CURRENT_DOC}`;
            };
        });
        
        async function handleProcess() {
            if (!CURRENT_DOC) return alert('No hay documento seleccionado');
            
            const modelSelect = document.getElementById('model');
            processBtn.disabled = true;
            showStatus('🔄 Generando ficha técnica y resumen...', 'processing');
            
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
            if (result.output?.json_data) showResults(result.output.json_data);
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
    </script>
</body>
</html>

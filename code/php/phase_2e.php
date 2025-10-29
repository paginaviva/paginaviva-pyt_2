<?php
/**
 * phase_2e.php
 * FRONTEND para Fase 2E: Auditoría y verificación final del JSON
 * 
 * Propósito: Validar, corregir y refinar el JSON-F2D completo contra el documento original
 * NO añade nuevos campos, solo verifica y optimiza los existentes (24 campos)
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
    die('Documento no especificado. Use: phase_2e.php?doc=NOMBRE_DOCUMENTO');
}

// Verificar que existe el JSON de F2D (24 campos)
$jsonFile = $cfg['docs_dir'] . DIRECTORY_SEPARATOR . $docBasename . DIRECTORY_SEPARATOR . $docBasename . '.json';
if (!file_exists($jsonFile)) {
    die('Error: Debe completar la Fase 2D primero.');
}

// Leer JSON de F2D
$jsonF2D = json_decode(file_get_contents($jsonFile), true);
if (!is_array($jsonF2D)) {
    die('Error: El JSON de F2D no es válido.');
}

// Verificar que tiene al menos 24 campos
if (count($jsonF2D) < 24) {
    die('Error: El JSON debe tener al menos 24 campos. Complete F2D primero.');
}

// Verificar si ya se ejecutó F2E (existe log)
$logFile = $cfg['docs_dir'] . DIRECTORY_SEPARATOR . $docBasename . DIRECTORY_SEPARATOR . $docBasename . '_2E.log';
$hasAudit = file_exists($logFile);

// URL del proxy
$proxyUrl = apio_public_from_cfg_path('/code/php/phase_2e_proxy.php');

// Configuración de parámetros OpenAI
$apioModels = $cfg['apio_models'] ?? ['gpt-4o', 'gpt-4o-mini', 'gpt-4'];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>🔍 Fase 2E — Auditoría Final</title>
    <link rel="stylesheet" href="<?php echo apio_public_from_cfg_path('/css/styles.css'); ?>">
    <link rel="stylesheet" href="<?php echo apio_public_from_cfg_path('/code/css/phase_common.css'); ?>">
    <style>
        .comparison-container {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin: 20px 0;
        }
        .comparison-panel {
            background: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 8px;
            padding: 15px;
        }
        .comparison-panel h4 {
            margin-top: 0;
            color: #495057;
            border-bottom: 2px solid #007bff;
            padding-bottom: 8px;
        }
        .comparison-panel pre {
            max-height: 400px;
            overflow-y: auto;
            background: white;
            padding: 10px;
            border-radius: 4px;
            font-size: 12px;
        }
        .audit-notice {
            background: #d1ecf1;
            border: 1px solid #bee5eb;
            border-radius: 6px;
            padding: 15px;
            margin: 20px 0;
        }
        .audit-notice strong {
            color: #0c5460;
        }
    </style>
</head>
<body>
    <?php require_once __DIR__ . '/header.php'; ?>
    
    <div class="container">
        <h2>🔍 Fase 2E &mdash; Auditoría y Verificación Final</h2>
        <p>
            Esta fase <strong>NO añade nuevos campos</strong>. Valida, corrige y refina el JSON-F2D completo 
            contrastándolo palabra por palabra con el documento original para garantizar <strong>fidelidad técnica absoluta</strong>.
        </p>
        
        <div class="file-info">
            <h3>📄 Información del Documento:</h3>
            <div class="info-display">
                <strong>Documento:</strong> <?php echo htmlspecialchars($docBasename); ?><br>
                <strong>Estado:</strong> <?php echo $hasAudit ? '✅ Auditoría ya ejecutada (se puede re-ejecutar)' : '⏳ Pendiente de auditoría'; ?><br>
                <strong>Producto:</strong> <?php echo htmlspecialchars($jsonF2D['nombre_producto'] ?? '(desconocido)'); ?><br>
                <strong>Familia:</strong> <?php echo htmlspecialchars($jsonF2D['familia'] ?? '(desconocida)'); ?><br>
                <strong>Categoría:</strong> <?php echo htmlspecialchars($jsonF2D['categoria'] ?? '(desconocida)'); ?><br>
                <strong>Campos actuales:</strong> <?php echo count($jsonF2D); ?>
            </div>
        </div>
        
        <?php if ($hasAudit): ?>
        <div class="audit-notice">
            ⚠️ <strong>Nota:</strong> Este JSON ya fue auditado. Si lo procesas de nuevo, se sobrescribirá con una nueva verificación.
        </div>
        <?php endif; ?>
        
        <div class="audit-notice">
            <strong>🎯 Objetivo de F2E:</strong>
            <ul style="margin: 10px 0 0 20px;">
                <li>✅ Verificar exactitud factual contra documento original</li>
                <li>✅ Corregir discrepancias en valores numéricos y técnicos</li>
                <li>✅ Optimizar redacción técnica (RAE español de España)</li>
                <li>✅ Completar datos omitidos (si existen en documento)</li>
                <li>❌ <strong>NO modifica</strong> el campo <code>resumen_tecnico</code></li>
                <li>❌ <strong>NO añade ni elimina</strong> campos del JSON</li>
            </ul>
        </div>
        
        <div class="params-panel">
            <h3>📊 JSON Actual a Auditar (F2D - 24 campos)</h3>
            <div class="json-display" style="max-height: 400px; overflow-y: auto;">
                <pre id="jsonF2DDisplay"><?php echo htmlspecialchars(json_encode($jsonF2D, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)); ?></pre>
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
                            $defaultModel = 'gpt-4o'; // Modelo por defecto para F2E
                            foreach ($apioModels as $model): 
                            ?>
                                <option value="<?php echo htmlspecialchars($model); ?>" 
                                    <?php echo ($model === $defaultModel) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($model); ?>
                                    <?php if ($model === 'gpt-4o-mini'): ?>
                                        (⚠️ No soporta code_interpreter)
                                    <?php endif; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <small style="display: block; margin-top: 5px; color: #6c757d;">
                            ℹ️ F2E usa <code>code_interpreter</code>. Requiere <strong>gpt-4o</strong>.
                        </small>
                    </div>
                </div>
            </form>
            <div class="timeout-notice">
                ⏱️ <strong>Nota:</strong> La auditoría puede tomar hasta <strong>60 segundos</strong>.
            </div>
        </div>
        
        <button type="button" id="processBtn" class="process-btn">
            🔍 Ejecutar Auditoría y Verificación
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
            <div class="results-header">✅ Auditoría Completada</div>
            <div class="results-content">
                <h3>📊 Comparación JSON-F2D vs JSON-FINAL:</h3>
                <div class="comparison-container">
                    <div class="comparison-panel">
                        <h4>📋 JSON Antes (F2D)</h4>
                        <pre id="jsonBeforeComparison"></pre>
                    </div>
                    <div class="comparison-panel">
                        <h4>✅ JSON Después (FINAL)</h4>
                        <pre id="jsonAfterComparison"></pre>
                    </div>
                </div>
                
                <div class="results-actions">
                    <button class="action-btn" onclick="copyResult()">📋 Copiar JSON-FINAL</button>
                    <button class="action-btn" onclick="downloadResult()">💾 Descargar JSON-FINAL</button>
                    <button class="action-btn" onclick="showDiff()">🔍 Ver Diferencias</button>
                </div>
                
                <div id="diffPanel" style="display: none; margin-top: 20px;">
                    <h3>🔄 Campos Modificados:</h3>
                    <div id="diffContent" class="json-display"></div>
                </div>
                
                <div class="next-phase-section">
                    <h3 style="margin-top: 30px; color: #28a745;">🚀 Continuar con el Flujo</h3>
                    <p>El JSON ha sido completamente verificado y está listo para las siguientes fases.</p>
                    <button id="continueBtn" class="btn" style="background: #28a745; color: white; padding: 12px 24px; border: none; border-radius: 6px; font-weight: 700; cursor: pointer; margin-right: 10px;">
                        ➡️ Continuar a Fase 3A
                    </button>
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
        let jsonBefore = null;
        let jsonAfter = null;
        
        document.addEventListener('DOMContentLoaded', () => {
            processBtn.addEventListener('click', handleProcess);
            document.getElementById('continueBtn').onclick = () => {
                window.location.href = `phase_3a.php?doc=${CURRENT_DOC}`;
            };
            document.getElementById('viewFilesBtn').onclick = () => viewGeneratedFiles(CURRENT_DOC);
        });
        
        async function handleProcess() {
            if (!CURRENT_DOC) return alert('No hay documento seleccionado');
            
            const modelSelect = document.getElementById('model');
            const selectedModel = modelSelect.value;
            
            // Advertir si se selecciona gpt-4o-mini
            if (selectedModel === 'gpt-4o-mini') {
                if (!confirm('⚠️ gpt-4o-mini NO soporta code_interpreter.\n\nF2E requiere gpt-4o para funcionar correctamente.\n\n¿Continuar de todos modos?')) {
                    return;
                }
            }
            
            processBtn.disabled = true;
            showStatus('🔄 Ejecutando auditoría...', 'processing');
            
            document.getElementById('timelinePanel').style.display = 'none';
            document.getElementById('resultsPanel').style.display = 'none';
            document.getElementById('errorRawPanel').style.display = 'none';
            
            // Guardar JSON antes
            jsonBefore = JSON.parse(document.getElementById('jsonF2DDisplay').textContent);
            
            try {
                const payload = { 
                    doc_basename: CURRENT_DOC,
                    model: selectedModel
                };
                
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
                    return handleError('Respuesta inválida del servidor', responseText, null, null);
                }
                
                if (response.ok && result.output) {
                    handleSuccess(result);
                } else {
                    handleError(result.debug?.error || 'Error desconocido', null, result.timeline, result.debug?.http);
                }
            } catch (error) {
                handleError('Error de conexión: ' + error.message, null, null, null);
            } finally {
                processBtn.disabled = false;
            }
        }
        
        function handleSuccess(result) {
            showStatus('✅ Auditoría Completada', 'success');
            
            if (result.timeline) showTimeline(result.timeline);
            if (result.debug?.http) showDebugHttp(result.debug.http);
            
            if (result.output?.json_data) {
                jsonAfter = result.output.json_data;
                processedResult = JSON.stringify(jsonAfter, null, 2);
                
                // Mostrar comparación
                document.getElementById('jsonBeforeComparison').textContent = JSON.stringify(jsonBefore, null, 2);
                document.getElementById('jsonAfterComparison').textContent = processedResult;
                
                document.getElementById('resultsPanel').style.display = 'block';
            }
        }
        
        function showDiff() {
            if (!jsonBefore || !jsonAfter) {
                alert('No hay datos para comparar');
                return;
            }
            
            const diffPanel = document.getElementById('diffPanel');
            const diffContent = document.getElementById('diffContent');
            
            const changes = [];
            
            // Comparar cada campo
            for (const key in jsonAfter) {
                if (JSON.stringify(jsonBefore[key]) !== JSON.stringify(jsonAfter[key])) {
                    changes.push({
                        field: key,
                        before: jsonBefore[key],
                        after: jsonAfter[key]
                    });
                }
            }
            
            if (changes.length === 0) {
                diffContent.innerHTML = '<p style="color: #28a745;">✅ No se detectaron cambios. El JSON ya estaba optimizado.</p>';
            } else {
                let html = '<table style="width: 100%; border-collapse: collapse;">';
                html += '<thead><tr style="background: #f8f9fa;"><th style="padding: 10px; border: 1px solid #dee2e6;">Campo</th><th style="padding: 10px; border: 1px solid #dee2e6;">Antes</th><th style="padding: 10px; border: 1px solid #dee2e6;">Después</th></tr></thead>';
                html += '<tbody>';
                
                changes.forEach(change => {
                    html += '<tr>';
                    html += `<td style="padding: 10px; border: 1px solid #dee2e6; font-weight: bold;">${change.field}</td>`;
                    html += `<td style="padding: 10px; border: 1px solid #dee2e6;"><pre style="margin: 0; white-space: pre-wrap;">${JSON.stringify(change.before, null, 2)}</pre></td>`;
                    html += `<td style="padding: 10px; border: 1px solid #dee2e6; background: #d4edda;"><pre style="margin: 0; white-space: pre-wrap;">${JSON.stringify(change.after, null, 2)}</pre></td>`;
                    html += '</tr>';
                });
                
                html += '</tbody></table>';
                html += `<p style="margin-top: 10px; color: #007bff;"><strong>Total de campos modificados:</strong> ${changes.length}</p>`;
                
                diffContent.innerHTML = html;
            }
            
            diffPanel.style.display = 'block';
        }
        
        function copyResult() {
            if (processedResult) copyToClipboard(processedResult, 'JSON-FINAL copiado al portapapeles');
        }
        
        function downloadResult() {
            if (processedResult) downloadFile(processedResult, `${CURRENT_DOC}_FINAL.json`);
        }
    </script>
</body>
</html>

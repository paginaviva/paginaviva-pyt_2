<?php
/**
 * phase_2c.php
 * FRONTEND para Fase 2C: Añadir campos taxonómicos
 * 
 * Propósito: Cruzar JSON de F2B con Taxonomia Cofem.csv para agregar
 * clasificación oficial (grupos_de_soluciones, familia, categoria)
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
    die('Documento no especificado. Use: phase_2c.php?doc=NOMBRE_DOCUMENTO');
}

// Verificar que existe el JSON de F2B
$jsonFile = $cfg['docs_dir'] . DIRECTORY_SEPARATOR . $docBasename . DIRECTORY_SEPARATOR . $docBasename . '.json';
if (!file_exists($jsonFile)) {
    die('Error: Debe completar la Fase 2B primero.');
}

// Leer JSON de F2B
$jsonF2B = json_decode(file_get_contents($jsonFile), true);
if (!is_array($jsonF2B)) {
    die('Error: El JSON de F2B no es válido.');
}

// Verificar si el JSON ya tiene campos taxonómicos (F2C completada)
$isTaxonomized = array_key_exists('grupos_de_soluciones', $jsonF2B) || 
                  array_key_exists('familia', $jsonF2B) ||
                  array_key_exists('categoria', $jsonF2B);

// URL del proxy
$proxyUrl = apio_public_from_cfg_path('/code/php/phase_2c_proxy.php');

// Configuración de parámetros OpenAI
$apioModels = $cfg['apio_models'] ?? ['gpt-4o', 'gpt-4o-mini', 'gpt-4'];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>🏷️ Fase 2C — Clasificación Taxonómica</title>
    <link rel="stylesheet" href="<?php echo apio_public_from_cfg_path('/css/styles.css'); ?>">
    <link rel="stylesheet" href="<?php echo apio_public_from_cfg_path('/code/css/phase_common.css'); ?>">
</head>
<body>
    <?php require_once __DIR__ . '/header.php'; ?>
    
    <div class="container">
        <h2>🏷️ Fase 2C &mdash; Clasificación Taxonómica</h2>
        <p>
            Esta fase cruza el JSON de <strong>Fase 2B</strong> con el archivo maestro <strong>Taxonomia Cofem.csv</strong> 
            para añadir campos de clasificación oficial: grupos de soluciones, familia y categoría.
        </p>
        
        <div class="file-info">
            <h3>📄 Información del Documento:</h3>
            <div class="info-display">
                <strong>Documento:</strong> <?php echo htmlspecialchars($docBasename); ?><br>
                <strong>Estado JSON:</strong> <?php echo $isTaxonomized ? '🏷️ Ya clasificado (se puede reprocesar)' : '📊 Sin clasificación taxonómica'; ?><br>
                <strong>Producto:</strong> <?php echo htmlspecialchars($jsonF2B['nombre_producto'] ?? '(desconocido)'); ?><br>
                <strong>Código Cofem:</strong> <?php echo htmlspecialchars($jsonF2B['codigo_referencia_cofem'] ?? '(desconocido)'); ?>
            </div>
        </div>
        
        <?php if ($isTaxonomized): ?>
        <div class="existing-json-warning">
            ⚠️ <strong>Nota:</strong> Este JSON ya tiene clasificación taxonómica. Si lo procesas de nuevo, se sobrescribirá.
        </div>
        <?php endif; ?>
        
        <div class="params-panel">
            <h3>📊 JSON Actual (F2B - 14 campos)</h3>
            <div class="json-display" style="max-height: 300px; overflow-y: auto;">
                <pre><?php echo htmlspecialchars(json_encode($jsonF2B, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)); ?></pre>
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
            🚀 Ejecutar Clasificación Taxonómica
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
            <div class="results-header">✅ Clasificación Completada</div>
            <div class="results-content">
                <h3>📄 JSON Final (18 campos):</h3>
                <div class="results-actions">
                    <button class="action-btn" onclick="copyResult()">📋 Copiar JSON</button>
                    <button class="action-btn" onclick="downloadResult()">💾 Descargar JSON</button>
                </div>
                <div id="resultsJson" class="json-display"></div>
                
                <h3 style="margin-top: 30px;">🏷️ Campos Taxonómicos:</h3>
                <div id="taxonomicFields" class="expanded-fields-display"></div>
                
                <div class="next-phase-section">
                    <h3 style="margin-top: 0; color: #28a745;">🚀 Continuar con el Flujo</h3>
                    <button id="continuePhaseBtn" class="btn" disabled style="background: #6c757d; color: white; padding: 12px 24px; border: none; border-radius: 6px; font-weight: 700; cursor: not-allowed;">
                        ➡️ Continuar a Próxima Fase (Próximamente)
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
        });
        
        async function handleProcess() {
            if (!CURRENT_DOC) return alert('No hay documento seleccionado');
            
            const modelSelect = document.getElementById('model');
            processBtn.disabled = true;
            showStatus('🔄 Clasificando producto...', 'processing');
            
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
            
            // Mostrar campos taxonómicos destacados
            const incidencias = jsonData.incidencias_taxonomia || [];
            const hasIncidencias = Array.isArray(incidencias) && incidencias.length > 0;
            
            const taxonomicFieldsHtml = `
                <div class="field-group">
                    <h4>🎯 Grupos de Soluciones:</h4>
                    <p style="font-size: 16px; font-weight: bold; color: ${jsonData.grupos_de_soluciones ? '#28a745' : '#dc3545'};">
                        ${escapeHtml(jsonData.grupos_de_soluciones || '(no encontrado)')}
                    </p>
                </div>
                <div class="field-group">
                    <h4>👨‍👩‍👧‍👦 Familia:</h4>
                    <p style="font-size: 16px; font-weight: bold; color: ${jsonData.familia ? '#28a745' : '#dc3545'};">
                        ${escapeHtml(jsonData.familia || '(no encontrado)')}
                    </p>
                </div>
                <div class="field-group">
                    <h4>📂 Categoría:</h4>
                    <p style="font-size: 16px; font-weight: bold; color: ${jsonData.categoria ? '#28a745' : '#dc3545'};">
                        ${escapeHtml(jsonData.categoria || '(no encontrado)')}
                    </p>
                </div>
                <div class="field-group">
                    <h4>⚠️ Incidencias de Taxonomía:</h4>
                    ${hasIncidencias 
                        ? '<ul style="color: #dc3545;">' + incidencias.map(inc => '<li>' + escapeHtml(inc) + '</li>').join('') + '</ul>'
                        : '<p style="color: #28a745; font-weight: bold;">✓ Sin incidencias (match encontrado)</p>'
                    }
                </div>
            `;
            
            document.getElementById('taxonomicFields').innerHTML = taxonomicFieldsHtml;
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
            if (processedResult) downloadFile(processedResult, `${CURRENT_DOC}_taxonomized.json`);
        }
    </script>
</body>
</html>

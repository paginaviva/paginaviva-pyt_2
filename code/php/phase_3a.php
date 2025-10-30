<?php
/**
 * phase_3a.php
 * FRONTEND para Fase 3A: Análisis terminológico SEO
 * 
 * Propósito: Extraer terminología técnica clasificada (kw, kw_lt, terminos_semanticos)
 * del documento original para generar diccionario SEO (JSON-SEO)
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
    die('Documento no especificado. Use: phase_3a.php?doc=NOMBRE_DOCUMENTO');
}

// Verificar que existe FILE_ID (F1C completada)
$fileidFile = $cfg['docs_dir'] . DIRECTORY_SEPARATOR . $docBasename . DIRECTORY_SEPARATOR . $docBasename . '.fileid';
if (!file_exists($fileidFile)) {
    die('Error: Debe completar la Fase 1C primero.');
}

$fileId = trim(file_get_contents($fileidFile));

// Verificar si ya se ejecutó F3A (existe _SEO.json)
$jsonSeoFile = $cfg['docs_dir'] . DIRECTORY_SEPARATOR . $docBasename . DIRECTORY_SEPARATOR . $docBasename . '_SEO.json';
$hasSEO = file_exists($jsonSeoFile);

// Si existe, cargar JSON-SEO previo
$jsonSEO = null;
if ($hasSEO) {
    $jsonSEO = json_decode(file_get_contents($jsonSeoFile), true);
}

// URL del proxy
$proxyUrl = apio_public_from_cfg_path('/code/php/phase_3a_proxy.php');

// Configuración de modelos
$apioModels = $cfg['apio_models'] ?? ['gpt-4o', 'gpt-4o-mini', 'gpt-4'];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>📚 Fase 3A — Análisis Terminológico SEO</title>
    <link rel="stylesheet" href="<?php echo apio_public_from_cfg_path('/css/styles.css'); ?>">
    <link rel="stylesheet" href="<?php echo apio_public_from_cfg_path('/code/css/phase_common.css'); ?>">
    <style>
        .terminology-container {
            display: grid;
            grid-template-columns: 1fr;
            gap: 20px;
            margin: 20px 0;
        }
        .terminology-panel {
            background: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 8px;
            padding: 15px;
        }
        .terminology-panel h4 {
            margin-top: 0;
            color: #495057;
            border-bottom: 2px solid #007bff;
            padding-bottom: 8px;
        }
        .term-list {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            margin-top: 10px;
        }
        .term-badge {
            background: white;
            border: 1px solid #ced4da;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 13px;
            color: #495057;
        }
        .term-badge.kw {
            background: #e7f3ff;
            border-color: #007bff;
            color: #004085;
            font-weight: 600;
        }
        .term-badge.kw-lt {
            background: #fff3cd;
            border-color: #ffc107;
            color: #856404;
        }
        .term-badge.semantic {
            background: #d4edda;
            border-color: #28a745;
            color: #155724;
        }
        .phase-info {
            background: #d1ecf1;
            border: 1px solid #bee5eb;
            border-radius: 6px;
            padding: 15px;
            margin: 20px 0;
        }
        .phase-info strong {
            color: #0c5460;
        }
    </style>
</head>
<body>
    <?php require_once __DIR__ . '/header.php'; ?>
    
    <div class="container">
        <h2>📚 Fase 3A &mdash; Análisis Terminológico SEO</h2>
        <p>
            Esta fase marca el <strong>inicio del análisis semántico-lingüístico</strong>. 
            Extrae terminología técnica real del documento original, clasificándola jerárquicamente 
            para generar un <strong>diccionario SEO (JSON-SEO)</strong> que se usará en las siguientes fases.
        </p>
        
        <div class="file-info">
            <h3>📄 Información del Documento:</h3>
            <div class="info-display">
                <strong>Documento:</strong> <?php echo htmlspecialchars($docBasename); ?><br>
                <strong>FILE_ID:</strong> <code><?php echo htmlspecialchars($fileId); ?></code><br>
                <strong>Estado:</strong> <?php echo $hasSEO ? '✅ Análisis SEO ya ejecutado (se puede re-ejecutar)' : '⏳ Pendiente de análisis'; ?>
            </div>
        </div>
        
        <?php if ($hasSEO && $jsonSEO): ?>
        <div class="phase-info">
            ⚠️ <strong>Nota:</strong> Este documento ya tiene análisis terminológico. Si lo procesas de nuevo, se sobrescribirá.
        </div>
        
        <div class="params-panel">
            <h3>📊 JSON-SEO Actual</h3>
            <div class="terminology-container">
                <div class="terminology-panel">
                    <h4>🎯 Keywords Principales (kw)</h4>
                    <div class="term-list">
                        <?php if (!empty($jsonSEO['kw'])): ?>
                            <?php foreach ($jsonSEO['kw'] as $term): ?>
                                <span class="term-badge kw"><?php echo htmlspecialchars($term); ?></span>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <em style="color: #6c757d;">Sin términos principales</em>
                        <?php endif; ?>
                    </div>
                </div>
                
                <div class="terminology-panel">
                    <h4>📝 Long-Tail Keywords (kw_lt)</h4>
                    <div class="term-list">
                        <?php if (!empty($jsonSEO['kw_lt'])): ?>
                            <?php foreach ($jsonSEO['kw_lt'] as $term): ?>
                                <span class="term-badge kw-lt"><?php echo htmlspecialchars($term); ?></span>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <em style="color: #6c757d;">Sin long-tail keywords</em>
                        <?php endif; ?>
                    </div>
                </div>
                
                <div class="terminology-panel">
                    <h4>🔧 Términos Semánticos (terminos_semanticos)</h4>
                    <div class="term-list">
                        <?php if (!empty($jsonSEO['terminos_semanticos'])): ?>
                            <?php foreach ($jsonSEO['terminos_semanticos'] as $term): ?>
                                <span class="term-badge semantic"><?php echo htmlspecialchars($term); ?></span>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <em style="color: #6c757d;">Sin términos semánticos</em>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>
        
        <div class="phase-info">
            <strong>🎯 Objetivo de F3A:</strong>
            <ul style="margin: 10px 0 0 20px;">
                <li>✅ Analiza <strong>SOLO el documento original</strong> (FILE_ID)</li>
                <li>✅ Extrae terminología <strong>real del texto</strong> (no inventa)</li>
                <li>✅ Clasifica en 3 niveles: <code>kw</code>, <code>kw_lt</code>, <code>terminos_semanticos</code></li>
                <li>✅ Genera <strong>JSON-SEO</strong> (diccionario terminológico)</li>
                <li>❌ <strong>NO genera</strong> texto narrativo ni contenido descriptivo</li>
                <li>❌ <strong>NO usa</strong> JSON previos ni bases de datos externas</li>
            </ul>
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
                            $defaultModel = 'gpt-4o'; // Modelo por defecto para F3A
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
                            ℹ️ F3A usa <code>code_interpreter</code>. Requiere <strong>gpt-4o</strong>.
                        </small>
                    </div>
                </div>
            </form>
            <div class="timeout-notice">
                ⏱️ <strong>Nota:</strong> El análisis puede tomar hasta <strong>60 segundos</strong>.
            </div>
        </div>
        
        <button type="button" id="processBtn" class="process-btn">
            📚 Ejecutar Análisis Terminológico SEO
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
            <div class="results-header">✅ Análisis Terminológico Completado</div>
            <div class="results-content">
                <h3>📊 JSON-SEO Generado:</h3>
                <div class="terminology-container" id="terminologyResults"></div>
                
                <h3>📄 JSON Completo:</h3>
                <div class="json-display">
                    <pre id="jsonSeoDisplay"></pre>
                </div>
                
                <div class="results-actions">
                    <button class="action-btn" onclick="copyResult()">📋 Copiar JSON-SEO</button>
                    <button class="action-btn" onclick="downloadResult()">💾 Descargar JSON-SEO</button>
                </div>
                
                <div class="next-phase-section">
                    <h3 style="margin-top: 30px; color: #28a745;">🚀 Continuar con el Flujo</h3>
                    <p>El diccionario terminológico SEO está listo para las siguientes fases.</p>
                    <button id="continueBtn" class="btn" style="background: #28a745; color: white; padding: 12px 24px; border: none; border-radius: 6px; font-weight: 700; cursor: pointer; margin-right: 10px;">
                        ➡️ Continuar a Fase 3B
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
        
        document.addEventListener('DOMContentLoaded', () => {
            processBtn.addEventListener('click', handleProcess);
            document.getElementById('continueBtn').onclick = () => {
                window.location.href = `phase_3b.php?doc=${CURRENT_DOC}`;
            };
            document.getElementById('viewFilesBtn').onclick = () => viewGeneratedFiles(CURRENT_DOC);
        });
        
        async function handleProcess() {
            if (!CURRENT_DOC) return alert('No hay documento seleccionado');
            
            const modelSelect = document.getElementById('model');
            const selectedModel = modelSelect.value;
            
            // Advertir si se selecciona gpt-4o-mini
            if (selectedModel === 'gpt-4o-mini') {
                if (!confirm('⚠️ gpt-4o-mini NO soporta code_interpreter.\n\nF3A requiere gpt-4o para funcionar correctamente.\n\n¿Continuar de todos modos?')) {
                    return;
                }
            }
            
            processBtn.disabled = true;
            showStatus('🔄 Ejecutando análisis terminológico...', 'processing');
            
            document.getElementById('timelinePanel').style.display = 'none';
            document.getElementById('resultsPanel').style.display = 'none';
            document.getElementById('errorRawPanel').style.display = 'none';
            
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
            showStatus('✅ Análisis Terminológico Completado', 'success');
            
            if (result.timeline) showTimeline(result.timeline);
            if (result.debug?.http) showDebugHttp(result.debug.http);
            
            if (result.output?.json_data) {
                const jsonSEO = result.output.json_data;
                processedResult = JSON.stringify(jsonSEO, null, 2);
                
                // Mostrar JSON completo
                document.getElementById('jsonSeoDisplay').textContent = processedResult;
                
                // Mostrar terminología clasificada
                displayTerminology(jsonSEO);
                
                document.getElementById('resultsPanel').style.display = 'block';
            }
        }
        
        function displayTerminology(jsonSEO) {
            const container = document.getElementById('terminologyResults');
            
            let html = '';
            
            // Keywords principales
            html += '<div class="terminology-panel">';
            html += '<h4>🎯 Keywords Principales (kw)</h4>';
            html += '<div class="term-list">';
            if (jsonSEO.kw && jsonSEO.kw.length > 0) {
                jsonSEO.kw.forEach(term => {
                    html += `<span class="term-badge kw">${escapeHtml(term)}</span>`;
                });
            } else {
                html += '<em style="color: #6c757d;">Sin términos principales</em>';
            }
            html += '</div></div>';
            
            // Long-tail keywords
            html += '<div class="terminology-panel">';
            html += '<h4>📝 Long-Tail Keywords (kw_lt)</h4>';
            html += '<div class="term-list">';
            if (jsonSEO.kw_lt && jsonSEO.kw_lt.length > 0) {
                jsonSEO.kw_lt.forEach(term => {
                    html += `<span class="term-badge kw-lt">${escapeHtml(term)}</span>`;
                });
            } else {
                html += '<em style="color: #6c757d;">Sin long-tail keywords</em>';
            }
            html += '</div></div>';
            
            // Términos semánticos
            html += '<div class="terminology-panel">';
            html += '<h4>🔧 Términos Semánticos (terminos_semanticos)</h4>';
            html += '<div class="term-list">';
            if (jsonSEO.terminos_semanticos && jsonSEO.terminos_semanticos.length > 0) {
                jsonSEO.terminos_semanticos.forEach(term => {
                    html += `<span class="term-badge semantic">${escapeHtml(term)}</span>`;
                });
            } else {
                html += '<em style="color: #6c757d;">Sin términos semánticos</em>';
            }
            html += '</div></div>';
            
            container.innerHTML = html;
        }
        
        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }
        
        function copyResult() {
            if (processedResult) copyToClipboard(processedResult, 'JSON-SEO copiado al portapapeles');
        }
        
        function downloadResult() {
            if (processedResult) downloadFile(processedResult, `${CURRENT_DOC}_SEO.json`);
        }
    </script>
</body>
</html>

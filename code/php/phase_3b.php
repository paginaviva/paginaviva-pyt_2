<?php
/**
 * phase_3b.php
 * FRONTEND para Fase 3B: Optimización y redacción final SEO
 * 
 * Propósito: Optimizar campos textuales del JSON-FINAL y crear descripcion_larga_producto
 * usando la TRÍADA (FILE_ID + JSON-FINAL + JSON-SEO) con técnicas SEO y E-E-A-T
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
    die('Documento no especificado. Use: phase_3b.php?doc=NOMBRE_DOCUMENTO');
}

// Verificar que existe FILE_ID (F1C)
$fileidFile = $cfg['docs_dir'] . DIRECTORY_SEPARATOR . $docBasename . DIRECTORY_SEPARATOR . $docBasename . '.fileid';
if (!file_exists($fileidFile)) {
    die('Error: Debe completar la Fase 1C primero.');
}

// Verificar que existe JSON-FINAL (F2E)
$jsonFinalFile = $cfg['docs_dir'] . DIRECTORY_SEPARATOR . $docBasename . DIRECTORY_SEPARATOR . $docBasename . '.json';
if (!file_exists($jsonFinalFile)) {
    die('Error: Debe completar la Fase 2E primero.');
}

// Verificar que existe JSON-SEO (F3A)
$jsonSeoFile = $cfg['docs_dir'] . DIRECTORY_SEPARATOR . $docBasename . DIRECTORY_SEPARATOR . $docBasename . '_SEO.json';
if (!file_exists($jsonSeoFile)) {
    die('Error: Debe completar la Fase 3A primero.');
}

$fileId = trim(file_get_contents($fileidFile));
$jsonFinal = json_decode(file_get_contents($jsonFinalFile), true);
$jsonSEO = json_decode(file_get_contents($jsonSeoFile), true);

// Verificar si ya se ejecutó F3B (existe descripcion_larga_producto)
$hasF3B = isset($jsonFinal['descripcion_larga_producto']) && !empty($jsonFinal['descripcion_larga_producto']);

// URL del proxy
$proxyUrl = apio_public_from_cfg_path('/code/php/phase_3b_proxy.php');

// Configuración de modelos
$apioModels = $cfg['apio_models'] ?? ['gpt-4o', 'gpt-4o-mini', 'gpt-4'];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>✍️ Fase 3B — Optimización y Redacción Final SEO</title>
    <link rel="stylesheet" href="<?php echo apio_public_from_cfg_path('/css/styles.css'); ?>">
    <link rel="stylesheet" href="<?php echo apio_public_from_cfg_path('/code/css/phase_common.css'); ?>">
    <style>
        .triad-container {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 15px;
            margin: 20px 0;
        }
        .triad-panel {
            background: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 8px;
            padding: 15px;
        }
        .triad-panel h4 {
            margin-top: 0;
            color: #495057;
            font-size: 14px;
            border-bottom: 2px solid #007bff;
            padding-bottom: 8px;
        }
        .triad-panel .info {
            font-size: 12px;
            color: #6c757d;
        }
        .description-preview {
            background: white;
            border: 1px solid #ced4da;
            border-radius: 6px;
            padding: 15px;
            margin: 15px 0;
            max-height: 400px;
            overflow-y: auto;
            white-space: pre-wrap;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            line-height: 1.6;
        }
        .phase-info {
            background: #fff3cd;
            border: 1px solid #ffc107;
            border-radius: 6px;
            padding: 15px;
            margin: 20px 0;
        }
        .phase-info strong {
            color: #856404;
        }
        .field-comparison {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
            margin: 15px 0;
        }
        .field-comparison .before, .field-comparison .after {
            background: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 6px;
            padding: 12px;
        }
        .field-comparison .before h5, .field-comparison .after h5 {
            margin-top: 0;
            font-size: 13px;
            color: #495057;
        }
        .field-comparison .before {
            border-left: 4px solid #dc3545;
        }
        .field-comparison .after {
            border-left: 4px solid #28a745;
        }
    </style>
</head>
<body>
    <?php require_once __DIR__ . '/header.php'; ?>
    
    <div class="container">
        <h2>✍️ Fase 3B &mdash; Optimización y Redacción Final SEO</h2>
        <p>
            Esta es la <strong>fase final de tratamiento de contenido</strong> Cofem. 
            Optimiza los textos del JSON-FINAL y genera el campo <code>descripcion_larga_producto</code> 
            (300-500 palabras) aplicando <strong>técnicas SEO profesionales</strong> y principios <strong>E-E-A-T</strong>.
        </p>
        
        <div class="file-info">
            <h3>📄 Información del Documento:</h3>
            <div class="info-display">
                <strong>Documento:</strong> <?php echo htmlspecialchars($docBasename); ?><br>
                <strong>Producto:</strong> <?php echo htmlspecialchars($jsonFinal['nombre_producto'] ?? '(desconocido)'); ?><br>
                <strong>Estado:</strong> <?php echo $hasF3B ? '✅ Optimización SEO ya ejecutada (se puede re-ejecutar)' : '⏳ Pendiente de optimización'; ?>
            </div>
        </div>
        
        <?php if ($hasF3B): ?>
        <div class="phase-info">
            ⚠️ <strong>Nota:</strong> Este JSON ya fue optimizado. Si lo procesas de nuevo, se sobrescribirá con una nueva optimización.
        </div>
        
        <div class="params-panel">
            <h3>📝 Descripción Larga Actual (F3B)</h3>
            <div class="description-preview">
                <?php echo htmlspecialchars($jsonFinal['descripcion_larga_producto']); ?>
            </div>
            <p style="color: #6c757d; font-size: 13px;">
                <strong>Longitud:</strong> <?php echo str_word_count($jsonFinal['descripcion_larga_producto']); ?> palabras | 
                <?php echo mb_strlen($jsonFinal['descripcion_larga_producto']); ?> caracteres
            </p>
        </div>
        <?php endif; ?>
        
        <div class="params-panel">
            <h3>🔺 LA TRÍADA (Fuentes de Información)</h3>
            <div class="triad-container">
                <div class="triad-panel">
                    <h4>1️⃣ FILE_ID</h4>
                    <div class="info">
                        <strong>ID:</strong> <code style="font-size: 11px;"><?php echo htmlspecialchars($fileId); ?></code><br>
                        <strong>Función:</strong> Documento técnico original (fuente primaria de verificación factual)
                    </div>
                </div>
                <div class="triad-panel">
                    <h4>2️⃣ JSON-FINAL</h4>
                    <div class="info">
                        <strong>Campos:</strong> <?php echo count($jsonFinal); ?><br>
                        <strong>Función:</strong> Datos técnicos verificados (F1-F2E)
                    </div>
                </div>
                <div class="triad-panel">
                    <h4>3️⃣ JSON-SEO</h4>
                    <div class="info">
                        <strong>KW:</strong> <?php echo count($jsonSEO['kw'] ?? []); ?> | 
                        <strong>KW_LT:</strong> <?php echo count($jsonSEO['kw_lt'] ?? []); ?> | 
                        <strong>Semánticos:</strong> <?php echo count($jsonSEO['terminos_semanticos'] ?? []); ?><br>
                        <strong>Función:</strong> Diccionario terminológico para integración SEO
                    </div>
                </div>
            </div>
        </div>
        
        <div class="phase-info">
            <strong>🎯 Objetivo de F3B:</strong>
            <ul style="margin: 10px 0 0 20px;">
                <li>✅ Optimizar campos: <code>ficha_tecnica</code>, <code>resumen_tecnico</code>, <code>razon_uso_formacion</code></li>
                <li>✅ Crear campo: <code>descripcion_larga_producto</code> (300-500 palabras, hasta 7 secciones)</li>
                <li>✅ Integrar terminología de JSON-SEO de forma natural</li>
                <li>✅ Aplicar principios <strong>E-E-A-T</strong> (Experiencia, Especialización, Autoridad, Fiabilidad)</li>
                <li>✅ Mantener tono <strong>técnico-institucional Cofem</strong> (educativo, neutral)</li>
                <li>❌ <strong>NO inventar</strong> información no verificable en la TRÍADA</li>
                <li>❌ <strong>NO usar</strong> tono comercial o promocional</li>
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
                            $defaultModel = 'gpt-4o'; // Modelo por defecto para F3B
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
                            ℹ️ F3B usa <code>code_interpreter</code>. Requiere <strong>gpt-4o</strong> para mejor calidad SEO.
                        </small>
                    </div>
                </div>
            </form>
            <div class="timeout-notice">
                ⏱️ <strong>Nota:</strong> La optimización puede tomar hasta <strong>2 minutos</strong> (generación de texto largo).
            </div>
        </div>
        
        <button type="button" id="processBtn" class="process-btn">
            ✍️ Ejecutar Optimización SEO Final
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
            <div class="results-header">✅ Optimización SEO Completada</div>
            <div class="results-content">
                <h3>📝 Descripción Larga del Producto (Nuevo Campo)</h3>
                <div class="description-preview" id="descriptionDisplay"></div>
                <p id="descriptionStats" style="color: #6c757d; font-size: 13px;"></p>
                
                <h3>📄 JSON-FINAL Optimizado Completo:</h3>
                <div class="json-display">
                    <pre id="jsonFinalDisplay"></pre>
                </div>
                
                <div class="results-actions">
                    <button class="action-btn" onclick="copyResult()">📋 Copiar JSON-FINAL</button>
                    <button class="action-btn" onclick="downloadResult()">💾 Descargar JSON-FINAL</button>
                </div>
                
                <div class="next-phase-section">
                    <h3 style="margin-top: 30px; color: #28a745;">🎉 Proceso Completado</h3>
                    <p>El JSON-FINAL ha sido optimizado y está listo para publicación en sistemas Cofem.</p>
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
            const selectedModel = modelSelect.value;
            
            // Advertir si se selecciona gpt-4o-mini
            if (selectedModel === 'gpt-4o-mini') {
                if (!confirm('⚠️ gpt-4o-mini NO soporta code_interpreter.\n\nF3B requiere gpt-4o para mejor calidad SEO.\n\n¿Continuar de todos modos?')) {
                    return;
                }
            }
            
            processBtn.disabled = true;
            showStatus('🔄 Ejecutando optimización SEO final...', 'processing');
            
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
            showStatus('✅ Optimización SEO Completada', 'success');
            
            if (result.timeline) showTimeline(result.timeline);
            if (result.debug?.http) showDebugHttp(result.debug.http);
            
            if (result.output?.json_data) {
                const jsonFinal = result.output.json_data;
                processedResult = JSON.stringify(jsonFinal, null, 2);
                
                // Mostrar descripción larga
                const descripcion = jsonFinal.descripcion_larga_producto || '';
                document.getElementById('descriptionDisplay').textContent = descripcion;
                
                const wordCount = descripcion.split(/\s+/).filter(w => w.length > 0).length;
                const charCount = descripcion.length;
                document.getElementById('descriptionStats').textContent = 
                    `Longitud: ${wordCount} palabras | ${charCount} caracteres`;
                
                // Mostrar JSON completo
                document.getElementById('jsonFinalDisplay').textContent = processedResult;
                
                document.getElementById('resultsPanel').style.display = 'block';
            }
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

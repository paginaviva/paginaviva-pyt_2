<?php
/**
 * phase_2a.php
 * Interfaz para Fase 2A: Extraer metadatos técnicos con Assistants API
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
    die('Documento no especificado. Use: phase_2a.php?doc=NOMBRE_DOCUMENTO');
}

// Verificar que existe el archivo .fileid de F1C
$fileidFile = $cfg['docs_dir'] . DIRECTORY_SEPARATOR . $docBasename . DIRECTORY_SEPARATOR . $docBasename . '.fileid';
if (!file_exists($fileidFile)) {
    die('Error: Debe completar la Fase 1C primero. No se encontró el archivo .fileid');
}

$fileId = trim(file_get_contents($fileidFile));

// Verificar si ya existe el JSON (fase completada previamente)
$jsonFile = $cfg['docs_dir'] . DIRECTORY_SEPARATOR . $docBasename . DIRECTORY_SEPARATOR . $docBasename . '.json';
$existingJson = null;
if (file_exists($jsonFile)) {
    $existingJson = json_decode(file_get_contents($jsonFile), true);
}

$proxyUrl = apio_public_from_cfg_path('/code/php/phase_2a_proxy.php');
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>🔍 Fase 2A — Extracción de Metadatos Técnicos</title>
    <link rel="stylesheet" href="<?php echo apio_public_from_cfg_path('/css/styles.css'); ?>">
    <link rel="stylesheet" href="<?php echo apio_public_from_cfg_path('/code/css/phase_common.css'); ?>">
    <style>
        /* Estilos específicos de F2A (si son necesarios) */
    </style>
</head>
<body>
    <?php require_once __DIR__ . '/header.php'; ?>
    
    <div class="container">
        <h2>🔍 Fase 2A &mdash; Extracción de Metadatos Técnicos</h2>
        <p>Analiza el documento con Assistants API para extraer información técnica estructurada.</p>
        
        <!-- Info del archivo -->
        <div class="file-info">
            <h3>📄 Información del Documento:</h3>
            <div class="info-display">
                <strong>Documento:</strong> <?php echo htmlspecialchars($docBasename); ?><br>
                <strong>File ID (OpenAI):</strong> <code><?php echo htmlspecialchars($fileId); ?></code><br>
                <strong>Estado:</strong> <?php echo $existingJson ? '✅ Ya procesado (se puede reprocesar)' : '⏳ Pendiente de análisis'; ?>
            </div>
        </div>
        
        <?php if ($existingJson): ?>
        <div class="existing-json-warning">
            ⚠️ <strong>Nota:</strong> Este documento ya fue procesado en F2A. Si lo procesas de nuevo, se sobrescribirá el JSON existente.
        </div>
        <?php endif; ?>
        
        <!-- Parámetros OpenAI API -->
        <div class="params-panel">
            <h3>⚙️ Parámetros OpenAI API</h3>
            
            <form id="phase2aForm">
                <input type="hidden" name="doc_basename" value="<?php echo htmlspecialchars($docBasename); ?>">
                
                <div class="param-row">
                    <div class="param-group">
                        <label for="model">Modelo:</label>
                        <select id="model" name="model">
                            <?php 
                            $apioModels = $cfg['apio_models'] ?? ['gpt-4o', 'gpt-4o-mini', 'gpt-4'];
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
                
                <p style="color: #6c757d; font-size: 14px; margin-top: 10px;">
                    ℹ️ El modelo seleccionado se usará para crear el Assistant. Los Assistants API no soportan parámetros como temperatura o max_tokens.
                </p>
            </form>
        </div>
        
        <!-- Botón de procesamiento -->
        <button type="button" id="processBtn" class="process-btn success">
            🚀 Ejecutar Análisis Técnico
        </button>
        
        <!-- Indicador de estado -->
        <div id="statusIndicator" class="status-indicator"></div>
        
        <!-- Panel de Timeline -->
        <div id="timelinePanel" class="timeline-panel">
            <div class="timeline-header" onclick="togglePanel('timeline')">
                ⏱️ Timeline de Ejecución
            </div>
            <div class="timeline-content" id="timelineContent">
                <!-- Se llena dinámicamente -->
            </div>
        </div>
        
        <!-- Panel de Debug HTTP -->
        <div id="debugPanel" class="debug-panel">
            <div class="debug-header" onclick="togglePanel('debug')">
                🔍 Debug HTTP (Click para expandir)
            </div>
            <div class="debug-content">
                <div id="debugContent" class="debug-json">
                    <!-- Se llena dinámicamente -->
                </div>
            </div>
        </div>
        
        <!-- Panel de Error Crudo (HTML encapsulado) -->
        <div id="errorRawPanel" class="error-raw-panel">
            <div class="error-raw-header">
                ⚠️ Respuesta Cruda del Servidor (Error PHP/HTML)
            </div>
            <div class="error-raw-content">
                <p><strong>El servidor devolvió HTML en lugar de JSON.</strong> Esto generalmente indica un error PHP. Contenido:</p>
                <div id="errorRawContent" class="error-raw-html">
                    <!-- Se llena dinámicamente -->
                </div>
            </div>
        </div>
        
        <!-- Panel de Resultados -->
        <div id="resultsPanel" class="results-panel">
            <div class="results-header">
                ✅ Análisis Completado
            </div>
            <div class="results-content">
                <h3>📊 Metadatos Extraídos:</h3>
                <div id="jsonFieldsDisplay">
                    <!-- Se llena dinámicamente -->
                </div>
                
                <h3>📄 JSON Completo:</h3>
                <button class="action-btn" onclick="copyJSON()">📋 Copiar JSON</button>
                <button class="action-btn" onclick="downloadJSON()">💾 Descargar JSON</button>
                <div id="jsonRawDisplay" class="json-display">
                    <!-- Se llena dinámicamente -->
                </div>
                
                <!-- Sección Siguiente Fase -->
                <div class="next-phase-section">
                    <h3 style="margin-top: 0; color: #28a745;">🚀 Continuar con el Flujo</h3>
                    <p>Los metadatos técnicos han sido extraídos y guardados. Puedes continuar con la siguiente fase.</p>
                    <button id="continuePhase2BBtn" class="btn" style="background: #28a745; color: white; padding: 12px 24px; border: none; border-radius: 6px; font-weight: 700; cursor: pointer; margin-right: 10px;">
                        📤 Continuar a Fase 2B
                    </button>
                    <button id="viewFilesBtn" class="btn" style="background: #6c757d; color: white; padding: 12px 24px; border: none; border-radius: 6px; font-weight: 700; cursor: pointer;">
                        📁 Ver Archivos Generados
                    </button>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Importar funciones comunes -->
    <script src="<?php echo apio_public_from_cfg_path('/code/js/phase_common.js'); ?>"></script>
    
    <script>
        // Configuración
        const PROXY_URL = '<?php echo $proxyUrl; ?>';
        const CURRENT_DOC = '<?php echo htmlspecialchars($docBasename); ?>';
        
        // Referencias DOM
        const processBtn = document.getElementById('processBtn');
        const statusIndicator = document.getElementById('statusIndicator');
        const timelinePanel = document.getElementById('timelinePanel');
        const timelineContent = document.getElementById('timelineContent');
        const resultsPanel = document.getElementById('resultsPanel');
        const jsonFieldsDisplay = document.getElementById('jsonFieldsDisplay');
        const jsonRawDisplay = document.getElementById('jsonRawDisplay');
        
        // Variables globales
        let extractedJSON = null;
        
        // Inicialización
        document.addEventListener('DOMContentLoaded', initializePhase2A);
        
        function initializePhase2A() {
            processBtn.addEventListener('click', handleProcess);
            
            const viewFilesBtn = document.getElementById('viewFilesBtn');
            if (viewFilesBtn) {
                viewFilesBtn.onclick = () => viewGeneratedFiles(CURRENT_DOC);
            }
            
            const continuePhase2BBtn = document.getElementById('continuePhase2BBtn');
            if (continuePhase2BBtn) {
                continuePhase2BBtn.onclick = () => {
                    window.location.href = 'phase_2b.php?doc=' + encodeURIComponent(CURRENT_DOC);
                };
            }
        }
        
        async function handleProcess() {
            if (!CURRENT_DOC) {
                alert('No hay documento seleccionado');
                return;
            }
            
            // Leer modelo del formulario
            const modelSelect = document.getElementById('model');
            const selectedModel = modelSelect ? modelSelect.value : null;
            
            processBtn.disabled = true;
            showStatus('🔄 Analizando documento con Assistants API... Esto puede tardar hasta 60 segundos.', 'processing');
            
            // Ocultar paneles anteriores
            timelinePanel.style.display = 'none';
            resultsPanel.style.display = 'none';
            
            try {
                const payload = {
                    doc_basename: CURRENT_DOC
                };
                
                // Añadir modelo si está seleccionado
                if (selectedModel) {
                    payload.model = selectedModel;
                }
                
                const response = await fetch(PROXY_URL, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify(payload)
                });
                
                // Intentar parsear como JSON
                let result;
                const responseText = await response.text();
                
                try {
                    result = JSON.parse(responseText);
                } catch (jsonError) {
                    // El servidor devolvió HTML u otro contenido no-JSON
                    handleError('Respuesta inválida del servidor', responseText, null, null);
                    return;
                }
                
                if (response.ok && result.output?.json_data) {
                    handleSuccess(result);
                } else {
                    handleError(
                        result.debug?.error || 'Error desconocido',
                        null,
                        result.timeline,
                        result.debug?.http
                    );
                }
                
            } catch (error) {
                handleError('Error de conexión: ' + error.message, null, null, null);
            } finally {
                processBtn.disabled = false;
            }
        }
        
        function handleSuccess(result) {
            showStatus('✅ Análisis completado exitosamente', 'success');
            
            // Mostrar timeline SIEMPRE
            if (result.timeline) {
                showTimeline(result.timeline);
            }
            
            // Mostrar debug SIEMPRE
            if (result.debug?.http) {
                showDebugHttp(result.debug.http);
            }
            
            // Mostrar resultados
            if (result.output?.json_data) {
                showResults(result.output.json_data);
            }
        }
        
        function handleError(errorMsg, rawResponse, timeline, debugHttp) {
            // Crear mensaje de error con encapsulación HTML si es necesario
            let displayError = errorMsg;
            
            if (rawResponse) {
                // El servidor devolvió algo que no es JSON (probablemente HTML con error PHP)
                displayError = 'El servidor devolvió una respuesta no válida. Ver detalles abajo.';
            }
            
            showStatus('❌ Error: ' + displayError, 'error');
            
            // Mostrar timeline si existe
            if (timeline) {
                showTimeline(timeline);
            }
            
            // Mostrar debug HTTP si existe
            if (debugHttp) {
                showDebugHttp(debugHttp);
            }
            
            // Mostrar respuesta cruda HTML encapsulada
            if (rawResponse) {
                showRawErrorResponse(rawResponse);
            }
        }
        
        // Las funciones showStatus, showTimeline, showDebugHttp, togglePanel y showRawErrorResponse
        // están definidas en phase_common.js
        
        function showResults(jsonData) {
            extractedJSON = jsonData;
            
            // Mostrar campos individuales
            jsonFieldsDisplay.innerHTML = '';
            const fieldLabels = {
                'file_id': 'File ID',
                'nombre_archivo': 'Nombre del Archivo',
                'nombre_producto': 'Nombre del Producto',
                'codigo_referencia_cofem': 'Código de Referencia Cofem',
                'tipo_documento': 'Tipo de Documento',
                'tipo_informacion_contenida': 'Tipo de Información',
                'fecha_emision_revision': 'Fecha de Emisión/Revisión',
                'idiomas_presentes': 'Idiomas Presentes'
            };
            
            for (const [key, label] of Object.entries(fieldLabels)) {
                const value = jsonData[key];
                const displayValue = Array.isArray(value) ? value.join(', ') : (value || '(vacío)');
                
                const fieldDiv = document.createElement('div');
                fieldDiv.className = 'json-field';
                fieldDiv.innerHTML = `
                    <div class="json-field-label">${label}:</div>
                    <div class="json-field-value">${displayValue}</div>
                `;
                jsonFieldsDisplay.appendChild(fieldDiv);
            }
            
            // Mostrar JSON completo
            jsonRawDisplay.textContent = JSON.stringify(jsonData, null, 2);
            
            resultsPanel.style.display = 'block';
        }
        
        function copyJSON() {
            copyToClipboard(JSON.stringify(extractedJSON, null, 2), 'JSON copiado al portapapeles');
        }
        
        function downloadJSON() {
            downloadFile(JSON.stringify(extractedJSON, null, 2), `${CURRENT_DOC}_metadata.json`, 'application/json;charset=utf-8');
        }
    </script>
</body>
</html>

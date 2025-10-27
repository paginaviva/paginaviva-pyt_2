<?php
// phase_1b.php - Interfaz Fase 1B: Procesar PDF en OpenAI
// Form con parámetros APIO configurables, panel debug expandible, resultados

session_start();

// Verificar autenticación
if (empty($_SESSION['user'])) {
    header('Location: login.php');
    exit;
}

require_once __DIR__ . '/lib_apio.php';
$cfg = apio_load_config();

// Obtener documento pre-seleccionado desde URL
$preSelectedDoc = isset($_GET['doc']) ? trim($_GET['doc']) : '';

// Obtener lista de documentos disponibles (directorios en docs_dir que contengan .pdf)
$docsDir = $cfg['docs_dir'] ?? '';
$availableDocs = [];

if (is_dir($docsDir)) {
    $items = scandir($docsDir);
    foreach ($items as $item) {
        if ($item === '.' || $item === '..') continue;
        
        $itemPath = $docsDir . DIRECTORY_SEPARATOR . $item;
        if (is_dir($itemPath)) {
            $pdfPath = $itemPath . DIRECTORY_SEPARATOR . $item . '.pdf';
            if (is_file($pdfPath)) {
                $availableDocs[] = [
                    'basename' => $item,
                    'pdf_path' => $pdfPath,
                    'pdf_size' => filesize($pdfPath),
                    'has_txt' => is_file($itemPath . DIRECTORY_SEPARATOR . $item . '.txt'),
                    'has_log' => is_file($itemPath . DIRECTORY_SEPARATOR . $item . '.log')
                ];
            }
        }
    }
}

// URLs dinámicas
$processUrl = apio_public_from_cfg_path('/code/php/process_1b.php');

// Configuración APIO
$apioModels = $cfg['apio_models'] ?? ['gpt-4o', 'gpt-4o-mini', 'gpt-5-mini'];
$apioDefaults = $cfg['apio_defaults'] ?? [];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Fase 1B - Procesar PDF en OpenAI - BeeVIVA</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
</head>
<body>
    <?php require_once __DIR__ . '/header.php'; ?>
    
    <div class="container">
        <h2>&#129302; Fase 1B &mdash; Procesar PDF en OpenAI</h2>
        <p>Procesa el documento subido en la Fase 1A y configura los par&aacute;metros para extraer texto usando IA.</p>
        
        <!-- Mostrar documento a procesar (solo lectura) -->
        <?php if ($preSelectedDoc && !empty($availableDocs)): ?>
            <?php 
            $currentDoc = null;
            foreach ($availableDocs as $doc) {
                if ($doc['basename'] === $preSelectedDoc) {
                    $currentDoc = $doc;
                    break;
                }
            }
            ?>
            <?php if ($currentDoc): ?>
                <div class="document-info">
                    <h3>&#128196; Documento a procesar:</h3>
                    <div class="doc-display">
                        <strong><?php echo htmlspecialchars($currentDoc['basename']); ?></strong><br>
                        &#128193; Tama&ntilde;o: <?php echo round($currentDoc['pdf_size'] / 1024); ?> KB<br>
                        <?php if ($currentDoc['has_txt']): ?>
                            &#9989; Ya procesado anteriormente
                        <?php else: ?>
                            &#128472; Pendiente de procesar
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>
        <?php else: ?>
            <div class="error-message">
                &#10060; No se encontr&oacute; el documento especificado. <a href="<?php echo apio_public_from_cfg_path('/code/php/upload_form.php'); ?>">Volver a Fase 1A</a>
            </div>
        <?php endif; ?>
        
        <!-- Formulario Principal -->
        <?php if ($currentDoc): ?>
        <div class="form-section">
            <form id="phase1bForm">
                <!-- Campo oculto con el documento -->
                <input type="hidden" name="doc_basename" value="<?php echo htmlspecialchars($preSelectedDoc); ?>">
                
                <!-- Parámetros APIO -->
                <fieldset class="apio-params">
                    <legend>⚙️ Parámetros OpenAI API</legend>
                    
                    <div class="field-row">
                        <div class="field-group">
                            <label for="model">Modelo:</label>
                            <select id="model" name="model">
                                <?php foreach ($apioModels as $model): ?>
                                    <option value="<?php echo htmlspecialchars($model); ?>" 
                                            <?php echo $model === ($apioDefaults['model'] ?? 'gpt-5-mini') ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($model); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="field-group">
                            <label for="temperature">Temperatura:</label>
                            <input type="range" id="temperature" name="temperature" 
                                   min="0" max="1" step="0.1" 
                                   value="<?php echo $apioDefaults['temperature'] ?? 0; ?>">
                            <span id="tempValue"><?php echo $apioDefaults['temperature'] ?? 0; ?></span>
                        </div>
                    </div>
                    
                    <div class="field-row">
                        <div class="field-group">
                            <label for="maxTokens">Máx. Tokens:</label>
                            <input type="number" id="maxTokens" name="max_tokens" 
                                   min="100" max="4000" step="100"
                                   value="<?php echo $apioDefaults['max_tokens'] ?? 1500; ?>">
                        </div>
                        
                        <div class="field-group">
                            <label for="topP">Top P:</label>
                            <input type="range" id="topP" name="top_p" 
                                   min="0.1" max="1" step="0.1"
                                   value="<?php echo $apioDefaults['top_p'] ?? 1.0; ?>">
                            <span id="topPValue"><?php echo $apioDefaults['top_p'] ?? 1.0; ?></span>
                        </div>
                    </div>
                </fieldset>
                
                <!-- Botón de Acción -->
                <div class="action-section">
                    <button type="submit" id="processBtn" class="btn btn-primary">
                        &#128640; Procesar con OpenAI
                    </button>
                    <div id="statusIndicator" class="status-indicator"></div>
                </div>
            </form>
        </div>
        <?php endif; ?>
        
        <!-- Panel de Debug (Expandible) -->
        <div id="debugPanel" class="debug-panel" style="display: none;">
            <h3>&#128269; Informaci&oacute;n de Debug</h3>
            
            <div class="debug-section" id="preflightSection" style="display: none;">
                <h4>&#9989; Pre-flight Checks</h4>
                <div id="preflightContent"></div>
            </div>
            
            <div class="debug-section" id="modelSection" style="display: none;">
                <h4>&#129302; Informaci&oacute;n del Modelo</h4>
                <div id="modelContent"></div>
            </div>
            
            <div class="debug-section" id="promptSection" style="display: none;">
                <h4>&#128221; Prompt Utilizado</h4>
                <pre id="promptContent"></pre>
            </div>
            
            <div class="debug-section" id="requestSection" style="display: none;">
                <h4>&#128228; Solicitud a OpenAI</h4>
                <div class="debug-subsection">
                    <h5>Headers:</h5>
                    <pre id="requestHeaders"></pre>
                </div>
                <div class="debug-subsection">
                    <h5>Body:</h5>
                    <pre id="requestBody"></pre>
                </div>
            </div>
            
            <div class="debug-section" id="responseSection" style="display: none;">
                <h4>&#128229; Respuesta de OpenAI</h4>
                <pre id="responseContent"></pre>
            </div>
        </div>
        
        <!-- Resultados -->
        <div id="resultsPanel" class="results-panel" style="display: none;">
            <h3>&#128196; Resultado de la Extracci&oacute;n</h3>
            
            <div class="result-info">
                <div id="extractionInfo"></div>
            </div>
            
            <div class="extracted-text">
                <h4>Texto Extra&iacute;do:</h4>
                <div id="extractedTextContent" class="text-content"></div>
            </div>
            
            <div class="action-buttons">
                <button id="downloadTxt" class="btn btn-secondary" style="display: none;">
                    &#128190; Descargar .TXT
                </button>
                <button id="continuePhase2" class="btn btn-success" style="display: none;">
                    &#10145; Continuar a Fase 2A
                </button>
            </div>
        </div>
    </div>
    
    <!-- CSS Inline para la Fase 1B -->
    <style>
        .form-section {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        
        .document-info {
            background: #e8f4fd;
            border: 2px solid #007cba;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 20px;
        }
        
        .doc-display {
            background: white;
            padding: 10px;
            border-radius: 4px;
            margin-top: 10px;
        }
        
        .error-message {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
            border-radius: 4px;
            padding: 15px;
            margin-bottom: 20px;
        }
        
        .field-group {
            margin-bottom: 15px;
        }
        
        .field-group label {
            display: block;
            font-weight: 700;
            margin-bottom: 5px;
            color: #333;
        }
        
        .field-group select, .field-group input {
            width: 100%;
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 14px;
        }
        
        .field-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }
        
        .field-group input[type="range"] {
            width: calc(100% - 50px);
            margin-right: 10px;
        }
        
        .field-group span {
            display: inline-block;
            min-width: 30px;
            text-align: center;
            font-weight: 700;
            color: #007cba;
        }
        
        .apio-params {
            border: 2px solid #e1e5e9;
            border-radius: 6px;
            padding: 15px;
            margin: 20px 0;
        }
        
        .apio-params legend {
            padding: 0 10px;
            font-weight: 700;
            color: #0b6cff;
        }
        
        .action-section {
            text-align: center;
            margin-top: 25px;
        }
        
        .btn {
            padding: 12px 24px;
            border: none;
            border-radius: 6px;
            font-size: 16px;
            font-weight: 700;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
        }
        
        .btn-primary {
            background: #0b6cff;
            color: white;
        }
        
        .btn-primary:disabled {
            background: #ccc;
            cursor: not-allowed;
        }
        
        .btn-secondary {
            background: #6c757d;
            color: white;
        }
        
        .btn-success {
            background: #28a745;
            color: white;
        }
        
        .status-indicator {
            margin-top: 10px;
            padding: 10px;
            border-radius: 4px;
            display: none;
        }
        
        .status-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .status-error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        .status-processing {
            background: #fff3cd;
            color: #856404;
            border: 1px solid #ffeaa7;
        }
        
        .debug-panel {
            background: #f1f3f4;
            border: 1px solid #dee2e6;
            border-radius: 6px;
            padding: 20px;
            margin: 20px 0;
        }
        
        .debug-section {
            margin-bottom: 20px;
            padding: 15px;
            background: white;
            border-radius: 4px;
        }
        
        .debug-section h4 {
            margin-top: 0;
            color: #333;
        }
        
        .debug-section pre {
            background: #2d3748;
            color: #e2e8f0;
            padding: 12px;
            border-radius: 4px;
            overflow-x: auto;
            font-size: 12px;
            line-height: 1.4;
        }
        
        .results-panel {
            background: #f8f9fa;
            border: 2px solid #28a745;
            border-radius: 8px;
            padding: 20px;
            margin: 20px 0;
        }
        
        .text-content {
            background: white;
            border: 1px solid #ddd;
            border-radius: 4px;
            padding: 15px;
            max-height: 400px;
            overflow-y: auto;
            font-family: 'Georgia', serif;
            line-height: 1.6;
            white-space: pre-wrap;
        }
        
        .action-buttons {
            margin-top: 20px;
            text-align: center;
            gap: 10px;
            display: flex;
            justify-content: center;
        }
        
        .doc-info {
            margin-top: 8px;
            padding: 8px;
            background: #e9ecef;
            border-radius: 4px;
            font-size: 14px;
        }
        
        @media (max-width: 768px) {
            .field-row {
                grid-template-columns: 1fr;
            }
            
            .action-buttons {
                flex-direction: column;
            }
        }
    </style>
    
    <!-- JavaScript para la interactividad -->
    <script>
        // Inyectar URLs desde PHP
        const PROCESS_URL = <?php echo json_encode($processUrl); ?>;
        
        // Estado persistente de parámetros APIO
        const APIO_STORAGE_KEY = 'ed_cfle_apio_params';
        
        // Elementos DOM
        const form = document.getElementById('phase1bForm');
        const processBtn = document.getElementById('processBtn');
        const statusIndicator = document.getElementById('statusIndicator');
        const debugPanel = document.getElementById('debugPanel');
        const resultsPanel = document.getElementById('resultsPanel');
        
        // Sliders
        const temperatureSlider = document.getElementById('temperature');
        const tempValue = document.getElementById('tempValue');
        const topPSlider = document.getElementById('topP');
        const topPValue = document.getElementById('topPValue');
        
        // Inicializar
        document.addEventListener('DOMContentLoaded', initializePhase1B);
        
        function initializePhase1B() {
            loadApioParams();
            setupEventListeners();
            
            // Habilitar botón automáticamente ya que el documento está pre-seleccionado
            if (processBtn) {
                processBtn.disabled = false;
            }
        }
        
        function loadApioParams() {
            const saved = localStorage.getItem(APIO_STORAGE_KEY);
            if (saved) {
                try {
                    const params = JSON.parse(saved);
                    
                    if (params.model) document.getElementById('model').value = params.model;
                    if (params.temperature !== undefined) {
                        temperatureSlider.value = params.temperature;
                        tempValue.textContent = params.temperature;
                    }
                    if (params.max_tokens !== undefined) {
                        document.getElementById('maxTokens').value = params.max_tokens;
                    }
                    if (params.top_p !== undefined) {
                        topPSlider.value = params.top_p;
                        topPValue.textContent = params.top_p;
                    }
                } catch (e) {
                    console.warn('Error cargando parámetros APIO:', e);
                }
            }
        }
        
        function saveApioParams() {
            const params = {
                model: document.getElementById('model').value,
                temperature: parseFloat(temperatureSlider.value),
                max_tokens: parseInt(document.getElementById('maxTokens').value),
                top_p: parseFloat(topPSlider.value)
            };
            
            localStorage.setItem(APIO_STORAGE_KEY, JSON.stringify(params));
        }
        
        function setupEventListeners() {
            // Sliders
            temperatureSlider.addEventListener('input', (e) => {
                tempValue.textContent = e.target.value;
                saveApioParams();
            });
            
            topPSlider.addEventListener('input', (e) => {
                topPValue.textContent = e.target.value;
                saveApioParams();
            });
            
            // Otros inputs
            document.getElementById('model').addEventListener('change', saveApioParams);
            document.getElementById('maxTokens').addEventListener('change', saveApioParams);
            
            // Form submit
            form.addEventListener('submit', onFormSubmit);
        }
        
        
        async function onFormSubmit(e) {
            e.preventDefault();
            
            showStatus('Iniciando procesamiento...', 'processing');
            processBtn.disabled = true;
            
            // Guardar parámetros
            saveApioParams();
            
            // Preparar datos
            const formData = new FormData(form);
            
            try {
                const response = await fetch(PROCESS_URL, {
                    method: 'POST',
                    body: formData,
                    credentials: 'same-origin'
                });
                
                const result = await response.json();
                
                if (result.ok) {
                    handleSuccess(result);
                } else {
                    handleError(result.error || 'Error desconocido');
                }
                
            } catch (error) {
                handleError('Error de conexión: ' + error.message);
            } finally {
                processBtn.disabled = false;
            }
        }
        
        function handleSuccess(result) {
            showStatus('✅ Extracción completada exitosamente', 'success');
            
            // Mostrar debug si disponible
            if (result.debug_info) {
                showDebugInfo(result.debug_info);
            }
            
            // Mostrar resultados
            showResults(result);
        }
        
        function handleError(error) {
            showStatus('❌ Error: ' + error, 'error');
        }
        
        function showStatus(message, type) {
            statusIndicator.textContent = message;
            statusIndicator.className = `status-indicator status-${type}`;
            statusIndicator.style.display = 'block';
        }
        
        function showDebugInfo(debugInfo) {
            debugPanel.style.display = 'block';
            
            if (debugInfo.preflight) {
                document.getElementById('preflightSection').style.display = 'block';
                document.getElementById('preflightContent').innerHTML = `
                    ✅ PDF existe: ${debugInfo.preflight.pdf_exists ? 'Sí' : 'No'}<br>
                    &#128207; Tama&ntilde;o PDF: ${Math.round(debugInfo.preflight.pdf_size / 1024)} KB<br>
                    &#128221; Prompt listo: ${debugInfo.preflight.prompt_ready ? 'S&iacute;' : 'No'}
                `;
            }
            
            if (debugInfo.model_info) {
                document.getElementById('modelSection').style.display = 'block';
                document.getElementById('modelContent').innerHTML = `
                    &#129302; Modelo: ${debugInfo.model_info.model}<br>
                    &#127777; Temperatura: ${debugInfo.model_info.temperature}<br>
                    &#127919; Max tokens: ${debugInfo.model_info.max_tokens}<br>
                    &#128202; Top P: ${debugInfo.model_info.top_p}
                `;
            }
            
            if (debugInfo.prompt) {
                document.getElementById('promptSection').style.display = 'block';
                document.getElementById('promptContent').textContent = debugInfo.prompt;
            }
            
            if (debugInfo.request_headers || debugInfo.request_payload) {
                document.getElementById('requestSection').style.display = 'block';
                if (debugInfo.request_headers) {
                    document.getElementById('requestHeaders').textContent = 
                        JSON.stringify(debugInfo.request_headers, null, 2);
                }
                if (debugInfo.request_payload) {
                    document.getElementById('requestBody').textContent = 
                        JSON.stringify(debugInfo.request_payload, null, 2);
                }
            }
            
            if (debugInfo.response_body) {
                document.getElementById('responseSection').style.display = 'block';
                document.getElementById('responseContent').textContent = 
                    JSON.stringify(debugInfo.response_body, null, 2);
            }
        }
        
        function showResults(result) {
            resultsPanel.style.display = 'block';
            
            // Información de la extracción
            const usage = result.api_usage;
            let infoHtml = `
                &#128196; <strong>Documento:</strong> ${result.doc_basename}<br>
                &#128221; <strong>Longitud texto:</strong> ${result.text_length.toLocaleString()} caracteres<br>
                &#9200; <strong>Procesado:</strong> ${result.timestamp}
            `;
            
            if (usage) {
                infoHtml += `<br>&#128290; <strong>Tokens usados:</strong> ${usage.total_tokens || 'N/A'}`;
                if (usage.prompt_tokens) {
                    infoHtml += ` (${usage.prompt_tokens} prompt + ${usage.completion_tokens} respuesta)`;
                }
            }
            
            document.getElementById('extractionInfo').innerHTML = infoHtml;
            
            // Mostrar texto extraído
            document.getElementById('extractedTextContent').textContent = result.text_content;
            
            // Mostrar botones
            document.getElementById('downloadTxt').style.display = 'inline-block';
            document.getElementById('continuePhase2').style.display = 'inline-block';
        }
    </script>
</body>
</html>
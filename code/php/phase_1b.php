<?php
// phase1b.php - Nueva UI Fase 1B: Procesar PDF con Sistema Proxy
// Interfaz moderna con debug en tiempo real usando proxy_common.php

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

// Obtener lista de documentos disponibles
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

// URLs del sistema proxy
$proxyUrl = apio_public_from_cfg_path('/code/php/phase1b_proxy.php');

// Configuración APIO
$apioModels = $cfg['apio_models'] ?? ['gpt-5-mini', 'gpt-5', 'gpt-4o', 'gpt-4o-mini'];
$apioDefaults = $cfg['apio_defaults'] ?? [];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>📄 Fase 1B — Procesar PDF en OpenAI</title>
    <link rel="stylesheet" href="<?php echo apio_public_from_cfg_path('/css/styles.css'); ?>">
    <style>
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }
        
        .document-info {
            background: #f8f9fa;
            border: 1px solid #e9ecef;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 20px;
        }
        
        .doc-display {
            background: white;
            border: 1px solid #dee2e6;
            border-radius: 4px;
            padding: 15px;
            margin-top: 10px;
        }
        
        .params-panel {
            background: #f8f9fa;
            border: 1px solid #e9ecef;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 20px;
        }
        
        .param-row {
            display: flex;
            gap: 20px;
            margin-bottom: 15px;
            align-items: center;
        }
        
        .param-group {
            flex: 1;
        }
        
        .param-group label {
            display: block;
            font-weight: bold;
            margin-bottom: 5px;
            color: #495057;
        }
        
        .param-group select,
        .param-group input {
            width: 100%;
            padding: 8px 12px;
            border: 1px solid #ced4da;
            border-radius: 4px;
            font-size: 14px;
        }
        
        .slider-container {
            position: relative;
        }
        
        .slider {
            width: 100%;
            height: 6px;
            border-radius: 3px;
            background: #e9ecef;
            outline: none;
            -webkit-appearance: none;
        }
        
        .slider::-webkit-slider-thumb {
            appearance: none;
            width: 20px;
            height: 20px;
            border-radius: 50%;
            background: #007bff;
            cursor: pointer;
        }
        
        .slider-value {
            position: absolute;
            right: 0;
            top: -25px;
            font-weight: bold;
            color: #007bff;
        }
        
        .process-btn {
            background: #007bff;
            color: white;
            border: none;
            padding: 12px 24px;
            border-radius: 6px;
            font-size: 16px;
            font-weight: bold;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 8px;
            margin: 20px auto;
        }
        
        .process-btn:disabled {
            background: #6c757d;
            cursor: not-allowed;
        }
        
        .timeline-panel {
            background: #fff;
            border: 1px solid #e9ecef;
            border-radius: 8px;
            margin-bottom: 20px;
            display: none;
        }
        
        .timeline-header {
            background: #f8f9fa;
            padding: 15px 20px;
            border-bottom: 1px solid #e9ecef;
            font-weight: bold;
            cursor: pointer;
        }
        
        .timeline-content {
            padding: 20px;
        }
        
        .timeline-item {
            display: flex;
            justify-content: space-between;
            padding: 8px 0;
            border-bottom: 1px solid #f8f9fa;
        }
        
        .timeline-stage {
            font-family: monospace;
            color: #495057;
        }
        
        .timeline-time {
            font-family: monospace;
            color: #6c757d;
            font-size: 12px;
        }
        
        .debug-panel {
            background: #fff;
            border: 1px solid #e9ecef;
            border-radius: 8px;
            margin-bottom: 20px;
            display: none;
        }
        
        .debug-header {
            background: #f8f9fa;
            padding: 15px 20px;
            border-bottom: 1px solid #e9ecef;
            font-weight: bold;
            cursor: pointer;
        }
        
        .debug-content {
            padding: 0;
        }
        
        .debug-http {
            border-bottom: 1px solid #f8f9fa;
        }
        
        .debug-http-header {
            background: #f1f3f4;
            padding: 10px 20px;
            font-family: monospace;
            font-size: 14px;
            cursor: pointer;
        }
        
        .debug-http-content {
            padding: 15px 20px;
            background: #f8f9fa;
            display: none;
        }
        
        .debug-json {
            background: #2d3748;
            color: #e2e8f0;
            padding: 15px;
            font-family: monospace;
            font-size: 12px;
            border-radius: 4px;
            overflow-x: auto;
            white-space: pre-wrap;
        }
        
        .results-panel {
            background: #fff;
            border: 1px solid #e9ecef;
            border-radius: 8px;
            margin-bottom: 20px;
            display: none;
        }
        
        .results-header {
            background: #d4edda;
            color: #155724;
            padding: 15px 20px;
            border-bottom: 1px solid #c3e6cb;
            font-weight: bold;
        }
        
        .results-content {
            padding: 20px;
        }
        
        .results-actions {
            display: flex;
            gap: 10px;
            margin-bottom: 15px;
        }
        
        .action-btn {
            padding: 8px 16px;
            border: 1px solid #007bff;
            background: white;
            color: #007bff;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
        }
        
        .action-btn:hover {
            background: #007bff;
            color: white;
        }
        
        .results-text {
            background: #f8f9fa;
            border: 1px solid #e9ecef;
            border-radius: 4px;
            padding: 15px;
            font-family: monospace;
            font-size: 14px;
            white-space: pre-wrap;
            max-height: 400px;
            overflow-y: auto;
        }
        
        .status-indicator {
            padding: 10px 20px;
            border-radius: 4px;
            margin-bottom: 20px;
            display: none;
            font-weight: bold;
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
    </style>
</head>
<body>
    <?php require_once __DIR__ . '/header.php'; ?>
    
    <div class="container">
        <h2>📄 Fase 1B &mdash; Procesar PDF en OpenAI</h2>
        <p>Procesa el documento subido en la Fase 1A y configura los parámetros para extraer texto usando IA.</p>
        
        <!-- Mostrar documento a procesar -->
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
                    <h3>📄 Documento a procesar:</h3>
                    <div class="doc-display">
                        <strong><?php echo htmlspecialchars($currentDoc['basename']); ?></strong><br>
                        📁 Tamaño: <?php echo number_format($currentDoc['pdf_size'] / 1024); ?> KB<br>
                        ⏳ Pendiente de procesar
                    </div>
                </div>
            <?php endif; ?>
        <?php endif; ?>
        
        <!-- Parámetros OpenAI -->
        <div class="params-panel">
            <h3>⚙️ Parámetros OpenAI API</h3>
            
            <form id="phase1bForm">
                <input type="hidden" name="doc_basename" value="<?php echo htmlspecialchars($preSelectedDoc); ?>">
                
                <div class="param-row">
                    <div class="param-group">
                        <label for="model">Modelo:</label>
                        <select id="model" name="model">
                            <?php foreach ($apioModels as $model): ?>
                                <option value="<?php echo htmlspecialchars($model); ?>" 
                                    <?php echo ($model === ($apioDefaults['model'] ?? '')) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($model); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="param-group">
                        <label for="temperature">Temperatura:</label>
                        <div class="slider-container">
                            <input type="range" id="temperature" name="temperature" 
                                min="0" max="2" step="0.1" 
                                value="<?php echo $apioDefaults['temperature'] ?? 0; ?>" 
                                class="slider">
                            <span id="tempValue" class="slider-value"><?php echo $apioDefaults['temperature'] ?? 0; ?></span>
                        </div>
                    </div>
                </div>
                
                <div class="param-row">
                    <div class="param-group">
                        <label for="maxTokens">Máx. Tokens:</label>
                        <input type="number" id="maxTokens" name="max_tokens" 
                            min="100" max="4000" step="100" 
                            value="<?php echo $apioDefaults['max_tokens'] ?? 1500; ?>">
                    </div>
                    
                    <div class="param-group">
                        <label for="topP">Top P:</label>
                        <div class="slider-container">
                            <input type="range" id="topP" name="top_p" 
                                min="0" max="1" step="0.1" 
                                value="<?php echo $apioDefaults['top_p'] ?? 1.0; ?>" 
                                class="slider">
                            <span id="topPValue" class="slider-value"><?php echo $apioDefaults['top_p'] ?? 1.0; ?></span>
                        </div>
                    </div>
                </div>
                
                <button type="submit" id="processBtn" class="process-btn" disabled>
                    🚀 Procesar con OpenAI
                </button>
            </form>
        </div>
        
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
                🔍 Debug HTTP
            </div>
            <div class="debug-content" id="debugContent">
                <!-- Se llena dinámicamente -->
            </div>
        </div>
        
        <!-- Panel de Resultados -->
        <div id="resultsPanel" class="results-panel">
            <div class="results-header">
                ✅ Extracción Completada
            </div>
            <div class="results-content">
                <div class="results-actions">
                    <button class="action-btn" onclick="copyText()">📋 Copiar</button>
                    <button class="action-btn" onclick="downloadText()">💾 Descargar</button>
                    <button class="action-btn" onclick="viewAsFile()">📄 TXT File</button>
                </div>
                <div id="resultsText" class="results-text">
                    <!-- Se llena dinámicamente -->
                </div>
                
                <!-- Sección Siguiente Fase -->
                <div class="phase2a-section" style="margin-top: 20px; padding: 15px; background: #e8f5e8; border: 2px solid #28a745; border-radius: 8px;">
                    <h3 style="margin-top: 0; color: #28a745;">🚀 Continuar con el Flujo</h3>
                    <p>El texto se ha extraído y guardado correctamente. Puedes continuar con la siguiente fase del procesamiento.</p>
                    <button id="continuePhase1CBtn" class="btn" style="background: #17a2b8; color: white; padding: 12px 24px; border: none; border-radius: 6px; font-weight: 700; cursor: pointer; margin-right: 10px;">
                        📤 Continuar a Fase 1C (Subir a OpenAI)
                    </button>
                    <button id="viewFilesBtn" class="btn" style="background: #6c757d; color: white; padding: 12px 24px; border: none; border-radius: 6px; font-weight: 700; cursor: pointer;">
                        📁 Ver Archivos Generados
                    </button>
                </div>
            </div>
        </div>
    </div>
    
    <script>
        // Configuración
        const PROXY_URL = '<?php echo $proxyUrl; ?>';
        const CURRENT_DOC = '<?php echo htmlspecialchars($preSelectedDoc); ?>';
        
        // Referencias DOM
        const form = document.getElementById('phase1bForm');
        const processBtn = document.getElementById('processBtn');
        const statusIndicator = document.getElementById('statusIndicator');
        const timelinePanel = document.getElementById('timelinePanel');
        const timelineContent = document.getElementById('timelineContent');
        const debugPanel = document.getElementById('debugPanel');
        const debugContent = document.getElementById('debugContent');
        const resultsPanel = document.getElementById('resultsPanel');
        const resultsText = document.getElementById('resultsText');
        
        // Sliders
        const temperatureSlider = document.getElementById('temperature');
        const tempValue = document.getElementById('tempValue');
        const topPSlider = document.getElementById('topP');
        const topPValue = document.getElementById('topPValue');
        
        // Variables globales
        let extractedText = '';
        
        // Inicialización
        document.addEventListener('DOMContentLoaded', initializePhase1B);
        
        function initializePhase1B() {
            setupEventListeners();
            
            // Habilitar botón si hay documento
            if (CURRENT_DOC && processBtn) {
                processBtn.disabled = false;
            }
        }
        
        function setupEventListeners() {
            // Sliders
            temperatureSlider.addEventListener('input', function() {
                tempValue.textContent = this.value;
            });
            
            topPSlider.addEventListener('input', function() {
                topPValue.textContent = this.value;
            });
            
            // Formulario
            form.addEventListener('submit', handleSubmit);
        }
        
        async function handleSubmit(event) {
            event.preventDefault();
            
            if (!CURRENT_DOC) {
                alert('No hay documento seleccionado');
                return;
            }
            
            processBtn.disabled = true;
            showStatus('🔄 Procesando documento...', 'processing');
            
            // Ocultar paneles anteriores
            timelinePanel.style.display = 'none';
            debugPanel.style.display = 'none';
            resultsPanel.style.display = 'none';
            
            try {
                // Preparar datos
                const formData = new FormData(form);
                
                // Construir URL del PDF
                const pdfUrl = `<?php echo apio_public_from_cfg_path('/docs/'); ?>${CURRENT_DOC}/${CURRENT_DOC}.pdf`;
                
                const payload = {
                    pdf_url: pdfUrl,
                    doc_basename: CURRENT_DOC, // Agregar NB del archivo
                    model: formData.get('model'),
                    temperature: parseFloat(formData.get('temperature')),
                    max_tokens: parseInt(formData.get('max_tokens')),
                    top_p: parseFloat(formData.get('top_p'))
                };
                
                // Llamada al proxy
                const response = await fetch(PROXY_URL, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify(payload)
                });
                
                const result = await response.json();
                
                if (response.ok && result.output) {
                    handleSuccess(result);
                } else {
                    handleError(result.debug?.error || 'Error desconocido');
                }
                
            } catch (error) {
                handleError('Error de conexión: ' + error.message);
            } finally {
                processBtn.disabled = false;
            }
        }
        
        function handleSuccess(result) {
            showStatus('✅ Extracción completada exitosamente', 'success');
            
            // Mostrar timeline
            if (result.timeline) {
                showTimeline(result.timeline);
            }
            
            // Mostrar debug HTTP
            if (result.debug?.http) {
                showDebugHttp(result.debug.http);
            }
            
            // Mostrar resultados
            if (result.output?.tex) {
                showResults(result.output.tex);
            }
        }
        
        function handleError(error) {
            showStatus('❌ Error: ' + error, 'error');
        }
        
        function showStatus(message, type) {
            statusIndicator.textContent = message;
            statusIndicator.className = `status-indicator status-${type}`;
            statusIndicator.style.display = 'block';
        }
        
        function showTimeline(timeline) {
            timelineContent.innerHTML = '';
            
            timeline.forEach(item => {
                const div = document.createElement('div');
                div.className = 'timeline-item';
                div.innerHTML = `
                    <span class="timeline-stage">${item.stage}</span>
                    <span class="timeline-time">${new Date(item.ts).toLocaleTimeString()}</span>
                `;
                timelineContent.appendChild(div);
            });
            
            timelinePanel.style.display = 'block';
        }
        
        function showDebugHttp(httpDebug) {
            debugContent.innerHTML = '';
            
            httpDebug.forEach((req, index) => {
                const div = document.createElement('div');
                div.className = 'debug-http';
                
                const header = document.createElement('div');
                header.className = 'debug-http-header';
                header.textContent = `${req.stage} - ${req.status_code} (${req.ms || 0}ms)`;
                header.onclick = () => toggleHttpDetail(index);
                
                const content = document.createElement('div');
                content.className = 'debug-http-content';
                content.id = `debug-http-${index}`;
                content.innerHTML = `<div class="debug-json">${JSON.stringify(req, null, 2)}</div>`;
                
                div.appendChild(header);
                div.appendChild(content);
                debugContent.appendChild(div);
            });
            
            debugPanel.style.display = 'block';
        }
        
        function continueToPhase1C() {
            if (!CURRENT_DOC) {
                alert('No hay documento procesado para continuar');
                return;
            }
            
            // Construir URL de Fase 1C
            const phase1CUrl = `/code/php/phase_1c.php?doc=${encodeURIComponent(CURRENT_DOC)}`;
            window.location.href = phase1CUrl;
        }
        
        function viewGeneratedFiles() {
            if (!CURRENT_DOC) {
                alert('No hay documento seleccionado');
                return;
            }
            
            // Abrir lista de documentos filtrada o navegar a docs_list
            const docsListUrl = `/code/php/docs_list.php#${encodeURIComponent(CURRENT_DOC)}`;
            window.open(docsListUrl, '_blank');
        }
        
        function showResults(text) {
            // Remover BOM si existe
            extractedText = text.replace(/^\uFEFF/, '');
            resultsText.textContent = extractedText;
            resultsPanel.style.display = 'block';
            
            // Configurar eventos de los botones
            const continuePhase1CBtn = document.getElementById('continuePhase1CBtn');
            const viewFilesBtn = document.getElementById('viewFilesBtn');
            
            if (continuePhase1CBtn) {
                continuePhase1CBtn.onclick = continueToPhase1C;
            }
            
            if (viewFilesBtn) {
                viewFilesBtn.onclick = viewGeneratedFiles;
            }
        }
        
        function togglePanel(panel) {
            const content = document.getElementById(`${panel}Content`);
            const isVisible = content.style.display !== 'none';
            content.style.display = isVisible ? 'none' : 'block';
        }
        
        function toggleHttpDetail(index) {
            const content = document.getElementById(`debug-http-${index}`);
            const isVisible = content.style.display !== 'none';
            content.style.display = isVisible ? 'none' : 'block';
        }
        
        function copyText() {
            navigator.clipboard.writeText(extractedText).then(() => {
                alert('Texto copiado al portapapeles');
            }).catch(err => {
                console.error('Error al copiar:', err);
                alert('Error al copiar el texto');
            });
        }
        
        function downloadText() {
            const blob = new Blob([extractedText], { type: 'text/plain;charset=utf-8' });
            const url = URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = `${CURRENT_DOC}_extracted.txt`;
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
            URL.revokeObjectURL(url);
        }
        
        function viewAsFile() {
            const blob = new Blob([extractedText], { type: 'text/plain;charset=utf-8' });
            const url = URL.createObjectURL(blob);
            window.open(url, '_blank');
        }
    </script>
</body>
</html>
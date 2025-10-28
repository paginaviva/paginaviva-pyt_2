<?php
/**
 * phase_1c.php
 * Interfaz para Fase 1C: Subir archivo .txt a OpenAI Files API
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
    die('Documento no especificado. Use: phase_1c.php?doc=NOMBRE_DOCUMENTO');
}

// Verificar que existe el archivo .txt de F1B
$txtFile = $cfg['docs_dir'] . DIRECTORY_SEPARATOR . $docBasename . DIRECTORY_SEPARATOR . $docBasename . '.txt';
if (!file_exists($txtFile)) {
    die('Error: Debe completar la Fase 1B primero. No se encontró el archivo de texto extraído.');
}

$proxyUrl = apio_public_from_cfg_path('/code/php/phase_1c_proxy.php');
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>📤 Fase 1C — Subir TXT a OpenAI</title>
    <link rel="stylesheet" href="<?php echo apio_public_from_cfg_path('/css/styles.css'); ?>">
    <style>
        .container { max-width: 1000px; margin: 0 auto; padding: 20px; }
        .section-card { background: #fff; border: 1px solid #e9ecef; border-radius: 8px; padding: 20px; margin-bottom: 20px; }
        .section-title { font-size: 1.2rem; font-weight: bold; margin-bottom: 15px; color: #333; }
        
        .file-info { background: #f8f9fa; padding: 15px; border-radius: 4px; margin-bottom: 20px; }
        .file-info-item { display: flex; justify-content: space-between; padding: 8px 0; border-bottom: 1px solid #e9ecef; }
        .file-info-item:last-child { border-bottom: none; }
        
        .upload-btn { background: #007bff; color: white; padding: 12px 24px; border: none; border-radius: 6px; font-weight: bold; cursor: pointer; font-size: 16px; }
        .upload-btn:disabled { background: #6c757d; cursor: not-allowed; }
        
        .status-indicator { padding: 15px; border-radius: 4px; margin-bottom: 20px; display: none; font-weight: bold; }
        .status-processing { background: #fff3cd; color: #856404; }
        .status-success { background: #d4edda; color: #155724; }
        .status-error { background: #f8d7da; color: #721c24; }
        
        .result-section { background: #d4edda; border-left: 4px solid #28a745; display: none; }
        .file-id-display { background: #2d3748; color: #e2e8f0; padding: 15px; font-family: monospace; font-size: 14px; border-radius: 4px; word-break: break-all; }
        
        .action-buttons { display: flex; gap: 10px; margin-top: 15px; }
        .action-btn { padding: 10px 20px; border: 1px solid #007bff; background: white; color: #007bff; border-radius: 4px; cursor: pointer; font-weight: 600; text-decoration: none; display: inline-block; }
        .action-btn.primary { background: #28a745; color: white; border-color: #28a745; }
        
        .debug-section { background: #f8d7da; border-left: 4px solid #dc3545; display: none; }
        .debug-json { background: #2d3748; color: #e2e8f0; padding: 15px; font-family: monospace; font-size: 11px; border-radius: 4px; max-height: 400px; overflow-y: auto; white-space: pre-wrap; }
        
        .timeline-section { display: none; }
        .timeline-item { display: flex; justify-content: space-between; padding: 5px 0; font-family: monospace; font-size: 12px; border-bottom: 1px solid #f8f9fa; }
    </style>
</head>
<body>
    <?php require_once __DIR__ . '/header.php'; ?>
    
    <div class="container">
        <h2>📤 Fase 1C — Subir Archivo TXT a OpenAI</h2>
        <p>Sube el archivo de texto extraído en la Fase 1B a la API de OpenAI para obtener un <code>file_id</code> que se usará en fases posteriores.</p>
        
        <!-- Status Indicator -->
        <div id="statusIndicator" class="status-indicator"></div>
        
        <!-- Información del Archivo -->
        <div class="section-card">
            <div class="section-title">📄 Archivo a Subir</div>
            <div class="file-info">
                <div class="file-info-item">
                    <strong>Documento:</strong>
                    <span><?php echo htmlspecialchars($docBasename); ?></span>
                </div>
                <div class="file-info-item">
                    <strong>Archivo fuente:</strong>
                    <span><?php echo htmlspecialchars(basename($txtFile)); ?></span>
                </div>
                <div class="file-info-item">
                    <strong>Tamaño:</strong>
                    <span><?php echo number_format(filesize($txtFile) / 1024, 2); ?> KB</span>
                </div>
                <div class="file-info-item">
                    <strong>Fase origen:</strong>
                    <span>1B - Extracción de Texto</span>
                </div>
            </div>
            
            <button id="uploadBtn" class="upload-btn">
                📤 Subir a OpenAI Files API
            </button>
        </div>
        
        <!-- Resultado -->
        <div id="resultSection" class="section-card result-section">
            <div class="section-title">✅ Archivo Subido Exitosamente</div>
            <p><strong>File ID de OpenAI:</strong></p>
            <div id="fileIdDisplay" class="file-id-display"></div>
            
            <div class="action-buttons">
                <button class="action-btn" onclick="copyFileId()">📋 Copiar File ID</button>
                <a class="action-btn primary" id="nextPhaseBtn" href="#">
                    ⏭️ Continuar a Fase 2A
                </a>
            </div>
        </div>
        
        <!-- Debug OpenAI -->
        <div id="debugSection" class="section-card debug-section">
            <div class="section-title" onclick="toggleDebug()" style="cursor: pointer;">
                🔍 Debug OpenAI API (Click para expandir)
            </div>
            <div id="debugContent" class="debug-json" style="display: none;"></div>
        </div>
        
        <!-- Timeline -->
        <div id="timelineSection" class="section-card timeline-section">
            <div class="section-title" onclick="toggleTimeline()" style="cursor: pointer;">
                ⏱️ Timeline de Ejecución (Click para expandir)
            </div>
            <div id="timelineContent" style="display: none;"></div>
        </div>
    </div>
    
    <script>
        const PROXY_URL = '<?php echo htmlspecialchars($proxyUrl); ?>';
        const DOC_BASENAME = '<?php echo htmlspecialchars($docBasename); ?>';
        
        let uploadedFileId = '';
        let rawDebugData = null;
        
        document.getElementById('uploadBtn').addEventListener('click', async function() {
            await uploadFile();
        });
        
        async function uploadFile() {
            showStatus('🔄 Subiendo archivo a OpenAI...', 'processing');
            document.getElementById('uploadBtn').disabled = true;
            
            try {
                const payload = {
                    doc_basename: DOC_BASENAME
                };
                
                const response = await fetch(PROXY_URL, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(payload)
                });
                
                const result = await response.json();
                
                if (response.ok && result.output && result.output.file_id) {
                    handleSuccess(result);
                } else {
                    handleError(result.debug?.error || 'Error desconocido al subir archivo');
                }
            } catch (error) {
                handleError('Error de conexión: ' + error.message);
            } finally {
                document.getElementById('uploadBtn').disabled = false;
            }
        }
        
        function handleSuccess(result) {
            uploadedFileId = result.output.file_id;
            
            showStatus('✅ Archivo subido exitosamente a OpenAI', 'success');
            
            // Mostrar file_id
            document.getElementById('fileIdDisplay').textContent = uploadedFileId;
            document.getElementById('resultSection').style.display = 'block';
            
            // Configurar botón siguiente fase
            const nextBtn = document.getElementById('nextPhaseBtn');
            nextBtn.href = `/code/php/phase_2a.php?doc=${encodeURIComponent(DOC_BASENAME)}`;
            
            // Mostrar debug
            rawDebugData = result;
            document.getElementById('debugContent').textContent = JSON.stringify(result, null, 2);
            document.getElementById('debugSection').style.display = 'block';
            
            // Mostrar timeline
            if (result.timeline) {
                showTimeline(result.timeline);
            }
        }
        
        function handleError(error) {
            showStatus('❌ Error: ' + error, 'error');
        }
        
        function showStatus(message, type) {
            const indicator = document.getElementById('statusIndicator');
            indicator.textContent = message;
            indicator.className = 'status-indicator status-' + type;
            indicator.style.display = 'block';
        }
        
        function showTimeline(timeline) {
            const content = document.getElementById('timelineContent');
            content.innerHTML = '';
            
            timeline.forEach(item => {
                const div = document.createElement('div');
                div.className = 'timeline-item';
                div.innerHTML = `
                    <span>${item.stage}</span>
                    <span>${new Date(item.ts).toLocaleTimeString()}</span>
                `;
                content.appendChild(div);
            });
            
            document.getElementById('timelineSection').style.display = 'block';
        }
        
        function copyFileId() {
            navigator.clipboard.writeText(uploadedFileId).then(() => {
                alert('File ID copiado al portapapeles:\n' + uploadedFileId);
            }).catch(err => {
                console.error('Error al copiar:', err);
                alert('Error al copiar el File ID');
            });
        }
        
        function toggleDebug() {
            const content = document.getElementById('debugContent');
            content.style.display = content.style.display === 'none' ? 'block' : 'none';
        }
        
        function toggleTimeline() {
            const content = document.getElementById('timelineContent');
            content.style.display = content.style.display === 'none' ? 'block' : 'none';
        }
    </script>
</body>
</html>

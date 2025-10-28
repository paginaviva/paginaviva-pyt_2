/**
 * phase_common.js
 * Funciones compartidas para todas las fases (F1B, F1C, F2A, F2B, etc.)
 * Arquitectura Común - Octubre 2025
 */

/**
 * Muestra un indicador de estado en la interfaz
 * @param {string} message - Mensaje a mostrar
 * @param {string} type - Tipo de estado: 'success', 'error', 'processing'
 */
function showStatus(message, type) {
    const statusIndicator = document.getElementById('statusIndicator');
    if (!statusIndicator) return;
    
    statusIndicator.textContent = message;
    statusIndicator.className = `status-indicator status-${type}`;
    statusIndicator.style.display = 'block';
}

/**
 * Muestra el panel de timeline con los eventos de ejecución
 * @param {Array} timeline - Array de objetos con {stage, ts}
 */
function showTimeline(timeline) {
    const timelineContent = document.getElementById('timelineContent');
    const timelinePanel = document.getElementById('timelinePanel');
    
    if (!timelineContent || !timelinePanel) return;
    
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

/**
 * Muestra el panel de debug HTTP con los detalles de cada llamada
 * Versión para F2A (muestra endpoint, request, response)
 * @param {Array} debugHttp - Array de objetos con detalles de HTTP
 */
function showDebugHttp(debugHttp) {
    const debugPanel = document.getElementById('debugPanel');
    const debugContent = document.getElementById('debugContent');
    
    if (!debugPanel || !debugContent) return;
    
    debugContent.innerHTML = '';
    
    debugHttp.forEach((req, index) => {
        const section = document.createElement('div');
        section.style.marginBottom = '20px';
        section.style.borderBottom = '1px solid #4a5568';
        section.style.paddingBottom = '15px';
        
        const header = document.createElement('div');
        header.style.fontWeight = 'bold';
        header.style.color = '#63b3ed';
        header.style.marginBottom = '10px';
        header.textContent = `${index + 1}. ${req.stage} - HTTP ${req.status_code} (${req.ms || 0}ms)`;
        section.appendChild(header);
        
        // Endpoint
        if (req.endpoint) {
            const endpoint = document.createElement('div');
            endpoint.style.marginBottom = '10px';
            endpoint.innerHTML = `<strong>Endpoint:</strong> ${req.method || 'GET'} ${req.endpoint}`;
            section.appendChild(endpoint);
        }
        
        // Request
        if (req.request) {
            const reqDiv = document.createElement('div');
            reqDiv.style.marginBottom = '10px';
            reqDiv.innerHTML = '<strong>Request:</strong>';
            const reqPre = document.createElement('pre');
            reqPre.style.background = '#1a202c';
            reqPre.style.padding = '10px';
            reqPre.style.borderRadius = '4px';
            reqPre.style.overflow = 'auto';
            reqPre.textContent = JSON.stringify(req.request, null, 2);
            reqDiv.appendChild(reqPre);
            section.appendChild(reqDiv);
        }
        
        // Response
        if (req.response) {
            const respDiv = document.createElement('div');
            respDiv.style.marginBottom = '10px';
            respDiv.innerHTML = '<strong>Response:</strong>';
            const respPre = document.createElement('pre');
            respPre.style.background = '#1a202c';
            respPre.style.padding = '10px';
            respPre.style.borderRadius = '4px';
            respPre.style.overflow = 'auto';
            respPre.textContent = JSON.stringify(req.response, null, 2);
            respDiv.appendChild(respPre);
            section.appendChild(respDiv);
        }
        
        debugContent.appendChild(section);
    });
    
    debugPanel.style.display = 'block';
}

/**
 * Muestra el panel de debug HTTP (versión F1B con acordeones)
 * @param {Array} httpDebug - Array de objetos con detalles de HTTP
 */
function showDebugHttpAccordion(httpDebug) {
    const debugPanel = document.getElementById('debugPanel');
    const debugContent = document.getElementById('debugContent');
    
    if (!debugPanel || !debugContent) return;
    
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

/**
 * Toggle de visibilidad de un panel (timeline o debug)
 * @param {string} panel - Identificador del panel ('timeline' o 'debug')
 */
function togglePanel(panel) {
    const content = document.getElementById(`${panel}Content`);
    if (!content) return;
    
    const isVisible = content.style.display !== 'none';
    content.style.display = isVisible ? 'none' : 'block';
}

/**
 * Toggle de detalles HTTP individual (para versión acordeón)
 * @param {number} index - Índice del elemento HTTP
 */
function toggleHttpDetail(index) {
    const content = document.getElementById(`debug-http-${index}`);
    if (!content) return;
    
    const isVisible = content.style.display !== 'none';
    content.style.display = isVisible ? 'none' : 'block';
}

/**
 * Muestra un error crudo (HTML) en panel dedicado
 * @param {string} rawResponse - Respuesta cruda del servidor
 */
function showRawErrorResponse(rawResponse) {
    const errorRawContent = document.getElementById('errorRawContent');
    const errorRawPanel = document.getElementById('errorRawPanel');
    
    if (!errorRawContent || !errorRawPanel) return;
    
    // Mostrar el contenido HTML COMO TEXTO (no interpretado)
    errorRawContent.textContent = rawResponse;
    errorRawPanel.style.display = 'block';
}

/**
 * Navega a la lista de documentos con ancla al documento actual
 * @param {string} docBasename - Nombre del documento actual
 */
function viewGeneratedFiles(docBasename) {
    if (!docBasename) {
        alert('No hay documento seleccionado');
        return;
    }
    
    const docsListUrl = `/code/php/docs_list.php#${encodeURIComponent(docBasename)}`;
    window.open(docsListUrl, '_blank');
}

/**
 * Copia texto al portapapeles
 * @param {string} text - Texto a copiar
 * @param {string} successMsg - Mensaje de éxito (opcional)
 */
function copyToClipboard(text, successMsg = 'Copiado al portapapeles') {
    navigator.clipboard.writeText(text).then(() => {
        alert(successMsg);
    }).catch(err => {
        console.error('Error al copiar:', err);
        alert('Error al copiar al portapapeles');
    });
}

/**
 * Descarga contenido como archivo
 * @param {string} content - Contenido a descargar
 * @param {string} filename - Nombre del archivo
 * @param {string} mimeType - Tipo MIME (default: text/plain)
 */
function downloadFile(content, filename, mimeType = 'text/plain;charset=utf-8') {
    const blob = new Blob([content], { type: mimeType });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = filename;
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
    URL.revokeObjectURL(url);
}

/**
 * Abre contenido en nueva pestaña
 * @param {string} content - Contenido a mostrar
 * @param {string} mimeType - Tipo MIME (default: text/plain)
 */
function openInNewTab(content, mimeType = 'text/plain;charset=utf-8') {
    const blob = new Blob([content], { type: mimeType });
    const url = URL.createObjectURL(blob);
    window.open(url, '_blank');
}

/**
 * Actualiza un slider en tiempo real mostrando su valor
 * @param {string} sliderId - ID del slider
 * @param {string} displayId - ID del elemento donde mostrar el valor
 * @param {function} formatter - Función opcional para formatear el valor
 */
function updateSlider(sliderId, displayId, formatter = null) {
    const slider = document.getElementById(sliderId);
    const display = document.getElementById(displayId);
    
    if (!slider || !display) return;
    
    const value = parseFloat(slider.value);
    display.textContent = formatter ? formatter(value) : value;
}

/**
 * Inicializa sliders con sus displays de valor
 * @param {Array} sliders - Array de objetos {sliderId, displayId, formatter}
 */
function initializeSliders(sliders) {
    sliders.forEach(({ sliderId, displayId, formatter }) => {
        const slider = document.getElementById(sliderId);
        if (!slider) return;
        
        // Inicializar valor
        updateSlider(sliderId, displayId, formatter);
        
        // Evento de cambio
        slider.addEventListener('input', () => {
            updateSlider(sliderId, displayId, formatter);
        });
    });
}

/**
 * Maneja errores genéricos mostrando timeline, debug y mensaje
 * @param {string} errorMsg - Mensaje de error
 * @param {string|null} rawResponse - Respuesta cruda HTML (si aplica)
 * @param {Array|null} timeline - Timeline de ejecución
 * @param {Array|null} debugHttp - Debug HTTP
 */
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

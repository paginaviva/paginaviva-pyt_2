// phase_1b.js - JavaScript adicional para funcionalidades avanzadas F1B
// Funciones para descarga de archivos, navegación entre fases, etc.

/**
 * Descargar archivo .TXT generado
 */
function downloadTxtFile(docBasename) {
    if (!docBasename) {
        alert('No hay documento seleccionado para descargar');
        return;
    }
    
    // Construir URL de descarga (implementar endpoint de descarga si es necesario)
    const downloadUrl = `/docs/${encodeURIComponent(docBasename)}/${encodeURIComponent(docBasename)}.txt`;
    
    // Crear enlace temporal para descarga
    const link = document.createElement('a');
    link.href = downloadUrl;
    link.download = `${docBasename}.txt`;
    link.style.display = 'none';
    
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
}

/**
 * Navegar a Fase 2A con el documento procesado
 */
function continueToPhase2A(docBasename) {
    if (!docBasename) {
        alert('No hay documento procesado para continuar');
        return;
    }
    
    // Construir URL de Fase 2A con parámetro del documento
    const phase2Url = `/code/php/phase_2a.php?doc=${encodeURIComponent(docBasename)}`;
    
    // Confirmar navegación
    if (confirm(`¿Continuar a la Fase 2A con el documento "${docBasename}"?`)) {
        window.location.href = phase2Url;
    }
}

/**
 * Expandir/contraer panel de debug
 */
function toggleDebugPanel() {
    const panel = document.getElementById('debugPanel');
    const isVisible = panel.style.display !== 'none';
    
    panel.style.display = isVisible ? 'none' : 'block';
    
    // Actualizar botón si existe
    const toggleBtn = document.getElementById('toggleDebugBtn');
    if (toggleBtn) {
        toggleBtn.textContent = isVisible ? '🔍 Mostrar Debug' : '🙈 Ocultar Debug';
    }
}

/**
 * Copiar contenido de debug al portapapeles
 */
async function copyDebugToClipboard(sectionId) {
    const section = document.getElementById(sectionId);
    if (!section) return;
    
    const text = section.textContent || section.innerText;
    
    try {
        await navigator.clipboard.writeText(text);
        
        // Mostrar feedback temporal
        const originalText = section.innerHTML;
        section.innerHTML = '📋 Copiado al portapapeles';
        
        setTimeout(() => {
            section.innerHTML = originalText;
        }, 1500);
        
    } catch (err) {
        console.error('Error copiando al portapapeles:', err);
        alert('No se pudo copiar al portapapeles');
    }
}

/**
 * Validar formulario antes del envío
 */
function validateForm() {
    const docSelect = document.getElementById('docSelect');
    const model = document.getElementById('model');
    const maxTokens = document.getElementById('maxTokens');
    
    // Validar documento seleccionado
    if (!docSelect.value) {
        alert('Debe seleccionar un documento para procesar');
        docSelect.focus();
        return false;
    }
    
    // Validar modelo
    if (!model.value) {
        alert('Debe seleccionar un modelo de IA');
        model.focus();
        return false;
    }
    
    // Validar tokens
    const tokens = parseInt(maxTokens.value);
    if (isNaN(tokens) || tokens < 100 || tokens > 4000) {
        alert('El número de tokens debe estar entre 100 y 4000');
        maxTokens.focus();
        return false;
    }
    
    return true;
}

/**
 * Resetear formulario a valores por defecto
 */
function resetToDefaults() {
    if (confirm('¿Resetear todos los parámetros a los valores por defecto?')) {
        localStorage.removeItem('ed_cfle_apio_params');
        location.reload();
    }
}

/**
 * Exportar configuración actual
 */
function exportApioConfig() {
    const config = {
        model: document.getElementById('model').value,
        temperature: parseFloat(document.getElementById('temperature').value),
        max_tokens: parseInt(document.getElementById('maxTokens').value),
        top_p: parseFloat(document.getElementById('topP').value),
        exported_at: new Date().toISOString()
    };
    
    const dataStr = JSON.stringify(config, null, 2);
    const dataBlob = new Blob([dataStr], { type: 'application/json' });
    
    const link = document.createElement('a');
    link.href = URL.createObjectURL(dataBlob);
    link.download = 'ed_cfle_apio_config.json';
    link.click();
    
    URL.revokeObjectURL(link.href);
}

/**
 * Importar configuración desde archivo
 */
function importApioConfig() {
    const input = document.createElement('input');
    input.type = 'file';
    input.accept = '.json';
    
    input.onchange = function(e) {
        const file = e.target.files[0];
        if (!file) return;
        
        const reader = new FileReader();
        reader.onload = function(e) {
            try {
                const config = JSON.parse(e.target.result);
                
                // Validar estructura básica
                if (typeof config !== 'object') {
                    throw new Error('Formato de archivo inválido');
                }
                
                // Aplicar configuración
                if (config.model) document.getElementById('model').value = config.model;
                if (config.temperature !== undefined) {
                    document.getElementById('temperature').value = config.temperature;
                    document.getElementById('tempValue').textContent = config.temperature;
                }
                if (config.max_tokens !== undefined) {
                    document.getElementById('maxTokens').value = config.max_tokens;
                }
                if (config.top_p !== undefined) {
                    document.getElementById('topP').value = config.top_p;
                    document.getElementById('topPValue').textContent = config.top_p;
                }
                
                // Guardar en localStorage
                localStorage.setItem('ed_cfle_apio_params', JSON.stringify(config));
                
                alert('Configuración importada exitosamente');
                
            } catch (err) {
                alert('Error importando configuración: ' + err.message);
            }
        };
        
        reader.readAsText(file);
    };
    
    input.click();
}

/**
 * Mostrar/ocultar secciones de debug individuales
 */
function toggleDebugSection(sectionId) {
    const section = document.getElementById(sectionId);
    if (!section) return;
    
    const isVisible = section.style.display !== 'none';
    section.style.display = isVisible ? 'none' : 'block';
}

/**
 * Verificar estado del procesamiento en tiempo real
 */
function checkProcessingStatus(docBasename) {
    // Implementar verificación de estado si es necesario
    // Por ejemplo, para procesos largos que requieren polling
    
    const statusUrl = `/code/php/check_status.php?doc=${encodeURIComponent(docBasename)}`;
    
    fetch(statusUrl, { credentials: 'same-origin' })
        .then(response => response.json())
        .then(data => {
            if (data.status === 'completed') {
                // Actualizar UI con resultado
                console.log('Procesamiento completado:', data);
            } else if (data.status === 'processing') {
                // Continuar verificando
                setTimeout(() => checkProcessingStatus(docBasename), 2000);
            } else if (data.status === 'error') {
                // Manejar error
                console.error('Error en procesamiento:', data.error);
            }
        })
        .catch(err => {
            console.error('Error verificando estado:', err);
        });
}

// Configurar listeners al cargar la página
document.addEventListener('DOMContentLoaded', function() {
    // Listener para descarga de TXT
    const downloadBtn = document.getElementById('downloadTxt');
    if (downloadBtn) {
        downloadBtn.addEventListener('click', function() {
            const docBasename = document.getElementById('docSelect').value;
            downloadTxtFile(docBasename);
        });
    }
    
    // Listener para continuar a Fase 2A
    const continueBtn = document.getElementById('continuePhase2');
    if (continueBtn) {
        continueBtn.addEventListener('click', function() {
            const docBasename = document.getElementById('docSelect').value;
            continueToPhase2A(docBasename);
        });
    }
    
    // Validación de formulario
    const form = document.getElementById('phase1bForm');
    if (form) {
        form.addEventListener('submit', function(e) {
            if (!validateForm()) {
                e.preventDefault();
                return false;
            }
        });
    }
    
    // Teclas de acceso rápido
    document.addEventListener('keydown', function(e) {
        // Ctrl+D para toggle debug
        if (e.ctrlKey && e.key === 'd') {
            e.preventDefault();
            toggleDebugPanel();
        }
        
        // Ctrl+R para reset (solo si formulario visible)
        if (e.ctrlKey && e.key === 'r' && form && form.style.display !== 'none') {
            e.preventDefault();
            resetToDefaults();
        }
    });
});
// upload.js - envía siempre el archivo completo en una única petición POST.
// Expects UPLOAD_URL injected in page and uses XMLHttpRequest to report progress.

document.addEventListener('DOMContentLoaded', () => {
  const fileInput = document.getElementById('fileInput');
  const start = document.getElementById('startUpload');
  const statusBox = document.getElementById('statusBox');
  const fileNameSpan = document.getElementById('fileName');

  if (!fileInput || !start || !statusBox) {
    console.error('upload.js: required DOM elements missing');
    return;
  }

  const uploadEndpoint = (typeof UPLOAD_URL !== 'undefined' && UPLOAD_URL) ? UPLOAD_URL : (window.location.origin + '/code/php/upload.php');

  fileInput.addEventListener('change', () => {
    const file = fileInput.files[0];
    if (!file) {
      fileNameSpan.textContent = '';
      return;
    }
    fileNameSpan.textContent = file.name;
    statusBox.style.display = 'none';
    statusBox.innerHTML = '';
  });

  function showSuccess(filename, sizeBytes, message) {
    statusBox.className = 'status-box status-success';
    statusBox.style.display = 'block';
    const kb = Math.round(sizeBytes / 1024);
    statusBox.innerHTML = '<div class="status-icon">✔</div>'
      + '<div>Subida correcta: <span style="font-weight:700;">' + escapeHtml(filename) + '</span> (' + kb + ' KB) ' 
      + (message ? '<div style="margin-top:6px;color:#1b6b2d;">' + escapeHtml(message) + '</div>' : '') + '</div>';
  }

  function showError(msg) {
    statusBox.className = 'status-box status-error';
    statusBox.style.display = 'block';
    statusBox.innerHTML = '<div class="status-icon">✖</div><div>' + escapeHtml(msg) + '</div>';
  }

  function showProgress(percent) {
    statusBox.className = 'status-box';
    statusBox.style.display = 'block';
    statusBox.innerHTML = 'Subiendo... ' + percent + '%';
  }

  function escapeHtml(s) {
    return String(s).replace(/[&<>"]/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[c]));
  }

  start.addEventListener('click', () => {
    const file = fileInput.files[0];
    if (!file) {
      showError('Selecciona un archivo PDF');
      return;
    }

    const form = new FormData();
    form.append('file', file, file.name);
    form.append('filename', file.name);

    const xhr = new XMLHttpRequest();
    xhr.open('POST', uploadEndpoint, true);
    xhr.withCredentials = true;

    xhr.upload.onprogress = function (evt) {
      if (evt.lengthComputable) {
        const percent = Math.round((evt.loaded / evt.total) * 100);
        showProgress(percent);
      }
    };

    xhr.onload = function () {
      let j = {};
      try { j = JSON.parse(xhr.responseText || '{}'); } catch (e) { j = {}; }

      if (xhr.status >= 200 && xhr.status < 300) {
        const stored = j.filepath || j.path || j.assembled || j.doc_dir || '';
        showSuccess(file.name, file.size, stored ? 'Guardado en: ' + stored : '');
      } else {
        showError('Error en subida (server): ' + (j.error || xhr.statusText || xhr.status));
      }
    };

    xhr.onerror = function () {
      showError('Error en la conexión durante la subida.');
    };

    try {
      xhr.send(form);
      showProgress(0);
    } catch (err) {
      showError('Error iniciando la subida: ' + err.message);
    }
  });
});
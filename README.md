```markdown
# ed_cfle — Scaffold inicial

Estructura generada (scaffold) para revisión y aprobación. No se ha ejecutado nada en el servidor.

Rutas importantes (tal y como confirmaste):
- Configuración: /home/plazzaxy/public_html/ed_cfle/config/
  - config.json
  - prompts.php
  - users.json
- Temporales y uploads: /home/plazzaxy/public_html/ed_cfle/tmp/
- Documentos generados (cada PDF genera un subdirectorio con mismo nombre base): /home/plazzaxy/public_html/ed_cfle/docs/
- Código: /home/plazzaxy/public_html/ed_cfle/code/
  - php, js, css

Archivos principales de este scaffold:
- .user.ini — sugerencias para PHP (upload_max_filesize=10M etc)
- config/config.json — configuración principal (API key placeholder, paths, límites)
- config/prompts.php — archivo PHP con plantillas P por fase (edición manual)
- config/users.json — usuarios (editar y generar password_hash con PHP)
- config/.htaccess — bloqueo de acceso web a ficheros de config
- code/php/* — endpoints scaffold: index, login, upload, fase, process, cleanup
- code/js/upload.js — ejemplo de cliente chunked (scaffold)
- code/css/styles.css — estilos mínimos
- README.md — este archivo

Siguientes pasos sugeridos tras aprobar el scaffold:
1. Revisar y editar config/config.json (poner apio_key y ajustar tiempo/límites).
2. Generar una contraseña con `password_hash('tuPassword', PASSWORD_DEFAULT)` y pegar el hash en config/users.json.
3. Subir los archivos al servidor en las rutas confirmadas y fijar permisos (config/*.json y users.json con 600).
4. Probar subida de un PDF pequeño y comprobar ensamblado en /docs/<basename>/.
5. Implementar la integración real con APIO en process_phase.php y la extracción via APIO en el endpoint de procesamiento (uso de chunking por texto).
6. Ajustar upload.js para enviar FormData con metadata + chunk blob en cada POST (en el scaffold el envío binario es conceptual; se completará en la integración).
7. Programar llamadas a Imagick para extraer/rasterizar imágenes en /docs/<basename>/images/ y generar el TXT final vía APIO.
8. Configurar cron (opcional) para ejecutar cleanup.php regularmente y rotar logs.

Notas de seguridad:
- Mantén config/*.json y users.json con permisos 600 y, si es posible, fuera del webroot o protegidos por .htaccess.
- Forzar HTTPS y cookie flags para sesiones.
# Configuración del Proyecto

## Archivos de configuración

1. Copia `config/config.example.json` a `config/config.json`
2. Edita `config/config.json` y reemplaza `YOUR_OPENAI_API_KEY_HERE` con tu clave API real de OpenAI
3. El archivo `config/config.json` está en `.gitignore` para mantener tus credenciales seguras

## Estructura del proyecto

- `code/` - Código PHP y JavaScript
- `config/` - Archivos de configuración
- `css/` - Estilos y recursos
- `docs/` - Documentos procesados
- `tmp/` - Archivos temporales

## Instalación

1. Configura tu servidor web para apuntar al directorio raíz
2. Asegúrate de que PHP tenga permisos de escritura en `tmp/` y `docs/`
3. Configura tu clave API de OpenAI en `config/config.json`
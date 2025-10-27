# Ed CFLE - Sistema de Procesamiento de PDFs con IA

**📝 Actualizado el 27 de octubre de 2025**

Sistema completo para procesar documentos PDF mediante inteligencia artificial, implementado para el dominio **cfle.plazza.xyz**.

## 🌐 Configuración de Dominios

- **plazza.xyz** → `/home/udnpviva/public_html`
- **cfle.plazza.xyz** → `/home/udnpviva/public_html/ed_cfle` (subdominio)

## 📁 Estructura del Servidor

```
/home/udnpviva/public_html/ed_cfle/
├── .htaccess
├── Dir+Arch.txt
├── index.php                    # Landing principal con redirección
├── php.ini
├── phpinfo.php
├── README.md
├── code/
│   ├── js/
│   │   └── upload.js           # Cliente para subida chunked de archivos
│   └── php/
│       ├── cleanup.php         # Limpieza automática de archivos temporales
│       ├── docs_list.php       # Lista de documentos procesados
│       ├── header.php          # Header común con menú y autenticación
│       ├── index.php           # Panel principal (post-login)
│       ├── lib_apio.php        # Biblioteca de utilidades y configuración
│       ├── login.php           # Sistema de autenticación
│       ├── logout.php          # Cierre de sesión
│       ├── phase.php           # Gestión de fases de procesamiento
│       ├── process_pdf.php     # Procesamiento principal de PDFs
│       ├── process_phase.php   # Procesamiento por fases con IA
│       ├── upload.php          # Endpoint para subida de archivos
│       └── upload_form.php     # Formulario de subida (Fase 1A)
├── config/
│   ├── config.json            # Configuración principal (con API keys)
│   ├── prompts.php            # Plantillas de prompts para IA
│   └── users.json             # Base de datos de usuarios
├── css/
│   ├── BeeViva_Logo_Colour.avif # Logo de la aplicación
│   └── styles.css             # Estilos principales
├── tmp/                       # Archivos temporales y uploads
└── uploads/                   # Área de subida de archivos
```

## ⚙️ Características Principales

### 🔐 Sistema de Autenticación
- Login/logout con sesiones PHP
- Gestión de usuarios mediante `users.json`
- Header dinámico con menú contextual

### 📤 Subida de Archivos
- Subida chunked para archivos grandes (hasta 10MB por defecto)
- Validación de tipos de archivo (PDFs)
- Gestión temporal segura

### 🤖 Procesamiento con IA
- Integración con OpenAI API
- Procesamiento por fases configurables
- Extracción y análisis de contenido PDF
- Generación de respuestas contextuales

### 🧹 Gestión Automática
- Limpieza de archivos temporales
- Rotación de logs
- Configuración de tiempos de retención

## 🚀 Configuración Inicial

### 1. Configurar API Key de OpenAI
```bash
# Copiar archivo de ejemplo
cp config/config.example.json config/config.json

# Editar y agregar tu API key real
nano config/config.json
```

### 2. Configurar Usuarios
```php
// Generar hash de contraseña
$hash = password_hash('tu_contraseña', PASSWORD_DEFAULT);
```

Agregar el hash generado a `config/users.json`:
```json
{
  "tu_usuario": "hash_generado_aqui"
}
```

### 3. Permisos de Archivos (Importante)
```bash
# Proteger archivos de configuración
chmod 600 config/*.json
chmod 600 config/users.json

# Permisos de escritura para directorios temporales
chmod 755 tmp/ uploads/
```

### 4. URLs de Acceso
- **Aplicación principal**: https://cfle.plazza.xyz
- **Login directo**: https://cfle.plazza.xyz/code/php/login.php
- **Panel administrativo**: https://cfle.plazza.xyz/code/php/index.php

## 🔧 Configuración Técnica

### Límites de Archivos
- **Upload máximo**: 10MB por archivo
- **Documento máximo**: 20MB procesado
- **DPI de imágenes**: 300 DPI

### Modelos de IA
- **Modelo por defecto**: gpt-5-mini
- **Temperatura**: 0.2 (respuestas consistentes)
- **Tokens máximos**: 1500 por respuesta

### Limpieza Automática
- **Archivos incompletos**: 6 horas
- **Retención de documentos**: 30 días

## 🛡️ Seguridad

- Archivos de configuración protegidos con `.htaccess`
- Validación de sesiones en todos los endpoints
- Sanitización de inputs
- URLs públicas configurables via `public_base`
- Gestión segura de archivos temporales

## 📋 Flujo de Trabajo

1. **Login** → Autenticación de usuario
2. **Subida** → Upload de PDF (Fase 1A)
3. **Procesamiento** → Análisis con IA por fases
4. **Resultados** → Visualización de documentos procesados
5. **Limpieza** → Gestión automática de recursos

## 🔄 Mantenimiento

### Limpieza Manual
```bash
php /home/udnpviva/public_html/ed_cfle/code/php/cleanup.php
```

### Logs de Sistema
Los logs se almacenan en `tmp/logs/` y se rotan automáticamente.

### Monitoreo
- Verificar espacio en disco regularmente
- Revisar logs de errores PHP
- Monitorear uso de API de OpenAI

## 📚 Documentación Técnica

### Funcionalidades del Sistema

**`index.php`** - Landing principal que redirige según estado de autenticación:
- Usuario no logueado → `/code/php/login.php`
- Usuario logueado → `/code/php/index.php`

**`code/php/header.php`** - Header unificado con:
- Logo BeeVIVA y navegación
- Menú desplegable contextual (solo para usuarios autenticados)
- Gestión de URLs públicas vía configuración

**`code/php/lib_apio.php`** - Biblioteca central que proporciona:
- Carga centralizada de configuración
- Resolución de rutas absolutas/relativas
- Utilidades para gestión de archivos

**`code/php/upload_form.php`** - Interfaz de subida (Fase 1A) con:
- Validación client-side
- Integración con `upload.js` para chunking
- Estilos card responsivos

### Estructura de Configuración

El archivo `config/config.json` centraliza:
- Credenciales API de OpenAI
- Rutas del sistema y URLs públicas
- Límites de archivos y procesamiento
- Parámetros del modelo de IA
- Configuración de limpieza automática
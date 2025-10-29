# Ed CFLE - Sistema de Procesamiento de PDFs con IA

**📝 Actualizado el 29 de octubre de 2025**

Sistema completo para procesar documentos PDF mediante inteligencia artificial, implementado para el dominio **cfle.plazza.xyz**.

## 🎯 Estado Actual del Proyecto

### ✅ **Fase 1 - Extracción de Texto**
- **Fase 1A**: Subida de archivos PDF
- **Fase 1B**: Extracción de texto mediante OpenAI API
- **Fase 1C**: Procesamiento y validación de texto extraído

### ✅ **Fase 2 - Análisis Técnico y Taxonomía** 
- **Fase 2A**: Extracción de metadatos técnicos básicos (8 campos)
- **Fase 2B**: Ampliación de metadatos técnicos (14 campos)
- **Fase 2C**: Clasificación taxonómica con catálogos CSV (22 campos)
- **Fase 2D**: Generación de ficha técnica y resumen (24 campos)

### 🔄 **Fase 3 - En Desarrollo**
- Funcionalidades adicionales según requerimientos del proyecto

## 🌐 Configuración de Dominios

- **plazza.xyz** → `/home/udnpviva/public_html`
- **cfle.plazza.xyz** → `/home/udnpviva/public_html/ed_cfle` (subdominio)

## 📁 Estructura del Servidor (Actualizada)

```
/home/udnpviva/public_html/ed_cfle/
├── .htaccess
├── index.php                    # Landing principal con redirección
├── php.ini
├── README.md
├── code/
│   ├── css/
│   │   └── phase_common.css    # Estilos comunes para fases
│   ├── js/
│   │   ├── upload.js           # Cliente para subida de archivos
│   │   ├── phase_common.js     # Funcionalidades comunes para fases
│   │   └── phase_1b.js         # Funcionalidades específicas F1B
│   └── php/
│       ├── cleanup.php         # Limpieza automática de archivos temporales
│       ├── docs_list.php       # Lista de documentos procesados
│       ├── header.php          # Header común con menú y autenticación
│       ├── index.php           # Panel principal (post-login)
│       ├── lib_apio.php        # Biblioteca de utilidades y configuración
│       ├── login.php           # Sistema de autenticación
│       ├── logout.php          # Cierre de sesión
│       ├── phase_1b.php        # Interfaz Fase 1B (extracción texto)
│       ├── phase_1b_proxy.php  # Proxy OpenAI para F1B
│       ├── phase_1c.php        # Interfaz Fase 1C
│       ├── phase_1c_proxy.php  # Proxy OpenAI para F1C
│       ├── phase_2a.php        # Interfaz Fase 2A (metadatos básicos)
│       ├── phase_2a_proxy.php  # Proxy OpenAI para F2A
│       ├── phase_2b.php        # Interfaz Fase 2B (metadatos ampliados)
│       ├── phase_2b_proxy.php  # Proxy OpenAI para F2B
│       ├── phase_2c.php        # Interfaz Fase 2C (taxonomía)
│       ├── phase_2c_proxy.php  # Proxy OpenAI para F2C
│       ├── phase_2d.php        # Interfaz Fase 2D (ficha técnica)
│       ├── phase_2d_proxy.php  # Proxy OpenAI para F2D
│       ├── proxy_common.php    # Utilidades comunes para proxies
│       ├── phase_base.php      # Clase base para fases
│       ├── phase.php           # Gestión de fases de procesamiento
│       ├── process_phase.php   # Procesamiento por fases con IA
│       ├── upload.php          # Endpoint para subida de archivos
│       └── upload_form.php     # Formulario de subida (Fase 1A)
├── config/
│   ├── config.json            # Configuración principal (con API keys y file_ids)
│   ├── prompts.php            # Plantillas de prompts para todas las fases
│   └── users.json             # Base de datos de usuarios
├── css/
│   ├── BeeViva_Logo_Colour.avif # Logo de la aplicación
│   └── styles.css             # Estilos principales
├── docs/                      # Documentos procesados con estructura:
│   └── {NB_archivo}/          #     ├── {NB_archivo}.pdf (original)
│       ├── {NB_archivo}.txt   #     ├── {NB_archivo}.txt (texto extraído)
│       ├── {NB_archivo}.json  #     ├── {NB_archivo}.json (datos procesados)
│       ├── {NB_archivo}_*.log #     └── {NB_archivo}_*.log (logs por fase)
│       └── {NB_archivo}_*.assistant_id # Assistant IDs persistentes
└── tmp/                       # Archivos temporales
    ├── logs/                  # Logs del sistema
    └── uploads/               # Uploads temporales
```

## ⚙️ Características Principales

### 🔐 Sistema de Autenticación
- Login/logout con sesiones PHP
- Gestión de usuarios mediante `users.json`
- Header dinámico con menú contextual

### 📤 Fase 1 - Procesamiento de Documentos
- **Subida de archivos PDF** con validación de tipos
- **Extracción de texto** mediante OpenAI API
- **Procesamiento y validación** de contenido extraído

### 🤖 Fase 2 - Análisis Técnico
- **Extracción de metadatos** técnicos básicos y ampliados
- **Clasificación taxonómica** usando catálogos CSV de productos
- **Generación de fichas técnicas** con resúmenes estructurados
- **Cumplimiento RAE** en español de España

### 🗂️ Gestión de Documentos
- **Estructura organizada** por nombre base del archivo
- **Logs detallados** de cada fase de procesamiento
- **Persistencia de Assistant IDs** para eficiencia
- **Archivos JSON** con datos estructurados

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

## 📋 Flujo de Trabajo Completo

### 🔄 **Pipeline de Procesamiento**
1. **Login** → Autenticación de usuario
2. **Fase 1A** → Subida de archivo PDF
3. **Fase 1B** → Extracción de texto con OpenAI
4. **Fase 1C** → Validación y procesamiento de texto
5. **Fase 2A** → Extracción de metadatos básicos (8 campos)
6. **Fase 2B** → Ampliación de metadatos técnicos (14 campos)
7. **Fase 2C** → Clasificación taxonómica (22 campos)
8. **Fase 2D** → Generación de ficha técnica y resumen (24 campos)

### � **Arquitectura de Fases**
- Cada fase usa **OpenAI Assistants API v2** con **code_interpreter**
- **Polling inteligente** con timeout de 60 segundos
- **Persistencia de Assistant IDs** para reutilización
- **Timeline y Debug HTTP** en todas las interfaces
- **Validación JSON** estricta según schemas definidos

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

### Arquitectura General
- **Proxies especializados** para cada fase con arquitectura común
- **OpenAI Assistants API v2** con code_interpreter
- **Prompts centralizados** en `config/prompts.php`
- **Configuración unificada** en `config/config.json`

### Sistema de Archivos
- **PDF original**: `/docs/{NB_archivo}/{NB_archivo}.pdf`
- **Texto extraído**: `/docs/{NB_archivo}/{NB_archivo}.txt`
- **Datos procesados**: `/docs/{NB_archivo}/{NB_archivo}.json`
- **Logs por fase**: `/docs/{NB_archivo}/{NB_archivo}_2A.log`, `_2B.log`, etc.
- **Assistant IDs**: `/docs/{NB_archivo}/{NB_archivo}_2A.assistant_id`, etc.

### Configuración
El archivo `config/config.json` centraliza:
- **Credenciales API** de OpenAI
- **File IDs** de catálogos CSV (productos y taxonomía)
- **Rutas del sistema** y URLs públicas
- **Límites de archivos** y procesamiento
- **Parámetros por defecto** del modelo de IA

### Prompts
El archivo `config/prompts.php` contiene:
- **Plantillas estructuradas** para todas las fases
- **Schemas JSON** de validación
- **Instrucciones RAE** para español de España
- **Reglas de extracción** y clasificación
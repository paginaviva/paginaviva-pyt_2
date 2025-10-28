# Ed CFLE - Sistema de Procesamiento de PDFs con IA

**📝 Actualizado el 28 de octubre de 2025**

Sistema completo para procesar documentos PDF mediante inteligencia artificial, implementado para el dominio **cfle.plazza.xyz**.

## 🎯 Estado Actual del Proyecto

### ✅ **Fase 1B - COMPLETAMENTE FUNCIONAL**
Sistema de extracción de texto PDF usando OpenAI API totalmente operativo:

- 🔄 **Extracción PDF → TXT** mediante OpenAI Chat Completions API
- 💾 **Guardado automático** en `/docs/{NB_archivo}/{NB_archivo}.txt`
- 📝 **Logs completos** en `/docs/{NB_archivo}/{NB_archivo}.log`
- 🚀 **Botón "Continuar a Fase 2A"** para flujo de trabajo
- 🐛 **Debug avanzado** con análisis de respuestas OpenAI
- ⚡ **Parámetros optimizados** (4000 max_completion_tokens)

### 🔄 **Próximas Fases**
- **Fase 2A**: Análisis y procesamiento del texto extraído
- **Fases adicionales**: Según requerimientos del proyecto

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
│   ├── js/
│   │   ├── upload.js           # Cliente para subida de archivos
│   │   └── phase_1b.js         # Funcionalidades avanzadas F1B
│   └── php/
│       ├── cleanup.php         # Limpieza automática de archivos temporales
│       ├── docs_list.php       # Lista de documentos procesados
│       ├── header.php          # Header común con menú y autenticación
│       ├── index.php           # Panel principal (post-login)
│       ├── lib_apio.php        # Biblioteca de utilidades y configuración
│       ├── login.php           # Sistema de autenticación
│       ├── logout.php          # Cierre de sesión
│       ├── phase_1b.php        # 🆕 Interfaz moderna Fase 1B
│       ├── phase_1b_proxy.php  # 🆕 Proxy OpenAI para extracción PDF
│       ├── proxy_common.php    # 🆕 Utilidades comunes para proxies
│       ├── phase.php           # Gestión de fases de procesamiento
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
├── docs/                      # 🆕 Documentos procesados con estructura:
│   └── {NB_archivo}/          #     ├── {NB_archivo}.pdf (original)
│       ├── {NB_archivo}.txt   #     ├── {NB_archivo}.txt (extraído)
│       └── {NB_archivo}.log   #     └── {NB_archivo}.log (proceso)
└── tmp/                       # Archivos temporales
    ├── logs/                  # Logs del sistema
    └── uploads/               # Uploads temporales
```

## ⚙️ Características Principales

### 🔐 Sistema de Autenticación
- Login/logout con sesiones PHP
- Gestión de usuarios mediante `users.json`
- Header dinámico con menú contextual

### 📤 Subida de Archivos (Fase 1A)
- Subida de archivos PDF (hasta 10MB por defecto)
- Validación de tipos de archivo
- Gestión temporal segura
- Integración automática con Fase 1B

### 🤖 **Sistema F1B - Extracción PDF con IA**
- **OpenAI Chat Completions API** para extracción de texto
- **Arquitectura Proxy** moderna con debug completo
- **Guardado automático** de archivos `.txt` y `.log`
- **Interfaz moderna** con timeline y debug HTTP
- **Parámetros configurables** (modelo, temperatura, tokens)
- **Botón "Continuar a Fase 2A"** para flujo de trabajo

### 🗂️ Gestión de Documentos
- **Estructura organizada** por nombre base del archivo
- **Logs detallados** de cada proceso
- **Lista de documentos** procesados
- **Descarga de archivos** generados

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

### 🔄 **Proceso F1B Actual (Funcional)**
1. **Login** → Autenticación de usuario
2. **Subida PDF (Fase 1A)** → Upload de archivo PDF
3. **Botón "Generar .TXT (F1B)"** → Redirige a interfaz F1B
4. **Configurar parámetros OpenAI** → Modelo, temperatura, tokens
5. **Procesamiento automático**:
   - Descarga PDF del servidor
   - Subida a OpenAI Files API
   - Extracción via Chat Completions API
   - Guardado automático de `.txt` y `.log`
6. **Resultados**:
   - Texto extraído en interfaz
   - Botón "Continuar a Fase 2A"
   - Botón "Ver Archivos Generados"

### 🚀 **Próximos Pasos**
- **Fase 2A**: Análisis del texto extraído
- **Integración completa** del flujo de fases
- **Expansión funcionalidades** según necesidades

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

## 📚 Documentación Técnica Actualizada

### Sistema F1B - Arquitectura Proxy

**`code/php/phase_1b.php`** - Interfaz moderna Fase 1B:
- Selección de documento desde URL (`?doc=NB_archivo`)
- Configuración de parámetros OpenAI (modelo, temperatura, tokens)
- Timeline de ejecución en tiempo real
- Debug HTTP completo con análisis de respuestas
- Resultados con acciones (copiar, descargar, ver como archivo)
- Botones de navegación a Fase 2A

**`code/php/phase_1b_proxy.php`** - Proxy especializado OpenAI:
- Validación de entrada y NB del archivo
- Descarga segura de PDF desde servidor
- Subida a OpenAI Files API (purpose: 'assistants')
- Extracción via Chat Completions API
- Guardado automático de archivos `.txt` y `.log`
- Debug completo del proceso

**`code/php/proxy_common.php`** - Utilidades comunes para proxies:
- Clase `ProxyRuntime` para gestión de timeline y debug
- Métodos para fetch, upload y API calls
- Extracción inteligente de texto de respuestas OpenAI
- Formato de respuesta estandarizado

### Funcionalidades del Sistema Base

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
- Funciones de logging para fases

**`code/php/upload_form.php`** - Interfaz de subida (Fase 1A) con:
- Validación client-side
- Integración con `upload.js`
- Botón automático "Generar .TXT (F1B)" después de subida exitosa
- Estilos card responsivos

### Estructura de Configuración

El archivo `config/config.json` centraliza:
- **Credenciales API** de OpenAI (`apio_key`)
- **Rutas del sistema** y URLs públicas (`public_base`)
- **Límites de archivos** y procesamiento
- **Parámetros por defecto** del modelo de IA (`apio_defaults`)
- **Configuración de limpieza** automática

Ejemplo de configuración F1B:
```json
{
  "apio_key": "sk-proj-TU_API_KEY_REAL_AQUI",
  "apio_models": ["gpt-4o-mini", "gpt-4o", "gpt-5-mini"],
  "apio_defaults": {
    "model": "gpt-4o-mini",
    "temperature": 0.1,
    "max_tokens": 4000,
    "top_p": 1.0
  },
  "docs_dir": "docs",
  "tmp_dir": "tmp",
  "public_base": "https://cfle.plazza.xyz"
}
```

## 🔧 Configuración Técnica F1B

### Parámetros OpenAI Optimizados
- **Modelo recomendado**: gpt-4o-mini (balance costo/calidad)
- **Max completion tokens**: 4000 (PDFs largos)
- **Temperatura**: 0.1 (extracción consistente)
- **Top P**: 1.0 (máxima precisión)

### Archivos Generados
- **Texto extraído**: `/docs/{NB_archivo}/{NB_archivo}.txt`
- **Log del proceso**: `/docs/{NB_archivo}/{NB_archivo}.log`
- **PDF original**: `/docs/{NB_archivo}/{NB_archivo}.pdf`

### Debug y Monitoreo
- **Timeline completo** de ejecución
- **Debug HTTP** con análisis de respuestas OpenAI
- **Logs estructurados** con metadatos del proceso
- **Información de uso** de tokens y costos
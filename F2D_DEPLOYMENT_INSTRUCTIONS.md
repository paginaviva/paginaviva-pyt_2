# 📄 INSTRUCCIONES DE DESPLIEGUE - FASE 2D

## ✅ ESTADO ACTUAL

Los archivos de la Fase 2D han sido completamente regenerados siguiendo **EXACTAMENTE** la estructura de F2C (Fase 2C), que está funcionando perfectamente.

### Archivos Creados/Actualizados:

1. **`config/prompts.php`** ✅
   - Prompt `p_generate_technical_sheet` ya existe
   - Genera `ficha_tecnica` y `resumen_tecnico` (máx. 300 chars)
   
2. **`code/php/phase_2d_proxy.php`** ✅ (765 líneas)
   - Backend que procesa la generación de ficha técnica
   - Valida JSON de F2C (22 campos de entrada)
   - Genera JSON de F2D (24 campos de salida)
   
3. **`code/php/phase_2d.php`** ✅ (274 líneas) **RECIÉN REGENERADO**
   - Frontend clonado de `phase_2c.php`
   - Estructura **IDÉNTICA** a F2C
   - Sin errores de sintaxis PHP

---

## 🚀 INSTRUCCIONES DE DESPLIEGUE AL SERVIDOR FTP

### Paso 1: Copiar `phase_2d.php` al FTP

1. Abre el archivo en el repositorio:
   ```
   /workspaces/paginaviva-pyt_2/code/php/phase_2d.php
   ```

2. Copia **TODO EL CONTENIDO** del archivo (274 líneas completas)

3. En tu cliente FTP, navega a:
   ```
   /code/php/
   ```

4. Sobrescribe el archivo existente `phase_2d.php` con el nuevo contenido

### Paso 2: Verificar que `phase_2d_proxy.php` también esté desplegado

1. Verifica que el archivo `phase_2d_proxy.php` (765 líneas) también esté en el FTP:
   ```
   /code/php/phase_2d_proxy.php
   ```

2. Si no está, copia el archivo desde:
   ```
   /workspaces/paginaviva-pyt_2/code/php/phase_2d_proxy.php
   ```

### Paso 3: Verificar `config/prompts.php`

1. Asegúrate de que el archivo `prompts.php` en el FTP contenga el prompt de F2D:
   ```
   /config/prompts.php
   ```

2. Debe incluir la entrada `$PROMPTS[2]['p_generate_technical_sheet']`

---

## 🧪 PRUEBA DESPUÉS DEL DESPLIEGUE

### URL de Prueba:
```
https://cfle.plazza.xyz/code/php/phase_2d.php?doc=A30XHA_FICHA
```

### ✅ Verificaciones Esperadas:

1. **La página debe cargar** sin el error "Documento no especificado"
   - Si aparece este error → El archivo no se desplegó correctamente al FTP

2. **Debe mostrar:**
   - Título: "📄 Fase 2D — Ficha Técnica y Resumen"
   - Información del documento: A30XHA_FICHA
   - JSON actual de F2C (22 campos)
   - Botón: "🚀 Generar Ficha Técnica"

3. **Al hacer clic en el botón:**
   - Debe aparecer "🔄 Generando ficha técnica y resumen..."
   - NO debe aparecer error HTML ("El servidor devolvió HTML...")
   - Debe mostrar Timeline de ejecución
   - Al completar, debe mostrar:
     * JSON expandido a 24 campos
     * Ficha técnica completa
     * Resumen técnico (con contador de caracteres)

---

## ⚠️ SOLUCIÓN DE PROBLEMAS

### Problema 1: "Documento no especificado"
**Causa:** El archivo `phase_2d.php` en el FTP es una versión antigua  
**Solución:** Asegúrate de copiar el archivo COMPLETO (274 líneas)

### Problema 2: "El servidor devolvió HTML en lugar de JSON"
**Causa:** El archivo `phase_2d_proxy.php` no está en el FTP o tiene errores  
**Solución:** Despliega también `phase_2d_proxy.php` (765 líneas)

### Problema 3: Campos vacíos en la ficha técnica
**Causa:** El prompt no está correctamente configurado  
**Solución:** Verifica que `config/prompts.php` tenga el prompt completo

---

## 📊 DIFERENCIAS CLAVE F2C → F2D

| Aspecto | F2C | F2D |
|---------|-----|-----|
| **Título** | Clasificación Taxonómica | Ficha Técnica y Resumen |
| **Validación** | JSON F2B (14 campos) | JSON F2C (22 campos) |
| **Proxy** | `phase_2c_proxy.php` | `phase_2d_proxy.php` |
| **Campos de entrada** | 14 | 22 |
| **Campos de salida** | 18 | 24 |
| **Nuevos campos** | grupos_de_soluciones, familia, categoria | ficha_tecnica, resumen_tecnico |
| **Botón siguiente** | "Continuar a Fase 2D" | No hay (F2D es terminal) |

---

## 🎯 RESULTADO ESPERADO

Después del despliegue y ejecución exitosa de F2D:

### JSON Final (24 campos):
```json
{
  // ... 22 campos existentes de F2C ...
  "ficha_tecnica": "Descripción técnica completa del producto...",
  "resumen_tecnico": "Resumen conciso de máximo 300 caracteres"
}
```

### Flujo Completo de Fase 2:
```
F2A → F2B → F2C → F2D ✅
10 → 14 → 22 → 24 campos
```

---

## 📝 NOTAS IMPORTANTES

1. **El archivo en el repositorio está correcto** (verificado sin errores de sintaxis)
2. **La estructura es IDÉNTICA a F2C** (que funciona perfectamente)
3. **Solo cambian los textos y campos específicos** de F2D
4. **NO se requieren más modificaciones** en el código

---

**Fecha de generación:** $(date)  
**Archivo validado:** ✅ Sin errores de sintaxis PHP  
**Estado:** Listo para desplegar

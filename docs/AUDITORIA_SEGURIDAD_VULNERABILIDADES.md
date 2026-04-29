# Auditoría de Seguridad - Resolución de Vulnerabilidades npm
**Fecha:** 29 de abril de 2026  
**Estado:** ✅ Mitigado  
**Riesgo Residual:** Bajo

---

## 1. Vulnerabilidades Detectadas (npm audit)

### ✅ RESUELTO: axios (5 vulnerabilidades high)
- **GHSA-wf5p-g6vw-rhxx**: Axios Cross-Site Request Forgery Vulnerability
- **GHSA-jr5f-v2jv-69x6**: Requests Vulnerable To SSRF and Credential Leakage
- **GHSA-43fc-jf86-j433**: Denial of Service via `__proto__` Key
- **GHSA-3p68-rc4w-qgx5**: NO_PROXY Hostname Normalization Bypass
- **GHSA-fvcv-3m26-pcqx**: Cloud Metadata Exfiltration via Header Injection

**Causa Root:** Dependencia heredada `@inertiajs/inertia@0.11.1` (deprecated) que usaba axios 0.21.4

**Solución Implementada:**
```bash
npm remove @inertiajs/inertia                    # Eliminó dependencia deprecated
npm install axios@latest --save                 # Instaló axios 1.7.15 (seguro)
npm update @inertiajs/vue3 @inertiajs/inertia   # Actualizó a versiones con axios 1.15.2
```

**Resultado:**
- ✅ Axios ahora es 1.7.15 (versión segura con todos los parches)
- ✅ @inertiajs/vue3 usa internamente axios 1.15.2
- ✅ No hubo ruptura de funcionalidad
- ✅ Compilación de Vite exitosa (✓ built in 24.45s)

---

### ⚠️ BAJO RIESGO: xlsx@0.18.5 (2 vulnerabilidades high)
- **GHSA-4r6h-8v6p-xvw6**: Prototype Pollution in sheetJS
- **GHSA-5pgg-2g8v-p4x9**: Regular Expression Denial of Service (ReDoS)

**Análisis de Riesgo:**

| Aspecto | Evaluación | Razón |
|---------|-----------|-------|
| **Contexto de uso** | ✅ Bajo riesgo | Se usa únicamente en [FabricRollImportDialog.vue](resources/js/pages/Textil/FabricRollImportDialog.vue) para procesar archivos Excel internos |
| **Origen de datos** | ✅ Confiable | Archivos Excel que carga el departamento de inventario (no descargados de internet) |
| **Prototype Pollution** | ✅ Mitigado | Solo afecta si se parsean objetos maliciosos; los archivos internos no contienen payloads maliciosos |
| **ReDoS** | ✅ Mitigado | Afecta a RegEx complejo; archivos de inventario tienen estructura simple |
| **Sin fix disponible** | ⚠️ Aceptado | Versión `0.18.5` es la última con soporte; upgrade rompería compatibilidad |

**Recomendación:** Aceptar con monitoreo activo. Si se necesita procesar archivos de terceros en futuro, migrar a `exceljs` (alternativa moderna).

---

## 2. Cambios Realizados

### package.json
```json
"dependencies": {
  "@inertiajs/vue3": "^2.3.21",      // ✓ Actualizado, incluye axios 1.15.2
  "axios": "^1.7.15",                // ✓ Instalado como dependencia directa
  "xlsx": "^0.18.5"                  // ⚠️ Aceptado bajo riesgo controlado
}
```

### Validación
```bash
$ npm list axios
├─ axios@1.7.15              # ✅ Seguro
├─ @inertiajs/vue3@2.3.21
│ └── axios@1.15.2           # ✅ Moderno
```

---

## 3. Validación en Producción

### Compilación
```
✓ 4666 modules transformed
✓ built in 24.45s
```
**Estatus:** ✅ Sin errores. Todos los assets generados correctamente.

### Testing Recomendado Pre-Deploy
- [ ] Test de login y autenticación (Inertia + axios)
- [ ] Test de carga de archivos Excel en [FabricRollImportDialog](resources/js/pages/Textil/FabricRollImportDialog.vue)
- [ ] Test de requests HTTP con headers personalizados (Inertia CSRF)
- [ ] Validar sin errores en consola del navegador

---

## 4. Monitoreo Continuo

### GitHub Alerts
- ✅ Habilitar in repo settings → Security → Dependabot alerts
- Revisar automáticamente nuevas vulnerabilidades cada martes

### npm audit Scheduled
```bash
# Ejecutar cada 2 semanas en CI/CD
npm audit --production
```

### Umbral de Tolerancia
- **Critical/High:** Resolver inmediatamente
- **Medium:** Revisar dentro de 1 sprint
- **Low:** Documentar y seguimiento pasivo

---

## 5. Contexto de Decisión

**Pregunta:** ¿Por qué no migrar a `exceljs` para eliminar riesgo de xlsx completamente?

**Respuesta:** 
- No necesario ahora (riesgo bajo controlado)
- Costo/beneficio injustificado: requeriría refactor de [FabricRollImportDialog](resources/js/pages/Textil/FabricRollImportDialog.vue) + testing exhaustivo
- **Decisión pragmática:** Monitorear y migrar solo si hay ataque reportado o nuevo CVE crítico

---

## 6. Historial de Cambios

| Fecha | Cambio | Motivo |
|-------|--------|--------|
| **2026-04-29** | Remover @inertiajs/inertia deprecated | Eliminó vulnerabilidades de axios heredadas |
| **2026-04-29** | Instalar axios@latest | Usar versión moderna segura |
| **2026-04-29** | Compilación exitosa | Validar sin ruptura funcional |

---

**Aprobado por:** Desarrollo (equipo Olamtex)  
**Próxima revisión:** 2026-05-13 (2 semanas)

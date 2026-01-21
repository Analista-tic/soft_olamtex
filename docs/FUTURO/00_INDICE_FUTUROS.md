# 📑 Índice - Documentación Futura

## 🔮 Planes para Desarrollos Posteriores

Esta carpeta contiene documentación de **módulos y mejoras planificadas** pero **no implementadas aún** en Soft Olamtex.

---

## 📚 Archivos Futuros

### 1. **[ANALISIS_LISTADO_OPERACIONAL.md](ANALISIS_LISTADO_OPERACIONAL.md)**

**Módulo:** Listados Operacionales para Confección Textil  
**Estado:** 🔵 Análisis completado | Implementación pendiente  
**Fecha:** 13 de diciembre de 2025  

**Propósito:**
- Capturar tiempos de confección (muestra y productivo)
- Generar catálogo reutilizable de operaciones
- Calcular costos y productividad automáticamente
- Agrupar por secciones de prenda
- Generar reportes tipo Excel

**Componentes Principales:**
- **Machine**: Catálogo de máquinas con factores de suplemento
- **Operation**: Catálogo reutilizable de operaciones
- **ProductOperationList**: Registro universal (muestra y productivo)

**Tabla Única vs Separada:**
- ✅ Decisión: **Una tabla** (`product_operation_lists`) para ambos tipos
- Razón: Reutilización perfecta sin duplicación

**Estimado de Implementación:** 16-20 horas (6 fases)

**Lectura Recomendada:**
- Si necesita entender el diseño: Leer secciones "Arquitectura Propuesta" y "Estructura de Datos Optimizada"
- Si va a implementar: Leer sección "Plan de Implementación" (6 fases)

---

### 2. **[REFACTOR_FRONTEND_TYPING.md](REFACTOR_FRONTEND_TYPING.md)**

**Módulo:** Refactor Frontend con TypeScript y Bounded Contexts  
**Estado:** 🔵 Análisis completado | Implementación pendiente  
**Fecha:** 30 de diciembre de 2025  

**Propósito:**
- Mejorar arquitectura frontend de Vue 3 + TypeScript
- Implementar **Bounded Contexts** inspirados en DDD
- Introducir patrón **DTO → Mapper → UI Model**
- Eliminar `any` y lograr 100% type-safe
- Facilitar mantenimiento y escalabilidad

**Patrón Clave:** DTO → Mapper → UI Model

**Estructura Propuesta:**
```
resources/js/domain/
├── auth/
├── shared/
├── inventory/
├── purchases/
├── fabric/
└── product-sheet/
```

**Beneficios:**
- Si API cambia, solo actualizar DTO + mapper
- Resto del código sigue igual (desacoplamiento)
- Type-safe completo (elimina `any`)
- Modular y escalable

**Estimado de Implementación:** 8-12 horas (6 fases)

**Lectura Recomendada:**
- Si necesita entender el patrón: Leer "Patrón Clave: DTO → Mapper → UI Model"
- Si va a implementar: Leer "Plan de Implementación" (6 fases)

---

## 🎯 Priorización

### Crítico para el Negocio
1. **Listados Operacionales** (ANALISIS_LISTADO_OPERACIONAL.md)
   - Impacto: Alto (genera reportes de costos/productividad)
   - Complejidad: Alta
   - Dedicar tiempo después de estabilizar módulos actuales

### Técnico/Mantenibilidad
2. **Refactor Frontend Typing** (REFACTOR_FRONTEND_TYPING.md)
   - Impacto: Medio (mejora DX, escalabilidad)
   - Complejidad: Media
   - Puede hacerse en paralelo a desarrollo de features

---

## 📋 Cómo Usar Esta Documentación

### Para Project Manager
- Revisar "Estimado de Implementación" de cada módulo
- Usar para planning de sprints futuros
- Considerar priorización según necesidades del negocio

### Para Desarrollador
- Leer documento completo antes de iniciar implementación
- Seguir plan de fases propuesto
- Validar cada fase con checklist incluido
- Reportar cualquier desviación

### Para QA/Testing
- Leer sección de "Casos de Uso" en cada módulo
- Usar checklist para validación
- Preparar test cases según flujos descritos

---

## 🔄 Transición de Futuro → Activo

Cuando se apruebe implementar un módulo futuro:

1. **Mover documentación** de `FUTURO/` a raíz de `docs/`
2. **Actualizar README.md** (índice principal)
3. **Crear branch** para desarrollo
4. **Seguir plan de fases** del módulo
5. **Documentar progreso** conforme avance implementación

---

## 📚 Referencias

- **Documentación Principal:** Ver [../README.md](../README.md)
- **Arquitectura del Proyecto:** Ver `.github/copilot-instructions.md`
- **Módulos Implementados:** Ver [../ARQUITECTURA_COMPRAS_REFERENCIA.md](../ARQUITECTURA_COMPRAS_REFERENCIA.md)

---

**Última actualización:** 16 de enero de 2026  
**Estado:** 🔵 Planificación Completada

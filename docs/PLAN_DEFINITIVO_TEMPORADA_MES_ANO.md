# Plan definitivo: normalización Mes/Año en Production Orders

## Objetivo
Cerrar de forma definitiva el manejo de temporada y despacho para que deje de depender de strings libres y pase a un modelo canónico, auditable y mantenible en base de datos relacional.

## Alcance
- Módulo: Producción (Programador, ProductionOrder, BatchControl, dashboards y filtros asociados).
- Entidad principal: `production_orders`.
- Estrategia: migración incremental con compatibilidad temporal (dual-write + dual-read controlado).

## Principios de diseño (best practice)
- Fuente de verdad: columnas estructuradas en DB, no parsing ad-hoc en frontend.
- Compatibilidad controlada: durante transición se mantienen campos legacy para evitar ruptura operativa.
- Trazabilidad: toda inferencia de backfill debe quedar documentada y auditable.
- Riesgo mínimo: despliegue por fases, con rollback simple en cada etapa.

---

## 1) Modelo de datos canónico (DB)

Agregar en `production_orders`:
- `season_month` TINYINT UNSIGNED NULL (1..12)
- `season_year` SMALLINT UNSIGNED NULL
- `dispatch_month_num` TINYINT UNSIGNED NULL (1..12)
- `dispatch_year` SMALLINT UNSIGNED NULL

### Reglas de integridad sugeridas
- Validar rango de meses en capa de aplicación y, si el motor lo permite, `CHECK (1..12)`.
- Índices recomendados:
  - `(season_year, season_month)`
  - `(dispatch_year, dispatch_month_num)`
  - `(status, season_year, season_month)` para listados operativos frecuentes.

### Campos legacy (se mantienen temporalmente)
- `season` (string)
- `dispatch_month` (string)

---

## 2) Backfill y migración de históricos

## 2.1 Parsing base
Convertir `season` y `dispatch_month` actuales a formato numérico:
- Mes por diccionario canónico (`ENERO`..`DICIEMBRE` -> `1..12`).
- Año explícito si viene en string (`"ENERO 2027"`).

## 2.2 Regla de inferencia cuando falta año
Orden de prioridad para inferir año:
1. `estimated_delivery_date` (año de la fecha).
2. Si no existe, `created_at`.
3. Si persiste ambigüedad de negocio, marcar como pendiente de revisión.

## 2.3 Casos ambiguos
Se consideran ambiguos, entre otros:
- Mes sin año y sin `estimated_delivery_date`.
- String de mes no reconocible.
- Conflicto entre string y fecha (ejemplo: `"DICIEMBRE"` con fecha en enero de año no esperado por regla definida).

## 2.4 Trazabilidad de backfill
Crear tabla de control recomendada: `production_order_temporal_migration_logs` con:
- `production_order_id`
- `legacy_season`, `legacy_dispatch_month`
- `resolved_season_month`, `resolved_season_year`
- `resolved_dispatch_month_num`, `resolved_dispatch_year`
- `resolution_source` (`explicit`, `estimated_delivery_date`, `created_at`, `manual`)
- `is_ambiguous` (bool)
- `notes`
- `resolved_by_user_id` (nullable)
- timestamps

Esto permite auditoría completa y revisión manual posterior.

---

## 3) Escritura dual temporal (transición)

Durante la transición, en `store` y `update`:
- Se reciben/selectores en formato Mes Año.
- Se escriben SIEMPRE columnas canónicas (`season_month`, `season_year`, `dispatch_month_num`, `dispatch_year`).
- Se sigue escribiendo legacy (`season`, `dispatch_month`) para compatibilidad.

### Regla de sincronización
- Legacy se deriva del canónico, no al revés, en operaciones nuevas/editadas.
- Formato legacy estándar temporal: `"MES YYYY"`.

---

## 4) Lectura prioritaria desde columnas nuevas

Actualizar consultas de:
- Listado Programador.
- Dashboard de distribución.
- Filtros por temporada/despacho.
- Cálculo de adelanto y fechas relacionadas.

### Política de lectura
1. Priorizar columnas canónicas.
2. Solo si faltan, aplicar fallback legacy parseado (transitorio).
3. Registrar métricas de uso de fallback para medir avance de migración.

---

## 5) UI única y explícita

Estandarizar UI en todas las pantallas:
- Selectores `Mes Año` obligatorios.
- Sin entrada de texto libre para temporada/despacho.
- Filtros consistentes con opciones explícitas:
  - `Mes Año`
  - `Sin temporada`
  - `Sin mes despacho`

La UI ya está parcialmente avanzada en Create/Edit/Index; completar cobertura total y eliminar variantes antiguas.

---

## 6) Limpieza final (deprecación legacy)

Cuando métricas indiquen cobertura completa:
1. Desactivar fallback legacy en lectura.
2. Dejar de persistir `season` y `dispatch_month`.
3. Ejecutar migración final para remover/archivar campos legacy (según política del negocio).

---

## Plan de ejecución por fases

## Fase A — Preparación técnica
- Crear migración de columnas canónicas + índices.
- Crear tabla de logs de migración temporal.
- Agregar casts/fillable en modelo `ProductionOrder`.

**Entregable:** esquema listo sin cambiar comportamiento actual.

## Fase B — Backfill controlado
- Implementar comando Artisan idempotente:
  - parsea legacy,
  - infiere año según reglas,
  - marca ambiguos,
  - registra todo en tabla de logs.
- Ejecutar en entorno de prueba y validar muestra.

**Entregable:** >95% registros resueltos automáticamente (meta sugerida), ambiguos listados.

## Fase C — Escritura dual
- Ajustar servicios/controladores para guardar canónico + legacy.
- Mantener contratos API actuales.

**Entregable:** nuevos registros completos en canónico.

## Fase D — Lectura prioritaria canónica
- Migrar filtros, dashboards y reglas de negocio a columnas estructuradas.
- Mantener fallback temporal legacy.

**Entregable:** funcionalidad operativa usa canónico por defecto.

## Fase E — Cierre y deprecación
- Resolver manualmente ambiguos pendientes.
- Medir fallback en cero por periodo acordado.
- Retirar dependencias legacy y planear migración final de limpieza.

**Entregable:** operación estable sin strings libres como fuente de verdad.

---

## Criterios de aceptación
- Todos los nuevos/actualizados `production_orders` guardan mes/año en columnas canónicas.
- Filtros y dashboard no dependen de parseo de string para casos nuevos.
- Existe reporte de ambiguos con trazabilidad y responsable de resolución.
- No hay regresiones en creación/edición/listado/edición masiva.

---

## Riesgos y mitigaciones
- Riesgo: inferencia incorrecta de año en históricos.
  - Mitigación: tabla de logs + bandera de ambiguo + revisión manual.
- Riesgo: discrepancia canónico vs legacy durante transición.
  - Mitigación: legacy derivado del canónico y validaciones en servicio.
- Riesgo: ruptura de filtros existentes.
  - Mitigación: dual-read temporal + pruebas funcionales por módulo.

---

## QA mínimo recomendado
- Pruebas unitarias/integración de parser de mes/año.
- Casos de backfill con:
  - año explícito,
  - año inferido por `estimated_delivery_date`,
  - año inferido por `created_at`,
  - ambiguo.
- Pruebas funcionales de UI/filtros en Programador, ProductionOrder y BatchControl.

---

## Secuencia sugerida de ejecución real
1. Implementar Fase A (migraciones + modelo).
2. Implementar Fase B (comando backfill + reporte ambiguos).
3. Validar con negocio ambiguos críticos.
4. Implementar Fase C y D en un mismo ciclo controlado.
5. Monitorear 1 a 2 ciclos operativos.
6. Ejecutar Fase E (deprecación legacy).

---

## Nota de gobernanza
Este plan cumple la regla del proyecto: datos de negocio críticos deben persistir en estructura relacional y no depender únicamente de frontend ni campos no estructurados.

# Estado y trazabilidad del proyecto

**Última actualización:** 2026-08-29
**Para qué sirve:** documento vivo. Aquí se registra en qué fase va la implementación, qué se está trabajando, y los hechos técnicos ya verificados contra el código. Se actualiza en cada cambio funcional, bug o mejora.

> **Si eres un agente (Claude, Copilot) retomando el trabajo:** lee primero la sección [Hechos técnicos verificados](#hechos-técnicos-verificados). Son cosas que ya se investigaron en el código fuente y no hace falta volver a derivar. Si algo de ahí resulta falso, corrígelo en este archivo en el mismo cambio.

---

## 1. Plan de implementación por fases

Se implementa **un módulo completo a la vez**, no un poco de todos. Un módulo a medias no se puede usar en la operación diaria; uno completo sí, aunque sea pequeño. Además acota el riesgo y permite validar con el área responsable antes de seguir.

| # | Fase | Alcance | Depende de | Responsable de validar | Estado |
|---|------|---------|------------|------------------------|--------|
| 0 | Desbloqueo | Corregir el error de `/listados-operacionales/bulk` | — | — | Pendiente |
| 1 | Control de Rollos | CRUD, kardex, ajustes, descargue, cálculo de piezas cortadas | Carga inicial de datos | Logística | Pendiente |
| 2 | Control de Inventario | Bodegas, stock, movimientos, traslados, solicitudes internas | Fase 1 | Logística | Pendiente |
| 3 | Control de Lotes | **Solo proceso de Corte.** Diseño y demás procesos quedan fuera | Fase 2 | Corte | Pendiente |
| 4 | Gestión de Compras | Flujo completo + recepción automática de rollos | Fase 1 | Contabilidad | Pendiente |
| 5+ | Ventas, Finanzas, Reportes, Configuración | Por definir | — | — | No planeado |

### Decisiones de alcance ya tomadas

- **Fase 1 arranca sin depender de Producción.** `FabricRoll` no tiene FK hacia `ProductionOrder` ni `TechnicalSheet`, así que Rollos se puede estabilizar solo. La única atadura real es la recepción desde factura de compra, que se difiere a la Fase 4.
- **Fase 3 simplifica el tiempo de confección a un solo número por orden.** El listado operacional completo (SAM por operación, factores, historial) es un módulo aparte y no entra en esta fase.
- **`PROVEEDOR` y `N° PEDIDO` de la plantilla de rollos no se llenan en la carga inicial.** El importador manual no los guarda; se completan solos cuando el rollo entra desde una compra real (Fase 4).

---

## 2. Registro de trabajo

Formato: una fila por cambio. Tipos: `BUG` · `FUNCIONAL` · `MEJORA` · `DEUDA` · `DOC`.
Estados: `Abierto` · `En curso` · `Resuelto` · `Descartado`.

| Fecha | Tipo | Fase | Descripción | Estado |
|-------|------|------|-------------|--------|
| 2026-08-29 | DOC | — | Se crea este documento de trazabilidad | Resuelto |
| 2026-08-29 | DOC | — | `CLAUDE.md` ahora referencia las reglas de ingeniería de `.github/copilot-instructions.md` | Resuelto |
| 2026-08-29 | MEJORA | — | Skill `agregar-funcionalidad` con checklist obligatorio (incluye regla nueva de pruebas mínimas) | Resuelto |
| 2026-08-29 | DOC | 1 | Plantillas de carga inicial generadas y verificadas contra el código (`docs/plantillas/`) | Resuelto |
| 2026-08-29 | BUG | 0 | `/listados-operacionales/bulk` responde 422/500. Causa por confirmar — ver hecho técnico #4 | Abierto |
| 2026-08-29 | DEUDA | — | `Documentacion.md` §5.5 cita migraciones `2026_06_22_000001/2` que no existen en el repo | Abierto |
| 2026-09-27 | FUNCIONAL | 4 | Recepción de telas: aprobación por rollo, motivo obligatorio, rollos restringidos, nota crédito enlazada con baja, retención de pago por monto y asiento contable proporcional. Ver [RECEPCION_ROLLOS_TOLERANCIA_Y_NOTAS_CREDITO.md](RECEPCION_ROLLOS_TOLERANCIA_Y_NOTAS_CREDITO.md). Falta aplicar migraciones y permiso en desarrollo | En curso |
| 2026-09-27 | FUNCIONAL | 4 | La factura de tela se contabiliza al aprobar la recepción (antes no tenía asiento) | En curso |

---

## 3. Hechos técnicos verificados

Todo lo de esta sección fue confirmado leyendo el código fuente, no inferido. Se indica el archivo para poder re-verificar.

### 3.1 Importación de ítems

`app/Http/Controllers/MasterData/ItemController.php:151` · `app/Services/MasterData/ItemService.php:385` · `app/Services/MasterData/ItemExcelService.php:39`

- Lee la **hoja activa** (`getActiveSheet()`), no busca por nombre. La hoja de datos debe quedar activa en el archivo.
- Mapea **por nombre de encabezado**, no por posición: el orden de columnas es flexible y las columnas extra se ignoran.
- 19 encabezados. Si el archivo trae `N° ITEM`, las filas hacen UPDATE en vez de INSERT.
- **`SUBGRUPO` es la única columna bloqueante**: si no existe, la fila se rechaza. No se crea automáticamente.
- **`COLOR` falla en silencio**: si el nombre no coincide con `colors`, el ítem se guarda con `color_id = null` sin error.
- **`IMPUESTO (%)` se auto-crea** si el porcentaje no existe (`Impuesto X%`). Un error de digitación ensucia el catálogo.
- `BODEGA` debe existir; si no, se reporta error de fila pero el ítem se importa sin bodega.
- `NOMBRE` se autogenera (subgrupo + campos adicionales + color). `GRUPO` no se usa.
- `CAMPOS ADICIONALES` / `VALORES ADICIONALES`: listas separadas por `;`, misma cantidad y orden. Aquí vive `BASE TELA`.
- `tracks_inventory` no está en la plantilla: se deriva del tipo (`service` → false).

### 3.2 Importación de rollos

`app/Http/Controllers/Textil/FabricRollController.php:36` (constante) y `:512` (import)

- Busca la hoja llamada **`rollos`** (`getSheetByName`), con fallback a la activa.
- Exige **exactamente 15 columnas**, en orden exacto y con el texto exacto. Cualquier diferencia rechaza el archivo completo.
- Bloqueantes: `N° ROLLO` (único), `REFERENCIA ÍTEM` (debe existir en `items.reference`), `CANTIDAD (m)` (> 0), `FECHA INGRESO`.
- `BASE TELA` se valida cruzada contra `items.extra_fields['BASE TELA']`; si difiere, rechaza la fila.
- `PROVEEDOR` y `N° PEDIDO` **no se persisten** en este flujo: el insert deja `purchase_document_item_id = null`.
- Los rollos importados quedan como `approval_status = approved`, `movement_type = 'entrada'`.

### 3.3 Cadena de dependencias de datos

```
Catálogos base (subgrupos, colores, bodegas, impuestos)
        ↓  SUBGRUPO bloqueante
Ítems  (items.reference)
        ↓  REFERENCIA ÍTEM bloqueante
Rollos (fabric_rolls)
```

### 3.4 Datos ya precargados por seeder

`database/seeders/` — cifras contadas del código, no estimadas:

| Catálogo | Cantidad | Seeder |
|---|---|---|
| Grupos de categoría | 11 | `CategorySeeder` |
| Subgrupos | 88 | `CategorySeeder` |
| Colores | 78 | `ColorsSeeder` (⚠ hace `delete()` de todos antes de insertar) |
| Bodegas | 2 | `WarehousesSeeder` |
| Impuestos | 3 | `TaxesSeeder` |
| Unidades de medida | 58 | `resources/js/data/unitMeasures.json` |
| Plan de cuentas | PUC básico | `AccountingAccountSeeder` |

Vacíos en producción: **ubicaciones de bodega** y **contactos** (proveedores/clientes).

### 3.5 Bug abierto: `/listados-operacionales/bulk`

`app/Http/Controllers/Production/ProductOperationListController.php:220`

- `Documentacion.md` §5.5 afirma que faltan correr dos migraciones `2026_06_22_000001` y `2026_06_22_000002`. **Esas migraciones no existen en el repo** (la más reciente es `2026_06_03_000003`). No es un despliegue pendiente: es código que nunca se escribió.
- **Hipótesis a confirmar:** `storeBulk` solo captura `\InvalidArgumentException`. La migración `2026_04_24_120000_harden_product_sheet_operations_constraints.php` agregó índices únicos `(product_sheet_id, operation_id)` y `(product_sheet_id, sequence)`. Un duplicado en el payload dispararía una excepción de BD **no capturada** → 500.
- **Cómo verificar:** reproducir en local con un payload que repita `sequence` u `operation_id`. Si se confirma, la corrección es capturar la excepción y devolver 422 con mensaje claro — no una migración nueva.

### 3.6 Entorno y calidad

- **No existe ambiente de staging.** Solo local (`composer dev`) y producción en Hostinger. No hay `docker-compose.yml` ni `.env.staging`; el CI de GitHub Actions corre sobre SQLite efímero. Toda validación previa se hace en local.
- **Despliegue manual** por SSH + `scp` del build de Vite. Sin CI/CD de despliegue.
- **Tests:** 28 archivos. Cobertura **nula** en Finance, Inventory, Textil y Reports. Producción y Auth tienen cobertura parcial; Compras solo vía Dusk.

### 3.7 Correcciones a documentación existente

- `docs/ANALISIS_FLUJO_COMPRAS.md` está **desactualizado**: afirma que a Compras le faltan estados (`draft`, `approved`, `rejected`, `cancelled`). Ya existen y están completos — ver `Documentacion.md` §4.6.4 y `app/Enums/PurchaseRequestStatus.php`. Tomar `Documentacion.md` como fuente de verdad.
- `docs/GUIA_ALERTAS.md` está vacío (0 bytes), pero `.github/copilot-instructions.md` sí documenta el patrón de alertas.
- `Documentacion.md` §5.5 cita migraciones inexistentes (ver 3.5).

---

## 4. Cómo mantener este documento

- **Cada cambio funcional, bug o mejora** agrega una fila en el [Registro de trabajo](#2-registro-de-trabajo).
- **Cada hallazgo técnico no obvio** que costó investigar va a [Hechos técnicos verificados](#3-hechos-técnicos-verificados), con su archivo y línea.
- Si un hecho registrado resulta falso, se corrige aquí en el mismo cambio que lo descubre.
- Al cerrar una fase, se actualiza su estado en la tabla de fases.

---

## 5. Documentos relacionados

| Documento | Para qué |
|---|---|
| [`Documentacion.md`](../Documentacion.md) | Documentación técnica y funcional completa. **Fuente de verdad** ante conflictos. |
| `Documentacion_ERP.pdf` | Misma documentación, pero **es la única copia que conserva los diagramas** (arquitectura, entidad-relación, UML de casos de uso, actividades y estados). El `.md` es una conversión que perdió todas las imágenes: los títulos de diagrama quedaron sin contenido. Para texto usa el `.md`; para cualquier diagrama, el PDF. |
| [`.github/copilot-instructions.md`](../.github/copilot-instructions.md) | Reglas de ingeniería obligatorias (persistencia, alertas, dialogs, cohesión de dominio). |
| [`docs/plantillas/`](plantillas/) | Plantillas de carga inicial + generador verificado. |
| [`docs/INDICE_DOCUMENTACION.md`](INDICE_DOCUMENTACION.md) | Índice de la documentación por módulo. |

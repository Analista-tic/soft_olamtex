# Módulo de Reportes

**Última actualización:** 2026-06-12
**Propósito:** Explicar qué hace el módulo de Reportes y cómo se conecta con el resto del sistema.

---

## 1. ¿Qué hace este módulo?

El módulo de Reportes centraliza **todos los informes y consultas analíticas** del sistema. No modifica datos — solo los presenta de forma organizada para tomar decisiones.

---

## 2. ¿Quién usa este módulo?

| Rol | ¿Para qué lo usa? |
|-----|-------------------|
| Gerencia | Tomar decisiones con base en datos reales |
| Producción | Ver avance y costos de las órdenes |
| Contabilidad | Revisar movimientos financieros y de inventario |
| Comercial | Ver ventas por período, cliente o producto |

---

## 3. Reportes disponibles

El módulo está dividido en 4 grandes áreas:

### 3.1 Producción (`resources/js/pages/Reports/Produccion/`)
Informes relacionados con las órdenes de producción, costos, avances y fichas técnicas.

Ejemplos de lo que muestra:
- Órdenes de producción por estado y temporada
- Costos de producción por orden
- Consumo de tela y materiales
- Avance por proceso en control de lotes

### 3.2 Financiero (`resources/js/pages/Reports/Financiero/`)
Informes de ventas, compras, cartera y flujo de caja.

Ejemplos de lo que muestra:
- Ventas por período y cliente
- Compras por período y proveedor
- Estado de cartera (facturas pendientes de pago)
- Retenciones aplicadas

### 3.3 Inventario (`resources/js/pages/Reports/Inventario/`)
Informes del estado del inventario y movimientos.

Ejemplos de lo que muestra:
- Stock actual por bodega
- Movimientos de inventario por período
- Ítems con stock bajo mínimo
- Valorización del inventario

### 3.4 Textil (`resources/js/pages/Reports/Textil/`)
Informes específicos de los rollos de tela.

Ejemplos de lo que muestra:
- Disponibilidad de rollos por tipo de tela
- Consumo de tela por orden de producción
- Rollos pendientes de aprobación

---

## 4. Archivos clave para un desarrollador

| Archivo | ¿Para qué? |
|---------|------------|
| `app/Http/Controllers/Reports/ReportController.php` | Controlador principal de reportes — aquí viven las consultas |
| `resources/js/pages/Reports/Index.vue` | Pantalla principal del módulo |
| `resources/js/pages/Reports/Produccion/Index.vue` | Reportes de producción |
| `resources/js/pages/Reports/Financiero/Index.vue` | Reportes financieros |
| `resources/js/pages/Reports/Inventario/Index.vue` | Reportes de inventario |
| `resources/js/pages/Reports/Textil/Index.vue` | Reportes textiles |

---

## 5. Reglas importantes

1. **Los reportes son solo lectura** — nunca modifican datos.
2. **Las consultas pesadas deben optimizarse** — los reportes pueden hacer consultas grandes a la base de datos; siempre agregar índices y filtros de fecha.
3. **Los filtros de fecha son obligatorios** en la mayoría de reportes — reportes sin filtro de período pueden ser muy lentos en bases de datos grandes.

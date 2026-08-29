# Documentación del Sistema Olamtex

**Última actualización:** 2026-06-12
**Para:** Desarrolladores nuevos que se integran al proyecto.

---

## ¿Por dónde empezar?

Antes de leer la documentación de módulos, lee primero:

1. [Estado y trazabilidad](ESTADO_Y_TRAZABILIDAD.md) — **documento vivo**: en qué fase va la implementación, qué se está trabajando, y los hechos técnicos ya verificados contra el código. Si retomas el proyecto, empieza aquí.
2. [Roadmap de conocimientos para empezar](ROADMAP_CONOCIMIENTOS_ONBOARDING.md) — tecnologías, estructura de carpetas, flujo de trabajo y buenas prácticas.

Luego continúa con los módulos en este orden (de más a menos importante):

---

## Módulos del sistema

| # | Módulo | Archivo | ¿Qué encontrarás? |
|---|--------|---------|-------------------|
| 1 | **Producción** | [MODULO_PRODUCCION.md](MODULO_PRODUCCION.md) | Sábanas, órdenes de producción, fichas técnicas, control de lotes |
| 2 | **Ingresos** | [MODULO_INGRESOS.md](MODULO_INGRESOS.md) | Cotizaciones, órdenes de venta, facturas, recibos de caja |
| 3 | **Gastos** | [MODULO_GASTOS.md](MODULO_GASTOS.md) | Solicitudes de compra, órdenes de compra, facturas de proveedor |
| 4 | **Inventario** | [MODULO_INVENTARIO.md](MODULO_INVENTARIO.md) | Bodegas, stock, movimientos, ajustes, solicitudes internas |
| 5 | **Finanzas** | [MODULO_FINANZAS.md](MODULO_FINANZAS.md) | Plan de cuentas, bancos, impuestos, retenciones, asientos |
| 6 | **Datos Maestros** | [MODULO_DATOS_MAESTROS.md](MODULO_DATOS_MAESTROS.md) | Contactos, ítems, colores, categorías, parámetros |
| 7 | **Textil** | [MODULO_TEXTIL.md](MODULO_TEXTIL.md) | Rollos de tela, disponibilidad, ajustes de rollo |
| 8 | **Reportes** | [MODULO_REPORTES.md](MODULO_REPORTES.md) | Informes de producción, financiero, inventario y textil |
| 9 | **Configuración** | [MODULO_CONFIGURACION.md](MODULO_CONFIGURACION.md) | Usuarios, roles, permisos, empresa, auditoría |

---

## Cómo se conectan los módulos (mapa general)

```
DATOS MAESTROS (base de todo)
    ├── Contactos → usados en Ingresos (clientes) y Gastos (proveedores)
    ├── Ítems → usados en Inventario, Ingresos y Gastos
    └── Parámetros → usados en Producción

TEXTIL
    └── Rollos de tela → asignados a fichas técnicas de Producción

PRODUCCIÓN
    ├── Sábana de producto → genera Orden de Producción
    ├── Orden de Producción → tiene Ficha Técnica (materiales, operaciones, costos)
    └── Control de lotes → genera salidas en Inventario y recepciones en Bodega

GASTOS
    └── Facturas de compra aprobadas → generan entradas en Inventario

INGRESOS
    └── Facturas de venta → generan salidas en Inventario

INVENTARIO
    └── Movimientos afectan cuentas contables en Finanzas

FINANZAS
    └── Plan de cuentas usado por Ingresos, Gastos e Inventario

CONFIGURACIÓN
    └── Permisos controlan el acceso a todos los módulos

REPORTES
    └── Consulta datos de todos los módulos (solo lectura)
```

---

## Tecnologías del sistema

| Capa | Tecnología |
|------|------------|
| Backend | Laravel 12 + PHP 8.2 |
| Frontend | Vue 3 + TypeScript + Inertia.js |
| Estilos | Tailwind CSS |
| Base de datos | MySQL (relacional) |
| Build | Vite |
| Estado global | Pinia |
| Rutas en frontend | Ziggy |
| Permisos | Spatie Laravel Permission |

---

## Otros documentos útiles en esta carpeta

| Archivo | ¿Para qué sirve? |
|---------|-----------------|
| [ESTADO_Y_TRAZABILIDAD.md](ESTADO_Y_TRAZABILIDAD.md) | Estado por fase, registro de cambios/bugs y hechos técnicos verificados |
| [plantillas/](plantillas/) | Plantillas de carga inicial (ítems, rollos, catálogos) y su generador |
| [ROADMAP_CONOCIMIENTOS_ONBOARDING.md](ROADMAP_CONOCIMIENTOS_ONBOARDING.md) | Guía de 6 semanas para ponerse al día |
| [DESPLIEGUE_HOSTINGER_SSH.md](DESPLIEGUE_HOSTINGER_SSH.md) | Cómo hacer deploy en el servidor de producción |
| [ANALISIS_FLUJO_COMPRAS.md](ANALISIS_FLUJO_COMPRAS.md) | Análisis detallado del flujo de compras |
| [FICHA_TECNICA_BD_Y_ALMACENAMIENTO.md](FICHA_TECNICA_BD_Y_ALMACENAMIENTO.md) | Estructura de la ficha técnica en base de datos |
| [AUDITORIA_SEGURIDAD_VULNERABILIDADES.md](AUDITORIA_SEGURIDAD_VULNERABILIDADES.md) | Revisión de seguridad del sistema |

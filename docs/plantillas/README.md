# Plantillas de carga inicial

Plantillas de Excel para que el cliente entregue los datos de arranque, y el generador que las produce.

## Archivos

| Archivo | Para qué | Orden |
|---|---|---|
| `03_Catalogos_Base.xlsx` | Bodegas, ubicaciones, contactos, y los colores/subgrupos que falten. Carga manual: estos catálogos no tienen importador. | 1º |
| `01_Plantilla_Items.xlsx` | Catálogo de ítems. Hoja `items` + hojas de consulta (subgrupos por grupo, colores, instrucciones). | 2º |
| `02_Plantilla_Rollos.xlsx` | Rollos de tela. Hoja `rollos` con las 15 columnas exactas. | 3º |

El orden importa: los rollos apuntan a un ítem por su `REFERENCIA`, y los ítems apuntan a un subgrupo que debe existir.

## Por qué se generan por script y no a mano

Los encabezados de ambas plantillas de importación deben coincidir **carácter por carácter** con lo que espera el sistema. `gen_plantillas.py` los toma del código fuente y `verificar.py` comprueba el resultado contra ese mismo código, así que las plantillas no se pueden desincronizar sin que la verificación falle.

Las listas desplegables (subgrupos, colores, unidades, bodegas, impuestos) se llenan con los valores reales de los seeders. Eso evita el error más costoso de esta carga: un color mal escrito no produce ningún error, simplemente deja el ítem sin color.

## Regenerar

```bash
pip install openpyxl
cd docs/plantillas
python gen_plantillas.py   # regenera los tres .xlsx
python verificar.py        # comprueba contra el codigo fuente; sale con codigo 1 si algo no cuadra
```

Hay que regenerarlas cuando cambien los encabezados de importación, los seeders de catálogos, o la lista de unidades de medida.

## Restricciones que no se pueden romper

Vienen del código del importador, no son preferencias:

- **Ítems:** la hoja de datos debe ser la **hoja activa** del archivo — el importador usa `getActiveSheet()`, no busca por nombre. Mapea por nombre de encabezado, así que el orden de columnas sí es flexible.
- **Rollos:** la hoja debe llamarse **`rollos`** y tener **exactamente 15 columnas**, en orden. Una columna de más o de menos rechaza el archivo completo.
- Ninguna plantilla trae fila de ejemplo en la hoja de datos: si el cliente olvida borrarla, se importaría como registro real. Los ejemplos están en la hoja `INSTRUCCIONES`.

Detalle completo de validaciones y trampas en [`../ESTADO_Y_TRAZABILIDAD.md`](../ESTADO_Y_TRAZABILIDAD.md) §3.1 y §3.2.

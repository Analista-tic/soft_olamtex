<?php

namespace App\Services\Production;

/**
 * Fuente única de columnas de la plantilla Excel de sábanas de producto.
 * Usada tanto por ProductSheetExcelService (genera el archivo) como por
 * ProductSheetBulkImportService (lo interpreta), para que ambos lados
 * siempre coincidan en encabezados y claves internas.
 */
class ProductSheetTemplateSchema
{
    /**
     * Columnas fijas (no dependen de catálogos dinámicos).
     * 'key' es el identificador interno usado al mapear filas del Excel.
     */
    public const FIXED_COLUMNS = [
        ['header' => 'ID', 'key' => 'id', 'required' => false],
        ['header' => 'TIPO *', 'key' => 'tipo', 'required' => true],
        ['header' => 'TIPO PRENDA', 'key' => 'tipo_prenda', 'required' => false],
        ['header' => 'SECCIÓN *', 'key' => 'seccion', 'required' => true],
        ['header' => 'GRUPO *', 'key' => 'grupo', 'required' => true],
        ['header' => 'MARCA *', 'key' => 'marca', 'required' => true],
        ['header' => 'MUNDO *', 'key' => 'mundo', 'required' => true],
        ['header' => 'COLECCIÓN', 'key' => 'coleccion', 'required' => false],
        ['header' => 'ES TEJIDO *', 'key' => 'es_tejido', 'required' => true],
        ['header' => 'BASE TEJIDO', 'key' => 'base_tejido', 'required' => false],
        ['header' => 'BASE HILAZA', 'key' => 'base_hilaza', 'required' => false],
        ['header' => 'LISTA OPERACIONAL', 'key' => 'lista_operacional', 'required' => false],
        ['header' => 'REF. MUESTRA', 'key' => 'ref_muestra', 'required' => false],
        ['header' => 'REF. PRODUCTO', 'key' => 'ref_producto', 'required' => false],
        ['header' => 'REF. PRODUCTO 2', 'key' => 'ref_producto_2', 'required' => false],
        ['header' => 'PRECIO FACTURACIÓN', 'key' => 'precio_facturacion', 'required' => false],
        ['header' => 'COSTO', 'key' => 'costo', 'required' => false],
        ['header' => 'PRECIO VENTA *', 'key' => 'precio_venta', 'required' => true],
        ['header' => 'DESCRIPCIÓN *', 'key' => 'descripcion', 'required' => true],
        ['header' => 'OBSERVACIONES', 'key' => 'observaciones', 'required' => false],
        ['header' => 'HABILITADO', 'key' => 'habilitado', 'required' => false],
    ];

    public static function sizeHeader(string $size): string
    {
        return 'TALLA ' . mb_strtoupper(trim($size));
    }

    public static function sizeDataKey(string $size): string
    {
        return 'talla:' . self::normalizeCatalogName($size);
    }

    public static function processHeader(string $process): string
    {
        return 'PROCESO ' . mb_strtoupper(trim($process));
    }

    public static function processDataKey(string $process): string
    {
        return 'proceso:' . self::normalizeCatalogName($process);
    }

    /**
     * Normaliza un encabezado leído del Excel para hacer match contra los
     * encabezados generados (tolera mayúsculas/espacios/asterisco distintos).
     */
    public static function normalizeHeader(string $header): string
    {
        $clean = str_replace(["\n", "\r"], ' ', $header);
        $clean = preg_replace('/\s*\*\s*$/', '', trim($clean));
        $clean = preg_replace('/\s+/', ' ', (string) $clean);

        return mb_strtoupper(trim((string) $clean));
    }

    public static function normalizeCatalogName(?string $value): string
    {
        return mb_strtolower(trim((string) $value));
    }
}

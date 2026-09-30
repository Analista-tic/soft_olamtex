<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Los números que identifican los documentos de compra no pueden repetirse.
 *
 * 1) Corrige los repetidos existentes (consecutivos internos y número de factura del proveedor).
 *    Se conserva el número de la fila más antigua; a las demás se les asigna el siguiente libre de su serie.
 *    Considera también los registros eliminados (soft delete), porque su número no se reutiliza.
 * 2) Agrega índices únicos como garantía final en BD, por si dos peticiones simultáneas pasan la validación:
 *    - purchase_documents (document_type, document_number)
 *    - purchase_documents.supplier_invoice_key: (proveedor + número de factura del proveedor) solo para facturas
 *      vivas; al eliminar la factura la columna queda en NULL y el número se puede volver a usar.
 *    (purchase_requests.request_number ya tenía índice único desde su creación.)
 */
return new class extends Migration
{
    public function up(): void
    {
        $this->fixDuplicateDocumentNumbers();
        $this->fixDuplicateSupplierInvoiceNumbers();

        Schema::table('purchase_documents', function (Blueprint $table) {
            $table->unique(['document_type', 'document_number'], 'purchase_documents_type_number_unique');
            $table->string('supplier_invoice_key', 320)->nullable()->virtualAs(
                "CASE WHEN document_type = 'purchase_invoice' AND deleted_at IS NULL AND contact_id IS NOT NULL "
                . "AND supplier_invoice_number IS NOT NULL AND TRIM(supplier_invoice_number) <> '' "
                . "THEN CONCAT(contact_id, '|', LOWER(TRIM(supplier_invoice_number))) ELSE NULL END"
            );
            $table->unique('supplier_invoice_key', 'purchase_documents_supplier_invoice_unique');
        });
    }

    public function down(): void
    {
        Schema::table('purchase_documents', function (Blueprint $table) {
            $table->dropUnique('purchase_documents_supplier_invoice_unique');
            $table->dropColumn('supplier_invoice_key');
            $table->dropUnique('purchase_documents_type_number_unique');
        });
    }

    private function fixDuplicateDocumentNumbers(): void
    {
        $groups = DB::table('purchase_documents')
            ->select('document_type', 'document_number')
            ->whereNotNull('document_number')
            ->groupBy('document_type', 'document_number')
            ->havingRaw('COUNT(*) > 1')
            ->get();

        foreach ($groups as $group) {
            $ids = DB::table('purchase_documents')
                ->where('document_type', $group->document_type)
                ->where('document_number', $group->document_number)
                ->orderBy('id')
                ->pluck('id');

            $ids->shift(); // la fila más antigua conserva su número

            foreach ($ids as $id) {
                DB::table('purchase_documents')->where('id', $id)->update([
                    'document_number' => $this->nextFreeNumber($group->document_type, $group->document_number, (int) $id),
                ]);
            }
        }
    }

    private function nextFreeNumber(string $documentType, string $number, int $id): string
    {
        if (preg_match('/^(.*)-(\d+)$/', $number, $m) !== 1) {
            return "{$number}-{$id}";
        }

        [, $prefix, $digits] = $m;
        $pattern = '/^' . preg_quote($prefix, '/') . '-(\d+)$/';

        $max = DB::table('purchase_documents')
            ->where('document_type', $documentType)
            ->where('document_number', 'like', $prefix . '-%')
            ->pluck('document_number')
            ->map(fn ($n) => preg_match($pattern, (string) $n, $mm) === 1 ? (int) $mm[1] : 0)
            ->max();

        return sprintf('%s-%0' . strlen($digits) . 'd', $prefix, ((int) $max) + 1);
    }

    private function fixDuplicateSupplierInvoiceNumbers(): void
    {
        $groups = DB::table('purchase_documents')
            ->selectRaw("contact_id, LOWER(TRIM(supplier_invoice_number)) AS number_key")
            ->where('document_type', 'purchase_invoice')
            ->whereNull('deleted_at')
            ->whereNotNull('contact_id')
            ->whereNotNull('supplier_invoice_number')
            ->whereRaw("TRIM(supplier_invoice_number) <> ''")
            ->groupBy('contact_id', DB::raw('LOWER(TRIM(supplier_invoice_number))'))
            ->havingRaw('COUNT(*) > 1')
            ->get();

        foreach ($groups as $group) {
            $rows = DB::table('purchase_documents')
                ->where('document_type', 'purchase_invoice')
                ->whereNull('deleted_at')
                ->where('contact_id', $group->contact_id)
                ->whereRaw('LOWER(TRIM(supplier_invoice_number)) = ?', [$group->number_key])
                ->orderBy('id')
                ->get(['id', 'supplier_invoice_number']);

            $rows->shift(); // la más antigua conserva el número

            foreach ($rows as $row) {
                // Marca visible para que el usuario corrija el número real del proveedor en esa factura.
                DB::table('purchase_documents')->where('id', $row->id)->update([
                    'supplier_invoice_number' => mb_substr(trim($row->supplier_invoice_number), 0, 240) . " (REPETIDA-{$row->id})",
                ]);
            }
        }
    }
};

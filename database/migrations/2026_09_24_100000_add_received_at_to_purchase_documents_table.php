<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Fecha real de recepción de la mercancía (trazabilidad: solicitud → orden → recepción → factura). */
    public function up(): void
    {
        if (Schema::hasColumn('purchase_documents', 'received_at')) {
            return;
        }

        Schema::table('purchase_documents', function (Blueprint $table) {
            $table->timestamp('received_at')->nullable()->after('invoiced_at');
        });

        // Las facturas ya recibidas guardaban la fecha de recepción en approved_at.
        DB::table('purchase_documents')
            ->where('document_type', 'purchase_invoice')
            ->whereIn('status', ['received', 'approved'])
            ->whereNotNull('approved_at')
            ->update(['received_at' => DB::raw('approved_at')]);
    }

    public function down(): void
    {
        Schema::table('purchase_documents', function (Blueprint $table) {
            $table->dropColumn('received_at');
        });
    }
};

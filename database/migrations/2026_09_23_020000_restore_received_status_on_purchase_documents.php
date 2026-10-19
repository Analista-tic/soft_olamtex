<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// 2026_05_24_200000 redefinió el enum de status sin 'received', que PurchaseDocumentInvoiceService::receiveInvoice() sigue asignando.
return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE purchase_documents MODIFY COLUMN status ENUM(
            'pending',
            'approved',
            'sent',
            'invoiced',
            'partially_invoiced',
            'rejected',
            'pending_receipt',
            'received'
        ) NULL");
    }

    public function down(): void
    {
        DB::table('purchase_documents')->where('status', 'received')->update(['status' => 'approved']);

        DB::statement("ALTER TABLE purchase_documents MODIFY COLUMN status ENUM(
            'pending',
            'approved',
            'sent',
            'invoiced',
            'partially_invoiced',
            'rejected',
            'pending_receipt'
        ) NULL");
    }
};

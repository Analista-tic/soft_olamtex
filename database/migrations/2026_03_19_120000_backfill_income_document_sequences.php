<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        foreach ([
            'quote',
            'sales_order',
            'sale_credit_note',
            'sale_debit_note',
        ] as $documentType) {
            DB::table('finance_document_sequences')->updateOrInsert(
                ['document_type' => $documentType],
                [
                    'current_value' => 0,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );
        }
    }

    public function down(): void
    {
        DB::table('finance_document_sequences')
            ->whereIn('document_type', [
                'quote',
                'sales_order',
                'sale_credit_note',
                'sale_debit_note',
            ])
            ->delete();
    }
};

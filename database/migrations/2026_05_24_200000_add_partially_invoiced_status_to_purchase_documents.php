<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

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
            'pending_receipt'
        ) NULL");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE purchase_documents MODIFY COLUMN status ENUM(
            'pending',
            'approved',
            'sent',
            'invoiced',
            'rejected',
            'pending_receipt'
        ) NULL");
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// "Enviada" deja de ser un estado de la orden y pasa a ser una marca (sent_at).
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('purchase_documents', function (Blueprint $table) {
            $table->timestamp('sent_at')->nullable()->after('approved_at');
        });

        DB::table('purchase_documents')->where('status', 'sent')->update([
            'status' => 'approved',
            'sent_at' => DB::raw('COALESCE(updated_at, NOW())'),
        ]);

        DB::statement("ALTER TABLE purchase_documents MODIFY COLUMN status ENUM(
            'pending',
            'approved',
            'invoiced',
            'partially_invoiced',
            'rejected',
            'pending_receipt',
            'received'
        ) NULL");
    }

    public function down(): void
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

        Schema::table('purchase_documents', function (Blueprint $table) {
            $table->dropColumn('sent_at');
        });
    }
};

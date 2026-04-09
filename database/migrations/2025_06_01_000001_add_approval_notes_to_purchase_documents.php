<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Agregar columnas nuevas después de approval_status
        Schema::table('purchase_documents', function (Blueprint $table) {
            $table->text('approval_notes')->nullable()->after('approval_status');
            $table->boolean('has_difference')->default(false)->after('approval_notes');
            $table->enum('difference_status', ['approved', 'rejected'])->nullable()->after('has_difference');
        });

        // 2. Normalizar valores existentes en approval_status antes de cambiar a enum
        DB::statement("
            UPDATE purchase_documents
            SET approval_status = NULL
            WHERE approval_status NOT IN ('pending', 'pending_difference_approval', 'difference_rejected', 'approved')
        ");

        // 3. Cambiar approval_status de VARCHAR a ENUM con los nuevos estados
        DB::statement("
            ALTER TABLE purchase_documents
            MODIFY COLUMN approval_status ENUM('pending','pending_difference_approval','difference_rejected','approved') NULL
        ");
    }

    public function down(): void
    {
        // Revertir ENUM a VARCHAR
        DB::statement("
            ALTER TABLE purchase_documents
            MODIFY COLUMN approval_status VARCHAR(50) NULL
        ");

        Schema::table('purchase_documents', function (Blueprint $table) {
            $table->dropColumn(['approval_notes', 'has_difference', 'difference_status']);
        });
    }
};

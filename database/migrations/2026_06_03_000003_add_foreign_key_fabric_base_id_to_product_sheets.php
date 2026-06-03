<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Limpiar referencias huérfanas antes de agregar la FK
        \Illuminate\Support\Facades\DB::statement('
            UPDATE product_sheets
            SET fabric_base_id = NULL
            WHERE fabric_base_id IS NOT NULL
              AND fabric_base_id NOT IN (SELECT id FROM fabric_bases)
        ');

        Schema::table('product_sheets', function (Blueprint $table) {
            $table->foreign('fabric_base_id')
                  ->references('id')
                  ->on('fabric_bases')
                  ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('product_sheets', function (Blueprint $table) {
            $table->dropForeign(['fabric_base_id']);
        });
    }
};

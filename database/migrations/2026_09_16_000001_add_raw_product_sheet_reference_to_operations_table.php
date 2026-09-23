<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('operations', function (Blueprint $table) {
            $table->string('raw_product_sheet_reference')
                ->nullable()
                ->after('product_sheet_id_reference')
                ->comment('Código textual original de referencia a ficha de producto (import masivo) cuando no se pudo resolver a product_sheets.id.');
        });
    }

    public function down(): void
    {
        Schema::table('operations', function (Blueprint $table) {
            $table->dropColumn('raw_product_sheet_reference');
        });
    }
};

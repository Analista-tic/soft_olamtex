<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('technical_sheet_supplies', function (Blueprint $table) {
            $table->enum('moda_linea', ['MODA', 'LINEA', 'NA'])->default('NA')->change();
        });
    }

    public function down(): void
    {
        Schema::table('technical_sheet_supplies', function (Blueprint $table) {
            $table->string('moda_linea', 10)->nullable()->default('NA')->change();
        });
    }
};

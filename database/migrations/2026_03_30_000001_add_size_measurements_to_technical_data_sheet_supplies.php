<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('technical_data_sheet_supplies', function (Blueprint $table) {
            $table->json('size_measurements')->nullable()->after('notes')
                ->comment('Medidas por talla: {"S":{"corte":0.66,"termi":0.64}, ...}');
        });
    }

    public function down(): void
    {
        Schema::table('technical_data_sheet_supplies', function (Blueprint $table) {
            $table->dropColumn('size_measurements');
        });
    }
};

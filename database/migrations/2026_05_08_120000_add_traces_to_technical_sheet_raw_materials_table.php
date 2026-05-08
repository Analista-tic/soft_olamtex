<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('technical_sheet_raw_materials', function (Blueprint $table) {
            if (!Schema::hasColumn('technical_sheet_raw_materials', 'traces')) {
                $table->json('traces')->nullable()->after('rows')->comment('Metadatos de trazos de materia prima');
            }
        });
    }

    public function down(): void
    {
        Schema::table('technical_sheet_raw_materials', function (Blueprint $table) {
            if (Schema::hasColumn('technical_sheet_raw_materials', 'traces')) {
                $table->dropColumn('traces');
            }
        });
    }
};

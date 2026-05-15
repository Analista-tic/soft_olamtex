<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // trazo_principal, liquidacion, espiga ya existen.
        // Eliminar columnas legadas si existen.
        Schema::table('technical_sheet_sizes', function (Blueprint $table) {
            $columns = array_column(DB::select('DESCRIBE technical_sheet_sizes'), 'Field');
            $toDrop = array_intersect(['units', 'curve', 'units_principal', 'units_liq', 'units_espiga'], $columns);
            if (!empty($toDrop)) {
                $table->dropColumn($toDrop);
            }
        });
    }

    public function down(): void
    {
        Schema::table('technical_sheet_sizes', function (Blueprint $table) {
            $table->dropColumn(['trazo_principal', 'liquidacion', 'espiga']);
            $table->unsignedInteger('units')->default(0)->after('size');
            $table->decimal('curve', 10, 2)->nullable()->after('units');
        });
    }
};

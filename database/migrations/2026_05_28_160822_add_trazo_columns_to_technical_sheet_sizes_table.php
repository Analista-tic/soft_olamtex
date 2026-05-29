<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('technical_sheet_sizes', function (Blueprint $table) {
            $columns = array_column(DB::select('DESCRIBE technical_sheet_sizes'), 'Field');

            if (!in_array('trazo_principal', $columns)) {
                $table->unsignedInteger('trazo_principal')->default(0)->after('size');
            }
            if (!in_array('liquidacion', $columns)) {
                $table->unsignedInteger('liquidacion')->default(0)->after('trazo_principal');
            }
            if (!in_array('espiga', $columns)) {
                $table->unsignedInteger('espiga')->default(0)->after('liquidacion');
            }
        });
    }

    public function down(): void
    {
        Schema::table('technical_sheet_sizes', function (Blueprint $table) {
            $table->dropColumn(['trazo_principal', 'liquidacion', 'espiga']);
        });
    }
};

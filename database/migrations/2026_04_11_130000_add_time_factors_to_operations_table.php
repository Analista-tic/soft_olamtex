<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('operations', function (Blueprint $table) {
            $table->decimal('factor_operario', 8, 2)->default(100)->after('sam_base')->comment('Factor operario en porcentaje');
            $table->decimal('suplemento_maquina', 8, 2)->default(0)->after('factor_operario')->comment('Suplemento de máquina en porcentaje');
            $table->decimal('sam_ajustado', 10, 4)->default(0)->after('suplemento_maquina')->comment('SAM ajustado al 100% de eficiencia');
            $table->decimal('sam_suplemento', 10, 4)->default(0)->after('sam_ajustado')->comment('Incremento por suplemento de máquina');
            $table->decimal('sam_total', 10, 4)->default(0)->after('sam_suplemento')->comment('SAM total calculado de la operación');
        });

        DB::table('operations')
            ->whereNull('factor_operario')
            ->update(['factor_operario' => 100]);

        DB::table('operations')
            ->whereNull('suplemento_maquina')
            ->update(['suplemento_maquina' => 0]);
    }

    public function down(): void
    {
        Schema::table('operations', function (Blueprint $table) {
            $table->dropColumn([
                'factor_operario',
                'suplemento_maquina',
                'sam_ajustado',
                'sam_suplemento',
                'sam_total',
            ]);
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Operation model debe SOLO guardar:
     * - sam_base
     * - factor_operario
     * - machine_id_reference
     *
     * Los campos calculados (suplemento_maquina, sam_ajustado, sam_suplemento, sam_total)
     * deben ser removidos de operations table.
     * Estos cálculos deben ocurrir SOLO en ProductOperationList (product_sheet_operations)
     */
    public function up(): void
    {
        Schema::table('operations', function (Blueprint $table) {
            $table->dropColumn([
                'suplemento_maquina',
                'sam_ajustado',
                'sam_suplemento',
                'sam_total',
            ]);
        });
    }

    public function down(): void
    {
        Schema::table('operations', function (Blueprint $table) {
            $table->decimal('suplemento_maquina', 8, 2)->default(0)->after('factor_operario')->comment('Suplemento de máquina en porcentaje');
            $table->decimal('sam_ajustado', 10, 4)->default(0)->after('suplemento_maquina')->comment('SAM ajustado al 100% de eficiencia');
            $table->decimal('sam_suplemento', 10, 4)->default(0)->after('sam_ajustado')->comment('Incremento por suplemento de máquina');
            $table->decimal('sam_total', 10, 4)->default(0)->after('sam_suplemento')->comment('SAM total calculado de la operación');
        });
    }
};

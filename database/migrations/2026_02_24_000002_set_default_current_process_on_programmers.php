<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Asigna el proceso por defecto (I&D) a órdenes existentes sin current_process.
     */
    public function up(): void
    {
        $defaultProcess = config('product-sheet.batch_control_processes')[0] ?? 'I&D';

        DB::table('programmers')
            ->whereNull('current_process')
            ->update(['current_process' => $defaultProcess]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // No revertir: no podemos saber cuáles eran null originalmente
    }
};

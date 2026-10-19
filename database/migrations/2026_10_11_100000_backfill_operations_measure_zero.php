<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * La medida pasa a ser obligatoria (0 = no aplica). Las operaciones antiguas sin medida quedan en 0,
     * que es como ya las trata la firma de unicidad (null y 0 son lo mismo), y se pueden editar sin bloqueo.
     */
    public function up(): void
    {
        DB::table('operations')->whereNull('measure')->update(['measure' => 0]);
    }

    public function down(): void
    {
        // Sin reversa: no se puede saber qué medidas eran null antes de la corrección.
    }
};

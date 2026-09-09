<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Secuencia de números de rollo.
 *
 * Antes el consecutivo se calculaba con MAX() sobre fabric_rolls dentro de un
 * lockForUpdate, pero ese lock se liberaba al cerrar la transacción del propio
 * cálculo y el INSERT ocurría en otra petición HTTP (GET /next-roll-number →
 * POST /rollos): dos recepciones simultáneas recibían el mismo número y una
 * fallaba al guardar. Con esta tabla el número se reserva de forma atómica en
 * el momento del alta.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('roll_number_sequences', function (Blueprint $table) {
            $table->id();
            $table->string('prefix', 20)->unique();
            $table->unsignedBigInteger('last_number')->default(0);
            $table->timestamps();
        });

        // Arranca la secuencia en el máximo canónico existente (formato RL<dígitos>).
        // Se excluyen a propósito los rollos con sufijo, como RL100-DUP1, porque el
        // cálculo anterior concatenaba todos sus dígitos y convertía RL100-DUP1 en
        // 1001, disparando el consecutivo a un número arbitrario.
        $max = DB::table('fabric_rolls')
            ->whereRaw("roll_number REGEXP '^RL[0-9]+$'")
            ->selectRaw('MAX(CAST(SUBSTRING(roll_number, 3) AS UNSIGNED)) as max_num')
            ->value('max_num');

        DB::table('roll_number_sequences')->insert([
            'prefix' => 'RL',
            'last_number' => (int) ($max ?? 0),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('roll_number_sequences');
    }
};

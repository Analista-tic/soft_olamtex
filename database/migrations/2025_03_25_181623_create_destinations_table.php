<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('destinations', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->timestamps();
        });

        // Insertar valores por defecto
        DB::table('destinations')->insert([
            ['name' => 'CCU'],
            ['name' => 'EJETEX'],
            ['name' => 'PASSARO'],
            ['name' => 'HERPO'],
            ['name' => 'ATUENDOS'],
            ['name' => 'LOHARY'],
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('destinations');
    }
};

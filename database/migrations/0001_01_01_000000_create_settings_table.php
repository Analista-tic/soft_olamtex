<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('settings', function (Blueprint $table) {
            $table->id();

            // parameter = valores globales del sistema (número/texto/json/listas)
            // field     = definición de campo adicional (metadata de formulario)
            $table->enum('kind', ['parameter', 'field'])->index();

            // Identidad
            $table->string('key')->nullable();          // identificador técnico (slug/código)
            $table->string('label');        // nombre visible para el usuario
            $table->text('description')->nullable();

            // Para PARAMETER
            $table->json('value')->nullable();  // 406, "COP", o JSON grande (unidades de medida)

            // Para FIELD
            $table->string('field_type')->nullable(); // text|number|decimal|date|boolean|select
            $table->json('options')->nullable();
            $table->string('default_value')->nullable();
            $table->boolean('is_required')->default(false);
            $table->integer('position')->default(0);

            $table->boolean('status')->default(true);
            $table->timestamps();

            // Unicidad por tipo (evita choques innecesarios)
            $table->unique(['kind', 'key'], 'settings_kind_key_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('settings');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fase 15 del refactor de materiales: reemplaza el JSON `technical_sheet_rolls.lines`
 * (programación de rollos de tela por Diseño) por una tabla relacional consultable,
 * para poder cruzarla con production_roll_consumptions (consumo real) en un reporte
 * de control. La columna `lines` no se borra en esta migración — queda como respaldo
 * de solo lectura hasta que una migración de limpieza posterior confirme en
 * producción que esta tabla tiene el mismo conteo/suma que el JSON.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fabric_roll_programming_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('technical_sheet_id')->constrained('technical_sheets')->cascadeOnDelete();

            // Trazabilidad con la línea JSON original (uuid) — solo para auditar la migración de datos.
            $table->string('legacy_uuid')->nullable()->index();

            $table->string('consecutive', 120)->nullable();
            $table->string('type', 20)->default('PRINCIPAL');

            $table->foreignId('fabric_roll_id')->nullable()->constrained('fabric_rolls')->nullOnDelete();
            $table->foreignId('fabric_item_id')->nullable()->constrained('items')->nullOnDelete();
            // Uid de la fila en la pestaña de materiales de la ficha técnica (no es FK, es un
            // identificador de cliente usado para reconciliar la línea guardada con su editor).
            $table->string('raw_material_uid', 100)->nullable();

            $table->string('fabric_name', 200)->nullable();
            $table->string('color', 120)->nullable();
            $table->string('color_code', 120)->nullable();
            $table->string('supplier', 200)->nullable();
            $table->string('location', 100)->nullable();
            $table->text('notes')->nullable();

            $table->decimal('average_trace', 10, 4)->nullable();
            $table->decimal('largo_trazo', 10, 4)->nullable();
            $table->decimal('largo_liq', 10, 4)->nullable();
            $table->decimal('factor', 8, 4)->nullable();
            $table->decimal('consumption_base', 10, 4)->default(0);
            $table->decimal('price_per_meter', 12, 4)->default(0);
            $table->decimal('extra_meters', 10, 2)->default(0);

            $table->decimal('programmed_meters', 10, 2)->nullable();
            $table->decimal('available_meters', 10, 2)->nullable();
            $table->decimal('balance_meters', 10, 2)->nullable();
            $table->decimal('width_cm', 8, 2)->nullable();

            $table->timestamps();

            $table->index('fabric_roll_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fabric_roll_programming_lines');
    }
};

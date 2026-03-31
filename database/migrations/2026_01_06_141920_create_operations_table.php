<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('operations', function (Blueprint $table) {
                $table->id();
                $table->string('codigo', 50)->unique()->nullable()->comment('Código auto-generado en backend - NO EDITABLE por usuario');
                $table->string('action')->comment('Acción: CERRAR, PRENDER, ASENTAR, etc.');
                $table->string('garment_part')->comment('Parte de prenda: BOLSILLO, PRETINA, CUELLO, etc.');
                $table->unsignedBigInteger('section_id')->nullable()->comment('FK a operation_sections');
                $table->decimal('measure', 8, 4)->nullable()->comment('Medida en cm');
                $table->integer('quantity')->nullable()->comment('Cantidad de piezas');
                $table->text('description')->nullable()->comment('Descripción generada: acción + parte + medida + cantidad + sección');
                $table->decimal('sam_base', 8, 4)->nullable()->comment('SAM base en minutos (ej: 0.25)');
                $table->unsignedBigInteger('product_sheet_id_reference')->nullable()->comment('FK a product_sheets (referencia opcional)');
                $table->unsignedBigInteger('machine_id_reference')->nullable()->comment('FK a machines (máquina de referencia)');
                $table->boolean('is_active')->default(true)->index();
                $table->timestamps();
                $table->softDeletes();

                // Índices
                $table->unique(['action', 'garment_part'], 'operations_action_garment_unique');
                $table->index('codigo');

                // Foreign Keys
                $table->foreign('section_id', 'operations_section_fk')
                    ->references('id')
                    ->on('operation_sections')
                    ->onDelete('set null');
                $table->foreign('product_sheet_id_reference', 'operations_product_sheet_fk')
                    ->references('id')
                    ->on('product_sheets')
                    ->onDelete('set null');
                $table->foreign('machine_id_reference', 'operations_machine_fk')
                    ->references('id')
                    ->on('machines')
                    ->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('operations');
    }
};

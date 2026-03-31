<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Imágenes de referencia vinculadas a la sección de estampado/bordado/lavandería
 * de la ficha técnica.
 *
 * Cada fila almacena las dos imágenes editables del diálogo:
 *   - stamping_image: fotografía o arte del estampado/bordado.
 *   - drawing_image:  dibujo plano técnico del estampado/bordado.
 *
 * Una fila única por (technical_sheet_id, section) gracias al unique constraint.
 * Los datos de imagen se guardan como data-URL base64 optimizado en frontend
 * (máx. 1400 px de ancho, compresión JPEG/WEBP al 82 %).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('technical_sheet_stamping_images', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('technical_sheet_id');
            $table->foreign('technical_sheet_id')
                ->references('id')
                ->on('technical_sheets')
                ->cascadeOnDelete();

            /**
             * Sección del diálogo a la que corresponde el registro.
             * Valores: stamping | laundry
             */
            $table->string('section', 20)->default('stamping')
                ->comment('stamping | laundry');

            /** Nombre original del archivo de imagen del estampado (ej. stamping-image.jpg). */
            $table->string('stamping_image_name', 200)->nullable();

            /** Data-URL base64 de la imagen del estampado. */
            $table->longText('stamping_image_data')->nullable();

            /** Nombre original del archivo del dibujo plano. */
            $table->string('drawing_image_name', 200)->nullable();

            /** Data-URL base64 del dibujo plano del estampado. */
            $table->longText('drawing_image_data')->nullable();

            $table->timestamps();

            /** Un registro por sección por ficha. */
            $table->unique(['technical_sheet_id', 'section'], 'ts_stamping_images_sheet_section_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('technical_sheet_stamping_images');
    }
};

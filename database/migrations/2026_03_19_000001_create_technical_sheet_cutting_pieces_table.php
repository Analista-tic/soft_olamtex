<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Despiece de trazo de corte vinculado a la ficha técnica.
 *
 * Cada fila representa una pieza del patrón con datos de ubicación en trazo,
 * tipo de tela, lado, sentido del hilo y cantidad. Reemplaza el almacenamiento
 * en el JSON de info-adicional (cutting.pieces) para tener trazabilidad en BD.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('technical_sheet_cutting_pieces', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('technical_sheet_id');
            $table->foreign('technical_sheet_id')
                ->references('id')
                ->on('technical_sheets')
                ->cascadeOnDelete();

            /** UUID generado en frontend para idempotencia (upsert). */
            $table->string('piece_uid', 100)->nullable()->comment('UUID de frontend para upsert idempotente');

            /** Orden de aparición en la tabla de despiece. */
            $table->unsignedSmallInteger('sequence')->default(0);

            /** Nombre de la pieza (Delantero, Espalda, Manga, etc.). */
            $table->string('name', 150);

            /**
             * Tipo de pieza en la prenda.
             * Valores: PRINCIPAL | SECUNDARIO
             */
            $table->string('piece_type', 30)->default('PRINCIPAL');

            /**
             * Tipo de tela para esta pieza.
             * Valores: PRINCIPAL | SECUNDARIA | ENTRETELA | RETAL
             */
            $table->string('fabric_type', 30)->default('PRINCIPAL');

            /** UID de la tela en la tabla de materia prima (rawMaterials). Nullable. */
            $table->string('fabric_uid', 100)->nullable();

            /**
             * Ubicación / dirección en el trazo.
             * Valores: AL_HILO | AL_DOBLEZ | APROVECHAMIENTO | SESGO
             */
            $table->string('trace_location', 30)->nullable();

            /**
             * Lado de la prenda que corresponde a la pieza.
             * Valores: IZQUIERDA | DERECHA | AMBOS | NO_APLICA
             */
            $table->string('side', 20)->default('AMBOS');

            /**
             * Dirección del hilo (sentido del tejido).
             * Valores: 90 | 180 | SESGO
             */
            $table->string('grain', 10)->default('90');

            /** Cantidad de cortes por prenda (calculado desde side). */
            $table->unsignedSmallInteger('quantity')->default(1);

            /** Observaciones técnicas de la pieza. */
            $table->text('notes')->nullable();

            $table->timestamps();

            $table->index(['technical_sheet_id', 'sequence']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('technical_sheet_cutting_pieces');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * ⚠️  IMPORTANTE: Esta migración solo debe ejecutarse DESPUÉS de validar
     * completamente en PRODUCCIÓN que todas las piezas de despiece se están
     * leyendo correctamente desde product_sheet_cutting_pieces y que no hay
     * consumidores activos de technical_sheet_cutting_pieces.
     * 
     * Fase 5 Cleanup: Eliminar tablas legacy de despiece de corte.
     */
    public function up(): void
    {
        // Primero eliminar la tabla de sizes (referencia a cutting_pieces)
        Schema::dropIfExists('technical_sheet_cutting_piece_sizes');
        
        // Luego eliminar la tabla de piezas
        Schema::dropIfExists('technical_sheet_cutting_pieces');
    }

    /**
     * Reverse the migrations.
     * 
     * ⚠️  Rollback: Esto no restaurará las tablas, solo las dejará para que
     * manualmente se ejecute migrate:refresh si es necesario.
     */
    public function down(): void
    {
        // No hay rollback automático para tablas eliminadas en producción.
        // Si es necesario restaurar, contactar a ops para recover desde backup.
    }
};

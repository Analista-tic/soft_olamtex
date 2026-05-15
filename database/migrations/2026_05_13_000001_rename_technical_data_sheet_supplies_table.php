<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Eliminar la tabla vieja vacía
        Schema::dropIfExists('technical_sheet_supplies');

        // Renombrar la tabla activa con datos
        Schema::rename('technical_data_sheet_supplies', 'technical_sheet_supplies');
    }

    public function down(): void
    {
        Schema::rename('technical_sheet_supplies', 'technical_data_sheet_supplies');
    }
};

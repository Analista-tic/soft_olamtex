<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('product_sheet_cutting_piece_sizes');
    }

    public function down(): void
    {
        // Tabla eliminada intencionalmente — funcionalidad reemplazada por technical_sheet_sizes
    }
};

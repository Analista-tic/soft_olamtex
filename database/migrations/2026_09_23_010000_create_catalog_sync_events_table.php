<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('catalog_sync_events', function (Blueprint $table) {
            // Una fila por catálogo (grupos, impuestos, ítems, etc.): solo interesa
            // cuándo fue la última vez que cambió, no un historial de eventos.
            $table->string('catalog_key')->primary();
            $table->timestamp('updated_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('catalog_sync_events');
    }
};

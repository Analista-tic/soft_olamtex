<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Cambia el nombre de la tabla física
        Schema::rename('articles', 'items');
    }

    public function down(): void
    {
        // Reverso por si haces rollback
        Schema::rename('items', 'articles');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::rename('warehouse_article', 'warehouse_item');
    }

    public function down(): void
    {
        Schema::rename('warehouse_item', 'warehouse_article');
    }
};

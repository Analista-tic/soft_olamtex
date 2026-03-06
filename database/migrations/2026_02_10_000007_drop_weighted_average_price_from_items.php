<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * El PP vive en warehouse_items. Eliminar caché global en items.
     */
    public function up(): void
    {
        if (Schema::hasColumn('items', 'weighted_average_price')) {
            Schema::table('items', function (Blueprint $table) {
                $table->dropColumn('weighted_average_price');
            });
        }
    }

    public function down(): void
    {
        Schema::table('items', function (Blueprint $table) {
            $table->decimal('weighted_average_price', 20, 6)->nullable()->after('cost_price');
        });
    }
};

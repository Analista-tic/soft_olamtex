<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Future ready: método de valorización (weighted_average | fifo | lifo).
     */
    public function up(): void
    {
        Schema::table('items', function (Blueprint $table) {
            $table->string('costing_method', 30)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('items', function (Blueprint $table) {
            $table->dropColumn('costing_method');
        });
    }
};

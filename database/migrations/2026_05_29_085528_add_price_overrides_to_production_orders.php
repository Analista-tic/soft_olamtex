<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('production_orders', function (Blueprint $table) {
            $table->decimal('billing_price_override', 15, 4)->nullable()->after('cut_fabric_consumption');
            $table->decimal('selling_price_override', 15, 4)->nullable()->after('billing_price_override');
        });
    }

    public function down(): void
    {
        Schema::table('production_orders', function (Blueprint $table) {
            $table->dropColumn(['billing_price_override', 'selling_price_override']);
        });
    }
};

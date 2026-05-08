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
            $table->string('dispatch_month')->nullable()->after('season');
            $table->boolean('is_advance')->default(false)->after('estimated_delivery_date');
            $table->text('notes')->nullable()->after('is_advance');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('production_orders', function (Blueprint $table) {
            $table->dropColumn(['dispatch_month', 'is_advance', 'notes']);
        });
    }
};

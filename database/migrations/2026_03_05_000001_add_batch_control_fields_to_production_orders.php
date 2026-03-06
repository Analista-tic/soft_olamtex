<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('production_orders', function (Blueprint $table) {
            $table->unsignedInteger('batch_priority')->default(0)->after('current_process');
            $table->json('process_estimated_dates')->nullable()->after('batch_priority');
        });
    }

    public function down(): void
    {
        Schema::table('production_orders', function (Blueprint $table) {
            $table->dropColumn(['batch_priority', 'process_estimated_dates']);
        });
    }
};

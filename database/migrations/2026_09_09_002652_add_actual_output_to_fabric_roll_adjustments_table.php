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
        Schema::table('fabric_roll_adjustments', function (Blueprint $table) {
            $table->decimal('actual_output_before', 10, 2)->nullable()->after('partial_after');
            $table->decimal('actual_output_after', 10, 2)->nullable()->after('actual_output_before');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('fabric_roll_adjustments', function (Blueprint $table) {
            $table->dropColumn(['actual_output_before', 'actual_output_after']);
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fabric_rolls', function (Blueprint $table) {
            // none | pending_review | approved_with_difference | rejected
            $table->string('tolerance_status', 30)->default('none')->after('approval_status');
            $table->text('tolerance_reason')->nullable()->after('tolerance_status');
            $table->timestamp('resolved_at')->nullable()->after('tolerance_reason');
            $table->timestamp('written_off_at')->nullable()->after('resolved_at');

            $table->index('tolerance_status', 'fabric_rolls_tolerance_status_idx');
        });
    }

    public function down(): void
    {
        Schema::table('fabric_rolls', function (Blueprint $table) {
            $table->dropIndex('fabric_rolls_tolerance_status_idx');
            $table->dropColumn(['tolerance_status', 'tolerance_reason', 'resolved_at', 'written_off_at']);
        });
    }
};

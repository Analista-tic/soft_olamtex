<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('product_sheet_operations', function (Blueprint $table) {
            $table->dropForeign(['machine_id']);
            $table->dropIndex('pol_machine_idx');
            $table->dropColumn('machine_id');
        });
    }

    public function down(): void
    {
        Schema::table('product_sheet_operations', function (Blueprint $table) {
            $table->foreignId('machine_id')->nullable()->after('operation_id')
                ->constrained('machines')->cascadeOnUpdate()->restrictOnDelete();
            $table->index('machine_id', 'pol_machine_idx');
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * El listado operacional de una sábana se marca como completo a mano (quién y cuándo), no por tener líneas.
     */
    public function up(): void
    {
        Schema::table('product_sheets', function (Blueprint $table) {
            $table->timestamp('operational_list_completed_at')->nullable()->after('operational_list_id');
            $table->foreignId('operational_list_completed_by_user_id')
                ->nullable()
                ->after('operational_list_completed_at')
                ->constrained('users')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('product_sheets', function (Blueprint $table) {
            $table->dropConstrainedForeignId('operational_list_completed_by_user_id');
            $table->dropColumn('operational_list_completed_at');
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('operations', function (Blueprint $table) {
            $table->dropUnique('operations_action_garment_unique');
            $table->index(['action', 'garment_part'], 'operations_action_garment_index');
        });
    }

    public function down(): void
    {
        Schema::table('operations', function (Blueprint $table) {
            $table->dropIndex('operations_action_garment_index');
            $table->unique(['action', 'garment_part'], 'operations_action_garment_unique');
        });
    }
};

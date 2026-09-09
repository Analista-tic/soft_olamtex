<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('items', function (Blueprint $table) {
            $table->foreignId('fabric_name_id')->nullable()->after('color_id')->constrained('fabric_names')->nullOnDelete();
            $table->foreignId('yarn_name_id')->nullable()->after('fabric_name_id')->constrained('yarn_names')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('items', function (Blueprint $table) {
            $table->dropForeign(['fabric_name_id']);
            $table->dropForeign(['yarn_name_id']);
            $table->dropColumn(['fabric_name_id', 'yarn_name_id']);
        });
    }
};

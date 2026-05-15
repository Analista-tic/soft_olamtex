<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('technical_sheet_sizes', function (Blueprint $table) {
            $table->unsignedInteger('total_unidades')->default(0);
        });
    }

    public function down(): void
    {
        Schema::table('technical_sheet_sizes', function (Blueprint $table) {
            $table->dropColumn('total_unidades');
        });
    }
};

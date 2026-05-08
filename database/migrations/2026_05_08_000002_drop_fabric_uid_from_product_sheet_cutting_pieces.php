<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('product_sheet_cutting_pieces', function (Blueprint $table) {
            $table->dropColumn(['fabric_uid', 'fabric_type']);
        });
    }

    public function down(): void
    {
        Schema::table('product_sheet_cutting_pieces', function (Blueprint $table) {
            $table->string('fabric_uid', 100)->nullable();
            $table->string('fabric_type', 30)->default('PRINCIPAL');
        });
    }
};

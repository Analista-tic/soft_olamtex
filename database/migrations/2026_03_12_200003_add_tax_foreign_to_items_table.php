<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('items', function (Blueprint $table) {
            $table->foreign('tax_id')
                ->references('id')
                ->on('taxes')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('items', function (Blueprint $table) {
            $table->dropForeign(['tax_id']);
        });
    }
};

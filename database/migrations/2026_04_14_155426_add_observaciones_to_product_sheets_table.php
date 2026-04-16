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
        Schema::table('product_sheets', function (Blueprint $table) {
            if (!Schema::hasColumn('product_sheets', 'observaciones')) {
                $table->text('observaciones')->nullable()->after('description');
            }
        });
    }

    public function down(): void
    {
        Schema::table('product_sheets', function (Blueprint $table) {
            if (Schema::hasColumn('product_sheets', 'observaciones')) {
                $table->dropColumn('observaciones');
            }
        });
    }
};

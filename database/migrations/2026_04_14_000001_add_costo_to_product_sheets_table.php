<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('product_sheets', function (Blueprint $table) {
            if (!Schema::hasColumn('product_sheets', 'costo')) {
                $table->float('costo')->nullable()->after('billing_price');
            }
        });
    }

    public function down(): void
    {
        Schema::table('product_sheets', function (Blueprint $table) {
            if (Schema::hasColumn('product_sheets', 'costo')) {
                $table->dropColumn('costo');
            }
        });
    }
};

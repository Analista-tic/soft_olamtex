<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('items', function (Blueprint $table) {
            $table->foreign('income_account_id')
                ->references('id')
                ->on('accounting_accounts')
                ->onDelete('set null');

            $table->foreign('inventory_account_id')
                ->references('id')
                ->on('accounting_accounts')
                ->onDelete('set null');

            $table->foreign('cost_of_sale_account_id')
                ->references('id')
                ->on('accounting_accounts')
                ->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::table('items', function (Blueprint $table) {
            $table->dropForeign(['income_account_id']);
            $table->dropForeign(['inventory_account_id']);
            $table->dropForeign(['cost_of_sale_account_id']);
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bank_accounts', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('name', 150);
            $table->string('type', 20);
            $table->string('bank_name', 100)->nullable();
            $table->string('account_number', 30)->nullable();
            $table->string('currency', 3)->default('COP');
            $table->decimal('opening_balance', 15, 2)->default(0);
            $table->date('opening_balance_date')->nullable();

            $table->unsignedBigInteger('accounting_account_id')->nullable()->index();
            $table->foreign('accounting_account_id')
                ->references('id')
                ->on('accounting_accounts')
                ->onDelete('set null');

            $table->boolean('is_active')->default(true);
            $table->text('description')->nullable();

            $table->unsignedBigInteger('created_by_user_id')->nullable()->index();
            $table->foreign('created_by_user_id')
                ->references('id')
                ->on('users')
                ->onDelete('set null');

            $table->timestamps();

            $table->index(['type', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::table('bank_accounts', function (Blueprint $table) {
            $table->dropForeign(['accounting_account_id']);
            $table->dropForeign(['created_by_user_id']);
        });

        Schema::dropIfExists('bank_accounts');
    }
};

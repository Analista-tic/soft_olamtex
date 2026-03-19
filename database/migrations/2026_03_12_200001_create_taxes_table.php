<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('taxes', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->decimal('percentage', 5, 2);
            $table->foreignId('debit_account_id')
                ->nullable()
                ->constrained('accounting_accounts')
                ->nullOnDelete();
            $table->foreignId('credit_account_id')
                ->nullable()
                ->constrained('accounting_accounts')
                ->nullOnDelete();
            $table->boolean('status')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('taxes');
    }
};

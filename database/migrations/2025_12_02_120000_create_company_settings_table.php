<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('company_settings', function (Blueprint $table) {
            $table->id();
            $table->string('name')->nullable();
            $table->string('nit')->nullable();
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->string('accounting_email')->nullable();
            $table->string('website')->nullable();
            $table->string('address')->nullable();
            $table->string('municipality')->nullable();
            $table->string('department')->nullable();
            $table->text('description')->nullable();
            $table->string('logo_path')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('company_settings');
    }
};

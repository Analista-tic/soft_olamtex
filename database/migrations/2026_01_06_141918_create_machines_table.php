<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('machines', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('name')->unique();
            $table->decimal('supplement_factor', 8, 4)->default(1.0)->comment('Factor de suplemento de máquina (1.0 = sin suplemento)');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->index('is_active', 'machines_is_active_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('machines');
    }
};

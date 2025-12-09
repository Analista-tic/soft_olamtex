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
        Schema::create('vault_sub_keys', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vault_entry_id')->constrained()->cascadeOnDelete();
            $table->text('encrypted_payload'); // Contains encrypted JSON: {name, value, notes}
            $table->string('iv', 32); // Initialization vector for AES-GCM
            $table->string('salt', 64); // Salt for key derivation
            $table->timestamps();
            
            $table->index('vault_entry_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('vault_sub_keys');
    }
};

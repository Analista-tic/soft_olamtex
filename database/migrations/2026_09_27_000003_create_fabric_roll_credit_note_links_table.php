<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fabric_roll_credit_note_links', function (Blueprint $table) {
            $table->bigIncrements('id');

            $table->unsignedBigInteger('fabric_roll_id');
            $table->foreign('fabric_roll_id', 'fr_cn_links_roll_fk')
                ->references('id')
                ->on('fabric_rolls')
                ->onDelete('cascade');

            $table->unsignedBigInteger('credit_note_document_id');
            $table->foreign('credit_note_document_id', 'fr_cn_links_note_fk')
                ->references('id')
                ->on('purchase_documents')
                ->onDelete('cascade');

            $table->unsignedBigInteger('created_by_user_id')->nullable();
            $table->foreign('created_by_user_id', 'fr_cn_links_user_fk')
                ->references('id')
                ->on('users')
                ->onDelete('set null');

            $table->timestamps();

            $table->index('fabric_roll_id', 'fr_cn_links_roll_idx');
            $table->index('credit_note_document_id', 'fr_cn_links_note_idx');
            $table->unique(['fabric_roll_id', 'credit_note_document_id'], 'fr_cn_links_roll_note_uq');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fabric_roll_credit_note_links');
    }
};

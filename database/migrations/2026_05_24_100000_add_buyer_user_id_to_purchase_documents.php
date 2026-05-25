<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('purchase_documents', function (Blueprint $table) {
            $table->unsignedBigInteger('buyer_user_id')->nullable()->after('executed_by_user_id');
            $table->foreign('buyer_user_id')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('purchase_documents', function (Blueprint $table) {
            $table->dropForeign(['buyer_user_id']);
            $table->dropColumn('buyer_user_id');
        });
    }
};

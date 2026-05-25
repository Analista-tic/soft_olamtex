<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('purchase_requests', function (Blueprint $table) {
            $table->string('solicited_to')->nullable()->after('requester_user_id');
            $table->unsignedBigInteger('buyer_user_id')->nullable()->after('solicited_to');
            $table->foreign('buyer_user_id')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('purchase_requests', function (Blueprint $table) {
            $table->dropForeign(['buyer_user_id']);
            $table->dropColumn(['solicited_to', 'buyer_user_id']);
        });
    }
};

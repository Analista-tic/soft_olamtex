<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('purchase_documents', function (Blueprint $table) {
            if (!Schema::hasColumn('purchase_documents', 'is_fabric')) {
                $table->boolean('is_fabric')->default(false)->after('description')->index();
            }
        });
    }

    public function down(): void
    {
        Schema::table('purchase_documents', function (Blueprint $table) {
            if (Schema::hasColumn('purchase_documents', 'is_fabric')) {
                $table->dropColumn('is_fabric');
            }
        });
    }
};

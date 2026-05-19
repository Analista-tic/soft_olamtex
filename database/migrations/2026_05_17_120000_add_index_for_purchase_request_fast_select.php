<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('purchase_documents', function (Blueprint $table) {
            $table->index(
                ['purchase_request_id', 'document_type', 'deleted_at', 'status'],
                'pd_req_type_deleted_status_idx'
            );
        });
    }

    public function down(): void
    {
        Schema::table('purchase_documents', function (Blueprint $table) {
            $table->dropIndex('pd_req_type_deleted_status_idx');
        });
    }
};

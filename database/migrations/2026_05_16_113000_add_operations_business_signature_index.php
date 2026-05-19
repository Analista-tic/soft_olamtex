<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('operations', function (Blueprint $table) {
            $table->index(
                ['section_id', 'action', 'garment_part', 'measure', 'quantity', 'sam_base'],
                'operations_business_signature_index'
            );
        });
    }

    public function down(): void
    {
        Schema::table('operations', function (Blueprint $table) {
            $table->dropIndex('operations_business_signature_index');
        });
    }
};

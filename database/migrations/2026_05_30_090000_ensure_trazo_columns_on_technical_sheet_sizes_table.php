<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('technical_sheet_sizes', function (Blueprint $table) {
            if (!Schema::hasColumn('technical_sheet_sizes', 'trazo_principal')) {
                $table->unsignedInteger('trazo_principal')->default(0)->after('size');
            }

            if (!Schema::hasColumn('technical_sheet_sizes', 'liquidacion')) {
                $table->unsignedInteger('liquidacion')->default(0)->after('trazo_principal');
            }

            if (!Schema::hasColumn('technical_sheet_sizes', 'espiga')) {
                $table->unsignedInteger('espiga')->default(0)->after('liquidacion');
            }
        });
    }

    public function down(): void
    {
        Schema::table('technical_sheet_sizes', function (Blueprint $table) {
            $toDrop = [];

            if (Schema::hasColumn('technical_sheet_sizes', 'trazo_principal')) {
                $toDrop[] = 'trazo_principal';
            }

            if (Schema::hasColumn('technical_sheet_sizes', 'liquidacion')) {
                $toDrop[] = 'liquidacion';
            }

            if (Schema::hasColumn('technical_sheet_sizes', 'espiga')) {
                $toDrop[] = 'espiga';
            }

            if (!empty($toDrop)) {
                $table->dropColumn($toDrop);
            }
        });
    }
};

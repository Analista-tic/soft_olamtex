<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('warehouses')
            ->whereNull('observation')
            ->update(['observation' => '']);

        DB::statement('ALTER TABLE `warehouses` MODIFY `observation` VARCHAR(255) NOT NULL');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE `warehouses` MODIFY `observation` VARCHAR(255) NULL');
    }
};

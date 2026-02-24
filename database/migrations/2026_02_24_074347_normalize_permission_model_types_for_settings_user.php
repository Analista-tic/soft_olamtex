<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const OLD_MODEL_TYPE = 'App\\Models\\User';
    private const NEW_MODEL_TYPE = 'App\\Models\\Settings\\User';

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::transaction(function () {
            DB::table('model_has_roles')
                ->where('model_type', self::OLD_MODEL_TYPE)
                ->update(['model_type' => self::NEW_MODEL_TYPE]);

            DB::table('model_has_permissions')
                ->where('model_type', self::OLD_MODEL_TYPE)
                ->update(['model_type' => self::NEW_MODEL_TYPE]);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::transaction(function () {
            DB::table('model_has_roles')
                ->where('model_type', self::NEW_MODEL_TYPE)
                ->update(['model_type' => self::OLD_MODEL_TYPE]);

            DB::table('model_has_permissions')
                ->where('model_type', self::NEW_MODEL_TYPE)
                ->update(['model_type' => self::OLD_MODEL_TYPE]);
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contacts', function ($table) {
            $table->dropUnique('contacts_id_number_unique');
            $table->dropUnique('contacts_email_unique');
        });

        // Único solo entre contactos activos: un contacto borrado (soft delete) no debe
        // impedir recrearlo con el mismo número de identificación o correo.
        DB::statement('CREATE UNIQUE INDEX contacts_id_number_active_unique ON contacts ((CASE WHEN deleted_at IS NULL THEN id_number END))');
        DB::statement('CREATE UNIQUE INDEX contacts_email_active_unique ON contacts ((CASE WHEN deleted_at IS NULL THEN email END))');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX contacts_id_number_active_unique ON contacts');
        DB::statement('DROP INDEX contacts_email_active_unique ON contacts');

        Schema::table('contacts', function ($table) {
            $table->unique('id_number', 'contacts_id_number_unique');
            $table->unique('email', 'contacts_email_unique');
        });
    }
};

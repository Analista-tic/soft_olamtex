<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Determinar driver para hacer operaciones seguras por SGBD
        $driver = DB::getPdo()->getAttribute(PDO::ATTR_DRIVER_NAME);

        // Eliminar índices únicos anteriores de forma segura según el driver
        if ($driver === 'mysql') {
            // MySQL soporta DROP INDEX IF EXISTS ... ON <table> a partir de 8.0.x
            DB::statement('DROP INDEX IF EXISTS contacts_id_number_unique ON contacts');
            DB::statement('DROP INDEX IF EXISTS contacts_email_unique ON contacts');
        } elseif ($driver === 'pgsql') {
            // Postgres: DROP INDEX IF EXISTS <index>
            DB::statement('DROP INDEX IF EXISTS contacts_id_number_unique');
            DB::statement('DROP INDEX IF EXISTS contacts_email_unique');
        } else {
            // Fallback: intentar con el schema builder y silenciar si no existe
            try {
                Schema::table('contacts', function ($table) {
                    $table->dropUnique('contacts_id_number_unique');
                    $table->dropUnique('contacts_email_unique');
                });
            } catch (\Exception $e) {
                // No hacemos nada: ya no existían o el driver no soporta la operación así
            }
        }

        // Crear índices únicos que solo apliquen a contactos "activos" (no soft-deleted)
        if ($driver === 'mysql') {
            // MySQL no soporta índices parciales; creamos una columna generada y un índice único
            // La columna `is_active_for_unique` será 1 cuando deleted_at IS NULL y 0 en caso contrario.
            DB::statement("ALTER TABLE contacts DROP COLUMN IF EXISTS is_active_for_unique");
            DB::statement("ALTER TABLE contacts ADD COLUMN is_active_for_unique TINYINT(1) AS (deleted_at IS NULL) STORED");

            DB::statement('CREATE UNIQUE INDEX contacts_id_number_active_unique ON contacts (id_number, is_active_for_unique)');
            DB::statement('CREATE UNIQUE INDEX contacts_email_active_unique ON contacts (email, is_active_for_unique)');
        } elseif ($driver === 'pgsql') {
            // En Postgres podemos usar índices parciales sobre una expresión
            DB::statement('CREATE UNIQUE INDEX contacts_id_number_active_unique ON contacts ((CASE WHEN deleted_at IS NULL THEN id_number END))');
            DB::statement('CREATE UNIQUE INDEX contacts_email_active_unique ON contacts ((CASE WHEN deleted_at IS NULL THEN email END))');
        } else {
            // Fallback genérico: intentar crear índices sobre expresión (puede fallar según SGBD)
            try {
                DB::statement('CREATE UNIQUE INDEX contacts_id_number_active_unique ON contacts ((CASE WHEN deleted_at IS NULL THEN id_number END))');
                DB::statement('CREATE UNIQUE INDEX contacts_email_active_unique ON contacts ((CASE WHEN deleted_at IS NULL THEN email END))');
            } catch (\Exception $e) {
                // Si falla, dejamos una excepción clara para el administrador.
                throw new \RuntimeException('No se pudo crear el índice único parcial en el driver: ' . $driver . '. Revise la compatibilidad del SGBD. Mensaje: ' . $e->getMessage());
            }
        }
    }

    public function down(): void
    {
        $driver = DB::getPdo()->getAttribute(PDO::ATTR_DRIVER_NAME);

        if ($driver === 'mysql') {
            DB::statement('DROP INDEX IF EXISTS contacts_id_number_active_unique ON contacts');
            DB::statement('DROP INDEX IF EXISTS contacts_email_active_unique ON contacts');

            // Eliminar la columna generada si existe
            DB::statement('ALTER TABLE contacts DROP COLUMN IF EXISTS is_active_for_unique');

            // Restaurar índices únicos simples
            Schema::table('contacts', function ($table) {
                $table->unique('id_number', 'contacts_id_number_unique');
                $table->unique('email', 'contacts_email_unique');
            });
        } elseif ($driver === 'pgsql') {
            DB::statement('DROP INDEX IF EXISTS contacts_id_number_active_unique');
            DB::statement('DROP INDEX IF EXISTS contacts_email_active_unique');

            Schema::table('contacts', function ($table) {
                $table->unique('id_number', 'contacts_id_number_unique');
                $table->unique('email', 'contacts_email_unique');
            });
        } else {
            try {
                DB::statement('DROP INDEX IF EXISTS contacts_id_number_active_unique');
                DB::statement('DROP INDEX IF EXISTS contacts_email_active_unique');
            } catch (\Exception $e) {
                // silenciar
            }

            Schema::table('contacts', function ($table) {
                $table->unique('id_number', 'contacts_id_number_unique');
                $table->unique('email', 'contacts_email_unique');
            });
        }
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('operation_actions', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->boolean('is_active')->default(true)->index();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['name']);
        });

        Schema::create('operation_garment_parts', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->boolean('is_active')->default(true)->index();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['name']);
        });

        $this->migrateSettingsCatalogToTable(
            ['operation_actions', 'acciones_operacion', 'acciones_de_operacion', 'acciones_operativas'],
            'operation_actions'
        );

        $this->migrateSettingsCatalogToTable(
            ['operation_garment_parts', 'partes_prenda_operacion', 'partes_de_prenda_operacion', 'partes_operacion'],
            'operation_garment_parts'
        );

        DB::table('settings')
            ->where('kind', 'parameter')
            ->whereIn('key', [
                'operation_actions',
                'acciones_operacion',
                'acciones_de_operacion',
                'acciones_operativas',
                'operation_garment_parts',
                'partes_prenda_operacion',
                'partes_de_prenda_operacion',
                'partes_operacion',
            ])
            ->delete();
    }

    public function down(): void
    {
        Schema::dropIfExists('operation_garment_parts');
        Schema::dropIfExists('operation_actions');
    }

    private function migrateSettingsCatalogToTable(array $keys, string $table): void
    {
        $rows = DB::table('settings')
            ->where('kind', 'parameter')
            ->whereIn('key', $keys)
            ->get(['value']);

        $names = [];

        foreach ($rows as $row) {
            $value = $row->value;

            if (is_string($value)) {
                $decoded = json_decode($value, true);
                $value = json_last_error() === JSON_ERROR_NONE ? $decoded : [];
            }

            if (!is_array($value)) {
                continue;
            }

            foreach ($value as $item) {
                $name = null;

                if (is_array($item)) {
                    $name = $item['name'] ?? null;
                } elseif (is_scalar($item)) {
                    $name = (string) $item;
                }

                $normalized = mb_strtoupper(trim((string) $name));
                if ($normalized === '') {
                    continue;
                }

                $names[$normalized] = $normalized;
            }
        }

        $now = now();
        foreach (array_values($names) as $name) {
            DB::table($table)->updateOrInsert(
                ['name' => $name],
                [
                    'is_active' => true,
                    'updated_at' => $now,
                    'created_at' => $now,
                    'deleted_at' => null,
                ]
            );
        }
    }
};

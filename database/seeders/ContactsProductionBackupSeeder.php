<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Restaura el catálogo real de contactos/terceros desde el backup de
 * producción (dump del 2026-09-08). A diferencia de LegacyContactsSeeder,
 * estos datos ya están en el formato final de la tabla `contacts` (columnas
 * y JSON ya resueltos) — no pasan por LegacyContactsMigrationService.
 *
 * Fuente: database/data/contacts_production_backup.csv (1242 contactos).
 * Upsert por `id_number`: reejecutar no duplica; si el contacto ya existe
 * (p. ej. porque se editó manualmente después) se actualiza con estos datos.
 */
class ContactsProductionBackupSeeder extends Seeder
{
    private const JSON_COLUMNS = ['groups', 'additional_contacts', 'branches'];

    public function run(): void
    {
        $csvPath = database_path('data/contacts_production_backup.csv');

        if (! file_exists($csvPath)) {
            $this->command?->warn("ContactsProductionBackupSeeder: no se encontró {$csvPath}, se omite.");

            return;
        }

        $handle = fopen($csvPath, 'r');

        $bom = fread($handle, 3);
        if ($bom !== "\xEF\xBB\xBF") {
            rewind($handle);
        }

        $headers = fgetcsv($handle);
        $inserted = 0;
        $updated = 0;
        $skipped = 0;

        while (($row = fgetcsv($handle)) !== false) {
            $data = array_combine($headers, $row);

            $idNumber = trim((string) ($data['id_number'] ?? ''));
            if ($idNumber === '' || trim((string) ($data['full_name'] ?? '')) === '') {
                $skipped++;

                continue;
            }

            $payload = $this->buildPayload($data);

            $exists = DB::table('contacts')->where('id_number', $idNumber)->exists();

            if ($exists) {
                DB::table('contacts')->where('id_number', $idNumber)->update($payload);
                $updated++;
            } else {
                $payload['id_number'] = $idNumber;
                $payload['created_at'] = $data['created_at'] !== '' ? $data['created_at'] : now();
                DB::table('contacts')->insert($payload);
                $inserted++;
            }
        }

        fclose($handle);

        $this->command?->info("ContactsProductionBackupSeeder: {$inserted} creados, {$updated} actualizados, {$skipped} omitidos.");
    }

    /**
     * @param  array<string, string>  $data
     * @return array<string, mixed>
     */
    private function buildPayload(array $data): array
    {
        $payload = [
            'contact_type' => $data['contact_type'],
            'id_type' => $data['id_type'],
            'dv' => $data['dv'] !== '' ? $data['dv'] : null,
            'full_name' => $data['full_name'],
            'person_type' => $data['person_type'],
            'municipality' => $data['municipality'] !== '' ? $data['municipality'] : null,
            'department' => $data['department'] !== '' ? $data['department'] : null,
            'address' => $data['address'] !== '' ? $data['address'] : null,
            'email' => $data['email'],
            'phone' => $data['phone'] !== '' ? $data['phone'] : null,
            'updated_at' => $data['updated_at'] !== '' ? $data['updated_at'] : now(),
            'deleted_at' => $data['deleted_at'] !== '' ? $data['deleted_at'] : null,
        ];

        foreach (self::JSON_COLUMNS as $column) {
            $raw = $data[$column] ?? '';
            $decoded = $raw !== '' ? json_decode($raw, true) : null;
            $payload[$column] = json_encode(is_array($decoded) ? $decoded : []);
        }

        return $payload;
    }
}

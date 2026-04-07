<?php

namespace Database\Seeders;

use App\Models\Expenses\PurchaseDocument;
use App\Models\Textil\FabricRoll;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Importa los 294 rollos faltantes desde db/rollos abril.csv
 * (mismo formato que FabricRollsLegacySeeder, sin encabezado).
 *
 * Es idempotente: omite roll_numbers ya existentes.
 *
 * Ejecutar:
 *   php artisan db:seed --class=FabricRollsAbrilSeeder
 */
class FabricRollsAbrilSeeder extends Seeder
{
    private const COMPANY_NIT = '901064600-2';

    public function run(): void
    {
        $csvPath = base_path('db/rollos abril.csv');

        if (! file_exists($csvPath)) {
            $this->command->error("CSV no encontrado en: {$csvPath}");
            return;
        }

        $tax19Id = DB::table('taxes')->where('name', 'IVA 19%')->value('id')
            ?? DB::table('taxes')->where('name', 'Exento')->value('id')
            ?? DB::table('taxes')->value('id');

        $telaSubGroupId = DB::table('category_sub_groups as cs')
            ->join('category_groups as cg', 'cs.category_group_id', '=', 'cg.id')
            ->where('cg.name', 'Materia Prima')
            ->where('cs.name', 'Tela')
            ->value('cs.id');

        if (! $telaSubGroupId || ! $tax19Id) {
            $this->command->error('Requisitos faltantes: subgrupo "Tela" o impuesto.');
            return;
        }

        $rows = $this->parseCsv($csvPath);
        $this->command->info("Total filas en CSV: " . count($rows));

        // Filtrar ya existentes
        $existing = DB::table('fabric_rolls')->pluck('roll_number')->flip()->all();
        $rows = array_values(array_filter($rows, fn ($r) => ! isset($existing[$r['roll_number']])));
        $rows = $this->deduplicateRollNumbers($rows);

        $toImport = count($rows);
        $this->command->info("Rollos a importar: {$toImport}");

        if ($toImport === 0) {
            $this->command->warn('No hay rollos nuevos para importar.');
            return;
        }

        DB::transaction(function () use ($rows, $tax19Id, $telaSubGroupId) {
            $contactMap = $this->createContacts($rows);
            $this->command->info('Proveedores: ' . count($contactMap));

            $itemMap = $this->createItems($rows, $telaSubGroupId, $tax19Id);
            $this->command->info('Artículos: ' . count($itemMap));

            [$docCount, $lineCount, $rollCount] = $this->createDocumentsAndRolls($rows, $contactMap, $itemMap);
            $this->command->info("Documentos: {$docCount} | Líneas: {$lineCount} | Rollos: {$rollCount}");
        });

        $this->command->info('✓ Importación "rollos abril" completada.');
    }

    private function parseCsv(string $path): array
    {
        $rows = [];
        $handle = fopen($path, 'r');

        while (($data = fgetcsv($handle)) !== false) {
            if (count($data) < 18) {
                continue;
            }

            $rollNumber = trim($data[12] ?? '');
            if ($rollNumber === '') {
                continue;
            }

            $partialRaw = trim($data[18] ?? 'NULL');
            $partial    = ($partialRaw === 'NULL' || $partialRaw === '') ? 0 : (float) $partialRaw;

            $rows[] = [
                'legacy_id'           => (int) preg_replace('/[^\d]/', '', (string) ($data[0] ?? '0')),
                'supplier'            => $this->cleanStr($data[1]),
                'order_number'        => $this->cleanStr($data[2]),
                'base_fabric'         => $this->cleanStr($data[3]),
                'fabric_name'         => $this->cleanStr($data[4]),
                'color'               => $this->cleanStr($data[5]),
                'color_code'          => $this->cleanStr($data[6]),
                'quantity'            => (float) trim($data[7]),
                'width'               => (float) trim($data[8]),
                'movement_type'       => $this->cleanStr($data[9]),
                'location'            => $this->cleanStr($data[10]),
                'observation'         => $this->cleanStr($data[11]),
                'roll_number'         => $rollNumber,
                'price'               => (float) trim($data[13]),
                'internal_color'      => $this->cleanStr($data[14]),
                'internal_color_code' => $this->cleanStr($data[15]),
                'entry_date'          => $this->cleanStr($data[16]),
                'entered_by'          => $this->cleanStr($data[17]),
                'partial_output'      => $partial,
                'item_reference'      => $this->cleanStr($data[19] ?? ''),
            ];
        }

        fclose($handle);

        return $rows;
    }

    private function cleanStr(string $value): string
    {
        return trim(preg_replace('/\s+/', ' ', $value));
    }

    private function deduplicateRollNumbers(array $rows): array
    {
        $seen = [];

        foreach ($rows as &$row) {
            $rn = $row['roll_number'];
            if (isset($seen[$rn])) {
                $seen[$rn]++;
                $row['roll_number'] = $rn . '-DUP' . $seen[$rn];
            } else {
                $seen[$rn] = 1;
            }
        }
        unset($row);

        return $rows;
    }

    private function createContacts(array $rows): array
    {
        $suppliers = collect($rows)
            ->pluck('supplier')
            ->unique()
            ->filter(fn ($s) => $s !== '' && strtoupper($s) !== 'NA')
            ->values();

        $contactMap = [];
        $counter    = DB::table('contacts')->where('id_number', 'like', 'LEG-%')->count();

        foreach ($suppliers as $name) {
            $existingId = DB::table('contacts')->where('full_name', $name)->value('id');

            if ($existingId) {
                $contactMap[$name] = $existingId;
                continue;
            }

            $counter++;
            $idNumber = 'LEG-' . str_pad($counter, 5, '0', STR_PAD_LEFT);
            while (DB::table('contacts')->where('id_number', $idNumber)->exists()) {
                $counter++;
                $idNumber = 'LEG-' . str_pad($counter, 5, '0', STR_PAD_LEFT);
            }

            $email = 'legacy-proveedor-' . $counter . '@importacion.local';
            while (DB::table('contacts')->where('email', $email)->exists()) {
                $counter++;
                $email = 'legacy-proveedor-' . $counter . '@importacion.local';
            }

            $now = now();
            $contactMap[$name] = DB::table('contacts')->insertGetId([
                'contact_type' => 'proveedor',
                'id_type'      => 'NIT',
                'id_number'    => $idNumber,
                'dv'           => null,
                'full_name'    => $name,
                'person_type'  => 'jurídica',
                'groups'       => null,
                'municipality' => null,
                'department'   => null,
                'address'      => null,
                'email'        => $email,
                'phone'        => null,
                'additional_contacts' => null,
                'branches'     => null,
                'created_at'   => $now,
                'updated_at'   => $now,
            ]);
        }

        return $contactMap;
    }

    private function createItems(array $rows, int $subGroupId, int $taxId): array
    {
        $seen    = [];
        $itemMap = [];
        $now     = now();

        foreach ($rows as $row) {
            $key = $row['base_fabric'] . '|' . $row['fabric_name'];
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;

            $existingId = null;

            if (! empty($row['item_reference'])) {
                $existingId = DB::table('items')
                    ->where('reference', $row['item_reference'])
                    ->value('id');
            }

            if (! $existingId) {
                $existingId = DB::table('items')
                    ->where('name', $row['fabric_name'])
                    ->where('description', $row['base_fabric'])
                    ->where('category_sub_group_id', $subGroupId)
                    ->value('id');
            }

            if ($existingId) {
                $itemMap[$key] = $existingId;
                continue;
            }

            $itemMap[$key] = DB::table('items')->insertGetId([
                'category_sub_group_id' => $subGroupId,
                'name'                  => $row['fabric_name'],
                'description'           => $row['base_fabric'],
                'tax_id'                => $taxId,
                'item_type'             => 'product',
                'unit_measure'          => 'Kg',
                'status'                => true,
                'negative_movement'     => false,
                'extra_fields'          => null,
                'reference'             => null,
                'bar_code'              => null,
                'img'                   => null,
                'created_at'            => $now,
                'updated_at'            => $now,
            ]);
        }

        return $itemMap;
    }

    private function createDocumentsAndRolls(array $rows, array $contactMap, array $itemMap): array
    {
        $docGroups = [];
        foreach ($rows as $row) {
            $key = $row['supplier'] . '||' . $row['order_number'];
            $docGroups[$key][] = $row;
        }

        $now         = now();
        $docCount    = 0;
        $lineCount   = 0;
        $rollCount   = 0;
        $rollsBatch  = [];
        $docSequence = $this->nextImportDocumentSequence();

        foreach ($docGroups as $groupKey => $groupRows) {
            [$supplier, $orderNumber] = explode('||', $groupKey, 2);

            $contactId    = $contactMap[$supplier] ?? null;
            $isRealOrder  = $orderNumber !== self::COMPANY_NIT && $orderNumber !== '';
            $earliestDate = $this->earliestDate($groupRows);

            $docNumber = 'IMP-' . str_pad($docSequence, 5, '0', STR_PAD_LEFT);
            $docSequence++;
            $docCount++;

            $docId = DB::table('purchase_documents')->insertGetId([
                'document_number'         => $docNumber,
                'document_type'           => PurchaseDocument::TYPE_INVOICE,
                'parent_document_id'      => null,
                'purchase_request_id'     => null,
                'contact_id'              => $contactId,
                'provider_name'           => $contactId ? null : $supplier,
                'provider_phone'          => null,
                'date'                    => $earliestDate,
                'delivery_date'           => $earliestDate,
                'description'             => 'Importación abril — ' . $supplier
                    . ($isRealOrder ? " (Pedido: {$orderNumber})" : ''),
                'created_by_user_id'      => null,
                'executed_by_user_id'     => null,
                'status'                  => 'received',
                'approval_status'         => 'approved',
                'supplier_order_number'   => $isRealOrder ? $orderNumber : null,
                'supplier_invoice_number' => null,
                'withholdings'            => null,
                'created_at'              => $now,
                'updated_at'              => $now,
            ]);

            $lineGroups = [];
            foreach ($groupRows as $row) {
                $lk = $row['base_fabric'] . '|' . $row['fabric_name']
                    . '|' . $row['color'] . '|' . $row['color_code'] . '|' . $row['price'];
                $lineGroups[$lk][] = $row;
            }

            foreach ($lineGroups as $lineRows) {
                $first    = $lineRows[0];
                $fabricKey = $first['base_fabric'] . '|' . $first['fabric_name'];
                $itemId    = $itemMap[$fabricKey] ?? null;
                $totalQty  = array_sum(array_column($lineRows, 'quantity'));

                $lineCount++;
                $docItemId = DB::table('purchase_document_items')->insertGetId([
                    'purchase_document_id' => $docId,
                    'item_id'              => $itemId,
                    'item_name'            => $first['fabric_name'],
                    'quantity'             => round($totalQty, 3),
                    'price'                => $first['price'],
                    'tax'                  => 0,
                    'total'                => round($totalQty * $first['price'], 2),
                    'total_tax'            => 0,
                    'supplier_code'        => $first['color_code'],
                    'supplier_color'       => $first['color'],
                    'order_width'          => $first['width'],
                    'description'          => $first['base_fabric'] . ' — ' . $first['fabric_name'],
                    'created_at'           => $now,
                    'updated_at'           => $now,
                ]);

                foreach ($lineRows as $row) {
                    $obs       = ($row['observation'] !== '' && strtoupper($row['observation']) !== 'NA') ? $row['observation'] : null;
                    $enteredBy = ($row['entered_by'] !== '' && strtolower($row['entered_by']) !== 'name') ? $row['entered_by'] : null;

                    $rollsBatch[] = [
                        'legacy_id'                 => $row['legacy_id'],
                        'roll_number'               => $row['roll_number'],
                        'purchase_document_item_id' => $docItemId,
                        'quantity'                  => $row['quantity'],
                        'roll_width'                => $row['width'],
                        'location'                  => $row['location'] !== '' ? $row['location'] : null,
                        'description'               => $obs,
                        'internal_color_name'       => $row['internal_color'] !== '' ? $row['internal_color'] : null,
                        'internal_color_code'       => $row['internal_color_code'] !== '' ? $row['internal_color_code'] : null,
                        'movement_type'             => $row['movement_type'] !== '' ? $row['movement_type'] : null,
                        'partial_output'            => $row['partial_output'],
                        'actual_output'             => $row['partial_output'],
                        'entry_date'                => $this->parseDate($row['entry_date']),
                        'approval_status'           => FabricRoll::APPROVAL_APPROVED,
                        'created_at'                => $now,
                        'updated_at'                => $now,
                    ];

                    if (count($rollsBatch) >= 500) {
                        $inserted = DB::table('fabric_rolls')->insertOrIgnore($rollsBatch);
                        $rollCount += $inserted;
                        $rollsBatch = [];
                    }
                }
            }
        }

        if (! empty($rollsBatch)) {
            $inserted = DB::table('fabric_rolls')->insertOrIgnore($rollsBatch);
            $rollCount += $inserted;
        }

        return [$docCount, $lineCount, $rollCount];
    }

    private function parseDate(string $raw): string
    {
        try {
            $date = Carbon::parse($raw);
            return $date->year < 2000 ? now()->toDateString() : $date->toDateString();
        } catch (\Exception) {
            return now()->toDateString();
        }
    }

    private function earliestDate(array $rows): string
    {
        $earliest = null;

        foreach ($rows as $row) {
            try {
                $date = Carbon::parse($row['entry_date']);
                if ($date->year < 2000) {
                    continue;
                }
                if (! $earliest || $date->lt($earliest)) {
                    $earliest = $date;
                }
            } catch (\Exception) {
                continue;
            }
        }

        return ($earliest ?? now())->toDateString();
    }

    private function nextImportDocumentSequence(): int
    {
        $maxSequence = DB::table('purchase_documents')
            ->where('document_number', 'like', 'IMP-%')
            ->pluck('document_number')
            ->map(fn (?string $d) => $d ? (int) preg_replace('/[^\d]/', '', $d) : 0)
            ->max();

        return ((int) $maxSequence) + 1;
    }
}

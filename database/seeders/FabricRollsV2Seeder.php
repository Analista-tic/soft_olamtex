<?php

namespace Database\Seeders;

use App\Models\Expenses\PurchaseDocument;
use App\Models\Textil\FabricRoll;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Seeder para importar rollos de tela desde db/rollos_completo_v2.csv.
 *
 * Columnas CSV (por posición):
 *   0:ROLLO, 1:TELA, 2:BASE TELA, 3:COLOR, 4:COD COLOR,
 *   5:KG, 6:ANCHO (m), 7:PROVEEDOR, 8:PRECIO, 9:UBICACIÓN,
 *   10:FECHA, 11:SALIDA, 12:REF ITEM, 13:NOMBRE ITEM,
 *   14:FUENTE MAPEO, 15:FUENTE NOMBRE, 16:ESTADO
 *
 * Relaciona cada rollo con su Item vía items.reference = REF ITEM.
 * Para rollos sin REF ITEM, intenta buscar por nombre o crea el item.
 *
 * Ejecutar:
 *   php artisan db:seed --class=FabricRollsV2Seeder
 */
class FabricRollsV2Seeder extends Seeder
{
    public function run(): void
    {
        $csvPath = base_path('db/rollos_completo_v2.csv');

        if (! file_exists($csvPath)) {
            $this->command->warn("FabricRollsV2Seeder: CSV no encontrado en {$csvPath}. Se omite importación legacy.");

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
            $this->command->error(
                'Requisitos faltantes: subgrupo "Tela" (Materia Prima) o impuesto. '
                . 'Ejecute CategorySeeder y TaxesSeeder primero.'
            );
            return;
        }

        $rows = $this->parseCsv($csvPath);
        $totalRows = count($rows);
        $this->command->info("Total filas en CSV: {$totalRows}");

        // Filtrar rollos ya existentes (idempotente)
        $existingRollNumbers = DB::table('fabric_rolls')->pluck('roll_number')->flip()->all();
        $rows = array_values(array_filter($rows, fn ($r) => ! isset($existingRollNumbers[$r['roll_number']])));

        $rows = $this->deduplicateRollNumbers($rows);

        $toImport = count($rows);
        $this->command->info("Rollos a importar: {$toImport}");

        if ($toImport === 0) {
            $this->command->warn('Todos los rollos ya están importados.');
            return;
        }

        DB::transaction(function () use ($rows, $tax19Id, $telaSubGroupId) {
            $contactMap = $this->createContacts($rows);
            $this->command->info('Proveedores creados/encontrados: ' . count($contactMap));

            $itemMap = $this->resolveItems($rows, $telaSubGroupId, $tax19Id);
            $this->command->info('Artículos resueltos: ' . count($itemMap));

            [$docCount, $lineCount, $rollCount] = $this->createDocumentsAndRolls($rows, $contactMap, $itemMap);
            $this->command->info("Documentos de compra: {$docCount}");
            $this->command->info("Líneas de documento: {$lineCount}");
            $this->command->info("Rollos creados: {$rollCount}");
        });

        $this->command->info('✓ Importación v2 de rollos completada.');
    }

    // ─── Parseo ──────────────────────────────────────────────────────────────

    private function parseCsv(string $path): array
    {
        $rows = [];
        $handle = fopen($path, 'r');
        $firstLine = true;

        while (($data = fgetcsv($handle)) !== false) {
            // Saltar encabezado
            if ($firstLine) {
                $firstLine = false;
                continue;
            }

            if (count($data) < 11) {
                continue;
            }

            $rollNumber = $this->cleanStr($data[0] ?? '');
            if ($rollNumber === '') {
                continue;
            }

            $rows[] = [
                'roll_number'    => $rollNumber,
                'fabric_name'    => $this->cleanStr($data[1] ?? ''),
                'base_fabric'    => $this->cleanStr($data[2] ?? ''),
                'color'          => $this->cleanStr($data[3] ?? ''),
                'color_code'     => $this->cleanStr($data[4] ?? ''),
                'quantity'       => (float) ($data[5] ?? 0),
                'width'          => (float) ($data[6] ?? 0),
                'supplier'       => $this->cleanStr($data[7] ?? ''),
                'price'          => (float) ($data[8] ?? 0),
                'location'       => $this->cleanStr($data[9] ?? ''),
                'entry_date'     => $this->cleanStr($data[10] ?? ''),
                'actual_output'  => ($data[11] ?? '') !== '' ? (float) $data[11] : 0,
                'item_reference' => $this->cleanStr($data[12] ?? ''),
                'item_name'      => $this->cleanStr($data[13] ?? ''),
                'estado'         => $this->cleanStr($data[16] ?? ''),
            ];
        }

        fclose($handle);

        return $rows;
    }

    private function cleanStr(string $value): string
    {
        return trim(preg_replace('/\s+/', ' ', $value));
    }

    // ─── Deduplicación ───────────────────────────────────────────────────────

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

    // ─── Contactos ───────────────────────────────────────────────────────────

    private function createContacts(array $rows): array
    {
        $suppliers = collect($rows)
            ->pluck('supplier')
            ->unique()
            ->filter(fn ($s) => $s !== '')
            ->values();

        $contactMap = [];
        $counter = DB::table('contacts')->where('id_number', 'like', 'LEG-%')->count();

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

            $email = 'leg-prov-' . $counter . '@importacion.local';
            while (DB::table('contacts')->where('email', $email)->exists()) {
                $counter++;
                $email = 'leg-prov-' . $counter . '@importacion.local';
            }

            $now = now();
            $contactMap[$name] = DB::table('contacts')->insertGetId([
                'contact_type' => 'proveedor',
                'id_type'      => 'NIT',
                'id_number'    => $idNumber,
                'dv'           => null,
                'full_name'    => $name,
                'person_type'  => 'jurídica',
                'email'        => $email,
                'created_at'   => $now,
                'updated_at'   => $now,
            ]);
        }

        return $contactMap;
    }

    // ─── Resolución de items ─────────────────────────────────────────────────

    /**
     * Devuelve mapa: roll_number → item_id
     * Prioridad: referencia exacta → nombre+descripción → crear nuevo
     */
    private function resolveItems(array $rows, int $subGroupId, int $taxId): array
    {
        $itemMap = [];
        $now = now();

        // Pre-cargar todos los items con referencia del CSV para evitar N+1
        $references = collect($rows)
            ->pluck('item_reference')
            ->filter(fn ($r) => $r !== '')
            ->unique()
            ->values()
            ->all();

        $itemsByRef = DB::table('items')
            ->whereIn('reference', $references)
            ->pluck('id', 'reference')
            ->all();

        // Cache para items creados por nombre (evitar duplicados)
        $createdByName = [];

        foreach ($rows as $row) {
            $rollNumber = $row['roll_number'];
            $ref = $row['item_reference'];

            // 1. Buscar por referencia exacta
            if ($ref !== '' && isset($itemsByRef[$ref])) {
                $itemMap[$rollNumber] = $itemsByRef[$ref];
                continue;
            }

            // 2. Fallback: buscar o crear por nombre de item del CSV
            $nameKey = $row['item_name'] !== '' ? $row['item_name'] : ($row['fabric_name'] . ' ' . $row['base_fabric']);

            if (isset($createdByName[$nameKey])) {
                $itemMap[$rollNumber] = $createdByName[$nameKey];
                continue;
            }

            $existingId = DB::table('items')
                ->where('name', $nameKey)
                ->where('category_sub_group_id', $subGroupId)
                ->value('id');

            if ($existingId) {
                $createdByName[$nameKey] = $existingId;
                $itemMap[$rollNumber] = $existingId;
                continue;
            }

            // 3. Crear item nuevo
            $newId = DB::table('items')->insertGetId([
                'category_sub_group_id' => $subGroupId,
                'name'                  => $nameKey,
                'description'           => $row['base_fabric'],
                'reference'             => $ref !== '' ? $ref : null,
                'tax_id'                => $taxId,
                'item_type'             => 'product',
                'unit_measure'          => 'Kg',
                'status'                => true,
                'negative_movement'     => false,
                'extra_fields'          => null,
                'bar_code'              => null,
                'img'                   => null,
                'created_at'            => $now,
                'updated_at'            => $now,
            ]);

            $createdByName[$nameKey] = $newId;
            if ($ref !== '') {
                $itemsByRef[$ref] = $newId;
            }
            $itemMap[$rollNumber] = $newId;
        }

        return $itemMap;
    }

    // ─── Documentos, líneas y rollos ─────────────────────────────────────────

    /**
     * Agrupa por (proveedor, año-mes) → PurchaseDocument
     * Luego por (item, color, código, precio) → PurchaseDocumentItem
     * Finalmente inserta FabricRoll vinculado al item.
     */
    private function createDocumentsAndRolls(array $rows, array $contactMap, array $itemMap): array
    {
        // Agrupar por (proveedor, año-mes de entrada)
        $docGroups = [];
        foreach ($rows as $row) {
            $parsedDate = $this->parseDate($row['entry_date']);
            $yearMonth  = substr($parsedDate, 0, 7); // YYYY-MM
            $key        = $row['supplier'] . '||' . $yearMonth;
            $docGroups[$key][] = $row;
        }

        $now          = now();
        $docCount     = 0;
        $lineCount    = 0;
        $rollCount    = 0;
        $rollsBatch   = [];
        $docSequence  = $this->nextImportDocumentSequence();

        foreach ($docGroups as $groupKey => $groupRows) {
            [$supplier, $yearMonth] = explode('||', $groupKey, 2);

            $contactId = $contactMap[$supplier] ?? null;
            $docDate   = $this->earliestDate($groupRows);

            $docNumber = 'IMP2-' . str_pad($docSequence, 5, '0', STR_PAD_LEFT);
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
                'date'                    => $docDate,
                'delivery_date'           => $docDate,
                'description'             => "Importación v2 — {$supplier} ({$yearMonth})",
                'created_by_user_id'      => null,
                'executed_by_user_id'     => null,
                'status'                  => 'received',
                'approval_status'         => 'approved',
                'supplier_order_number'   => null,
                'supplier_invoice_number' => null,
                'withholdings'            => null,
                'created_at'              => $now,
                'updated_at'              => $now,
            ]);

            // Sub-agrupar por (item, color, código, precio) → línea de documento
            $lineGroups = [];
            foreach ($groupRows as $row) {
                $itemId   = $itemMap[$row['roll_number']] ?? null;
                $lineKey  = ($itemId ?? 0) . '|' . $row['color'] . '|' . $row['color_code'] . '|' . $row['price'];
                $lineGroups[$lineKey][] = $row;
            }

            foreach ($lineGroups as $lineRows) {
                $first    = $lineRows[0];
                $itemId   = $itemMap[$first['roll_number']] ?? null;
                $totalQty = array_sum(array_column($lineRows, 'quantity'));
                $price    = $first['price'];

                $lineCount++;
                $docItemId = DB::table('purchase_document_items')->insertGetId([
                    'purchase_document_id' => $docId,
                    'item_id'              => $itemId,
                    'item_name'            => $first['item_name'] !== '' ? $first['item_name'] : $first['fabric_name'],
                    'quantity'             => round($totalQty, 3),
                    'price'                => $price,
                    'tax'                  => 0,
                    'total'                => round($totalQty * $price, 2),
                    'total_tax'            => 0,
                    'supplier_code'        => $first['color_code'],
                    'supplier_color'       => $first['color'],
                    'order_width'          => $first['width'],
                    'description'          => $first['base_fabric'] . ' — ' . $first['fabric_name'],
                    'created_at'           => $now,
                    'updated_at'           => $now,
                ]);

                foreach ($lineRows as $row) {
                    $rollsBatch[] = [
                        'legacy_id'                 => null,
                        'roll_number'               => $row['roll_number'],
                        'purchase_document_item_id' => $docItemId,
                        'quantity'                  => $row['quantity'],
                        'roll_width'                => $row['width'] > 0 ? $row['width'] : null,
                        'location'                  => $row['location'] !== '' ? $row['location'] : null,
                        'description'               => null,
                        'internal_color_name'       => $row['color'] !== '' ? $row['color'] : null,
                        'internal_color_code'       => $row['color_code'] !== '' ? $row['color_code'] : null,
                        'movement_type'             => null,
                        'partial_output'            => $row['actual_output'],
                        'actual_output'             => $row['actual_output'],
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

    // ─── Utilidades ──────────────────────────────────────────────────────────

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
            ->where('document_number', 'like', 'IMP2-%')
            ->pluck('document_number')
            ->map(fn (?string $d) => $d ? (int) preg_replace('/[^\d]/', '', $d) : 0)
            ->max();

        return ((int) $maxSequence) + 1;
    }
}

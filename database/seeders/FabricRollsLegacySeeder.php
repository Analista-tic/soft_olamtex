<?php

namespace Database\Seeders;

use App\Models\Expenses\PurchaseDocument;
use App\Models\Textil\FabricRoll;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Seeder para importar rollos de tela desde el CSV legacy (db/rollos.csv).
 *
 * Cadena de datos creada:
 *   Contact (proveedor)
 *     └─ PurchaseDocument (factura legacy)
 *          └─ PurchaseDocumentItem (línea de compra)
 *               └─ FabricRoll (rollo de tela)
 *
 *   Item (artículo de tela, subgrupo "Tela" > grupo "Materia Prima")
 *
 * Columnas CSV (por posición):
 *   0:ID, 1:PROVEEDOR, 2:NPEDIDO, 3:BASETELA, 4:TELA, 5:COLOR,
 *   6:CODIGOCOLOR, 7:CANTIDAD, 8:ANCHO, 9:TIPOMOVIMIENTO, 10:UBICACION,
 *   11:OBSERVACION, 12:ROLLO, 13:PRECIO, 14:COLORINT, 15:CODIGOCOLORINT,
 *   16:FECHA_INGRESO, 17:NOMBRE_INGRESA, 18:SALIDA_PARCIAL
 *
 *  Ejecutar:
 *    php artisan db:seed --class=FabricRollsLegacySeeder
 */
class FabricRollsLegacySeeder extends Seeder
{
    /** NIT de la empresa (se usa como valor por defecto en NPEDIDO cuando no hay # de pedido real) */
    private const COMPANY_NIT = '901064600-2';

    public function run(): void
    {
        $csvPath = base_path('db/rollos.csv');

        if (! file_exists($csvPath)) {
            $this->command->warn("FabricRollsLegacySeeder: CSV no encontrado en {$csvPath}. Se omite importación legacy.");

            return;
        }

        // ── Referencias requeridas ────────────────────────────────────────
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

        // ── Parsear CSV ───────────────────────────────────────────────────
        $rows = $this->parseCsv($csvPath);
        $totalRows = count($rows);
        $this->command->info("Total filas en CSV: {$totalRows}");

        // Filtrar solo rollos con cantidad disponible > 0
        $rows = array_values(array_filter($rows, function (array $row): bool {
            $available = $row['quantity'] - $row['partial_output'];

            return $row['quantity'] > 0 && $available > 0;
        }));

        // Deduplicar roll_number dentro del CSV
        $rows = $this->deduplicateRollNumbers($rows);

        $availableCount = count($rows);
        $this->command->info("Rollos con cantidad disponible: {$availableCount}");

        if ($availableCount === 0) {
            $this->command->warn('No hay rollos con cantidad disponible para importar.');

            return;
        }

        // Hacer el seeder idempotente: omitir rollos ya existentes
        $existingRollNumbers = DB::table('fabric_rolls')->pluck('roll_number')->all();
        $existingRollLookup = array_fill_keys($existingRollNumbers, true);

        $rows = array_values(array_filter($rows, function (array $row) use ($existingRollLookup): bool {
            return ! isset($existingRollLookup[$row['roll_number']]);
        }));

        $rowsToImportCount = count($rows);
        $skippedExisting = $availableCount - $rowsToImportCount;

        if ($skippedExisting > 0) {
            $this->command->info("Rollos ya existentes omitidos: {$skippedExisting}");
        }

        if ($rowsToImportCount === 0) {
            $this->command->warn('Todos los rollos disponibles del CSV ya están importados.');

            return;
        }

        DB::transaction(function () use ($rows, $tax19Id, $telaSubGroupId) {
            // 1. Crear proveedores (contacts)
            $contactMap = $this->createContacts($rows);
            $this->command->info('Proveedores creados/encontrados: ' . count($contactMap));

            // 2. Crear artículos de tela (items)
            $itemMap = $this->createItems($rows, $telaSubGroupId, $tax19Id);
            $this->command->info('Artículos de tela creados/encontrados: ' . count($itemMap));

            // 3. Crear documentos de compra, líneas e insertar rollos
            [$docCount, $itemCount, $rollCount] = $this->createDocumentsAndRolls($rows, $contactMap, $itemMap);
            $this->command->info("Documentos de compra creados: {$docCount}");
            $this->command->info("Líneas de documento creadas: {$itemCount}");
            $this->command->info("Rollos de tela creados: {$rollCount}");
        });

        $this->command->info('✓ Importación legacy de rollos completada.');
    }

    // ═══════════════════════════════════════════════════════════════════════
    //  Deduplicación de roll_number
    // ═══════════════════════════════════════════════════════════════════════

    /**
     * Si hay filas con roll_number duplicado, renombra las ocurrencias
     * posteriores añadiendo sufijo -DUP2, -DUP3, etc.
     */
    private function deduplicateRollNumbers(array $rows): array
    {
        $seen = [];

        foreach ($rows as &$row) {
            $rollNumber = $row['roll_number'];

            if (isset($seen[$rollNumber])) {
                $seen[$rollNumber]++;
                $row['roll_number'] = $rollNumber . '-DUP' . $seen[$rollNumber];
            } else {
                $seen[$rollNumber] = 1;
            }
        }
        unset($row);

        return $rows;
    }

    // ═══════════════════════════════════════════════════════════════════════
    //  Parseo del CSV
    // ═══════════════════════════════════════════════════════════════════════

    private function parseCsv(string $path): array
    {
        $rows = [];
        $handle = fopen($path, 'r');

        while (($data = fgetcsv($handle)) !== false) {
            if (count($data) < 18) {
                continue;
            }

            $partialRaw = trim($data[18] ?? 'NULL');
            $partial = ($partialRaw === 'NULL' || $partialRaw === '') ? 0 : (float) $partialRaw;

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
                'roll_number'         => $this->cleanStr($data[12]),
                'price'               => (float) trim($data[13]),
                'internal_color'      => $this->cleanStr($data[14]),
                'internal_color_code' => $this->cleanStr($data[15]),
                'entry_date'          => $this->cleanStr($data[16]),
                'entered_by'          => $this->cleanStr($data[17]),
                'partial_output'      => $partial,
                // Col 19: referencia del ítem de tela (generada por import:match-rolls-items)
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

    // ═══════════════════════════════════════════════════════════════════════
    //  Proveedores (contacts)
    // ═══════════════════════════════════════════════════════════════════════

    /**
     * Crea contactos de tipo proveedor para cada PROVEEDOR único del CSV.
     *
     * @return array<string, int> Mapa: nombre proveedor → contact.id
     */
    private function createContacts(array $rows): array
    {
        $uniqueSuppliers = collect($rows)
            ->pluck('supplier')
            ->unique()
            ->filter(fn (string $s) => $s !== '' && strtoupper($s) !== 'NA')
            ->values();

        $contactMap = [];
        $now = now();
        $counter = 0;

        foreach ($uniqueSuppliers as $supplierName) {
            // Buscar primero por nombre exacto (puede existir de otro seeder)
            $existingId = DB::table('contacts')
                ->where('full_name', $supplierName)
                ->value('id');

            if ($existingId) {
                $contactMap[$supplierName] = $existingId;

                continue;
            }

            $counter++;
            $idNumber = 'LEG-' . str_pad($counter, 5, '0', STR_PAD_LEFT);

            // Garantizar id_number único
            while (DB::table('contacts')->where('id_number', $idNumber)->exists()) {
                $counter++;
                $idNumber = 'LEG-' . str_pad($counter, 5, '0', STR_PAD_LEFT);
            }

            // Garantizar email único
            $email = 'legacy-proveedor-' . $counter . '@importacion.local';
            while (DB::table('contacts')->where('email', $email)->exists()) {
                $counter++;
                $email = 'legacy-proveedor-' . $counter . '@importacion.local';
            }

            $contactId = DB::table('contacts')->insertGetId([
                'contact_type'        => 'proveedor',
                'id_type'             => 'nit',
                'id_number'           => $idNumber,
                'dv'                  => null,
                'full_name'           => $supplierName,
                'person_type'         => 'persona_juridica',
                'groups'              => null,
                'municipality'        => null,
                'department'          => null,
                'address'             => null,
                'email'               => $email,
                'phone'               => null,
                'additional_contacts' => null,
                'branches'            => null,
                'created_at'          => $now,
                'updated_at'          => $now,
            ]);

            $contactMap[$supplierName] = $contactId;
        }

        return $contactMap;
    }

    // ═══════════════════════════════════════════════════════════════════════
    //  Artículos de tela (items)
    // ═══════════════════════════════════════════════════════════════════════

    /**
     * Crea Items para cada combinación única de BASETELA + TELA.
     *
     * @return array<string, int> Mapa: "BASETELA|TELA" → item.id
     */
    private function createItems(array $rows, int $subGroupId, int $taxId): array
    {
        $seen = [];
        $itemMap = [];
        $now = now();

        foreach ($rows as $row) {
            $key = $row['base_fabric'] . '|' . $row['fabric_name'];
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;

            // 1. Prioridad: buscar por referencia exacta (col 19 del CSV enriquecido)
            $existingId = null;
            if (! empty($row['item_reference'])) {
                $existingId = DB::table('items')
                    ->where('reference', $row['item_reference'])
                    ->where('category_sub_group_id', $subGroupId)
                    ->value('id');
            }

            // 2. Fallback: buscar por nombre + descripción (comportamiento original)
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

            $itemId = DB::table('items')->insertGetId([
                'category_sub_group_id' => $subGroupId,
                'name'                  => $row['fabric_name'],
                'description'           => $row['base_fabric'],
                'tax_id'                => $taxId,
                'item_type'             => 'product',
                'unit_measure'          => 'Metros',
                'status'                => true,
                'negative_movement'     => false,
                'extra_fields'          => null,
                'reference'             => null,
                'bar_code'              => null,
                'img'                   => null,
                'created_at'            => $now,
                'updated_at'            => $now,
            ]);

            $itemMap[$key] = $itemId;
        }

        return $itemMap;
    }

    // ═══════════════════════════════════════════════════════════════════════
    //  Documentos de compra, líneas y rollos
    // ═══════════════════════════════════════════════════════════════════════

    /**
     * Agrupa las filas del CSV por (proveedor, # pedido) → PurchaseDocument,
     * luego por (artículo, color, código, precio) → PurchaseDocumentItem,
     * y finalmente inserta cada FabricRoll.
     *
     * @return array{int, int, int} [documentos, líneas, rollos] creados
     */
    private function createDocumentsAndRolls(array $rows, array $contactMap, array $itemMap): array
    {
        // Agrupar filas por (proveedor, # pedido) → un PurchaseDocument por grupo
        $docGroups = [];
        foreach ($rows as $row) {
            $key = $row['supplier'] . '||' . $row['order_number'];
            $docGroups[$key][] = $row;
        }

        $now = now();
        $docCount = 0;
        $itemLineCount = 0;
        $rollCount = 0;
        $rollsBatch = [];
        $docSequence = $this->nextImportDocumentSequence();

        foreach ($docGroups as $groupKey => $groupRows) {
            [$supplier, $orderNumber] = explode('||', $groupKey, 2);

            $contactId = $contactMap[$supplier] ?? null;
            $isRealOrder = $orderNumber !== self::COMPANY_NIT && $orderNumber !== '';

            // Fecha del documento = fecha más antigua del grupo
            $earliestDate = $this->earliestDate($groupRows);

            $docNumber = 'IMP-' . str_pad($docSequence, 5, '0', STR_PAD_LEFT);
            $docSequence++;
            $docCount++;

            $docId = DB::table('purchase_documents')->insertGetId([
                'document_number'        => $docNumber,
                'document_type'          => PurchaseDocument::TYPE_INVOICE,
                'parent_document_id'     => null,
                'purchase_request_id'    => null,
                'contact_id'             => $contactId,
                'provider_name'          => $contactId ? null : $supplier,
                'provider_phone'         => null,
                'date'                   => $earliestDate,
                'delivery_date'          => $earliestDate,
                'description'            => 'Importación legacy de rollos — '
                    . $supplier
                    . ($isRealOrder ? " (Pedido: {$orderNumber})" : ''),
                'created_by_user_id'     => null,
                'executed_by_user_id'    => null,
                'status'                 => 'invoiced',
                'approval_status'        => 'approved',
                'supplier_order_number'  => $isRealOrder ? $orderNumber : null,
                'supplier_invoice_number' => null,
                'withholdings'           => null,
                'created_at'             => $now,
                'updated_at'             => $now,
            ]);

            // Sub-agrupar por (artículo, color, código color, precio) → una línea de documento
            $lineGroups = [];
            foreach ($groupRows as $row) {
                $lineKey = $row['base_fabric'] . '|' . $row['fabric_name']
                    . '|' . $row['color'] . '|' . $row['color_code'] . '|' . $row['price'];
                $lineGroups[$lineKey][] = $row;
            }

            foreach ($lineGroups as $lineRows) {
                $first = $lineRows[0];
                $fabricKey = $first['base_fabric'] . '|' . $first['fabric_name'];
                $itemId = $itemMap[$fabricKey] ?? null;

                $totalQty = array_sum(array_column($lineRows, 'quantity'));
                $price = $first['price'];

                $itemLineCount++;
                $docItemId = DB::table('purchase_document_items')->insertGetId([
                    'purchase_document_id' => $docId,
                    'item_id'              => $itemId,
                    'item_name'            => $first['fabric_name'],
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

                // Crear cada rollo vinculado a esta línea
                foreach ($lineRows as $row) {
                    $entryDate = $this->parseDate($row['entry_date']);
                    $obs = ($row['observation'] !== '' && strtoupper($row['observation']) !== 'NA')
                        ? $row['observation']
                        : null;
                    $enteredBy = ($row['entered_by'] !== '' && strtolower($row['entered_by']) !== 'name')
                        ? $row['entered_by']
                        : null;

                    $rollsBatch[] = [
                        'legacy_id'                => $row['legacy_id'],
                        'roll_number'              => $row['roll_number'],
                        'purchase_document_item_id' => $docItemId,
                        'quantity'                 => $row['quantity'],
                        'roll_width'               => $row['width'],
                        'location'                 => $row['location'] !== '' ? $row['location'] : null,
                        'description'              => $obs,
                        'internal_color_name'      => $row['internal_color'] !== '' ? $row['internal_color'] : null,
                        'internal_color_code'      => $row['internal_color_code'] !== '' ? $row['internal_color_code'] : null,
                        'movement_type'            => $row['movement_type'] !== '' ? $row['movement_type'] : null,
                        'partial_output'           => $row['partial_output'],
                        'actual_output'            => $row['partial_output'], // Legacy: salida real = salida parcial
                        'entry_date'               => $entryDate,
                        'approval_status'          => FabricRoll::APPROVAL_APPROVED,
                        'created_at'               => $now,
                        'updated_at'               => $now,
                    ];

                    // Insertar en lotes de 500 para eficiencia
                    if (count($rollsBatch) >= 500) {
                        $inserted = DB::table('fabric_rolls')->insertOrIgnore($rollsBatch);
                        $rollCount += $inserted;
                        $rollsBatch = [];
                    }
                }
            }
        }

        // Insertar rollos restantes
        if (! empty($rollsBatch)) {
            $inserted = DB::table('fabric_rolls')->insertOrIgnore($rollsBatch);
            $rollCount += $inserted;
        }

        return [$docCount, $itemLineCount, $rollCount];
    }

    // ═══════════════════════════════════════════════════════════════════════
    //  Utilidades
    // ═══════════════════════════════════════════════════════════════════════

    /**
     * Parsea la fecha del CSV. Si es inválida o anterior al año 2000, usa hoy.
     */
    private function parseDate(string $raw): string
    {
        try {
            $date = Carbon::parse($raw);

            // Fecha placeholder (1900-01-01, etc.)
            if ($date->year < 2000) {
                return now()->toDateString();
            }

            return $date->toDateString();
        } catch (\Exception) {
            return now()->toDateString();
        }
    }

    /**
     * Obtiene la fecha más temprana válida del grupo de filas.
     */
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

    /**
     * Obtiene la siguiente secuencia de documento para IMP-xxxxx.
     */
    private function nextImportDocumentSequence(): int
    {
        $maxSequence = DB::table('purchase_documents')
            ->where('document_number', 'like', 'IMP-%')
            ->pluck('document_number')
            ->map(function (?string $documentNumber): int {
                if (! $documentNumber) {
                    return 0;
                }

                return (int) preg_replace('/[^\d]/', '', $documentNumber);
            })
            ->max();

        return ((int) $maxSequence) + 1;
    }
}

<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Seeder para poblar datos de costos en fichas técnicas.
 *
 * Crea registros en:
 *   - technical_sheet_rolls   (materia prima - rollos con precio COP/m)
 *   - technical_sheet_threads (hilos con costo por prenda)
 *   - technical_sheet_supplies (insumos con costo y cantidad por unidad)
 *   - technical_sheet_processes (procesos: corte, confección, terminación)
 *   - technical_sheet_raw_materials (filas de materia prima)
 *
 * Ejecutar después de ExampleDataSeeder y BatchControlModuleSeeder.
 *
 * php artisan db:seed --class=TechnicalSheetCostsSeeder
 */
class TechnicalSheetCostsSeeder extends Seeder
{
    public function run(): void
    {
        if (
            !Schema::hasTable('technical_sheets') ||
            !Schema::hasTable('production_orders') ||
            !Schema::hasTable('product_sheets')
        ) {
            $this->command->warn('⚠️ Tablas requeridas no existen. Saltando TechnicalSheetCostsSeeder.');
            return;
        }

        $now = now();

        // ── Obtener OPs con datos completos (units, billing_price, selling_price) ──
        $ops = DB::table('production_orders as po')
            ->join('product_sheets as ps', 'po.product_sheet_id', '=', 'ps.id')
            ->join('technical_sheets as ts', 'ts.production_order_id', '=', 'po.id')
            ->whereNotNull('po.units')
            ->where('po.units', '>', 0)
            ->whereNotNull('ps.billing_price')
            ->select(
                'po.id as op_id',
                'po.op_code',
                'po.units',
                'ps.billing_price',
                'ps.selling_price',
                'ts.id as technical_sheet_id'
            )
            ->get();

        if ($ops->isEmpty()) {
            $this->command->warn('⚠️ No hay OPs con datos completos para costos. Saltando.');
            return;
        }

        $this->command->info("📊 TechnicalSheetCostsSeeder: Procesando {$ops->count()} OPs...");

        // ── Obtener rollos de fabric_rolls con su precio de compra ──
        $fabricRolls = DB::table('fabric_rolls as fr')
            ->join('purchase_document_items as pdi', 'fr.purchase_document_item_id', '=', 'pdi.id')
            ->join('items as i', 'pdi.item_id', '=', 'i.id')
            ->select(
                'fr.id as roll_id',
                'fr.roll_number',
                'fr.quantity as available_meters',
                'fr.roll_width',
                'fr.location',
                'pdi.price as price_per_meter',
                'i.id as item_id',
                'i.name as item_name',
                'i.reference as item_reference'
            )
            ->where('fr.quantity', '>', 0)
            ->orderBy('fr.id')
            ->get();

        $rollsForCost = $fabricRolls->where('price_per_meter', '>=', 1000)->values();
        if ($rollsForCost->isEmpty()) {
            $rollsForCost = $fabricRolls;
        }

        $supplyItemIds = DB::table('items')
            ->whereIn('reference', [
                'ETQ-TEJ-BLN',
                'BOT-PLAS-24',
                'CIE-MET-15',
                'ELA-PRET-25',
                'BOL-POL-40',
            ])
            ->pluck('id', 'reference');

        $etiquetaId = isset($supplyItemIds['ETQ-TEJ-BLN']) ? (int) $supplyItemIds['ETQ-TEJ-BLN'] : null;
        $botonId = isset($supplyItemIds['BOT-PLAS-24']) ? (int) $supplyItemIds['BOT-PLAS-24'] : null;
        $cierreId = isset($supplyItemIds['CIE-MET-15']) ? (int) $supplyItemIds['CIE-MET-15'] : null;
        $elasticoId = isset($supplyItemIds['ELA-PRET-25']) ? (int) $supplyItemIds['ELA-PRET-25'] : null;
        $bolsaId = isset($supplyItemIds['BOL-POL-40']) ? (int) $supplyItemIds['BOL-POL-40'] : null;

        // ── Datos de costos por OP ──
        // Cada OP tiene su propio perfil de consumo distinto
        $costProfiles = [
            // Perfil para MFT-0001 (500 unids, producto tejido premium)
            0 => [
                'rollLines' => function ($rolls) {
                    $lines = [];
                    // Tomar los primeros 3 rollos disponibles
                    $selected = $rolls->take(3);
                    $consumptions = [0.85, 0.65, 0.40]; // metros por prenda
                    $extras       = [0.03, 0.02, 0.01];
                    foreach ($selected as $idx => $roll) {
                        $lines[] = [
                            'id'               => "seed-roll-{$roll->roll_id}",
                            'consecutive'      => "Rollo " . ($idx + 1),
                            'type'             => 'PRINCIPAL',
                            'fabricName'       => $roll->item_name,
                            'averageTrace'     => $consumptions[$idx] ?? 0.5,
                            'factor'           => 0.03,
                            'consumptionBase'  => $consumptions[$idx] ?? 0.5,
                            'extraMeters'      => $extras[$idx] ?? 0.02,
                            'pricePerMeter'    => (float) $roll->price_per_meter,
                            'programmedMeters' => null, // Se calcula desde consumo
                            'colors'           => null,
                            'availableMeters'  => (float) $roll->available_meters,
                            'balanceMeters'    => null,
                            'color'            => null,
                            'colorCode'        => null,
                            'widthCm'          => (float) $roll->roll_width * 100,
                            'supplier'         => null,
                            'notes'            => 'Generado por seeder',
                            'fabricItemId'     => (int) $roll->item_id,
                            'rollId'           => (string) $roll->roll_id,
                        ];
                    }
                    return $lines;
                },
                'threadLines' => [
                    ['id' => 'seed-thread-1', 'threadName' => 'Hilo poliéster negro', 'gauge' => 40, 'brand' => 'Coats', 'color' => null, 'colorCode' => null, 'stitchesPerInch' => 12, 'costPerGarment' => 85.50, 'defaultThreadCost' => 85.50, 'createdAt' => null, 'updatedAt' => null],
                    ['id' => 'seed-thread-2', 'threadName' => 'Hilo algodón blanco', 'gauge' => 60, 'brand' => 'Gutermann', 'color' => null, 'colorCode' => null, 'stitchesPerInch' => 14, 'costPerGarment' => 62.00, 'defaultThreadCost' => 62.00, 'createdAt' => null, 'updatedAt' => null],
                    ['id' => 'seed-thread-3', 'threadName' => 'Hilo overlok gris', 'gauge' => 50, 'brand' => 'Amann', 'color' => null, 'colorCode' => null, 'stitchesPerInch' => 10, 'costPerGarment' => 45.00, 'defaultThreadCost' => 45.00, 'createdAt' => null, 'updatedAt' => null],
                ],
                'supplyItems' => [
                    ['id' => 'seed-supply-1', 'itemId' => null, 'name' => 'Etiqueta tejida blanca', 'qtyPerUnit' => 1, 'unitCost' => 120.00],
                    ['id' => 'seed-supply-2', 'itemId' => null, 'name' => 'Etiqueta de cuidados',   'qtyPerUnit' => 1, 'unitCost' => 85.00],
                    ['id' => 'seed-supply-3', 'itemId' => null, 'name' => 'Etiqueta de talla',      'qtyPerUnit' => 1, 'unitCost' => 45.00],
                    ['id' => 'seed-supply-4', 'itemId' => null, 'name' => 'Botón plástico 24mm',    'qtyPerUnit' => 4, 'unitCost' => 25.00],
                    ['id' => 'seed-supply-5', 'itemId' => null, 'name' => 'Cierre metálico 15cm',   'qtyPerUnit' => 1, 'unitCost' => 350.00],
                    ['id' => 'seed-supply-6', 'itemId' => null, 'name' => 'Elástico pretina 25mm',  'qtyPerUnit' => 0.45, 'unitCost' => 280.00],
                    ['id' => 'seed-supply-7', 'itemId' => null, 'name' => 'Bolsa polietileno 40x50','qtyPerUnit' => 1, 'unitCost' => 100.00],
                    ['id' => 'seed-supply-8', 'itemId' => null, 'name' => 'Marquilla hiladora',     'qtyPerUnit' => 1, 'unitCost' => 65.00],
                    ['id' => 'seed-supply-9', 'itemId' => null, 'name' => 'Cinta sesgada 25mm',     'qtyPerUnit' => 0.30, 'unitCost' => 150.00],
                    ['id' => 'seed-supply-10','itemId' => null, 'name' => 'Botón madera pequeño',   'qtyPerUnit' => 2, 'unitCost' => 60.00],
                ],
                'processes' => [
                    ['sequence' => 1, 'type' => 'CORTE',       'supplier' => 'OLAMTEX', 'manual_price' => null, 'fixed_cost' => 350.00],
                    ['sequence' => 2, 'type' => 'CONFECCION',  'supplier' => 'OLAMTEX', 'manual_price' => null, 'fixed_cost' => 1250.00],
                    ['sequence' => 3, 'type' => 'ESTAMPADO',   'supplier' => 'Estampart SAS', 'manual_price' => 450.00, 'fixed_cost' => 450.00],
                    ['sequence' => 4, 'type' => 'TERMINACION', 'supplier' => 'OLAMTEX', 'manual_price' => null, 'fixed_cost' => 580.00],
                ],
                'rawMaterialRows' => function ($rolls) {
                    $rows = [];
                    $selected = $rolls->take(3);
                    foreach ($selected as $idx => $roll) {
                        $rows[] = [
                            'uid'               => "seed-raw-" . ($idx + 1),
                            'telaItemId'        => (int) $roll->item_id,
                            'tela'              => $roll->item_name,
                            'supplierId'        => null,
                            'lastSupplierName'  => 'Textiles del Valle S.A.S',
                            'lastUnitPriceCop'  => (float) $roll->price_per_meter,
                            'ancho'             => (float) $roll->roll_width,
                            'anchoUnit'         => 'm',
                            'promTrazo'         => 0.85,
                            'largoTrazo'        => 1.20,
                            'largoLiq'          => 1.15,
                            'consumo'           => [0.85, 0.65, 0.40][$idx] ?? 0.5,
                        ];
                    }
                    return $rows;
                },
            ],
            // Perfil para MF-0001 (buzo hoodie / precosteo realista)
            1 => [
                'rollLines' => function ($rolls) {
                    $lines = [];
                    $selected = $rolls->take(1);
                    $idx = 0;
                    foreach ($selected as $roll) {
                        $lines[] = [
                            'id'               => "seed-roll-op2-{$roll->roll_id}",
                            'consecutive'      => "Rollo " . ($idx + 1),
                            'type'             => 'PRINCIPAL',
                            'fabricName'       => 'FLEECEE',
                            'averageTrace'     => 1.02,
                            'factor'           => 0.03,
                            'consumptionBase'  => 1.02,
                            'extraMeters'      => 0.03,
                            'pricePerMeter'    => 7875.00,
                            'programmedMeters' => null,
                            'colors'           => null,
                            'availableMeters'  => (float) $roll->available_meters,
                            'balanceMeters'    => null,
                            'color'            => null,
                            'colorCode'        => null,
                            'widthCm'          => 170,
                            'supplier'         => 'CHEVIOTTO',
                            'notes'            => 'Generado por seeder',
                            'fabricItemId'     => (int) $roll->item_id,
                            'rollId'           => (string) $roll->roll_id,
                        ];
                        $idx++;
                    }
                    return $lines;
                },
                'threadLines' => [
                    ['id' => 'seed-thread-4', 'threadName' => 'Hilo poliéster negro', 'gauge' => 40, 'brand' => 'Coats', 'color' => null, 'colorCode' => null, 'stitchesPerInch' => 12, 'costPerGarment' => 95.00, 'defaultThreadCost' => 95.00, 'createdAt' => null, 'updatedAt' => null],
                ],
                'supplyItems' => [
                    ['id' => 'seed-supply-11', 'itemId' => null, 'name' => 'INST LAVADO', 'stage' => 'CONFECCION', 'sequence' => 1, 'supplier' => 'OLAMTEX', 'description' => 'SATÍN DOBLADA DAMA', 'modaLinea' => 'LINEA', 'qtyPerUnit' => 1, 'unitCost' => 25.00],
                    ['id' => 'seed-supply-12', 'itemId' => $elasticoId, 'name' => 'RESORTE', 'stage' => 'CONFECCION', 'sequence' => 2, 'supplier' => 'BOMBAY', 'description' => 'ANCHO 5 CM', 'modaLinea' => 'LINEA', 'qtyPerUnit' => 0.39, 'unitCost' => 564.00],
                    ['id' => 'seed-supply-13', 'itemId' => $cierreId, 'name' => 'CREMALLERA', 'stage' => 'CONFECCION', 'sequence' => 3, 'supplier' => 'BOMBAY', 'description' => 'NYLON 15CM', 'modaLinea' => 'MODA', 'qtyPerUnit' => 344, 'unitCost' => 1.00],
                    ['id' => 'seed-supply-14', 'itemId' => $botonId, 'name' => 'BOTON', 'stage' => 'CONFECCION', 'sequence' => 4, 'supplier' => 'BOMBAY', 'description' => 'BOTON CONSUL CB 2H 30L', 'modaLinea' => 'MODA', 'qtyPerUnit' => 1, 'unitCost' => 58.00],
                    ['id' => 'seed-supply-15', 'itemId' => null, 'name' => 'HERRAJE', 'stage' => 'CONFECCION', 'sequence' => 5, 'supplier' => 'BOMBAY', 'description' => 'HERRAJE AMORFO', 'modaLinea' => 'MODA', 'qtyPerUnit' => 1, 'unitCost' => 2095.00],
                    ['id' => 'seed-supply-16', 'itemId' => $etiquetaId, 'name' => 'ETIQUETA', 'stage' => 'TERMINACION', 'sequence' => 1, 'supplier' => 'MYA', 'description' => 'EVA CASTILLO', 'modaLinea' => 'LINEA', 'qtyPerUnit' => 1, 'unitCost' => 69.00],
                    ['id' => 'seed-supply-17', 'itemId' => null, 'name' => 'CODIGO', 'stage' => 'TERMINACION', 'sequence' => 2, 'supplier' => 'OLAMTEX', 'description' => 'PRECIO COLOR', 'modaLinea' => 'LINEA', 'qtyPerUnit' => 1, 'unitCost' => 5.00],
                    ['id' => 'seed-supply-18', 'itemId' => $bolsaId, 'name' => 'BOLSA', 'stage' => 'TERMINACION', 'sequence' => 3, 'supplier' => 'DISTRITODO', 'description' => 'TRANSPARENTE 12x16', 'modaLinea' => 'LINEA', 'qtyPerUnit' => 1, 'unitCost' => 33.00],
                ],
                'processes' => [
                    ['sequence' => 1, 'type' => 'CORTE',       'supplier' => 'OLAMTEX', 'manual_price' => null, 'fixed_cost' => 320.00],
                    ['sequence' => 2, 'type' => 'CONFECCION',  'supplier' => 'OLAMTEX', 'manual_price' => null, 'fixed_cost' => 980.00],
                    ['sequence' => 3, 'type' => 'TERMINACION', 'supplier' => 'OLAMTEX', 'manual_price' => null, 'fixed_cost' => 420.00],
                ],
                'rawMaterialRows' => function ($rolls) {
                    $rows = [];
                    $selected = $rolls->take(1);
                    $idx = 0;
                    foreach ($selected as $roll) {
                        $rows[] = [
                            'uid'               => "seed-raw-op2-" . ($idx + 1),
                            'telaItemId'        => (int) $roll->item_id,
                            'tela'              => 'FLEECEE',
                            'supplierId'        => null,
                            'lastSupplierName'  => 'CHEVIOTTO',
                            'lastUnitPriceCop'  => 7875.00,
                            'ancho'             => 1.70,
                            'anchoUnit'         => 'm',
                            'promTrazo'         => 1.02,
                            'largoTrazo'        => 1.05,
                            'largoLiq'          => 1.05,
                            'consumo'           => 1.05,
                        ];
                        $idx++;
                    }
                    return $rows;
                },
            ],
        ];

        foreach ($ops as $op) {
            $opCode = strtoupper((string) ($op->op_code ?? ''));
            $profile = str_starts_with($opCode, 'MF-') ? $costProfiles[1] : $costProfiles[0];
            $tsId    = (int) $op->technical_sheet_id;

            $this->command->info("  📋 OP {$op->op_code} (ID: {$op->op_id}, TS: {$tsId})");
            $this->command->info("     Units: {$op->units} | Billing: \${$op->billing_price} | Sale: \${$op->selling_price}");

            // ── 1) Rollos ──
            if (!Schema::hasTable('technical_sheet_rolls')) {
                $this->command->warn('     ⚠️ Tabla technical_sheet_rolls no existe');
            } else {
                $existsRolls = DB::table('technical_sheet_rolls')->where('technical_sheet_id', $tsId)->exists();
                if ($existsRolls) {
                    $this->command->info('     ✓ Rollos ya existen - omitiendo');
                } else {
                    $rollLines = is_callable($profile['rollLines'])
                        ? $profile['rollLines']($rollsForCost)
                        : $profile['rollLines'];

                    DB::table('technical_sheet_rolls')->insert([
                        'technical_sheet_id' => $tsId,
                        'params'             => json_encode([
                            'trazo'        => 0.8,
                            'defaultExtra' => 0.03,
                        ]),
                        'lines' => json_encode($rollLines),
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);

                    $count = count($rollLines);
                    $totalMeters = array_sum(array_column($rollLines, 'consumptionBase'));
                    $this->command->info("     → Rollos: {$count} líneas guardadas (consumo total base: {$totalMeters} m)");
                }
            }

            // ── 2) Hilos ──
            if (!Schema::hasTable('technical_sheet_threads')) {
                $this->command->warn('     ⚠️ Tabla technical_sheet_threads no existe');
            } else {
                $existsThreads = DB::table('technical_sheet_threads')->where('technical_sheet_id', $tsId)->exists();
                if ($existsThreads) {
                    $this->command->info('     ✓ Hilos ya existen - omitiendo');
                } else {
                    $threadLines = $profile['threadLines'];
                    $totalCostPerGarment = array_sum(array_column($threadLines, 'costPerGarment'));

                    DB::table('technical_sheet_threads')->insert([
                        'technical_sheet_id' => $tsId,
                        'params'             => json_encode([
                            'defaultYear' => (int) date('Y'),
                            'method'      => 'PER_GARMENT',
                        ]),
                        'lines' => json_encode($threadLines),
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);

                    $count = count($threadLines);
                    $this->command->info("     → Hilos: {$count} líneas (costo total/prenda: \${$totalCostPerGarment})");
                }
            }

            // ── 3) Insumos ──
            if (!Schema::hasTable('technical_sheet_supplies')) {
                $this->command->warn('     ⚠️ Tabla technical_sheet_supplies no existe');
            } else {
                $existsSupplies = DB::table('technical_sheet_supplies')->where('technical_sheet_id', $tsId)->exists();
                if ($existsSupplies) {
                    $this->command->info('     ✓ Insumos ya existen - omitiendo');
                } else {
                    $supplyItems = $profile['supplyItems'];
                    $totalSupplyCost = array_sum(array_map(fn($s) => $s['qtyPerUnit'] * $s['unitCost'], $supplyItems));

                    DB::table('technical_sheet_supplies')->insert([
                        'technical_sheet_id' => $tsId,
                        'items'              => json_encode($supplyItems),
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);

                    $count = count($supplyItems);
                    $this->command->info("     → Insumos: {$count} items (costo total/prenda: \${$totalSupplyCost})");
                }
            }

            // ── 4) Procesos ──
            if (!Schema::hasTable('technical_sheet_processes')) {
                $this->command->warn('     ⚠️ Tabla technical_sheet_processes no existe');
            } else {
                $existsProcesses = DB::table('technical_sheet_processes')->where('technical_sheet_id', $tsId)->exists();
                if ($existsProcesses) {
                    $this->command->info('     ✓ Procesos ya existen - omitiendo');
                } else {
                    $processes = $profile['processes'];
                    $totalProcessCost = array_sum(array_column($processes, 'fixed_cost'));

                    foreach ($processes as $proc) {
                        DB::table('technical_sheet_processes')->insert([
                            'technical_sheet_id' => $tsId,
                            'sequence'           => $proc['sequence'],
                            'type'               => $proc['type'],
                            'supplier'           => $proc['supplier'],
                            'manual_price'       => $proc['manual_price'],
                            'fixed_cost'         => $proc['fixed_cost'],
                            'created_at' => $now,
                            'updated_at' => $now,
                        ]);
                    }

                    $count = count($processes);
                    $this->command->info("     → Procesos: {$count} registros (costo total: \${$totalProcessCost})");
                }
            }

            // ── 5) Materia Prima (raw materials) ──
            if (Schema::hasTable('technical_sheet_raw_materials')) {
                $existsRaw = DB::table('technical_sheet_raw_materials')->where('technical_sheet_id', $tsId)->exists();
                if ($existsRaw) {
                    $this->command->info('     ✓ Materia prima ya existe - omitiendo');
                } else {
                    $rawRows = is_callable($profile['rawMaterialRows'])
                        ? $profile['rawMaterialRows']($rollsForCost)
                        : $profile['rawMaterialRows'];

                    DB::table('technical_sheet_raw_materials')->insert([
                        'technical_sheet_id' => $tsId,
                        'rows'               => json_encode($rawRows),
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);

                    $count = count($rawRows);
                    $totalConsumo = array_sum(array_column($rawRows, 'consumo'));
                    $this->command->info("     → Materia prima: {$count} filas (consumo total: {$totalConsumo} m)");
                }
            }

            // ── Resumen de costos calculados ──
            $this->printCostSummary($op, $profile, $rollsForCost);
        }

        $this->command->info("✅ TechnicalSheetCostsSeeder completado.");
    }

    /**
     * Imprime un resumen de los costos que se calcularían para esta OP.
     */
    private function printCostSummary(object $op, array $profile, $fabricRolls): void
    {
        $units = (float) $op->units;
        $billing = (float) $op->billing_price;
        $trazo = 0.8;

        // Costo rollos
        $rollLines = is_callable($profile['rollLines']) ? $profile['rollLines']($fabricRolls) : $profile['rollLines'];
        $rollTotal = 0.0;
        foreach ($rollLines as $line) {
            $base  = (float) ($line['consumptionBase'] ?? 0);
            $extra = (float) ($line['extraMeters'] ?? 0);
            $price = (float) ($line['pricePerMeter'] ?? 0);
            $programmed = $line['programmedMeters'] ?? null;

            if ($programmed !== null) {
                $rollTotal += (float) $programmed * $price;
            } else {
                $consumoAjustado = ($base / $trazo) + $extra;
                $rollTotal += $consumoAjustado * $units * $price;
            }
        }

        // Costo hilos
        $threadTotal = 0.0;
        foreach ($profile['threadLines'] as $line) {
            $threadTotal += (float) ($line['costPerGarment'] ?? 0) * $units;
        }

        $rawMaterialTotal = $rollTotal + $threadTotal;

        // Costo insumos
        $suppliesTotal = 0.0;
        foreach ($profile['supplyItems'] as $item) {
            $suppliesTotal += (float) ($item['qtyPerUnit'] ?? 0) * (float) ($item['unitCost'] ?? 0);
        }

        // Costo procesos
        $processesTotal = array_sum(array_column($profile['processes'], 'fixed_cost'));

        $totalCost = $rawMaterialTotal + $suppliesTotal + $processesTotal;

        // Rentabilidad
        $fixedCost = $billing * 0.1458;
        $rentValue = $billing - $fixedCost;
        $rentPercent = $billing > 0 ? ($rentValue / $billing) * 100 : 0;

        $this->command->info("     ────────────────────────────────────────────");
        $this->command->info("     RESUMEN COSTOS {$op->op_code}:");
        $this->command->info("       Materia prima (rollos): $" . number_format($rollTotal, 0, ',', '.'));
        $this->command->info("       Materia prima (hilos):  $" . number_format($threadTotal, 0, ',', '.'));
        $this->command->info("       Materia prima TOTAL:    $" . number_format($rawMaterialTotal, 0, ',', '.'));
        $this->command->info("       Insumos:                $" . number_format($suppliesTotal, 0, ',', '.'));
        $this->command->info("       Procesos:               $" . number_format($processesTotal, 0, ',', '.'));
        $this->command->info("       COSTO TOTAL:            $" . number_format($totalCost, 0, ',', '.'));
        $this->command->info("       Costo fijo (14.58%):    $" . number_format($fixedCost, 0, ',', '.'));
        $this->command->info("       Rentabilidad:           $" . number_format($rentValue, 0, ',', '.') . " (" . number_format($rentPercent, 2) . "%)");
        $this->command->info("     ────────────────────────────────────────────");
    }
}

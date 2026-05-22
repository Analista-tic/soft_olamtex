<?php

namespace Database\Seeders;

use App\Models\Production\Operation;
use App\Models\Production\ProductSheet;
use App\Models\Production\Machine;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Reconcilia TODAS las operaciones contra el CSV corregido.
 * Usa updateOrCreate por codigo — idempotente, no duplica registros.
 */
class ReconcileOperationsSeeder extends Seeder
{
    private array $sheetMap  = [];
    private array $machineMap = [];

    public function run(): void
    {
        $this->buildSheetMap();
        $this->buildMachineMap();

        $csvPath = database_path('seeders/data/PLANTILLA LISTA OPERACIONAL - BASE DE DATOS OPERACIONES.csv');

        if (! file_exists($csvPath)) {
            $this->command->error("CSV no encontrado: {$csvPath}");
            return;
        }

        $handle = fopen($csvPath, 'r');
        fgetcsv($handle); // saltar encabezado

        $updated  = 0;
        $created  = 0;
        $errors   = [];

        DB::beginTransaction();
        try {
            while (($row = fgetcsv($handle)) !== false) {
                if (empty($row[1])) continue;

                [, $codigo, $action, $garmentPart, $anexo, $sectionRaw,
                   $measureRaw, $quantity, , $samBase, $factorOperario,
                   $sheetRef, $machineCode, $isActive] = array_pad($row, 14, null);

                $codigo      = trim($codigo);
                $action      = trim($action);
                $garmentPart = trim($garmentPart);
                $anexo       = trim($anexo) ?: null;
                $sectionId   = $this->parseSection($sectionRaw);
                $measure     = $this->parseMeasure($measureRaw);
                $quantity    = (int) $quantity ?: 1;
                $samBase     = (float) $samBase;
                $factorOp    = (float) ($factorOperario ?: 100);
                $sheetId     = $this->sheetMap[trim($sheetRef)] ?? null;
                $machineId   = $this->machineMap[trim($machineCode)] ?? null;
                $active      = (int) $isActive === 1;

                if (! $sheetId && trim($sheetRef)) {
                    $errors[] = "{$codigo}: product_sheet_reference '{$sheetRef}' no encontrada";
                }

                $exists = Operation::withTrashed()->where('codigo', $codigo)->exists();

                Operation::withTrashed()->updateOrCreate(
                    ['codigo' => $codigo],
                    [
                        'action'                      => strtoupper($action),
                        'garment_part'                => strtoupper($garmentPart),
                        'anexo'                       => $anexo ? strtoupper($anexo) : null,
                        'section_id'                  => $sectionId,
                        'measure'                     => $measure,
                        'quantity'                    => $quantity,
                        'sam_base'                    => $samBase,
                        'factor_operario'             => $factorOp,
                        'product_sheet_id_reference'  => $sheetId,
                        'machine_id_reference'        => $machineId,
                        'is_active'                   => $active,
                        'deleted_at'                  => null,
                    ]
                );

                $exists ? $updated++ : $created++;
            }

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            $this->command->error('Error: ' . $e->getMessage());
            return;
        }

        fclose($handle);

        $this->command->info("Reconciliación completada: {$updated} actualizadas, {$created} creadas.");

        foreach ($errors as $err) {
            $this->command->warn($err);
        }
    }

    private function buildSheetMap(): void
    {
        // Mapea product_reference (301xxx) → id real de product_sheets
        $sheets = ProductSheet::select('id', 'product_reference')->get();
        foreach ($sheets as $sheet) {
            if ($sheet->product_reference) {
                $this->sheetMap[(string) $sheet->product_reference] = $sheet->id;
            }
        }
    }

    private function buildMachineMap(): void
    {
        $machines = Machine::select('id', 'code')->get();
        foreach ($machines as $machine) {
            $this->machineMap[strtoupper(trim($machine->code))] = $machine->id;
        }
    }

    private function parseSection(?string $raw): int
    {
        if (! $raw) return 1;
        $raw = strtoupper(trim($raw));
        if (str_contains($raw, 'ENSAMBLE'))    return 2;
        if (str_contains($raw, 'TERMINACION')) return 3;
        return 1; // PREPARACION por defecto
    }

    private function parseMeasure(?string $raw): ?float
    {
        $raw = trim((string) $raw);
        if ($raw === '') return null;
        // "44 X 40" → toma el primer número
        $first = explode('X', $raw)[0];
        $num   = trim($first);
        return is_numeric($num) ? (float) $num : null;
    }
}

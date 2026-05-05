<?php

namespace Database\Seeders;

use App\Models\Production\Operation;
use App\Models\Production\OperationSection;
use App\Models\Production\ProductOperationList;
use App\Models\Production\ProductSheet;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class OperationDemoSeeder extends Seeder
{
    private const SECTIONS = [
        ['name' => 'PREPARACION', 'type' => 'PREPARACION'],
        ['name' => 'ENSAMBLE', 'type' => 'ENSAMBLE'],
        ['name' => 'TERMINACION', 'type' => 'TERMINACION'],
    ];

    private const OPERATIONS = [
        ['action' => 'CERRAR', 'garment_part' => 'BOLSILLO', 'section_type' => 'PREPARACION', 'sam_base' => 0.25],
        ['action' => 'PRENDER', 'garment_part' => 'CUELLO', 'section_type' => 'PREPARACION', 'sam_base' => 0.18],
        ['action' => 'FILETEAR', 'garment_part' => 'MANGA CORTA', 'section_type' => 'PREPARACION', 'sam_base' => 0.20],
        ['action' => 'DOBLADILLAR', 'garment_part' => 'RUEDO', 'section_type' => 'PREPARACION', 'sam_base' => 0.22],
        ['action' => 'PREPARAR', 'garment_part' => 'PRETINA', 'section_type' => 'PREPARACION', 'sam_base' => 0.30],
        ['action' => 'UNIR', 'garment_part' => 'HOMBROS', 'section_type' => 'ENSAMBLE', 'sam_base' => 0.15],
        ['action' => 'UNIR', 'garment_part' => 'LATERAL', 'section_type' => 'ENSAMBLE', 'sam_base' => 0.18],
        ['action' => 'COLOCAR', 'garment_part' => 'MANGA CORTA', 'section_type' => 'ENSAMBLE', 'sam_base' => 0.20],
        ['action' => 'CERRAR', 'garment_part' => 'ENTREPIERNA', 'section_type' => 'ENSAMBLE', 'sam_base' => 0.25],
        ['action' => 'PESPUNTAR', 'garment_part' => 'PRETINA', 'section_type' => 'ENSAMBLE', 'sam_base' => 0.22],
        ['action' => 'REVISAR', 'garment_part' => 'PRENDA COMPLETA', 'section_type' => 'TERMINACION', 'sam_base' => 0.10],
        ['action' => 'PLANCHAR', 'garment_part' => 'PRENDA COMPLETA', 'section_type' => 'TERMINACION', 'sam_base' => 0.12],
        ['action' => 'TIQUETEAR', 'garment_part' => 'PRENDA COMPLETA', 'section_type' => 'TERMINACION', 'sam_base' => 0.08],
        ['action' => 'DOBLADILLAR', 'garment_part' => 'MANGA CORTA', 'section_type' => 'TERMINACION', 'sam_base' => 0.15],
        ['action' => 'VAPORIZAR', 'garment_part' => 'PRENDA COMPLETA', 'section_type' => 'TERMINACION', 'sam_base' => 0.10],
    ];

    private const SHEET_OPERATION_MAP = [
        'OV-DEMO-001' => [
            ['action' => 'CERRAR', 'garment_part' => 'BOLSILLO'],
            ['action' => 'PRENDER', 'garment_part' => 'CUELLO'],
            ['action' => 'FILETEAR', 'garment_part' => 'MANGA CORTA'],
            ['action' => 'DOBLADILLAR', 'garment_part' => 'RUEDO'],
            ['action' => 'PREPARAR', 'garment_part' => 'PRETINA'],
            ['action' => 'UNIR', 'garment_part' => 'LATERAL'],
            ['action' => 'CERRAR', 'garment_part' => 'ENTREPIERNA'],
            ['action' => 'REVISAR', 'garment_part' => 'PRENDA COMPLETA'],
            ['action' => 'PLANCHAR', 'garment_part' => 'PRENDA COMPLETA'],
            ['action' => 'TIQUETEAR', 'garment_part' => 'PRENDA COMPLETA'],
        ],
        'CAM-DEMO-001' => [
            ['action' => 'CERRAR', 'garment_part' => 'BOLSILLO'],
            ['action' => 'PRENDER', 'garment_part' => 'CUELLO'],
            ['action' => 'FILETEAR', 'garment_part' => 'MANGA CORTA'],
            ['action' => 'UNIR', 'garment_part' => 'HOMBROS'],
            ['action' => 'COLOCAR', 'garment_part' => 'MANGA CORTA'],
            ['action' => 'REVISAR', 'garment_part' => 'PRENDA COMPLETA'],
            ['action' => 'TIQUETEAR', 'garment_part' => 'PRENDA COMPLETA'],
            ['action' => 'VAPORIZAR', 'garment_part' => 'PRENDA COMPLETA'],
        ],
        'PAN-DEMO-001' => [
            ['action' => 'DOBLADILLAR', 'garment_part' => 'RUEDO'],
            ['action' => 'PREPARAR', 'garment_part' => 'PRETINA'],
            ['action' => 'UNIR', 'garment_part' => 'LATERAL'],
            ['action' => 'CERRAR', 'garment_part' => 'ENTREPIERNA'],
            ['action' => 'PESPUNTAR', 'garment_part' => 'PRETINA'],
            ['action' => 'REVISAR', 'garment_part' => 'PRENDA COMPLETA'],
            ['action' => 'PLANCHAR', 'garment_part' => 'PRENDA COMPLETA'],
            ['action' => 'TIQUETEAR', 'garment_part' => 'PRENDA COMPLETA'],
        ],
    ];

    public function run(): void
    {
        DB::transaction(function () {
            $sections = $this->seedSections();
            $operations = $this->seedOperations($sections);
            $this->seedSheetOperations($sections, $operations);
        });
    }

    private function seedSections(): Collection
    {
        $sections = collect();

        foreach (self::SECTIONS as $data) {
            $section = OperationSection::withTrashed()->updateOrCreate(
                ['type' => $data['type']],
                [
                    'name' => $data['name'],
                    'is_active' => true,
                ],
            );

            if ($section->trashed()) {
                $section->restore();
            }

            $section->is_active = true;
            $section->save();

            $sections->put($data['type'], $section);

            $this->command?->info(sprintf(
                'OperationSection %s: %s',
                $data['type'],
                $section->wasRecentlyCreated ? 'creada' : 'actualizada',
            ));
        }

        return $sections;
    }

    private function seedOperations(Collection $sections): Collection
    {
        $operations = collect();

        foreach (self::OPERATIONS as $data) {
            /** @var OperationSection|null $section */
            $section = $sections->get($data['section_type']);
            if (! $section) {
                $this->command?->warn("No se encontró la sección {$data['section_type']} para la operación {$data['action']} {$data['garment_part']}.");
                continue;
            }

            $operation = Operation::withTrashed()->updateOrCreate(
                [
                    'action' => $data['action'],
                    'garment_part' => $data['garment_part'],
                ],
                [
                    'section_id' => $section->id,
                    'sam_base' => $data['sam_base'],
                    'factor_operario' => 100.0,
                    'is_active' => true,
                ],
            );

            if ($operation->trashed()) {
                $operation->restore();
            }

            $operation->is_active = true;
            $operation->save();

            $operations->put($this->operationKey($data['action'], $data['garment_part']), $operation);

            $this->command?->info(sprintf(
                'Operation %s %s: %s',
                $data['action'],
                $data['garment_part'],
                $operation->wasRecentlyCreated ? 'creada' : 'actualizada',
            ));
        }

        return $operations;
    }

    private function seedSheetOperations(Collection $sections, Collection $operations): void
    {
        foreach (self::SHEET_OPERATION_MAP as $sampleReference => $operationDefinitions) {
            $sheet = ProductSheet::where('sample_reference', $sampleReference)->first();

            if (! $sheet) {
                $this->command?->warn("No existe la sábana demo {$sampleReference}. Ejecuta primero ProductionDemoSeeder.");
                continue;
            }

            $sequence = 1;

            foreach ($operationDefinitions as $definition) {
                /** @var Operation|null $operation */
                $operation = $operations->get($this->operationKey($definition['action'], $definition['garment_part']));

                if (! $operation) {
                    $this->command?->warn("No existe la operación {$definition['action']} {$definition['garment_part']} para {$sampleReference}.");
                    continue;
                }

                /** @var OperationSection|null $section */
                $section = $sections->firstWhere('id', $operation->section_id);
                if (! $section) {
                    $this->command?->warn("No se encontró la sección para {$definition['action']} {$definition['garment_part']} en {$sampleReference}.");
                    continue;
                }

                $sheetOperation = ProductOperationList::updateOrCreate(
                    [
                        'product_sheet_id' => $sheet->id,
                        'operation_id' => $operation->id,
                        'operation_section_id' => $section->id,
                    ],
                    [
                        'sequence' => $sequence++,
                        'sam_base' => $operation->sam_base,
                        'sam' => $operation->sam_base,
                        'fabric_base_factor' => 1.0,
                        'operator_efficiency_factor' => 1.0,
                        'machine_supplement_factor' => 1.0,
                        'quantity' => 1,
                        'has_override' => false,
                        'is_active' => true,
                    ],
                );

                $this->command?->info(sprintf(
                    '  ProductOperationList %s -> %s %s: %s',
                    $sampleReference,
                    $definition['action'],
                    $definition['garment_part'],
                    $sheetOperation->wasRecentlyCreated ? 'creada' : 'actualizada',
                ));
            }
        }
    }

    private function operationKey(string $action, string $garmentPart): string
    {
        return "{$action}|{$garmentPart}";
    }
}

<?php

namespace App\Services\Production;

use App\Models\Production\Machine;
use App\Models\Production\Operation;
use App\Models\Production\OperationSection;
use App\Models\Production\ProductSheet;
use PhpOffice\PhpSpreadsheet\IOFactory;

/**
 * Importa masivamente el catálogo de operaciones de producción desde el
 * archivo plano "PLANTILLA LISTA OPERACIONAL - BASE DE DATOS OPERACIONES".
 *
 * Reglas de negocio (ver plan de importación):
 * - Firma de deduplicación propia (distinta a OperationService/Operation):
 *   section_id|action|garment_part|anexo|measure|quantity|sam_base.
 *   Debe permanecer alineada con Operation::buildUniquenessSignature().
 * - Máquinas desconocidas se crean automáticamente (code = name = código CSV).
 * - Fichas de producto no resueltas dejan product_sheet_id_reference=null y
 *   conservan el código original en raw_product_sheet_reference.
 * - Filas con acción/parte/sección/SAM ausentes o inválidos van a errores,
 *   no interrumpen el resto del proceso.
 *
 * Esta clase NO reutiliza OperationService::create()/findOrCreate() porque
 * esos métodos deduplican con una firma que no incluye `anexo` (ver
 * copilot-instructions / notas de la tarea) y exigen product_sheet_id_reference
 * y machine_id_reference obligatorios, lo cual no aplica a la importación.
 */
class OperationImportService
{
    // Índices de columna (0-based) en el CSV plantilla real.
    private const COL_ACCION = 2;

    private const COL_PARTE = 3;

    private const COL_ANEXO = 4;

    private const COL_SECCION = 5;

    private const COL_MEDIDA = 6;

    private const COL_MAQUINA = 7;

    private const COL_SAM = 8;

    private const COL_CANTIDAD = 9;

    private const COL_FACTOR_OPERARIO = 12;

    private const COL_FICHA_REF = 13;

    private const COL_ACTIVO = 15;

    public function __construct(protected OperationCatalogService $catalogService) {}

    /**
     * Lee el archivo (CSV/XLSX vía PhpSpreadsheet) y ejecuta la importación.
     */
    public function importFromFile(string $path, bool $apply = false): array
    {
        return $this->importRows($this->readRows($path), $apply);
    }

    /**
     * Lee el archivo fuente y devuelve las filas de datos (sin encabezado),
     * como arreglos indexados numéricamente por columna.
     */
    public function readRows(string $path): array
    {
        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        $readerType = in_array($extension, ['xlsx', 'xls'], true) ? 'Xlsx' : 'Csv';

        $reader = IOFactory::createReader($readerType);
        if ($readerType === 'Csv') {
            $reader->setDelimiter(',');
            $reader->setEnclosure('"');
            $reader->setSheetIndex(0);
        }

        $spreadsheet = $reader->load($path);
        $sheet = $spreadsheet->getActiveSheet();
        $rows = $sheet->toArray(null, true, false, false);

        // La primera fila es el encabezado de la plantilla.
        array_shift($rows);

        return $rows;
    }

    /**
     * Ejecuta la lógica de importación sobre filas ya parseadas.
     *
     * Cuando $apply=true, escribe en BD (se espera que el llamador controle
     * la transacción: commit normal para --apply, rollback forzado para
     * --sql-dump). Cuando $apply=false es un dry-run puro de solo lectura.
     */
    public function importRows(array $rows, bool $apply = false): array
    {
        $machineMap = $this->loadMachineMap();
        $sectionMap = $this->loadSectionMap();
        $productSheetMap = $this->loadProductSheetMap();
        $existingActions = $this->loadCatalogNames('operation_actions');
        $existingGarmentParts = $this->loadCatalogNames('operation_garment_parts');
        $existingSignatures = $this->loadExistingSignatures();

        $summary = [
            'total_rows' => 0,
            'imported' => 0,
            'duplicated_in_db' => 0,
            'duplicated_in_file' => 0,
            'errors' => [],
            'machines_created' => [],
            'actions_registered' => [],
            'garment_parts_registered' => [],
            'unresolved_product_sheets' => [],
            'created_operations_detail' => [],
            'created_machines_detail' => [],
        ];

        $seenSignatures = [];
        $machinesCreatedCount = [];
        $registeredActions = [];
        $registeredGarmentParts = [];

        foreach ($rows as $index => $row) {
            $rowNumber = $index + 2; // +1 por índice base 1, +1 por el encabezado descartado

            if ($this->isRowEmpty($row)) {
                continue;
            }

            $summary['total_rows']++;

            try {
                $normalized = $this->normalizeRow($row);
            } catch (\InvalidArgumentException $e) {
                $summary['errors'][] = ['row' => $rowNumber, 'reason' => $e->getMessage()];

                continue;
            }

            $sectionId = $sectionMap[$normalized['section_name']] ?? null;
            if ($sectionId === null) {
                $summary['errors'][] = [
                    'row' => $rowNumber,
                    'reason' => "Sección desconocida: '{$normalized['section_name']}'.",
                ];

                continue;
            }

            $signature = $this->buildSignature(
                $sectionId,
                $normalized['action'],
                $normalized['garment_part'],
                $normalized['anexo'],
                $normalized['measure'],
                $normalized['quantity'],
                $normalized['sam_base'],
            );

            if (isset($existingSignatures[$signature])) {
                $summary['duplicated_in_db']++;

                continue;
            }
            if (isset($seenSignatures[$signature])) {
                $summary['duplicated_in_file']++;

                continue;
            }
            $seenSignatures[$signature] = true;

            // ── Resolver máquina ──
            $machineId = null;
            $machineCode = $normalized['machine_code'];
            if ($machineCode !== null) {
                if (isset($machineMap[$machineCode])) {
                    $machineId = $machineMap[$machineCode];
                } else {
                    $machinesCreatedCount[$machineCode] = ($machinesCreatedCount[$machineCode] ?? 0) + 1;
                    if ($apply) {
                        $machine = Machine::create([
                            'code' => $machineCode,
                            'name' => $machineCode,
                            'supplement_factor' => 1.0,
                            'is_active' => true,
                        ]);
                        $machineId = $machine->id;
                        $machineMap[$machineCode] = $machineId;
                        $summary['created_machines_detail'][] = $machine->fresh()->toArray();
                    }
                }
            }

            // ── Resolver ficha de producto ──
            $productSheetId = null;
            $rawProductSheetReference = null;
            if ($normalized['ficha_ref'] !== null) {
                if (isset($productSheetMap[$normalized['ficha_ref']])) {
                    $productSheetId = $productSheetMap[$normalized['ficha_ref']];
                } else {
                    $rawProductSheetReference = $normalized['ficha_ref'];
                    $summary['unresolved_product_sheets'][] = [
                        'row' => $rowNumber,
                        'raw_reference' => $normalized['ficha_ref'],
                    ];
                }
            }

            // ── Registrar action/garment_part en catálogos ──
            if (! isset($existingActions[$normalized['action']]) && ! isset($registeredActions[$normalized['action']])) {
                $registeredActions[$normalized['action']] = true;
                $summary['actions_registered'][] = $normalized['action'];
                if ($apply) {
                    $this->catalogService->createOption('operation_actions', ['name' => $normalized['action'], 'is_active' => true]);
                }
            }
            if (! isset($existingGarmentParts[$normalized['garment_part']]) && ! isset($registeredGarmentParts[$normalized['garment_part']])) {
                $registeredGarmentParts[$normalized['garment_part']] = true;
                $summary['garment_parts_registered'][] = $normalized['garment_part'];
                if ($apply) {
                    $this->catalogService->createOption('operation_garment_parts', ['name' => $normalized['garment_part'], 'is_active' => true]);
                }
            }

            $description = $this->buildDescription(
                $normalized['action'],
                $normalized['garment_part'],
                $normalized['anexo'],
                $normalized['measure'],
                $normalized['quantity'],
            );

            if ($apply) {
                try {
                    $operation = Operation::create([
                        'action' => $normalized['action'],
                        'garment_part' => $normalized['garment_part'],
                        'anexo' => $normalized['anexo'],
                        'section_id' => $sectionId,
                        'measure' => $normalized['measure'],
                        'quantity' => $normalized['quantity'],
                        'description' => $description,
                        'sam_base' => $normalized['sam_base'],
                        'factor_operario' => $normalized['factor_operario'],
                        'product_sheet_id_reference' => $productSheetId,
                        'raw_product_sheet_reference' => $rawProductSheetReference,
                        'machine_id_reference' => $machineId,
                        'is_active' => $normalized['is_active'],
                    ]);
                    $operation->update(['codigo' => 'OP-'.str_pad((string) $operation->id, 4, '0', STR_PAD_LEFT)]);
                    $operation = $operation->fresh();

                    $summary['imported']++;
                    $summary['created_operations_detail'][] = $operation->toArray();
                } catch (\Throwable $e) {
                    $summary['errors'][] = [
                        'row' => $rowNumber,
                        'reason' => 'Error al crear la operación: '.$e->getMessage(),
                    ];
                }
            } else {
                // Dry-run: no se escribe, solo se cuenta como importable.
                $summary['imported']++;
            }
        }

        $summary['machines_created'] = collect($machinesCreatedCount)
            ->map(fn ($rows, $code) => ['code' => $code, 'rows_in_file' => $rows])
            ->values()
            ->all();

        return $summary;
    }

    // ── Carga de mapas en memoria (una sola vez) ──────────────────────────

    private function loadMachineMap(): array
    {
        $map = [];
        foreach (Machine::query()->pluck('id', 'code') as $code => $id) {
            $key = mb_strtoupper(trim((string) $code));
            if ($key === '') {
                continue;
            }
            $map[$key] = (int) $id;
        }

        return $map;
    }

    private function loadSectionMap(): array
    {
        $map = [];
        foreach (OperationSection::query()->pluck('id', 'name') as $name => $id) {
            $key = mb_strtoupper(trim((string) $name));
            if ($key === '') {
                continue;
            }
            $map[$key] = (int) $id;
        }

        return $map;
    }

    private function loadProductSheetMap(): array
    {
        $map = [];
        foreach (['sample_reference', 'product_reference', 'product_reference_2'] as $column) {
            foreach (ProductSheet::query()->whereNotNull($column)->where($column, '!=', '')->pluck('id', $column) as $ref => $id) {
                $key = mb_strtoupper(trim((string) $ref));
                if ($key === '' || isset($map[$key])) {
                    continue;
                }
                $map[$key] = (int) $id;
            }
        }

        return $map;
    }

    private function loadCatalogNames(string $catalog): array
    {
        $names = $catalog === 'operation_actions'
            ? $this->catalogService->actionOptions()
            : $this->catalogService->garmentPartOptions();

        return $names->mapWithKeys(fn ($name) => [$name => true])->all();
    }

    private function loadExistingSignatures(): array
    {
        $set = [];
        Operation::query()
            ->select(['id', 'section_id', 'action', 'garment_part', 'anexo', 'measure', 'quantity', 'sam_base'])
            ->get()
            ->each(function (Operation $op) use (&$set) {
                $set[$this->buildSignature(
                    $op->section_id,
                    $op->action,
                    $op->garment_part,
                    $op->anexo,
                    $op->measure,
                    $op->quantity,
                    $op->sam_base,
                )] = true;
            });

        return $set;
    }

    // ── Normalización y firma ──────────────────────────────────────────────

    private function isRowEmpty(array $row): bool
    {
        foreach ($row as $cell) {
            if (trim((string) $cell) !== '') {
                return false;
            }
        }

        return true;
    }

    /**
     * Normaliza y valida una fila cruda del CSV. Lanza InvalidArgumentException
     * con un motivo legible cuando la fila no puede procesarse.
     */
    private function normalizeRow(array $row): array
    {
        $action = mb_strtoupper(trim((string) ($row[self::COL_ACCION] ?? '')));
        $garmentPart = mb_strtoupper(trim((string) ($row[self::COL_PARTE] ?? '')));
        $anexoRaw = trim((string) ($row[self::COL_ANEXO] ?? ''));
        $anexo = $anexoRaw !== '' ? mb_strtoupper($anexoRaw) : null;
        $seccionRaw = trim((string) ($row[self::COL_SECCION] ?? ''));
        $medidaRaw = trim((string) ($row[self::COL_MEDIDA] ?? ''));
        $machineRaw = trim((string) ($row[self::COL_MAQUINA] ?? ''));
        $samRaw = trim((string) ($row[self::COL_SAM] ?? ''));
        $cantidadRaw = trim((string) ($row[self::COL_CANTIDAD] ?? ''));
        $factorRaw = trim((string) ($row[self::COL_FACTOR_OPERARIO] ?? ''));
        $fichaRaw = trim((string) ($row[self::COL_FICHA_REF] ?? ''));
        $activoRaw = trim((string) ($row[self::COL_ACTIVO] ?? ''));

        if ($action === '') {
            throw new \InvalidArgumentException('ACCIÓN vacía.');
        }
        if ($garmentPart === '') {
            throw new \InvalidArgumentException('PARTE DE PRENDA vacía.');
        }
        if ($seccionRaw === '') {
            throw new \InvalidArgumentException('ID SECCIÓN vacía.');
        }

        $sectionName = mb_strtoupper(trim((string) preg_replace('/^\d+-\s*/', '', $seccionRaw)));
        if ($sectionName === '') {
            throw new \InvalidArgumentException("ID SECCIÓN inválida: '{$seccionRaw}'.");
        }

        if ($samRaw === '') {
            throw new \InvalidArgumentException('BASE SAM vacía.');
        }
        $samNormalizedStr = str_replace(',', '.', $samRaw);
        if (! is_numeric($samNormalizedStr)) {
            throw new \InvalidArgumentException("BASE SAM inválida/corrupta: '{$samRaw}'.");
        }
        $samBase = (float) $samNormalizedStr;

        $measure = null;
        if ($medidaRaw !== '') {
            $measureNormalizedStr = str_replace(',', '.', $medidaRaw);
            if (! is_numeric($measureNormalizedStr)) {
                throw new \InvalidArgumentException("MEDIDA CM inválida: '{$medidaRaw}'.");
            }
            $measure = (float) $measureNormalizedStr;
        }

        $quantity = $cantidadRaw === '' ? 1 : (int) $cantidadRaw;
        if ($quantity < 1) {
            $quantity = 1;
        }

        $factorOperario = $factorRaw === '' ? 100.0 : (float) str_replace(',', '.', $factorRaw);
        if ($factorOperario <= 0) {
            $factorOperario = 100.0;
        }

        // Acordado: vacío también se importa como ACTIVO=true.
        $isActive = ! in_array(mb_strtoupper($activoRaw), ['0', 'NO', 'FALSE', 'INACTIVO'], true);

        return [
            'action' => $action,
            'garment_part' => $garmentPart,
            'anexo' => $anexo,
            'section_name' => $sectionName,
            'measure' => $measure,
            'quantity' => $quantity,
            'sam_base' => $samBase,
            'factor_operario' => $factorOperario,
            'machine_code' => $machineRaw !== '' ? mb_strtoupper($machineRaw) : null,
            'ficha_ref' => $fichaRaw !== '' ? mb_strtoupper($fichaRaw) : null,
            'is_active' => $isActive,
        ];
    }

    /**
     * Firma de deduplicación propia del importador (SÍ incluye `anexo`).
     * Debe permanecer alineada con Operation::buildUniquenessSignature().
     */
    private function buildSignature(
        int|string|null $sectionId,
        ?string $action,
        ?string $garmentPart,
        ?string $anexo,
        float|int|string|null $measure,
        int|string|null $quantity,
        float|int|string|null $samBase,
    ): string {
        $measureNorm = ($measure === null || $measure === '') ? '0.0000' : number_format((float) $measure, 4, '.', '');
        $samNorm = ($samBase === null || $samBase === '') ? '0.0000' : number_format((float) $samBase, 4, '.', '');
        $qtyNorm = ($quantity === null || $quantity === '') ? 1 : (int) $quantity;

        return implode('|', [
            (string) ($sectionId ?? ''),
            mb_strtoupper(trim((string) $action)),
            mb_strtoupper(trim((string) $garmentPart)),
            mb_strtoupper(trim((string) ($anexo ?? ''))),
            $measureNorm,
            (string) $qtyNorm,
            $samNorm,
        ]);
    }

    /**
     * Replica el estilo de descripción autogenerada de OperationService::buildDescription()
     * para no romper la consistencia con el resto del sistema.
     */
    private function buildDescription(
        string $action,
        string $garmentPart,
        ?string $anexo,
        float|int|string|null $measure,
        int|string|null $quantity,
    ): string {
        $parts = [
            strtoupper(trim($action)),
            strtoupper(trim($garmentPart)),
        ];

        $normalizedAnexo = trim((string) ($anexo ?? ''));
        if ($normalizedAnexo !== '') {
            $parts[] = strtoupper($normalizedAnexo);
        }

        if ($measure !== null && $measure !== '' && is_numeric((string) $measure)) {
            $parts[] = rtrim(rtrim((string) $measure, '0'), '.').' CM';
        }

        if ($quantity !== null && $quantity !== '' && is_numeric((string) $quantity)) {
            $parts[] = 'X'.(string) (int) $quantity;
        }

        return trim(implode(' ', $parts));
    }
}

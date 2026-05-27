<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Completa la importación del CSV de operaciones.
 *
 * Estrategia:
 * - Operaciones: deduplicadas por firma de negocio (section+action+garment_part+measure+quantity+sam_base).
 *   Si ya existe una con esa firma, reutiliza su id.
 *   Si no existe, crea una nueva (con el codigo del CSV).
 * - Relaciones: una fila product_sheet_operations por cada fila del CSV.
 *   Si el par (product_sheet_id, operation_id) ya existe, omite.
 */
class FinishCsvOperationImportSeeder extends Seeder
{
    private const CSV_PATH = __DIR__ . '/data/PLANTILLA LISTA OPERACIONAL - BASE DE DATOS OPERACIONES.csv';

    /** section_number (1|2|3) → operation_sections.id */
    private array $sectionMap = [];

    /** machine code → machines.id */
    private array $machineMap = [];

    /** product_reference (301xxx) → product_sheets.id */
    private array $sheetMap = [];

    /** business signature → operations.id */
    private array $signatureToId = [];

    /** operations.codigo → operations.id */
    private array $codigoToId = [];

    /** product_sheet_operations columns available */
    private array $psoColumns = [];

    public function run(): void
    {
        if (! file_exists(self::CSV_PATH)) {
            $this->command->error('CSV no encontrado: ' . self::CSV_PATH);
            return;
        }

        $this->loadMaps();

        $rows = $this->parseCsv();
        $this->command->info('Filas en CSV: ' . count($rows));

        $opsInserted   = 0;
        $relsInserted  = 0;
        $skipped       = 0;

        // Sequencia por product_sheet para no pisar secuencias existentes
        $maxSeqBySheet = DB::table('product_sheet_operations')
            ->selectRaw('product_sheet_id, MAX(sequence) as max_seq')
            ->groupBy('product_sheet_id')
            ->pluck('max_seq', 'product_sheet_id')
            ->map(fn ($v) => (int) ($v ?? 0))
            ->toArray();

        // Pares (sheet,operation) ya existentes
        $existingPairs = DB::table('product_sheet_operations')
            ->get(['product_sheet_id', 'operation_id'])
            ->mapWithKeys(fn ($r) => [$r->product_sheet_id . '|' . $r->operation_id => true])
            ->toArray();

        foreach ($rows as $row) {
            $csvId      = (int) ($row['ID'] ?? 0);
            $codigo     = mb_strtoupper(trim((string) ($row['CODIGO'] ?? '')));
            $action     = mb_strtoupper(trim((string) ($row['ACTION'] ?? '')));
            $garmentPart = mb_strtoupper(trim((string) ($row['GARMENT_PART'] ?? '')));
            $anexo      = $this->nullableUpper($row['ANEXO'] ?? $row['ANEXO '] ?? null);
            $sectionNum = $this->parseSectionNum((string) ($row['SECTION_ID'] ?? ''));
            $measure    = $this->parseMeasure($row['MEASURE'] ?? null);
            $quantity   = max(1, (int) ($row['QUANTITY'] ?? 1));
            $samBase    = max(0.0, (float) ($row['SAM_BASE'] ?? 0));
            $factorOp   = max(0.01, (float) ($row['FACTOR_OPERARIO'] ?? 100));
            $isActive   = ((int) ($row['IS_ACTIVE'] ?? 1)) === 1;
            $machineCode = mb_strtoupper(trim((string) ($row['MACHINE_ID_REFERENCE'] ?? '')));
            $sheetRef   = trim((string) ($row['PRODUCT_SHEET_ID_REFERENCE'] ?? ''));

            if ($codigo === '' || $action === '' || $garmentPart === '') {
                $this->warn($csvId, 'Campos obligatorios vacíos');
                $skipped++;
                continue;
            }

            $sectionId = $this->sectionMap[$sectionNum] ?? null;
            if ($sectionId === null) {
                $this->warn($csvId, "Sección no resuelta: '{$row['SECTION_ID']}'");
                $skipped++;
                continue;
            }

            $machineId  = $this->machineMap[$machineCode] ?? null;
            $sheetId    = $this->sheetMap[$sheetRef] ?? null;

            // ── 1. Resolver o crear la operación ──────────────────────────────
            $operationId = $this->resolveOperation(
                $codigo, $action, $garmentPart, $anexo,
                $sectionId, $measure, $quantity, $samBase, $factorOp,
                $sheetId, $machineId, $isActive
            );

            if ($operationId === null) {
                $this->warn($csvId, "No se pudo crear/resolver la operación '{$codigo}'");
                $skipped++;
                continue;
            }

            if ($sheetId === null) {
                $this->warn($csvId, "product_sheet no encontrada para referencia '{$sheetRef}'");
                $skipped++;
                continue;
            }

            // ── 2. Crear la relación si no existe ─────────────────────────────
            $pairKey = $sheetId . '|' . $operationId;
            if (isset($existingPairs[$pairKey])) {
                continue;
            }

            $nextSeq = ($maxSeqBySheet[$sheetId] ?? 0) + 10;
            $maxSeqBySheet[$sheetId] = $nextSeq;

            $machineSup = $machineId !== null
                ? (float) DB::table('machines')->where('id', $machineId)->value('supplement_factor') ?: 1.0
                : 1.0;

            $factorMult      = round($factorOp / 100, 4);
            $samTotalUnit    = round($samBase * $factorMult * $machineSup, 4);

            $payload = [
                'product_sheet_id'          => $sheetId,
                'operation_id'              => $operationId,
                'sequence'                  => $nextSeq,
                'sam_base'                  => $samBase,
                'sam'                       => $samBase,
                'fabric_base_factor'        => 1.0,
                'operator_efficiency_factor'=> $factorMult,
                'machine_supplement_factor' => $machineSup,
                'sam_total_unitario'        => $samTotalUnit,
                'sam_total_operacion'       => $samTotalUnit,
                'optimal_time'              => $samTotalUnit,
                'is_active'                 => $isActive,
                'notes'                     => null,
                'created_at'                => now(),
                'updated_at'                => now(),
            ];

            // Filtrar columnas que existen en la tabla
            $filtered = array_filter($payload, fn ($_, $k) => isset($this->psoColumns[$k]), ARRAY_FILTER_USE_BOTH);

            DB::table('product_sheet_operations')->insert($filtered);
            $existingPairs[$pairKey] = true;
            $relsInserted++;
            $opsInserted += ($opsInserted === 0 ? 0 : 0); // contado en resolveOperation
        }

        $this->command->info("Operaciones nuevas creadas: {$this->newOpsCount}");
        $this->command->info("Relaciones nuevas creadas:   {$relsInserted}");
        if ($skipped > 0) {
            $this->command->warn("Filas omitidas: {$skipped}");
        }
    }

    private int $newOpsCount = 0;

    /**
     * Busca una operación existente por firma de negocio o por codigo.
     * Si no existe, la crea. Devuelve el id.
     */
    private function resolveOperation(
        string $codigo,
        string $action,
        string $garmentPart,
        ?string $anexo,
        int $sectionId,
        ?float $measure,
        int $quantity,
        float $samBase,
        float $factorOp,
        ?int $sheetId,
        ?int $machineId,
        bool $isActive
    ): ?int {
        // a) Ya visto en esta ejecución por codigo
        if (isset($this->codigoToId[$codigo])) {
            return $this->codigoToId[$codigo];
        }

        // b) Firma de negocio
        $sig = $this->buildSig($sectionId, $action, $garmentPart, $measure, $quantity, $samBase);

        if (isset($this->signatureToId[$sig])) {
            $id = $this->signatureToId[$sig];
            $this->codigoToId[$codigo] = $id;
            return $id;
        }

        // c) ¿Existe en BD por codigo?
        $existing = DB::table('operations')
            ->whereNull('deleted_at')
            ->where('codigo', $codigo)
            ->value('id');

        if ($existing) {
            $this->signatureToId[$sig]   = $existing;
            $this->codigoToId[$codigo]   = $existing;
            return $existing;
        }

        // d) ¿Existe en BD por firma?
        $existing = $this->findBySignatureInDb($sectionId, $action, $garmentPart, $measure, $quantity, $samBase);

        if ($existing) {
            $this->signatureToId[$sig]   = $existing;
            $this->codigoToId[$codigo]   = $existing;
            return $existing;
        }

        // e) Crear nueva operación (raw insert para no disparar booted hook de unicidad que ya comprobamos)
        $description = $this->buildDescription($action, $garmentPart, $anexo, $measure, $quantity);

        $newId = DB::table('operations')->insertGetId([
            'codigo'                    => $codigo,
            'action'                    => $action,
            'garment_part'              => $garmentPart,
            'anexo'                     => $anexo,
            'section_id'                => $sectionId,
            'measure'                   => $measure,
            'quantity'                  => $quantity,
            'description'               => $description,
            'sam_base'                  => $samBase,
            'factor_operario'           => $factorOp,
            'product_sheet_id_reference'=> $sheetId,
            'machine_id_reference'      => $machineId,
            'is_active'                 => $isActive,
            'created_at'                => now(),
            'updated_at'                => now(),
        ]);

        $this->signatureToId[$sig] = $newId;
        $this->codigoToId[$codigo] = $newId;
        $this->newOpsCount++;

        return $newId;
    }

    /**
     * Busca en BD una operación cuya firma normalizada coincida.
     */
    private function findBySignatureInDb(
        int $sectionId,
        string $action,
        string $garmentPart,
        ?float $measure,
        int $quantity,
        float $samBase
    ): ?int {
        $measureFmt = $measure !== null ? number_format($measure, 4, '.', '') : null;
        $samFmt     = number_format($samBase, 4, '.', '');

        $query = DB::table('operations')
            ->whereNull('deleted_at')
            ->where('section_id', $sectionId)
            ->where('action', $action)
            ->where('garment_part', $garmentPart)
            ->where('quantity', $quantity)
            ->whereRaw('ROUND(sam_base, 4) = ?', [$samFmt]);

        if ($measureFmt !== null) {
            $query->whereRaw('ROUND(measure, 4) = ?', [$measureFmt]);
        } else {
            $query->whereNull('measure');
        }

        $id = $query->value('id');

        if ($id) {
            $sig = $this->buildSig($sectionId, $action, $garmentPart, $measure, $quantity, $samBase);
            $this->signatureToId[$sig] = $id;
        }

        return $id ?: null;
    }

    private function buildSig(
        int $sectionId,
        string $action,
        string $garmentPart,
        ?float $measure,
        int $quantity,
        float $samBase
    ): string {
        $m = $measure !== null ? number_format($measure, 4, '.', '') : '';
        $s = number_format($samBase, 4, '.', '');
        return "{$sectionId}|{$action}|{$garmentPart}|{$m}|{$quantity}|{$s}";
    }

    private function buildDescription(string $action, string $garmentPart, ?string $anexo, ?float $measure, int $quantity): string
    {
        $parts = [$action, $garmentPart];
        if ($anexo !== null && $anexo !== '') {
            $parts[] = $anexo;
        }
        if ($measure !== null) {
            $parts[] = rtrim(rtrim((string) $measure, '0'), '.') . ' CM';
        }
        $parts[] = 'X' . $quantity;
        return implode(' ', $parts);
    }

    private function loadMaps(): void
    {
        // Secciones: número (1,2,3) → id real
        $sections = DB::table('operation_sections')->get(['id', 'type']);
        foreach ($sections as $s) {
            $num = match (strtoupper($s->type)) {
                'PREPARACION'  => 1,
                'ENSAMBLE'     => 2,
                'TERMINACION'  => 3,
                default        => null,
            };
            if ($num !== null) {
                $this->sectionMap[$num] = $s->id;
            }
        }

        // Máquinas: code → id
        $this->machineMap = DB::table('machines')
            ->whereNull('deleted_at')
            ->pluck('id', 'code')
            ->toArray();

        // Product sheets: product_reference → id
        $this->sheetMap = DB::table('product_sheets')
            ->whereNull('deleted_at')
            ->whereNotNull('product_reference')
            ->pluck('id', 'product_reference')
            ->toArray();

        // Cargar firmas de operaciones ya existentes en memoria
        $existingOps = DB::table('operations')->whereNull('deleted_at')
            ->get(['id', 'codigo', 'section_id', 'action', 'garment_part', 'measure', 'quantity', 'sam_base']);

        foreach ($existingOps as $op) {
            $sig = $this->buildSig(
                (int) $op->section_id,
                (string) $op->action,
                (string) $op->garment_part,
                $op->measure !== null ? (float) $op->measure : null,
                (int) $op->quantity,
                (float) $op->sam_base
            );
            $this->signatureToId[$sig]          = $op->id;
            $this->codigoToId[(string) $op->codigo] = $op->id;
        }

        // Columnas disponibles en product_sheet_operations
        $cols = Schema::getColumnListing('product_sheet_operations');
        $this->psoColumns = array_fill_keys($cols, true);

        $this->command->line('  → Secciones: ' . count($this->sectionMap));
        $this->command->line('  → Máquinas: ' . count($this->machineMap));
        $this->command->line('  → Fichas técnicas: ' . count($this->sheetMap));
        $this->command->line('  → Operaciones existentes: ' . count($this->codigoToId));
    }

    private function parseSectionNum(string $raw): ?int
    {
        if (preg_match('/^(\d+)/', trim($raw), $m)) {
            return (int) $m[1];
        }
        return null;
    }

    private function parseMeasure(?string $raw): ?float
    {
        $raw = trim((string) $raw);
        if ($raw === '') {
            return null;
        }
        // "44 X 40" → primer número
        $first = explode('X', $raw)[0];
        $num   = trim($first);
        return is_numeric($num) ? (float) $num : null;
    }

    private function nullableUpper(?string $value): ?string
    {
        $v = trim((string) $value);
        return $v === '' ? null : mb_strtoupper($v);
    }

    private function warn(int $csvId, string $msg): void
    {
        $this->command->warn("  CSV #{$csvId}: {$msg}");
    }

    /** @return array<int, array<string, string>> */
    private function parseCsv(): array
    {
        $handle = fopen(self::CSV_PATH, 'r');
        if ($handle === false) {
            throw new \RuntimeException('No se pudo abrir el CSV.');
        }

        $header = fgetcsv($handle);
        $header = array_map(fn (string $v): string => mb_strtoupper(trim($v)), $header);

        $rows = [];
        while (($line = fgetcsv($handle)) !== false) {
            if (count($line) !== count($header)) {
                continue;
            }
            $row = array_combine($header, $line);
            if (is_array($row)) {
                $rows[] = $row;
            }
        }

        fclose($handle);
        return $rows;
    }
}

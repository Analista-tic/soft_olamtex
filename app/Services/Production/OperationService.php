<?php

namespace App\Services\Production;

use App\Models\Production\Operation;
use App\Models\Settings\AuditLog;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class OperationService
{
    public function __construct(protected Operation $model) {}

    private function normalizeText(?string $value): string
    {
        return mb_strtoupper(trim((string) ($value ?? '')));
    }

    private function normalizeInt(int|string|null $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }
        return (int) $value;
    }

    private function normalizeFloat(float|int|string|null $value, int $precision = 4): ?string
    {
        if ($value === null || $value === '' || !is_numeric((string) $value)) {
            return null;
        }
        return number_format((float) $value, $precision, '.', '');
    }

    /**
     * Firma de unicidad para operación:
     * sección + acción + parte + medida + cantidad + SAM base.
     */
    private function buildOperationSignatureFromPayload(array $data): string
    {
        $sectionId = $this->normalizeInt($data['section_id'] ?? null);
        $measure = $this->normalizeFloat($data['measure'] ?? 0, 4) ?? '0.0000';
        $samBase = $this->normalizeFloat($data['sam_base'] ?? 0, 4) ?? '0.0000';
        $quantity = $this->normalizeInt($data['quantity'] ?? 1) ?? 1;

        return implode('|', [
            (string) ($sectionId ?? ''),
            $this->normalizeText($data['action'] ?? null),
            $this->normalizeText($data['garment_part'] ?? null),
            $measure,
            (string) $quantity,
            $samBase,
        ]);
    }

    private function buildOperationSignatureFromModel(Operation $operation): string
    {
        return $this->buildOperationSignatureFromPayload([
            'section_id' => $operation->section_id,
            'action' => $operation->action,
            'garment_part' => $operation->garment_part,
            'measure' => $operation->measure,
            'quantity' => $operation->quantity,
            'sam_base' => $operation->sam_base,
        ]);
    }

    /**
     * Auto-genera el código de operación: OP-{ID_PADDED}
     */
    private function generateCodigo(int $operationId): string
    {
        return 'OP-' . str_pad($operationId, 4, '0', STR_PAD_LEFT);
    }

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

        if ($measure !== null && $measure !== '' && is_numeric($measure)) {
            $parts[] = rtrim(rtrim((string) $measure, '0'), '.') . ' CM';
        }

        if ($quantity !== null && $quantity !== '' && is_numeric($quantity)) {
            $parts[] = 'X' . (string) (int) $quantity;
        }

        return trim(implode(' ', $parts));
    }

    private function normalizeReferenceFields(array &$data): void
    {
        if (array_key_exists('product_sheet_id_reference', $data)) {
            $data['product_sheet_id_reference'] = $data['product_sheet_id_reference'] !== null && $data['product_sheet_id_reference'] !== ''
                ? (int) $data['product_sheet_id_reference']
                : null;
        }

        if (array_key_exists('machine_id_reference', $data)) {
            $data['machine_id_reference'] = $data['machine_id_reference'] !== null && $data['machine_id_reference'] !== ''
                ? (int) $data['machine_id_reference']
                : null;
        }
    }

    private function logOperationFieldChange(Operation $operation, string $field, mixed $oldValue, mixed $newValue, ?string $motivo = null): void
    {
        $user = auth()->user();

        $description = 'Cambio de operación en campo ' . $field . '.';
        if (!empty($motivo)) {
            $description .= ' Motivo: ' . $motivo;
        }

        AuditLog::create([
            'user_id' => $user?->id,
            'user_name' => $user?->name,
            'action' => 'updated',
            'auditable_type' => Operation::class,
            'auditable_id' => $operation->id,
            'module' => 'Production',
            'description' => $description,
            'old_values' => [$field => $oldValue],
            'new_values' => [$field => $newValue],
            'ip_address' => request()?->ip(),
            'user_agent' => request()?->userAgent(),
        ]);
    }

    /**
     * Obtiene opciones de operaciones activas (para selects)
     */
    public function options(): Collection
    {
        return $this->model
            ->active()
            ->select([
                'id',
                'codigo',
                'action',
                'garment_part',
                'anexo',
                'measure',
                'quantity',
                'description',
                'section_id',
                'sam_base',
                'factor_operario',
                'product_sheet_id_reference',
                'machine_id_reference',
            ])
            ->get()
            ->map(function ($op) {
                return [
                    'id' => $op->id,
                    'name' => $op->name,
                    'code' => $op->codigo,
                    'action' => $op->action,
                    'garment_part' => $op->garment_part,
                    'description' => $op->description,
                    'anexo' => $op->anexo,
                    'measure' => $op->measure,
                    'quantity' => $op->quantity,
                    'section_id' => $op->section_id,
                    'sam_base' => $op->sam_base,
                    'factor_operario' => $op->factor_operario,
                    'product_sheet_id_reference' => $op->product_sheet_id_reference,
                    'machine_id_reference' => $op->machine_id_reference,
                ];
            });
    }

    /**
     * Crea una nueva operación con validación de duplicados.
        * Unicidad basada en: sección + acción + parte + medida + cantidad + SAM base.
     */
    public function create(array $data): Operation
    {
        $action = strtoupper(trim($data['action'] ?? ''));
        $garmentPart = strtoupper(trim($data['garment_part'] ?? ''));

        $data['action'] = $action;
        $data['garment_part'] = $garmentPart;
        if (! empty($data['anexo'])) {
            $data['anexo'] = strtoupper(trim($data['anexo']));
        }
        if (! empty($data['location'])) {
            $data['location'] = strtoupper(trim($data['location']));
        }

        $this->normalizeReferenceFields($data);

        $signature = $this->buildOperationSignatureFromPayload($data);
        $existing = $this->model
            ->where('action', $action)
            ->where('garment_part', $garmentPart)
            ->get()
            ->first(fn (Operation $op) => $this->buildOperationSignatureFromModel($op) === $signature);

        if ($existing) {
            $operationName = "{$action} {$garmentPart}";
            throw new \InvalidArgumentException(
                "La operación '{$operationName}' ya existe con la misma combinación de sección, medida, cantidad y SAM base."
            );
        }

        $data['description'] = $this->buildDescription(
            $data['action'],
            $data['garment_part'],
            $data['anexo'] ?? null,
            $data['measure'] ?? null,
            $data['quantity'] ?? null,
        );

        return DB::transaction(function () use ($data) {
            $op = $this->model->create($data);
            $op->update(['codigo' => $this->generateCodigo($op->id)]);

            return $op->fresh();
        });
    }

    /**
     * Actualiza una operación existente.
     * Si cambia action o garment_part se valida unicidad.
     * Los campos auditables se registran en audit_logs.
     */
    public function update(Operation $operation, array $data, ?string $motivo = null): Operation
    {
        if (isset($data['action'])) {
            $data['action'] = strtoupper(trim($data['action']));
        }
        if (isset($data['garment_part'])) {
            $data['garment_part'] = strtoupper(trim($data['garment_part']));
        }
        if (isset($data['anexo'])) {
            $data['anexo'] = $data['anexo'] ? strtoupper(trim($data['anexo'])) : null;
        }
        if (isset($data['location'])) {
            $data['location'] = strtoupper(trim($data['location']));
        }

        $this->normalizeReferenceFields($data);

        if (
            array_key_exists('product_sheet_id_reference', $data)
            && !empty($operation->product_sheet_id_reference)
            && (int) ($data['product_sheet_id_reference'] ?? 0) !== (int) $operation->product_sheet_id_reference
        ) {
            $data['product_sheet_id_reference'] = (int) $operation->product_sheet_id_reference;
        }

        $newAction = $data['action'] ?? $operation->action;
        $newPart = $data['garment_part'] ?? $operation->garment_part;
        $newAnexo = array_key_exists('anexo', $data) ? $data['anexo'] : $operation->anexo;
        $newMeasure = array_key_exists('measure', $data) ? $data['measure'] : $operation->measure;
        $newQuantity = array_key_exists('quantity', $data) ? $data['quantity'] : $operation->quantity;

        $data['description'] = $this->buildDescription(
            $newAction,
            $newPart,
            $newAnexo,
            $newMeasure,
            $newQuantity,
        );

        $existing = $this->model
            ->where('action', strtoupper(trim((string) $newAction)))
            ->where('garment_part', strtoupper(trim((string) $newPart)))
            ->where('id', '!=', $operation->id)
            ->get()
            ->first(function (Operation $candidate) use ($operation, $data) {
                $merged = [
                    'action' => $data['action'] ?? $operation->action,
                    'garment_part' => $data['garment_part'] ?? $operation->garment_part,
                    'anexo' => array_key_exists('anexo', $data) ? $data['anexo'] : $operation->anexo,
                    'quantity' => array_key_exists('quantity', $data) ? $data['quantity'] : $operation->quantity,
                    'machine_id_reference' => array_key_exists('machine_id_reference', $data) ? $data['machine_id_reference'] : $operation->machine_id_reference,
                    'product_sheet_id_reference' => array_key_exists('product_sheet_id_reference', $data) ? $data['product_sheet_id_reference'] : $operation->product_sheet_id_reference,
                    'measure' => array_key_exists('measure', $data) ? $data['measure'] : $operation->measure,
                    'sam_base' => array_key_exists('sam_base', $data) ? $data['sam_base'] : $operation->sam_base,
                    'factor_operario' => array_key_exists('factor_operario', $data) ? $data['factor_operario'] : $operation->factor_operario,
                ];

                return $this->buildOperationSignatureFromPayload($merged) === $this->buildOperationSignatureFromModel($candidate);
            });

        if ($existing) {
            throw new \InvalidArgumentException(
                "Ya existe otra operación '{$newAction} {$newPart}' con la misma combinación de sección, medida, cantidad y SAM base."
            );
        }

        return DB::transaction(function () use ($operation, $data, $motivo) {
            $auditableFields = [
                'sam_base',
                'action',
                'garment_part',
                'anexo',
                'section_id',
                'location',
                'measure',
                'quantity',
                'product_sheet_id_reference',
                'machine_id_reference',
                'factor_operario',
                'description',
            ];

            foreach ($auditableFields as $field) {
                if (array_key_exists($field, $data) && (string) $operation->$field !== (string) $data[$field]) {
                    $this->logOperationFieldChange(
                        $operation,
                        $field,
                        $operation->$field,
                        $data[$field],
                        $motivo,
                    );
                }
            }

            $operation->fill($data);
            $operation->saveQuietly();

            return $operation->fresh();
        });
    }

    /**
     * Elimina (soft delete) una operación
     */
    public function delete(Operation $operation): bool
    {
        return $operation->delete();
    }

    /**
     * Actualiza el tiempo SAM base de una operación, registra auditoría
     * y recalcula todas sus listas asociadas.
     */
    public function updateStandardTime(Operation $operation, float $newSamBase, ?string $motivo = null): Operation
    {
        return DB::transaction(function () use ($operation, $newSamBase, $motivo) {
            $oldSam = $operation->sam_base;

            if ((float) $oldSam !== $newSamBase) {
                $this->logOperationFieldChange(
                    $operation,
                    'sam_base',
                    $oldSam,
                    $newSamBase,
                    $motivo ?? 'Actualización de SAM base',
                );
            }

            $operation->fill(['sam_base' => $newSamBase]);
            $operation->saveQuietly();

            $operation->productOperationLists()->update([
                'optimal_time' => $newSamBase,
                'sam' => $newSamBase,
                'updated_at' => now(),
            ]);

            $operation->productOperationLists()->each(function ($list) {
                $productionTime = $list->optimal_time
                    * ($list->operator_efficiency_factor ?? 1.0)
                    * ($list->machine_supplement_factor ?? 1.0);
                $list->update(['production_time' => round($productionTime, 4)]);
            });

            return $operation->fresh();
        });
    }

    /**
     * Obtiene valores únicos de acciones derivados de las operaciones existentes.
     */
    public function getUniqueActions(): Collection
    {
        return $this->model->active()->whereNotNull('action')
            ->pluck('action')->unique()->sort()->values();
    }

    /**
     * Obtiene valores únicos de partes de prenda derivados de las operaciones existentes.
     */
    public function getUniqueGarmentParts(): Collection
    {
        return $this->model->active()->whereNotNull('garment_part')
            ->pluck('garment_part')->unique()->sort()->values();
    }

    /**
     * Busca una operación existente por action+garment_part.
     * Si existe, la retorna. Si no existe, la crea con todos los datos proporcionados.
     */
    public function findOrCreate(array $data): array
    {
        $action = strtoupper(trim($data['action'] ?? ''));
        $garmentPart = strtoupper(trim($data['garment_part'] ?? ''));

        $this->normalizeReferenceFields($data);

        $data['action'] = $action;
        $data['garment_part'] = $garmentPart;
        if (! empty($data['anexo'])) {
            $data['anexo'] = strtoupper(trim((string) $data['anexo']));
        }

        $signature = $this->buildOperationSignatureFromPayload($data);

        $existing = $this->model
            ->where('action', $action)
            ->where('garment_part', $garmentPart)
            ->get()
            ->first(fn (Operation $op) => $this->buildOperationSignatureFromModel($op) === $signature);

        if ($existing) {
            $updates = [];

            if (
                array_key_exists('product_sheet_id_reference', $data)
                && empty($existing->product_sheet_id_reference)
                && !empty($data['product_sheet_id_reference'])
            ) {
                $updates['product_sheet_id_reference'] = (int) $data['product_sheet_id_reference'];
            }

            if (array_key_exists('machine_id_reference', $data)
                && (int) ($existing->machine_id_reference ?? 0) !== (int) ($data['machine_id_reference'] ?? 0)) {
                $updates['machine_id_reference'] = $data['machine_id_reference'];
            }

            if (array_key_exists('factor_operario', $data)
                && (float) ($existing->factor_operario ?? 0) !== (float) ($data['factor_operario'] ?? 0)) {
                $updates['factor_operario'] = $data['factor_operario'];
            }

            // Nota: suplemento_maquina NO se persiste en Operation.
            // Se deriva en tiempo real de Machine.supplement_factor al consumir en ProductOperationList.

            if (! empty($updates)) {
                $existing->update($updates);
                $existing = $existing->fresh();
            }

            return ['operation' => $existing, 'reused' => true];
        }

        $data['is_active'] = $data['is_active'] ?? true;
        if (! empty($data['location'])) {
            $data['location'] = strtoupper(trim($data['location']));
        }

        $data['description'] = $this->buildDescription(
            $data['action'],
            $data['garment_part'],
            $data['anexo'] ?? null,
            $data['measure'] ?? null,
            $data['quantity'] ?? null,
        );

        $operation = DB::transaction(function () use ($data) {
            $op = $this->model->create($data);
            $op->update(['codigo' => $this->generateCodigo($op->id)]);

            return $op->fresh();
        });

        return ['operation' => $operation, 'reused' => false];
    }
}

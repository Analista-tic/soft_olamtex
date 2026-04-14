<?php

namespace App\Services\Production;

use App\Models\Production\Operation;
use App\Models\Production\OperationAuditLog;
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
     * descripción funcional + variables que la diferencian.
     */
    private function buildOperationSignatureFromPayload(array $data): string
    {
        return implode('|', [
            $this->normalizeText($data['action'] ?? null),
            $this->normalizeText($data['garment_part'] ?? null),
            (string) ($this->normalizeInt($data['quantity'] ?? null) ?? ''),
            (string) ($this->normalizeInt($data['machine_id_reference'] ?? null) ?? ''),
            (string) ($this->normalizeInt($data['product_sheet_id_reference'] ?? null) ?? ''),
            (string) ($this->normalizeFloat($data['measure'] ?? null) ?? ''),
            (string) ($this->normalizeFloat($data['sam_base'] ?? null) ?? ''),
            (string) ($this->normalizeFloat($data['factor_operario'] ?? null) ?? ''),
        ]);
    }

    private function buildOperationSignatureFromModel(Operation $operation): string
    {
        return $this->buildOperationSignatureFromPayload([
            'action' => $operation->action,
            'garment_part' => $operation->garment_part,
            'anexo' => $operation->anexo,
            'quantity' => $operation->quantity,
            'machine_id_reference' => $operation->machine_id_reference,
            'product_sheet_id_reference' => $operation->product_sheet_id_reference,
            'measure' => $operation->measure,
            'sam_base' => $operation->sam_base,
            'factor_operario' => $operation->factor_operario,
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
     * Unicidad basada en: action + garment_part (normalizado a mayúsculas)
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
                "La operación '{$operationName}' ya existe con la misma descripción y variables."
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
     * Los campos auditables se registran en operation_audit_logs.
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
                "Ya existe otra operación '{$newAction} {$newPart}' con la misma descripción y variables."
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
                    OperationAuditLog::create([
                        'operation_id' => $operation->id,
                        'field_changed' => $field,
                        'old_value' => $operation->$field,
                        'new_value' => $data[$field],
                        'motivo' => $motivo,
                        'changed_by' => auth()->id(),
                    ]);
                }
            }

            $operation->update($data);

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
                OperationAuditLog::create([
                    'operation_id' => $operation->id,
                    'field_changed' => 'sam_base',
                    'old_value' => $oldSam,
                    'new_value' => $newSamBase,
                    'motivo' => $motivo ?? 'Actualización de SAM base',
                    'changed_by' => auth()->id(),
                ]);
            }

            $operation->update(['sam_base' => $newSamBase]);

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

            if (array_key_exists('product_sheet_id_reference', $data)
                && (int) ($existing->product_sheet_id_reference ?? 0) !== (int) ($data['product_sheet_id_reference'] ?? 0)) {
                $updates['product_sheet_id_reference'] = $data['product_sheet_id_reference'];
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

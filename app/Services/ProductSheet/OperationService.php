<?php

namespace App\Services\ProductSheet;

use App\Models\ProductSheet\Operation;
use App\Models\ProductSheet\OperationAuditLog;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class OperationService
{
    public function __construct(protected Operation $model) {}

    /**
     * Auto-genera el código de operación: OP-{ID_PADDED}
     */
    private function generateCodigo(int $operationId): string
    {
        return 'OP-' . str_pad($operationId, 4, '0', STR_PAD_LEFT);
    }

    /**
     * Obtiene opciones de operaciones activas (para selects)
     */
    public function options(): Collection
    {
        return $this->model
            ->active()
            ->select(['id', 'action', 'garment_part', 'section_id', 'sam_base'])
            ->get()
            ->map(function ($op) {
                return [
                    'id' => $op->id,
                    'name' => $op->name,
                    'action' => $op->action,
                    'garment_part' => $op->garment_part,
                    'section_id' => $op->section_id,
                    'sam_base' => $op->sam_base,
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

        $existing = $this->model
            ->byNormalizedName($action, $garmentPart)
            ->first();

        if ($existing) {
            $operationName = "{$action} {$garmentPart}";
            throw new \InvalidArgumentException(
                "La operación '{$operationName}' ya existe en el sistema."
            );
        }

        $data['action'] = $action;
        $data['garment_part'] = $garmentPart;
        if (!empty($data['location'])) {
            $data['location'] = strtoupper(trim($data['location']));
        }

        return DB::transaction(function () use ($data) {
            $op = $this->model->create($data);
            $op->update(['codigo' => $this->generateCodigo($op->id)]);
            return $op->fresh();
        });
    }

    /**
     * Actualiza una operación existente.
     * Si cambia action o garment_part se valida unicidad.
     * Los campos auditables (sam_base) se registran en operation_audit_logs.
     */
    public function update(Operation $operation, array $data, ?string $motivo = null): Operation
    {
        if (isset($data['action'])) {
            $data['action'] = strtoupper(trim($data['action']));
        }
        if (isset($data['garment_part'])) {
            $data['garment_part'] = strtoupper(trim($data['garment_part']));
        }
        if (isset($data['location'])) {
            $data['location'] = strtoupper(trim($data['location']));
        }

        $newAction = $data['action'] ?? $operation->action;
        $newPart = $data['garment_part'] ?? $operation->garment_part;

        $existing = $this->model
            ->byNormalizedName($newAction, $newPart)
            ->where('id', '!=', $operation->id)
            ->first();

        if ($existing) {
            throw new \InvalidArgumentException(
                "Ya existe otra operación '{$newAction} {$newPart}' en el sistema."
            );
        }

        return DB::transaction(function () use ($operation, $data, $motivo) {
            $auditableFields = ['sam_base', 'action', 'garment_part', 'section_id', 'location', 'measure', 'quantity'];

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

            $operation->productSheetOperations()->update([
                'optimal_time' => $newSamBase,
                'sam' => $newSamBase,
                'updated_at' => now(),
            ]);

            $operation->productSheetOperations()->each(function ($list) {
                $productionTime = $list->optimal_time
                    * $list->operator_efficiency_factor
                    * $list->machine_supplement_factor;
                $list->update(['production_time' => round($productionTime, 4)]);
            });

            return $operation->fresh();
        });
    }

    /**
     * Obtiene valores únicos de acciones
     */
    public function getUniqueActions(): Collection
    {
        return $this->model
            ->active()
            ->whereNotNull('action')
            ->pluck('action')
            ->unique()
            ->sort()
            ->values();
    }

    /**
     * Obtiene valores únicos de partes de prenda
     */
    public function getUniqueGarmentParts(): Collection
    {
        return $this->model
            ->active()
            ->whereNotNull('garment_part')
            ->pluck('garment_part')
            ->unique()
            ->sort()
            ->values();
    }

    /**
     * Obtiene valores únicos de ubicaciones
     */
    public function getUniqueLocations(): Collection
    {
        return $this->model
            ->active()
            ->whereNotNull('location')
            ->where('location', '!=', '')
            ->pluck('location')
            ->unique()
            ->sort()
            ->values();
    }

    /**
     * Busca una operación existente por action+garment_part.
     * Si existe, la retorna (opcionalmente actualiza campos vacíos).
     * Si no existe, la crea con todos los datos proporcionados.
     */
    public function findOrCreate(array $data): array
    {
        $action = strtoupper(trim($data['action'] ?? ''));
        $garmentPart = strtoupper(trim($data['garment_part'] ?? ''));

        $existing = $this->model
            ->byNormalizedName($action, $garmentPart)
            ->first();

        if ($existing) {
            return ['operation' => $existing, 'reused' => true];
        }

        $data['action'] = $action;
        $data['garment_part'] = $garmentPart;
        $data['is_active'] = $data['is_active'] ?? true;
        if (!empty($data['location'])) {
            $data['location'] = strtoupper(trim($data['location']));
        }

        $operation = DB::transaction(function () use ($data) {
            $op = $this->model->create($data);
            $op->update(['codigo' => $this->generateCodigo($op->id)]);
            return $op->fresh();
        });

        return ['operation' => $operation, 'reused' => false];
    }
}

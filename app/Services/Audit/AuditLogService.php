<?php

namespace App\Services\Audit;

use App\Models\MasterData\Parameter;
use App\Models\Settings\AuditLog;
use App\Models\Settings\Setting;
use App\Models\Settings\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Pagination\LengthAwarePaginator as Paginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;

class AuditLogService
{
    private const TIMESTAMP_KEYS = ['created_at', 'updated_at', 'deleted_at'];

    /**
     * @param array{user?: mixed, ip_address?: ?string, user_agent?: ?string} $context
     */
    public function log(Model $model, string $action, array $context = []): void
    {
        if (!Schema::hasTable('audit_logs')) {
            return;
        }

        // Avoid recursive audit logging.
        if ($model instanceof AuditLog) {
            return;
        }

        $user = $context['user'] ?? null;
        $ipAddress = $context['ip_address'] ?? null;
        $userAgent = $context['user_agent'] ?? null;

        $ignored = $model->auditIgnore ?? [];

        $old = array_diff_key($model->getOriginal(), array_flip($ignored));
        $new = array_diff_key($model->getAttributes(), array_flip($ignored));

        if ($action === 'updated' && $old == $new) {
            return;
        }

        $description = $this->buildDescription($model, $action, $old, $new);

        AuditLog::create([
            'user_id' => $user?->id,
            'user_name' => $user?->name,
            'action' => $action,
            'auditable_type' => $model::class,
            'auditable_id' => $model->getKey(),
            'module' => $this->guessModule($model),
            'old_values' => $old ?: null,
            'new_values' => $new ?: null,
            'description' => $description,
            'ip_address' => $ipAddress,
            'user_agent' => $userAgent,
        ]);
    }

    public function paginate(array $filters, int $perPage = 50): LengthAwarePaginator
    {
        if (!Schema::hasTable('audit_logs')) {
            return new Paginator(collect([]), 0, $perPage, 1, [
                'path' => request()?->url() ?? '',
                'query' => request()?->query() ?? [],
            ]);
        }

        $userFilter = $this->normalizeFilter($filters, 'user');
        $moduleFilter = $this->normalizeFilter($filters, 'module');
        $actionFilter = $this->normalizeFilter($filters, 'action');
        $startDate = $this->normalizeFilter($filters, 'start_date');
        $endDate = $this->normalizeFilter($filters, 'end_date');
        $areaId = $this->normalizeFilter($filters, 'area_id');

        $logs = AuditLog::query()
            ->with('user.roles')
            ->when($userFilter, static fn (Builder $query) => $query->where('user_name', 'like', '%' . $userFilter . '%'))
            ->when($moduleFilter, static fn (Builder $query) => $query->where('module', $moduleFilter))
            ->when($actionFilter, static fn (Builder $query) => $query->where('action', $actionFilter))
            ->when($startDate, static fn (Builder $query) => $query->whereDate('created_at', '>=', Carbon::parse($startDate)))
            ->when($endDate, static fn (Builder $query) => $query->whereDate('created_at', '<=', Carbon::parse($endDate)))
            ->when(
                $areaId,
                static fn (Builder $query) => $query->whereHas(
                    'user',
                    static fn (Builder $subQuery) => $subQuery->where('area_id', $areaId)
                )
            )
            ->latest()
            ->paginate($perPage);

        $logs->getCollection()->transform(function (AuditLog $log) {
            $log->module_label = $this->translateModuleName($log->module);
            $log->resource_label = $this->resolveResourceLabel($log);
            $log->user_area = null;
            $log->user_role = null;

            $user = $log->user_id ? $log->user : null;
            if (!$user && $log->user_id) {
                $user = User::query()->find($log->user_id);
            }

            if ($user) {
                if ($user->area_id) {
                    $area = null;

                    if (Schema::hasTable('parameters')) {
                        try {
                            $param = Parameter::query()->where('nombre', 'areas')->first();
                            if ($param && is_array($param->valor)) {
                                $area = collect($param->valor)->firstWhere('id', $user->area_id);
                            }
                        } catch (\Throwable $e) {
                            $area = null;
                        }
                    }

                    if (!$area && Schema::hasTable('settings')) {
                        $setting = Setting::parameters()
                            ->where(static function (Builder $query) {
                                $query->where('key', 'parameter_areas')
                                    ->orWhere('key', 'areas')
                                    ->orWhere('label', 'like', '%Area%')
                                    ->orWhere('label', 'like', '%area%');
                            })
                            ->first();

                        if ($setting) {
                            $values = $setting->value ?? $setting->valor ?? null;
                            if (is_array($values)) {
                                $area = collect($values)->firstWhere('id', $user->area_id);
                            }
                        }
                    }

                    if ($area) {
                        $log->user_area = $area['name'] ?? $area['nombre'] ?? null;
                    }
                }

                $roles = $user->getRoleNames();
                if ($roles->isNotEmpty()) {
                    $log->user_role = $roles->implode(', ');
                }
            }

            return $log;
        });

        return $logs;
    }

    protected function normalizeFilter(array $filters, string $key): ?string
    {
        if (!array_key_exists($key, $filters)) {
            return null;
        }

        $value = $filters[$key];

        if (is_string($value)) {
            $value = trim($value);

            return $value === '' ? null : $value;
        }

        return $value === null ? null : (string) $value;
    }

    protected function translateModuleName(?string $module): string
    {
        if (!$module) {
            return 'General';
        }

        return match ($module) {
            'Expenses' => 'Gastos',
            'Inventory' => 'Inventario',
            'MasterData' => 'Datos Maestros',
            'Finance' => 'Financiero',
            'Production' => 'Produccion',
            'Textil' => 'Textil',
            'Settings' => 'Configuracion',
            // Legacy values
            'Contacts' => 'Contactos',
            'Configuration' => 'Configuracion',
            default => $module,
        };
    }

    protected function resolveResourceLabel(AuditLog $log): string
    {
        if (!$log->auditable_type) {
            return 'Global';
        }

        $modelClass = $log->auditable_type;

        if (class_exists($modelClass) && method_exists($modelClass, 'auditLabel')) {
            return $modelClass::auditLabel();
        }

        $segments = explode('\\', $modelClass);
        $base = $segments ? end($segments) : $modelClass;

        return $this->translateResourceName((string) $base);
    }

    protected function translateResourceName(string $base): string
    {
        return match ($base) {
            'PurchaseOrder' => 'Orden de compra',
            'PurchaseInvoice' => 'Factura de compra',
            'PurchaseRequest' => 'Solicitud de compra',
            'PurchaseDocument' => 'Documento de compra',
            'PurchaseDocumentItem' => 'Linea de documento de compra',
            'Item' => 'Articulo',
            'Warehouse' => 'Bodega',
            'WarehouseTransfer' => 'Transferencia de bodega',
            'WarehouseTransferItem' => 'Item de transferencia de bodega',
            'InventoryRequest' => 'Solicitud de insumos',
            'InventoryRequestItem' => 'Item de solicitud de insumos',
            'InventoryAdjustment' => 'Ajuste de inventario',
            'InventoryAdjustmentLine' => 'Linea de ajuste de inventario',
            'InventoryMovement' => 'Movimiento de inventario',
            'Contact' => 'Contacto',
            'User' => 'Usuario',
            'CompanySetting' => 'Configuracion de empresa',
            'Retention' => 'Retencion',
            'Tax' => 'Impuesto',
            'CategoryGroup' => 'Grupo de categorias',
            'CategorySubGroup' => 'Subgrupo de categorias',
            'ProductSheet' => 'Sabana de productos',
            'Programmer' => 'Programador',
            'ProductOperationList' => 'Listado operacional',
            'Operation' => 'Operacion',
            'OperationSection' => 'Seccion operacional',
            'Machine' => 'Maquina',
            'FabricRoll' => 'Rollo de tela',
            'FabricOrder' => 'Orden de tela',
            'FabricOrderItem' => 'Item de orden de tela',
            'FabricProvider' => 'Proveedor de tela',
            default => $base,
        };
    }

    protected function guessModule(Model $model): ?string
    {
        $class = $model::class;
        $prefix = 'App\\Models\\';

        if (!str_starts_with($class, $prefix)) {
            return null;
        }

        $segments = explode('\\', $class);

        return $segments[2] ?? null;
    }

    protected function buildDescription(Model $model, string $action, ?array $old = null, ?array $new = null): ?string
    {
        $modelName = class_basename($model);

        $formatValue = function ($value): string {
            if (is_array($value)) {
                return (string) json_encode($value, JSON_UNESCAPED_UNICODE);
            }

            if ($value === null) {
                return 'null';
            }

            if ($value instanceof \BackedEnum) {
                return (string) $value->value;
            }

            return (string) $value;
        };

        $old = $old ?? [];
        $new = $new ?? [];

        if ($action === 'created') {
            $pairs = [];
            foreach ($new as $key => $value) {
                if (in_array($key, self::TIMESTAMP_KEYS, true)) {
                    continue;
                }
                $pairs[] = "$key: {$formatValue($value)}";
            }

            $values = $pairs ? implode('; ', $pairs) : 'sin valores';
            return $this->stripDatesFromText("Creado registro de {$modelName}. Valores: {$values}.");
        }

        if ($action === 'deleted') {
            $pairs = [];
            foreach ($old as $key => $value) {
                if (in_array($key, self::TIMESTAMP_KEYS, true)) {
                    continue;
                }
                $pairs[] = "$key: {$formatValue($value)}";
            }

            $values = $pairs ? implode('; ', $pairs) : 'sin valores';
            return $this->stripDatesFromText("Eliminado registro de {$modelName}. Valores: {$values}.");
        }

        if ($action === 'updated') {
            $changes = [];
            $keys = array_unique(array_merge(array_keys($old), array_keys($new)));

            foreach ($keys as $key) {
                if (in_array($key, self::TIMESTAMP_KEYS, true)) {
                    continue;
                }

                $oldValue = $old[$key] ?? null;
                $newValue = $new[$key] ?? null;

                if ($oldValue != $newValue) {
                    $changes[] = "Campo '{$key}' cambiado de '{$formatValue($oldValue)}' a '{$formatValue($newValue)}'";
                }
            }

            if ($changes === []) {
                return null;
            }

            return $this->stripDatesFromText(
                "Actualizado registro de {$modelName}: " . implode('; ', $changes) . '.'
            );
        }

        return null;
    }

    protected function stripDatesFromText(string $text): string
    {
        $patterns = [
            '/\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(?:\.\d+)?Z?/',
            '/\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}/',
            '/\d{4}-\d{2}-\d{2}/',
            '/\d{2}\/\d{2}\/\d{4}/',
            '/\d{1,2}:\d{2}:\d{2}/',
            '/\d{1,2}:\d{2}/',
            '/\d{1,2}\s*(?:a\.m\.|p\.m\.|am|pm)/i',
        ];

        $clean = preg_replace($patterns, '', $text);
        $clean = preg_replace('/\s{2,}/', ' ', (string) $clean);
        $clean = preg_replace('/\s+\./', '.', (string) $clean);

        return trim((string) $clean);
    }
}

<?php

namespace App\Services\Audit;

use App\Models\Settings\AuditLog;
use App\Models\Settings\Setting;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;

class AuditLogService
{
    /**
     * Registra una acciÃ³n en la tabla audit_logs.
     *
     * @param Model $model
     * @param string $action
     * @param array $context ['user' => ?\Illuminate\Contracts\Auth\Authenticatable, 'ip_address' => ?string, 'user_agent' => ?string]
     */
    public function log(Model $model, string $action, array $context = []): void
    {
        if (!Schema::hasTable('audit_logs')) {
            return;
        }

        // Evitar recursiÃ³n
        if ($model instanceof AuditLog) {
            return;
        }

        $user = $context['user'] ?? null;
        $ipAddress = $context['ip_address'] ?? null;
        $userAgent = $context['user_agent'] ?? null;

        // Campos a ignorar definidos en el modelo
        $ignored = $model->auditIgnore ?? [];

        $old = array_diff_key($model->getOriginal(), array_flip($ignored));
        $new = array_diff_key($model->getAttributes(), array_flip($ignored));

        if ($action === 'updated' && $old == $new) {
            // No hubo cambios reales
            return;
        }

        $description = $this->buildDescription($model, $action, $old, $new);

        AuditLog::create([
            'user_id'        => $user?->id,
            'user_name'      => $user?->name,
            'action'         => $action,
            'auditable_type' => $model::class,
            'auditable_id'   => $model->getKey(),
            'module'         => $this->guessModule($model),
            'old_values'     => $old ?: null,
            'new_values'     => $new ?: null,
            'description'    => $description,
            'ip_address'     => $ipAddress,
            'user_agent'     => $userAgent,
        ]);
    }

    public function paginate(array $filters, int $perPage = 50): LengthAwarePaginator
    {
        // Si la tabla de auditorÃ­a no existe (entorno local sin migraciones),
        // devolver un paginador vacÃ­o para evitar consultas que lancen excepciones.
        if (!Schema::hasTable('audit_logs')) {
            return new \Illuminate\Pagination\LengthAwarePaginator(collect([]), 0, $perPage, 1, [
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
            ->when($userFilter, fn($query) => $query->where('user_name', 'like', '%' . $userFilter . '%'))
            ->when($moduleFilter, fn($query) => $query->where('module', $moduleFilter))
            ->when($actionFilter, fn($query) => $query->where('action', $actionFilter))
            ->when($startDate, fn($query) => $query->whereDate('created_at', '>=', Carbon::parse($startDate)))
            ->when($endDate, fn($query) => $query->whereDate('created_at', '<=', Carbon::parse($endDate)))
            ->when($areaId, fn($query) => $query->whereHas('user', fn($query) => $query->where('area_id', $areaId)))
            ->latest()
            ->paginate($perPage);

        $logs->getCollection()->transform(function (AuditLog $log) {
            $log->module_label = $this->translateModuleName($log->module);
            $log->resource_label = $this->resolveResourceLabel($log);

            // Inicializar valores
            $log->user_area = null;
            $log->user_role = null;

            // El usuario deberÃ­a estar eager-loaded por with('user.roles') arriba; usar directamente
            $user = $log->user ?? null;

            if ($user) {
                if (!empty($user->area_id)) {
                    $area = null;

                    // Fuente principal: settings actuales.
                    if (Schema::hasTable('settings')) {
                        $setting = Setting::parameters()
                            ->where(function ($q) {
                                $q->where('key', 'parameter_areas')
                                    ->orWhere('key', 'areas')
                                    ->orWhere('label', 'like', '%Area%')
                                    ->orWhere('label', 'like', '%Ãrea%');
                            })
                            ->first();

                        $values = $setting?->value ?? $setting?->valor ?? null;
                        if (is_array($values)) {
                            $area = collect($values)->firstWhere('id', $user->area_id);
                        }
                    }

                    // Fallback legacy: tabla parameters.
                    if (!$area && class_exists(\App\Models\Parameter::class) && Schema::hasTable('parameters')) {
                        try {
                            $param = \App\Models\Parameter::where('nombre', 'areas')->first();
                            if ($param && is_array($param->valor)) {
                                $area = collect($param->valor)->firstWhere('id', $user->area_id);
                            }
                        } catch (\Throwable $e) {
                            $area = null;
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
            'Contacts' => 'Contactos',
            'Configuration' => 'ConfiguraciÃ³n',
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
        $base = $segments ? $segments[count($segments) - 1] : $modelClass;

        return $this->translateResourceName($base);
    }

    protected function translateResourceName(string $base): string
    {
        return match ($base) {
            'PurchaseOrder' => 'Orden de compra',
            'PurchaseInvoice' => 'Factura de compra',
            'PurchaseRequest' => 'Solicitud de compra',
            'PurchaseDocument' => 'Documento de compra',
            'PurchaseDocumentItem' => 'LÃ­nea de documento de compra',
            'Item' => 'ArtÃ­culo',
            'Warehouse' => 'Bodega',
            'Contact' => 'Contacto',
            'User' => 'Usuario',
            'CompanySetting' => 'ConfiguraciÃ³n de empresa',
            'Retention' => 'RetenciÃ³n',
            'Tax' => 'Impuesto',
            'CategoryGroup' => 'Grupo de categorÃ­as',
            'CategorySubGroup' => 'Subgrupo de categorÃ­as',
            default => $base,
        };
    }

    // Se debe actualizar este mÃ©todo para incluir mÃ¡s modelos y mÃ³dulos segÃºn sea necesario
    protected function guessModule(Model $model): ?string
    {
        return match (true) {
            $model instanceof \App\Models\Expenses\PurchaseRequest,
            $model instanceof \App\Models\Expenses\PurchaseDocument,
            $model instanceof \App\Models\Expenses\PurchaseDocumentItem
                => 'Expenses',

            $model instanceof \App\Models\MasterData\Item,
            $model instanceof \App\Models\Inventory\Warehouse
                => 'Inventory',

            $model instanceof \App\Models\MasterData\Contact,
            $model instanceof \App\Models\Settings\User
                => 'Contacts',

            $model instanceof \App\Models\Settings\CompanySetting,
            $model instanceof \App\Models\Finance\Retention,
            $model instanceof \App\Models\Finance\Tax,
            $model instanceof \App\Models\MasterData\CategoryGroup,
            $model instanceof \App\Models\MasterData\CategorySubGroup
                => 'Configuration',

            default => null,
        };
    }

    /**
     * Genera una descripciÃ³n en espaÃ±ol del cambio realizado.
     */
    protected function buildDescription(Model $model, string $action, array $old = null, array $new = null): ?string
    {
        $modelName = class_basename($model);

        $formatValue = function ($v) {
            if (is_array($v)) {
                return json_encode($v, JSON_UNESCAPED_UNICODE);
            }
            if (is_null($v)) {
                return 'null';
            }
            // Si es un Enum respaldado (BackedEnum), devolver su valor
            if ($v instanceof \BackedEnum) {
                return $v->value;
            }
            return (string) $v;
        };

        $old = $old ?? [];
        $new = $new ?? [];

        if ($action === 'created') {
            $pairs = [];
            $ignoreDateKeys = ['created_at', 'updated_at', 'deleted_at'];
            foreach ($new as $k => $v) {
                if (in_array($k, $ignoreDateKeys, true)) {
                    continue;
                }
                $pairs[] = "$k: {$formatValue($v)}";
            }
            $values = $pairs ? implode('; ', $pairs) : 'sin valores';
            $text = "Creado registro de {$modelName}. Valores: {$values}.";
            return $this->stripDatesFromText($text);
        }

        if ($action === 'deleted') {
            $pairs = [];
            $ignoreDateKeys = ['created_at', 'updated_at', 'deleted_at'];
            foreach ($old as $k => $v) {
                if (in_array($k, $ignoreDateKeys, true)) {
                    continue;
                }
                $pairs[] = "$k: {$formatValue($v)}";
            }
            $values = $pairs ? implode('; ', $pairs) : 'sin valores';
            $text = "Eliminado registro de {$modelName}. Valores: {$values}.";
            return $this->stripDatesFromText($text);
        }

        if ($action === 'updated') {
            $changes = [];
            // Ignorar campos de marca de tiempo en la descripciÃ³n
            $ignoreDateKeys = ['created_at', 'updated_at', 'deleted_at'];
            $keys = array_unique(array_merge(array_keys($old), array_keys($new)));
            foreach ($keys as $k) {
                if (in_array($k, $ignoreDateKeys, true)) {
                    continue;
                }
                $o = $old[$k] ?? null;
                $n = $new[$k] ?? null;
                if ($o != $n) {
                    $changes[] = "Campo '{$k}' cambiado de '{$formatValue($o)}' a '{$formatValue($n)}'";
                }
            }
            if (empty($changes)) {
                return null;
            }
            $text = "Actualizado registro de {$modelName}: " . implode('; ', $changes) . '.';
            return $this->stripDatesFromText($text);
        }

        return null;
    }

    /**
     * Elimina patrones de fecha/hora comunes del texto de descripciÃ³n.
     */
    protected function stripDatesFromText(string $text): string
    {
        $patterns = [
            '/\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(?:\.\d+)?Z?/', // ISO 8601
            '/\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}/', // Y-m-d H:i:s
            '/\d{4}-\d{2}-\d{2}/', // Y-m-d
            '/\d{2}\/\d{2}\/\d{4}/', // dd/mm/YYYY
            '/\d{1,2}:\d{2}:\d{2}/', // HH:MM:SS
            '/\d{1,2}:\d{2}/', // HH:MM
            '/\d{1,2}\s*(?:a\.m\.|p\.m\.|am|pm)/i', // 9:50 a.m.
        ];

        $clean = preg_replace($patterns, '', $text);
        // Limpiar espacios dobles y puntos repetidos resultantes
        $clean = preg_replace('/\s{2,}/', ' ', $clean);
        $clean = preg_replace('/\s+\./', '.', $clean);
        return trim($clean);
    }

}

<?php

namespace App\Services\Audit;

use App\Models\AuditLog;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Model;

class AuditLogService
{
    /**
     * Registra una acción en la tabla audit_logs.
     *
     * @param Model $model
     * @param string $action
     * @param array $context ['user' => ?\Illuminate\Contracts\Auth\Authenticatable, 'ip_address' => ?string, 'user_agent' => ?string]
     */
    public function log(Model $model, string $action, array $context = []): void
    {
        // Evitar recursión
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
        $userFilter = $this->normalizeFilter($filters, 'user');
        $moduleFilter = $this->normalizeFilter($filters, 'module');
        $actionFilter = $this->normalizeFilter($filters, 'action');

        $logs = AuditLog::query()
            ->when($userFilter, fn($query) => $query->where('user_name', 'like', '%' . $userFilter . '%'))
            ->when($moduleFilter, fn($query) => $query->where('module', $moduleFilter))
            ->when($actionFilter, fn($query) => $query->where('action', $actionFilter))
            ->latest()
            ->paginate($perPage);

        $logs->getCollection()->transform(function (AuditLog $log) {
            $log->module_label = $this->translateModuleName($log->module);
            $log->resource_label = $this->resolveResourceLabel($log);
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
            'Configuration' => 'Configuración',
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
            'PurchaseDocumentItem' => 'Línea de documento de compra',
            'Item' => 'Artículo',
            'Warehouse' => 'Bodega',
            'Contact' => 'Contacto',
            'User' => 'Usuario',
            'CompanySetting' => 'Configuración de empresa',
            'Retention' => 'Retención',
            'Tax' => 'Impuesto',
            'CategoryGroup' => 'Grupo de categorías',
            'CategorySubGroup' => 'Subgrupo de categorías',
            default => $base,
        };
    }

    // Se debe actualizar este método para incluir más modelos y módulos según sea necesario
    protected function guessModule(Model $model): ?string
    {
        return match (true) {
            $model instanceof \App\Models\Expenses\PurchaseOrder,
            $model instanceof \App\Models\Expenses\PurchaseInvoice,
            $model instanceof \App\Models\Expenses\PurchaseRequest,
            $model instanceof \App\Models\Expenses\PurchaseDocument,
            $model instanceof \App\Models\Expenses\PurchaseDocumentItem
                => 'Expenses',

            $model instanceof \App\Models\Inventory\Item,
            $model instanceof \App\Models\Common\Warehouse
                => 'Inventory',

            $model instanceof \App\Models\Common\Contact,
            $model instanceof \App\Models\User
                => 'Contacts',

            $model instanceof \App\Models\Settings\CompanySetting,
            $model instanceof \App\Models\Settings\Retention,
            $model instanceof \App\Models\Common\Tax,
            $model instanceof \App\Models\Common\CategoryGroup,
            $model instanceof \App\Models\Common\CategorySubGroup
                => 'Configuration',

            default => null,
        };
    }

    /**
     * Genera una descripción en español del cambio realizado.
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
            // Ignorar campos de marca de tiempo en la descripción
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
     * Elimina patrones de fecha/hora comunes del texto de descripción.
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

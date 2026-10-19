<?php

namespace App\Services\Audit;

use App\Models\Settings\Area;
use App\Models\Settings\AuditLog;
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
     * Campos que nunca deben persistirse en la auditoría, sin importar el modelo.
     */
    public const SENSITIVE_KEYS = [
        'password',
        'remember_token',
        'two_factor_secret',
        'two_factor_recovery_codes',
    ];

    private static ?bool $tableExists = null;

    /**
     * @param array{user?: mixed, ip_address?: ?string, user_agent?: ?string} $context
     */
    public function log(Model $model, string $action, array $context = []): void
    {
        if (!$this->auditTableExists()) {
            return;
        }

        // Avoid recursive audit logging.
        if ($model instanceof AuditLog) {
            return;
        }

        $ignored = array_flip(array_merge($model->auditIgnore ?? [], self::SENSITIVE_KEYS));

        $old = array_diff_key($model->getOriginal(), $ignored);
        $new = array_diff_key($model->getAttributes(), $ignored);

        if (in_array($action, ['updated', 'restored'], true) && $old == $new) {
            return;
        }

        $this->write([
            'action' => $action,
            'auditable_type' => $model::class,
            'auditable_id' => $model->getKey(),
            'module' => $this->guessModule($model),
            'old_values' => $old ?: null,
            'new_values' => $new ?: null,
            'description' => $this->buildDescription($model, $action, $old, $new),
        ], $context);
    }

    /**
     * Registra un evento que no proviene de un evento Eloquent (sync de pivotes,
     * operaciones masivas, autenticación, etc.).
     *
     * @param array{user?: mixed, ip_address?: ?string, user_agent?: ?string}|null $context
     */
    public function logEvent(
        string $action,
        ?string $description,
        ?Model $subject = null,
        ?array $old = null,
        ?array $new = null,
        ?string $module = null,
        ?array $context = null,
    ): void {
        if (!$this->auditTableExists()) {
            return;
        }

        $sensitive = array_flip(self::SENSITIVE_KEYS);

        $this->write([
            'action' => $action,
            'auditable_type' => $subject ? $subject::class : null,
            'auditable_id' => $subject?->getKey(),
            'module' => $module ?? ($subject ? $this->guessModule($subject) : null),
            'old_values' => $old ? array_diff_key($old, $sensitive) : null,
            'new_values' => $new ? array_diff_key($new, $sensitive) : null,
            'description' => $description,
        ], $context ?? $this->currentContext());
    }

    /**
     * Registra una operación masiva (query builder) sobre un modelo auditado,
     * que no dispara eventos Eloquent.
     *
     * @param class-string<Model> $modelClass
     * @param array<int, int|string> $ids
     */
    public function logBulk(string $modelClass, string $action, array $ids, ?array $changes = null, ?string $reason = null): void
    {
        $ids = array_values(array_unique(array_filter($ids, static fn ($id) => $id !== null)));

        if ($ids === []) {
            return;
        }

        $verb = match ($action) {
            'created' => 'Creación',
            'deleted' => 'Eliminación',
            default => 'Actualización',
        };

        $description = sprintf(
            '%s masiva de %s (%d): ids %s.%s',
            $verb,
            class_basename($modelClass),
            count($ids),
            implode(', ', array_slice($ids, 0, 200)) . (count($ids) > 200 ? '…' : ''),
            $reason ? " Motivo: {$reason}." : ''
        );

        $this->logEvent(
            action: $action,
            description: $description,
            new: array_filter(['ids' => $ids, 'changes' => $changes], static fn ($value) => $value !== null),
            module: $this->guessModuleFromClass($modelClass),
        );
    }

    /**
     * @return array{user: mixed, ip_address: ?string, user_agent: ?string}
     */
    public function currentContext(): array
    {
        $request = app()->bound('request') ? app('request') : null;
        $user = app()->bound('auth') ? app('auth')->user() : null;

        return [
            'user' => $user,
            'ip_address' => $request?->ip(),
            'user_agent' => $request?->userAgent(),
        ];
    }

    /**
     * @param array<string, mixed> $attributes
     * @param array{user?: mixed, ip_address?: ?string, user_agent?: ?string} $context
     */
    private function write(array $attributes, array $context): void
    {
        $user = $context['user'] ?? null;

        AuditLog::create($attributes + [
            'user_id' => $user?->id,
            'user_name' => $user?->name,
            'ip_address' => $context['ip_address'] ?? null,
            'user_agent' => $context['user_agent'] ?? null,
        ]);
    }

    private function auditTableExists(): bool
    {
        // Solo se cachea el resultado positivo: la tabla puede crearse durante migraciones.
        if (self::$tableExists !== true) {
            self::$tableExists = Schema::hasTable('audit_logs') ?: null;
        }

        return self::$tableExists === true;
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
                    $log->user_area = Area::query()->whereKey($user->area_id)->value('name');
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
            'Production' => 'Producción',
            'Textil' => 'Textil',
            'Settings' => 'Configuración',
            // Legacy values
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
            'PurchaseDocumentItem' => 'Línea de documento de compra',
            'Item' => 'Artículo',
            'Warehouse' => 'Bodega',
            'WarehouseTransfer' => 'Transferencia de bodega',
            'WarehouseTransferItem' => 'Item de transferencia de bodega',
            'InventoryRequest' => 'Solicitud de insumos',
            'InventoryRequestItem' => 'Item de solicitud de insumos',
            'InventoryAdjustment' => 'Ajuste de inventario',
            'InventoryAdjustmentLine' => 'Línea de ajuste de inventario',
            'InventoryMovement' => 'Movimiento de inventario',
            'Contact' => 'Contacto',
            'User' => 'Usuario',
            'CompanySetting' => 'Configuración de empresa',
            'Retention' => 'Retención',
            'Tax' => 'Impuesto',
            'CategoryGroup' => 'Grupo de categorías',
            'CategorySubGroup' => 'Subgrupo de categorías',
            'ProductSheet' => 'Sábana de productos',
            'ProductionOrder' => 'Orden de producción',
            'TechnicalSheet' => 'Ficha técnica',
            'TechnicalSheetOperation' => 'Operación de ficha técnica',
            'ProductOperationList' => 'Listado operacional',
            'Operation' => 'Operación',
            'OperationSection' => 'Sección operacional',
            'Machine' => 'Máquina',
            'FabricRoll' => 'Rollo de tela',
            'FabricRollAdjustment' => 'Ajuste de rollo',
            'FabricRollCreditNoteLink' => 'Vínculo rollo / nota crédito',
            'FabricRollLengthDifference' => 'Diferencia de longitud de rollo',
            'SaleDocument' => 'Documento de venta',
            'SaleDocumentItem' => 'Línea de documento de venta',
            'SalePayment' => 'Pago de venta',
            'CashReceipt' => 'Recibo de caja',
            'JournalEntry' => 'Comprobante contable',
            'JournalEntryLine' => 'Línea de comprobante contable',
            'BankAccount' => 'Cuenta bancaria',
            'AccountingAccount' => 'Cuenta contable',
            'PurchasePayment' => 'Pago de compra',
            'ExpenseVoucher' => 'Comprobante de egreso',
            'WarehouseItem' => 'Existencia en bodega',
            'WarehouseLocation' => 'Ubicación de bodega',
            'InventoryPeriodLock' => 'Cierre de período de inventario',
            'BatchControlDelivery' => 'Entrega de lote',
            'BatchControlInspection' => 'Inspección de lote',
            'BatchControlNovelty' => 'Novedad de lote',
            'BatchControlWarehouseReceipt' => 'Recepción de lote en bodega',
            'ManualRollProgramming' => 'Programación manual de rollos',
            'FabricRollProgrammingLine' => 'Línea de programación de rollos',
            'ProductionRollConsumption' => 'Consumo de rollo en producción',
            'Color' => 'Color',
            'Role' => 'Rol',
            'Permission' => 'Permiso',
            default => $base,
        };
    }

    protected function guessModule(Model $model): ?string
    {
        return $this->guessModuleFromClass($model::class);
    }

    protected function guessModuleFromClass(string $class): ?string
    {
        if (str_starts_with($class, 'Spatie\\Permission\\')) {
            return 'Settings';
        }

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

        if ($action === 'restored') {
            return "Restaurado registro de {$modelName}.";
        }

        if ($action === 'deleted' || $action === 'force_deleted') {
            $verb = $action === 'force_deleted' ? 'Eliminado definitivamente' : 'Eliminado';
            $pairs = [];
            foreach ($old as $key => $value) {
                if (in_array($key, self::TIMESTAMP_KEYS, true)) {
                    continue;
                }
                $pairs[] = "$key: {$formatValue($value)}";
            }

            $values = $pairs ? implode('; ', $pairs) : 'sin valores';
            return $this->stripDatesFromText("{$verb} registro de {$modelName}. Valores: {$values}.");
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

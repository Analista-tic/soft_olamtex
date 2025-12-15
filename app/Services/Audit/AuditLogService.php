<?php

namespace App\Services\Audit;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;

class AuditLogService
{
    /**
     * Registra una acción en la tabla audit_logs.
     */
    public function log(Model $model, string $action): void
    {
        // Evitar recursión
        if ($model instanceof AuditLog) {
            return;
        }

        $user = auth()->user();

        // Campos a ignorar definidos en el modelo
        $ignored = $model->auditIgnore ?? [];

        $old = array_diff_key($model->getOriginal(), array_flip($ignored));
        $new = array_diff_key($model->getAttributes(), array_flip($ignored));

        if ($action === 'updated' && $old == $new) {
            // No hubo cambios reales
            return;
        }

        AuditLog::create([
            'user_id'        => $user?->id,
            'user_name'      => $user?->name,
            'action'         => $action,
            'auditable_type' => $model::class,
            'auditable_id'   => $model->getKey(),
            'module'         => $this->guessModule($model),
            'old_values'     => $old ?: null,
            'new_values'     => $new ?: null,
            'ip_address'     => request()->ip(),
            'user_agent'     => request()->userAgent(),
        ]);
    }

    protected function guessModule(Model $model): ?string
    {
        return match (true) {
            $model instanceof \App\Models\Expenses\PurchaseOrder,
            $model instanceof \App\Models\Expenses\PurchaseInvoice,
            $model instanceof \App\Models\Expenses\PurchaseRequest
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

}

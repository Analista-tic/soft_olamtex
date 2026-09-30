<?php

namespace App\Observers;

use App\Services\Audit\AuditLogService;
use Illuminate\Database\Eloquent\Model;

class AuditObserver
{
    public function __construct(
        protected AuditLogService $logger
    ) {}

    public function created(Model $model): void
    {
        $this->logWithContext($model, 'created');
    }

    public function updated(Model $model): void
    {
        // restore() guarda el modelo (dispara "updated"); se registra como restauración.
        $isRestore = $this->usesSoftDeletes($model)
            && $model->wasChanged($model->getDeletedAtColumn())
            && $model->getOriginal($model->getDeletedAtColumn()) !== null
            && $model->{$model->getDeletedAtColumn()} === null;

        $this->logWithContext($model, $isRestore ? 'restored' : 'updated');
    }

    public function deleted(Model $model): void
    {
        $isForceDelete = $this->usesSoftDeletes($model) && $model->isForceDeleting();

        $this->logWithContext($model, $isForceDelete ? 'force_deleted' : 'deleted');
    }

    protected function logWithContext(Model $model, string $action): void
    {
        $this->logger->log($model, $action, $this->logger->currentContext());
    }

    protected function usesSoftDeletes(Model $model): bool
    {
        return method_exists($model, 'isForceDeleting');
    }
}

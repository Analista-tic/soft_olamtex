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
        $this->logger->log($model, 'created');
    }

    public function updated(Model $model): void
    {
        $this->logger->log($model, 'updated');
    }

    public function deleted(Model $model): void
    {
        $this->logger->log($model, 'deleted');
    }
}

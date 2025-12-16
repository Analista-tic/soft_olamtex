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
        $this->logWithContext($model, 'updated');
    }

    public function deleted(Model $model): void
    {
        $this->logWithContext($model, 'deleted');
    }

    protected function logWithContext(Model $model, string $action): void
    {
        $this->logger->log($model, $action, $this->gatherContext());
    }

    protected function gatherContext(): array
    {
        $request = app()->bound('request') ? app('request') : null;

        return [
            'user' => auth()->user(),
            'ip_address' => $request?->ip(),
            'user_agent' => $request?->userAgent(),
        ];
    }
}

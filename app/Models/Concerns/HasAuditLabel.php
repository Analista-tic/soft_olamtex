<?php

namespace App\Models\Concerns;

trait HasAuditLabel
{
    public static function auditLabel(): string
    {
        return class_basename(static::class);
    }
}

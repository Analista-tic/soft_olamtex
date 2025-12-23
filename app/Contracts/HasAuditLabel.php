<?php

namespace App\Contracts;

interface HasAuditLabel
{
    /**
     * Retorna una etiqueta legible para el modelo (para audit logs).
     */
    public static function auditLabel(): string;
}

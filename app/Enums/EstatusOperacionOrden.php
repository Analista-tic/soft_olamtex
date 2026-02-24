<?php

namespace App\Enums;

enum EstatusOperacionOrden: string
{
    case PENDIENTE = 'PENDIENTE';
    case EN_EJECUCION = 'EN_EJECUCION';
    case PAUSADA = 'PAUSADA';
    case COMPLETADA = 'COMPLETADA';
    case CANCELADA = 'CANCELADA';

    public function label(): string
    {
        return match ($this) {
            self::PENDIENTE => 'Pendiente',
            self::EN_EJECUCION => 'En ejecución',
            self::PAUSADA => 'Pausada',
            self::COMPLETADA => 'Completada',
            self::CANCELADA => 'Cancelada',
        };
    }

    public function isTerminal(): bool
    {
        return in_array($this, [self::COMPLETADA, self::CANCELADA]);
    }

    public function canTransitionTo(self $target): bool
    {
        return match ($this) {
            self::PENDIENTE => in_array($target, [self::EN_EJECUCION, self::CANCELADA]),
            self::EN_EJECUCION => in_array($target, [self::PAUSADA, self::COMPLETADA, self::CANCELADA]),
            self::PAUSADA => in_array($target, [self::EN_EJECUCION, self::CANCELADA]),
            self::COMPLETADA, self::CANCELADA => false,
        };
    }
}

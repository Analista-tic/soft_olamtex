<?php

namespace App\Exceptions\Settings;

use RuntimeException;

class AreaInUseException extends RuntimeException
{
    public function __construct(public readonly int $usersCount)
    {
        parent::__construct(
            $usersCount === 1
                ? 'No se puede eliminar el área: 1 usuario la tiene asignada. Desactívala o reasigna al usuario.'
                : "No se puede eliminar el área: {$usersCount} usuarios la tienen asignada. Desactívala o reasígnalos.",
        );
    }
}

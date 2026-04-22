<?php

namespace App\Exceptions\MasterData;

use RuntimeException;

class ParameterInUseException extends RuntimeException
{
    /**
     * @param array<string, mixed> $payload
     */
    public function __construct(
        private readonly array $payload,
    ) {
        parent::__construct((string) ($payload['message'] ?? 'No se puede eliminar el parámetro.'));
    }

    /**
     * @return array<string, mixed>
     */
    public function context(): array
    {
        return $this->payload;
    }
}

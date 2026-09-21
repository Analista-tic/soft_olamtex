<?php

namespace App\Services\MasterData;

use App\Models\MasterData\CategoryGroup;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CategoryGroupService
{
    /**
     * `code` no se pide en el formulario de alta — se asigna aquí de forma
     * automática y consecutiva (2 dígitos), igual que ColorService. Sin código el
     * grupo no puede participar en la referencia automática de ítems.
     */
    public function create(array $data): CategoryGroup
    {
        return DB::transaction(function () use ($data) {
            return CategoryGroup::create([
                'name' => $data['name'],
                'code' => $this->nextCode(),
            ]);
        });
    }

    private function nextCode(): string
    {
        $next = (int) CategoryGroup::query()->lockForUpdate()->max('code') + 1;

        if ($next > 99) {
            throw ValidationException::withMessages([
                'code' => 'Se alcanzó el máximo de códigos de grupo disponibles (99).',
            ]);
        }

        return str_pad((string) $next, 2, '0', STR_PAD_LEFT);
    }
}

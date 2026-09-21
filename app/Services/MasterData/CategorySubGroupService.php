<?php

namespace App\Services\MasterData;

use App\Models\MasterData\CategorySubGroup;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CategorySubGroupService
{
    /**
     * Crea los subgrupos de un grupo asignando `code` automático y consecutivo
     * (2 dígitos, único global). Ver ColorService::create().
     *
     * @param  array<int, array{name: string}>  $subgroups
     * @return array<int, CategorySubGroup>
     */
    public function createMany(int $groupId, array $subgroups): array
    {
        return DB::transaction(function () use ($groupId, $subgroups) {
            $created = [];

            foreach ($subgroups as $subgroupData) {
                $created[] = CategorySubGroup::create([
                    'name' => $subgroupData['name'],
                    'category_group_id' => $groupId,
                    'code' => $this->nextCode(),
                ]);
            }

            return $created;
        });
    }

    private function nextCode(): string
    {
        $next = (int) CategorySubGroup::query()->lockForUpdate()->max('code') + 1;

        if ($next > 99) {
            throw ValidationException::withMessages([
                'code' => 'Se alcanzó el máximo de códigos de subgrupo disponibles (99).',
            ]);
        }

        return str_pad((string) $next, 2, '0', STR_PAD_LEFT);
    }
}

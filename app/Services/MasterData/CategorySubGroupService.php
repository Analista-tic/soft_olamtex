<?php

namespace App\Services\MasterData;

use App\Models\MasterData\CategorySubGroup;
use App\Support\TextNormalizer;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CategorySubGroupService
{
    /**
     * Crea uno o varios subgrupos para un mismo grupo. El nombre se normaliza a
     * MAYÚSCULAS y el `code` se asigna automáticamente y de forma consecutiva
     * (uno distinto por cada subgrupo creado en la misma solicitud) — igual
     * patrón que ColorService/CategoryGroupService. Sin `code`,
     * ItemReferenceSequenceService::generate() no puede construir la referencia
     * automática de un ítem que use este subgrupo.
     *
     * @param  array<int, array{name?: mixed}>  $rawSubgroups
     * @return Collection<int, CategorySubGroup>
     */
    public function create(int $groupId, array $rawSubgroups): Collection
    {
        $subgroups = $this->normalizeIncomingSubgroups($rawSubgroups);
        $this->ensureSubgroupsDoNotExist($groupId, $subgroups);

        return DB::transaction(function () use ($groupId, $subgroups) {
            return collect($subgroups)->map(function (array $subgroupData) use ($groupId) {
                return CategorySubGroup::create([
                    'name' => $subgroupData['name'],
                    'category_group_id' => $groupId,
                    'code' => $this->nextCode(),
                ]);
            });
        });
    }

    public function update(CategorySubGroup $subgroup, int $groupId, string $name, ?string $code, ?string $materialKind): CategorySubGroup
    {
        $normalizedName = TextNormalizer::upperCaseWords($name);
        $this->ensureSubgroupDoesNotExist($groupId, $normalizedName, $subgroup->id);

        $subgroup->update([
            'name' => $normalizedName,
            'category_group_id' => $groupId,
            'material_kind' => $materialKind,
            'code' => $code,
        ]);

        return $subgroup;
    }

    public function previewNextCode(): string
    {
        return $this->buildNextCode(CategorySubGroup::query()->max('code'));
    }

    private function nextCode(): string
    {
        return $this->buildNextCode(CategorySubGroup::query()->lockForUpdate()->max('code'));
    }

    private function buildNextCode(?string $max): string
    {
        $next = ((int) $max) + 1;

        if ($next > 99) {
            throw ValidationException::withMessages([
                'code' => 'Se alcanzó el máximo de códigos de subgrupo disponibles (99).',
            ]);
        }

        return str_pad((string) $next, 2, '0', STR_PAD_LEFT);
    }

    /**
     * @param  array<int, array{name?: mixed}>  $subgroups
     * @return array<int, array{name: string}>
     */
    private function normalizeIncomingSubgroups(array $subgroups): array
    {
        $normalized = [];
        $seen = [];

        foreach ($subgroups as $subgroup) {
            $display = TextNormalizer::upperCaseWords($subgroup['name'] ?? null);
            $semanticKey = TextNormalizer::semanticKey($display);

            if ($display === '' || $semanticKey === '' || isset($seen[$semanticKey])) {
                continue;
            }

            $seen[$semanticKey] = true;
            $normalized[] = ['name' => $display];
        }

        if ($normalized === []) {
            throw ValidationException::withMessages([
                'subgroups' => ['Debe agregar al menos un subgrupo válido.'],
            ]);
        }

        return $normalized;
    }

    /**
     * @param  array<int, array{name: string}>  $subgroups
     */
    private function ensureSubgroupsDoNotExist(int $groupId, array $subgroups): void
    {
        $incomingKeys = array_map(
            static fn (array $subgroup): string => TextNormalizer::semanticKey($subgroup['name']),
            $subgroups,
        );

        $existing = CategorySubGroup::query()
            ->where('category_group_id', $groupId)
            ->get(['name']);

        foreach ($existing as $subgroup) {
            $existingKey = TextNormalizer::semanticKey($subgroup->name);

            if ($existingKey !== '' && in_array($existingKey, $incomingKeys, true)) {
                throw ValidationException::withMessages([
                    'subgroups' => ['Uno o más subgrupos ya existen en el grupo seleccionado.'],
                ]);
            }
        }
    }

    private function ensureSubgroupDoesNotExist(int $groupId, string $name, ?int $ignoreId = null): void
    {
        $candidateKey = TextNormalizer::semanticKey($name);

        $existing = CategorySubGroup::query()
            ->where('category_group_id', $groupId)
            ->when($ignoreId !== null, static fn ($query) => $query->where('id', '<>', $ignoreId))
            ->get(['id', 'name']);

        foreach ($existing as $subgroup) {
            if (TextNormalizer::semanticKey($subgroup->name) === $candidateKey) {
                throw ValidationException::withMessages([
                    'name' => ['Ya existe un subgrupo con ese nombre en el grupo seleccionado.'],
                ]);
            }
        }
    }
}

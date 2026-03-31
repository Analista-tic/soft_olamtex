<?php

namespace App\Services\Production;

use App\Models\MasterData\Item;
use Illuminate\Database\Eloquent\Builder;

class TechnicalDataSheetItemOptionsService
{
    /**
     * @return array<int, array{id:int,name:string,reference:?string,unit:?string}>
     */
    public function getOptions(string $group, string $search = '', string $status = 'habilitado', int $limit = 100): array
    {
        $normalizedGroup = $this->normalizeText($group);

        // Si no se especifica grupo, buscar en todos los grupos de insumos/materia prima
        if ($normalizedGroup === null) {
            $groupLike = null;
        } else {
            $groupLike = $this->resolveGroupLikePattern($normalizedGroup);
            if ($groupLike === null) {
                return [];
            }
        }

        $query = Item::query()
            ->select(['id', 'name', 'reference', 'unit_measure', 'status', 'img'])
            ->with(['categorySubGroup.categoryGroup:id,name'])
            ->whereHas('categorySubGroup.categoryGroup', function (Builder $builder) use ($groupLike) {
                if ($groupLike !== null) {
                    $builder->whereRaw('LOWER(name) LIKE ?', [$groupLike]);
                } else {
                    // Sin grupo específico: retornar ítems de insumos y materia prima
                    $builder->whereRaw('LOWER(name) LIKE ?', ['%insumo%'])
                            ->orWhereRaw('LOWER(name) LIKE ?', ['%materia prima%']);
                }
            })
            ->orderBy('name')
            ->limit(max(1, $limit));

        if ($search !== '') {
            $term = '%' . $search . '%';
            $query->where(function (Builder $builder) use ($term) {
                $builder->where('name', 'like', $term)
                    ->orWhere('reference', 'like', $term);
            });
        }

        $this->applyStatusFilter($query, $status);

        $results = $query->get()->map(function (Item $item): array {
            $name = trim((string) $item->name);
            $isDraft = $item->item_type === 'draft';

            return [
                'id' => (int) $item->id,
                'name' => $isDraft ? "{$name} [Solicitado]" : $name,
                'reference' => $this->nullableText($item->reference),
                'unit' => $this->nullableText($item->unit_measure),
                'item_type' => $item->item_type ?? 'product',
                'is_draft'  => $isDraft,
                'img_url'   => $item->img_url,
            ];
        })->values()->all();

        return $results;
    }

    private function resolveGroupLikePattern(string $group): ?string
    {
        if (str_contains($group, 'insumo')) {
            return '%insumo%';
        }

        if (str_contains($group, 'materia prima')) {
            return '%materia prima%';
        }

        return null;
    }

    private function applyStatusFilter(Builder $query, string $status): void
    {
        $normalizedStatus = mb_strtolower(trim($status));
        if (in_array($normalizedStatus, ['all', 'todos'], true)) {
            return;
        }

        $disabledStatuses = ['inhabilitado', 'disabled', 'inactive', 'inactivo', '0', 'false'];
        if (in_array($normalizedStatus, $disabledStatuses, true)) {
            $query->where('status', false);
            return;
        }

        // Incluir items habilitados + items draft (solicitados, aún inhabilitados)
        $query->where(function (Builder $q) {
            $q->where('status', true)
              ->orWhere('item_type', 'draft');
        });
    }

    private function normalizeText(string $value): ?string
    {
        $text = trim(mb_strtolower($value));
        if ($text === '') {
            return null;
        }

        $ascii = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $text);
        if ($ascii !== false) {
            $text = mb_strtolower(trim($ascii));
        }

        return preg_replace('/\s+/', ' ', $text) ?: null;
    }

    private function nullableText(mixed $value): ?string
    {
        if (!is_string($value)) {
            return null;
        }

        $text = trim($value);
        return $text !== '' ? $text : null;
    }
}

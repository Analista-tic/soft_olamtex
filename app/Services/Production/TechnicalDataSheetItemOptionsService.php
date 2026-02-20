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
        if ($normalizedGroup === null) {
            return [];
        }

        $groupLike = $this->resolveGroupLikePattern($normalizedGroup);
        if ($groupLike === null) {
            return [];
        }

        $query = Item::query()
            ->select(['id', 'name', 'reference', 'unit_measure', 'status'])
            ->with(['categorySubGroup.categoryGroup:id,name'])
            ->whereHas('categorySubGroup.categoryGroup', function (Builder $builder) use ($groupLike) {
                $builder->whereRaw('LOWER(name) LIKE ?', [$groupLike]);
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

        return $query->get()->map(function (Item $item): array {
            return [
                'id' => (int) $item->id,
                'name' => trim((string) $item->name),
                'reference' => $this->nullableText($item->reference),
                'unit' => $this->nullableText($item->unit_measure),
            ];
        })->values()->all();
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

        $query->where('status', true);
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

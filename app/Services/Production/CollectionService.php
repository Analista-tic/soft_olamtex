<?php

namespace App\Services\Production;

use App\Models\Production\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection as SupportCollection;

class CollectionService
{
    public function paginate(int $perPage = 15, ?string $search = null): LengthAwarePaginator
    {
        return Collection::query()
            ->when($search, fn ($q, $s) => $q->where('name', 'like', "%{$s}%")
                ->orWhere('description', 'like', "%{$s}%"))
            ->orderBy('name')
            ->paginate($perPage);
    }

    public function allActive(): SupportCollection
    {
        return Collection::where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'description']);
    }

    public function create(array $data): Collection
    {
        return Collection::create($data);
    }

    public function update(Collection $collection, array $data): Collection
    {
        $collection->update($data);
        return $collection->fresh();
    }

    public function delete(Collection $collection): void
    {
        $collection->delete();
    }

    public function toggleActive(Collection $collection): Collection
    {
        $collection->update(['is_active' => !$collection->is_active]);
        return $collection->fresh();
    }
}

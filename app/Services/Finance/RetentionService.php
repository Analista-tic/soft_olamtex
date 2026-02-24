<?php

namespace App\Services\Finance;

use App\Models\Finance\Retention;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class RetentionService
{
    public function paginate(array $filters = [], int $perPage = 20): LengthAwarePaginator
    {
        $query = Retention::query();

        if (!empty($filters['search'])) {
            $q = $filters['search'];
            $query->where(function ($sub) use ($q) {
                $sub->where('name', 'like', "%{$q}%")
                    ->orWhere('type', 'like', "%{$q}%");
            });
        }

        return $query->orderByDesc('id')->paginate($perPage);
    }

    public function all()
    {
        return Retention::all();
    }

    public function findOrFail($id): Retention
    {
        return Retention::findOrFail($id);
    }

    public function create(array $data): Retention
    {
        return DB::transaction(function () use ($data) {
            return Retention::create($data);
        });
    }

    public function update(Retention $retention, array $data): Retention
    {
        return DB::transaction(function () use ($retention, $data) {
            $retention->update($data);
            return $retention->fresh();
        });
    }

    public function delete(Retention $retention): void
    {
        $retention->delete();
    }
}

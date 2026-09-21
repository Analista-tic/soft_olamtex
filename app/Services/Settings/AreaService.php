<?php

namespace App\Services\Settings;

use App\Exceptions\Settings\AreaInUseException;
use App\Models\Settings\Area;
use Illuminate\Database\Eloquent\Collection;

class AreaService
{
    /**
     * @return Collection<int, Area>
     */
    public function list(): Collection
    {
        return Area::query()
            ->withCount('users')
            ->orderBy('name')
            ->get();
    }

    /**
     * @param array{name: string, is_active?: bool} $data
     */
    public function create(array $data): Area
    {
        return Area::create($data)->loadCount('users');
    }

    /**
     * @param array{name?: string, is_active?: bool} $data
     */
    public function update(Area $area, array $data): Area
    {
        $area->update($data);

        return $area->loadCount('users');
    }

    public function delete(Area $area): void
    {
        $usersCount = $area->users()->count();

        if ($usersCount > 0) {
            throw new AreaInUseException($usersCount);
        }

        $area->delete();
    }
}

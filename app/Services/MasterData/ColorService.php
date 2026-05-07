<?php

namespace App\Services\MasterData;

use App\Models\MasterData\Color;
use Illuminate\Support\Collection;

class ColorService
{
    public function getAll(): Collection
    {
        return Color::select('id', 'name', 'rgb', 'hexadecimal', 'description')
            ->orderBy('name')->get();
    }

    public function create(array $data): Color
    {
        return Color::create($data);
    }

    public function update(Color $color, array $data): Color
    {
        $color->update($data);
        return $color->fresh();
    }

    public function delete(Color $color): void
    {
        $color->delete();
    }
}

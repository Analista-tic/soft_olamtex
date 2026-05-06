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
}

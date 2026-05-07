<?php

namespace App\Services\MasterData;

use App\Models\MasterData\Color;
use App\Models\MasterData\Item;
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
        $itemCount = Item::where('color_id', $color->id)->count();

        if ($itemCount > 0) {
            $label = $itemCount === 1
                ? '1 ítem asignado'
                : "{$itemCount} ítems asignados";

            throw new \RuntimeException(
                "No se puede eliminar el color \"{$color->name}\" porque tiene {$label}. "
                . "Reasigne o quite el color de esos ítems antes de eliminarlo."
            );
        }

        $color->delete();
    }
}

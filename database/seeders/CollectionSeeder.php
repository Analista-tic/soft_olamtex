<?php

namespace Database\Seeders;

use App\Models\Production\Collection;
use Illuminate\Database\Seeder;

/**
 * Catálogo de colecciones (collections).
 *
 * `name` es la clave única: reejecutar el seeder no duplica filas.
 */
class CollectionSeeder extends Seeder
{
    public function run(): void
    {
        foreach ($this->catalog() as $name) {
            Collection::query()->updateOrCreate(
                ['name' => $name],
                ['is_active' => true],
            );
        }
    }

    /**
     * @return list<string>
     */
    private function catalog(): array
    {
        return [
            'CHOCO-LATE', 'FULL BLOOM', 'MODO ON', 'RAIZ', 'PADRES 2025',
            'MEDIA NOCHE', 'MADRES 2025', 'ALBOR', 'SOLICITUD', 'SINTONIA',
        ];
    }
}

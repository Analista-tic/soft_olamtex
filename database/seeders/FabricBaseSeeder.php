<?php

namespace Database\Seeders;

use App\Models\Production\FabricBase;
use Illuminate\Database\Seeder;

/**
 * Catálogo de materiales base de tela (fabric_bases).
 *
 * `nombre` es la clave única: reejecutar el seeder no duplica filas.
 * `codigo` (también único) se asigna correlativo BT-XXX a partir del mayor
 * número ya usado, para no chocar con filas creadas por otros seeders/pruebas.
 */
class FabricBaseSeeder extends Seeder
{
    public function run(): void
    {
        $nextNumber = $this->nextCodigoNumber();

        foreach ($this->catalog() as $nombre) {
            $existing = FabricBase::query()->where('nombre', $nombre)->first();

            if ($existing) {
                $existing->update(['factor' => 1.0, 'is_active' => true]);

                continue;
            }

            FabricBase::query()->create([
                'nombre' => $nombre,
                'codigo' => 'BT-'.str_pad((string) $nextNumber, 3, '0', STR_PAD_LEFT),
                'factor' => 1.0,
                'is_active' => true,
            ]);

            $nextNumber++;
        }
    }

    private function nextCodigoNumber(): int
    {
        $max = FabricBase::query()
            ->where('codigo', 'like', 'BT-%')
            ->pluck('codigo')
            ->map(fn (string $codigo) => (int) substr($codigo, 3))
            ->max();

        return ($max ?? 0) + 1;
    }

    /**
     * @return list<string>
     */
    private function catalog(): array
    {
        return [
            'ALGODÓN', 'POLIÉSTER', 'LINO', 'SEDA', 'LANA', 'BURDA', 'ANTIFLUIDO',
            'FORRO MAYA', 'BURDA SUBLIMADA', 'BENGALINE', 'BURDA VISCOSA', 'LYCRA',
            'LYCRA ALGODÓN', 'BASE SUBLIMAR', 'BURDA PERCHADA', 'RIB', 'PUNTO ROMA',
            'MOMA', 'PERCHADA', 'POLILYCRA', 'HOJALILLO', 'CREPE', 'MALLATEX', 'TWILL',
            'NOCHES DE VIENA', 'DRILL', 'MICROCONFORT', 'GAMUZA', 'RAYON', 'BLONDA',
            'DACRON', 'CUERINA', 'POPELINA', 'FRANELA', 'PIEL DE DURAZNO',
            'FALSO CHALIS CRAQUELADO', 'CHALIS', 'DENIM', 'ESCUBA REPUJADA',
            'ENTRETELA', 'JERSEY', 'CHAMBRAY', 'NYLON', 'CORDUROY', 'GALLETA',
            'CHALÍS ESTAMPADO', 'VISCOSA',
        ];
    }
}

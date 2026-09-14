<?php

namespace Database\Seeders;

use App\Models\MasterData\FabricName;
use App\Models\Production\FabricBase;
use Illuminate\Database\Seeder;

/**
 * Catálogo de nombres de tela (fabric_names), cada uno enlazado a su
 * material base (fabric_bases) por nombre. Requiere que FabricBaseSeeder
 * se haya ejecutado antes.
 *
 * `nombre` es la clave única: reejecutar el seeder no duplica filas.
 */
class FabricNameSeeder extends Seeder
{
    public function run(): void
    {
        $baseIds = FabricBase::query()->pluck('id', 'nombre');

        foreach ($this->catalog() as [$nombre, $baseNombre]) {
            $baseId = $baseIds[$baseNombre] ?? null;

            if (! $baseId) {
                $this->command?->warn("FabricNameSeeder: base '{$baseNombre}' no encontrada para '{$nombre}', se omite.");

                continue;
            }

            FabricName::query()->updateOrCreate(
                ['nombre' => $nombre],
                [
                    'fabric_base_id' => $baseId,
                    'is_active' => true,
                ]
            );
        }
    }

    /**
     * @return list<array{0: string, 1: string}> [nombre_tela, nombre_base_tela]
     */
    private function catalog(): array
    {
        return [
            ['ACERO', 'CUERINA'],
            ['ANTARES', 'BURDA'],
            ['ANTIFLUIDO STRETCH', 'ANTIFLUIDO'],
            ['ARUBA', 'PUNTO ROMA'],
            ['ATENAS', 'BURDA PERCHADA'],
            ['AVANTGARD', 'MOMA'],
            ['BALSILLAS', 'ANTIFLUIDO'],
            ['BANANA', 'POPELINA'],
            ['BENGALINA', 'BENGALINE'],
            ['BENGALINA HEAVY', 'BENGALINE'],
            ['BENGALINA IMP', 'BENGALINE'],
            ['BENGALINA TIFFANY', 'BENGALINE'],
            ['BLONDA', 'BLONDA'],
            ['BOOMY CAMU', 'BURDA'],
            ['BOSSANOVA', 'PUNTO ROMA'],
            ['BOSSE', 'PUNTO ROMA'],
            ['BOSTONFLEX', 'BURDA'],
            ['BOWIE SHELL', 'NYLON'],
            ['BRAZILIA', 'JERSEY'],
            ['BROOKLYN', 'DRILL'],
            ['BURDA SOUL', 'BURDA'],
            ['BYBLOS', 'BENGALINE'],
            ['BYBLOS RAYON', 'BENGALINE'],
            ['CAIRO PLUS', 'BURDA PERCHADA'],
            ['CAMPIÑA', 'POPELINA'],
            ['CAPRI', 'MOMA'],
            ['CARDIGAN RIB', 'RIB'],
            ['CARGO', 'MICROCONFORT'],
            ['CENTA FROZEN', 'LYCRA'],
            ['CHENOA', 'RIB'],
            ['COQUETA', 'TWILL'],
            ['CORDUROY', 'CORDUROY'],
            ['DANESA', 'NOCHES DE VIENA'],
            ['DENIM GINGER', 'DENIM'],
            ['DIESEL', 'CHAMBRAY'],
            ['DIVINA', 'CUERINA'],
            ['DOLCE', 'RIB'],
            ['DRILL ALG', 'DRILL'],
            ['DUBAI', 'MOMA'],
            ['ELOISA', 'CREPE'],
            ['ENTRETELA', 'ENTRETELA'],
            ['EXITO', 'RIB'],
            ['FALCON', 'CUERINA'],
            ['FIORENZO', 'CREPE'],
            ['FIZZ', 'CREPE'],
            ['FLEECE', 'BURDA PERCHADA'],
            ['FLEECE RIB', 'RIB'],
            ['GALLETA', 'CREPE'],
            ['HANA POP', 'CREPE'],
            ['HANNA POP', 'CREPE'],
            ['HOJALILLO007', 'HOJALILLO'],
            ['INDIGO', 'DENIM'],
            ['INDIGO MASALA', 'DENIM'],
            ['INTERLON PESADO', 'ENTRETELA'],
            ['ISLAND668', 'RAYON'],
            ['JABON SHINE', 'POLILYCRA'],
            ['JACQUARD', 'GALLETA'],
            ['JAZMIN', 'TWILL'],
            ['KAROL GI', 'RIB'],
            ['KEIRA PLUS', 'BURDA PERCHADA'],
            ['KOALITA', 'ESCUBA REPUJADA'],
            ['LATINA', 'BURDA'],
            ['LEONOR', 'CHALIS'],
            ['LEVIHOJA', 'POLILYCRA'],
            ['LIMON', 'RIB'],
            ['LINA14', 'CHALIS'],
            ['LINO', 'LINO'],
            ['LIZA', 'RAYON'],
            ['LOTTO', 'BURDA PERCHADA'],
            ['LUMORELIANA-V1', 'POLILYCRA'],
            ['LYCRA BRUSH', 'PIEL DE DURAZNO'],
            ['MALE', 'JERSEY'],
            ['MALLA ORION', 'FORRO MAYA'],
            ['MILE', 'MOMA'],
            ['MININA', 'CUERINA'],
            ['MINIWAFER', 'CREPE'],
            ['MONTEVIDEO', 'BURDA PERCHADA'],
            ['MOREL', 'NYLON'],
            ['MOSCHINO', 'POPELINA'],
            ['NAVAL', 'BURDA PERCHADA'],
            ['OLIMPIA', 'BURDA PERCHADA'],
            ['OLIMPO', 'BURDA'],
            ['OPORTO', 'CREPE'],
            ['OPORTO 002', 'CREPE'],
            ['PALACETE', 'BURDA'],
            ['PALADIO', 'BURDA'],
            ['PALADIO PERCHA', 'BURDA PERCHADA'],
            ['PANDERO', 'RIB'],
            ['PARACHUTE', 'GAMUZA'],
            ['PARIS', 'PIEL DE DURAZNO'],
            ['PEACH SUBLI', 'PIEL DE DURAZNO'],
            ['PEKIN', 'BURDA PERCHADA'],
            ['PIEL DE DURAZNO', 'PIEL DE DURAZNO'],
            ['PONDEROSA', 'PIEL DE DURAZNO'],
            ['POPELINA VISUAL', 'POPELINA'],
            ['POTOMAC', 'BURDA PERCHADA'],
            ['PRETTY', 'DENIM'],
            ['PUNTO ROMA', 'PUNTO ROMA'],
            ['QATAR', 'MOMA'],
            ['RAYON SLUB', 'CHALIS'],
            ['RIB ALASKA', 'RIB'],
            ['RIB ARCOIRIS', 'RIB'],
            ['RIB COORDI', 'RIB'],
            ['RIB EXITO', 'RIB'],
            ['RIB FLEECE', 'RIB'],
            ['RIB JEREMY', 'RIB'],
            ['RIB ROSLEYS', 'RIB'],
            ['RIB STOP', 'DRILL'],
            ['RIB SUPER', 'RIB'],
            ['RIB TITO', 'RIB'],
            ['RIB TORONJA', 'RIB'],
            ['RIB UNICOLOR', 'RIB'],
            ['RIB VERSACE', 'RIB'],
            ['ROMANCE', 'MOMA'],
            ['ROSELLO PERCHA', 'BURDA PERCHADA'],
            ['ROSLEYS', 'RIB'],
            ['ROYAL', 'CREPE'],
            ['SALSA ENMALLADA', 'MALLATEX'],
            ['SICILIA', 'TWILL'],
            ['SLUBPARAISO03', 'RAYON'],
            ['SLUBPARAISO04', 'RAYON'],
            ['SMILISH', 'BURDA PERCHADA'],
            ['SONORY CRUNCH', 'CREPE'],
            ['SPRIT EST', 'CHALÍS ESTAMPADO'],
            ['SUEDE', 'GAMUZA'],
            ['SUEÑOS', 'BURDA'],
            ['SUGAR', 'NOCHES DE VIENA'],
            ['SUPER', 'RIB'],
            ['SUSAN', 'CHALIS'],
            ['TAYLOR PLUS', 'DRILL'],
            ['TERRA RECYCLED', 'BURDA PERCHADA'],
            ['TERRY', 'BURDA VISCOSA'],
            ['TOBAGO', 'FRANELA'],
            ['TORONJA PERCHADA', 'BURDA PERCHADA'],
            ['TWIZT', 'BURDA PERCHADA'],
            ['TWIZT EIB', 'RIB'],
            ['VALENTINA', 'MOMA'],
            ['VALIANA', 'MALLATEX'],
            ['VALIANAS', 'MALLATEX'],
            ['VANCOOVER', 'BURDA PERCHADA'],
            ['VISCOSA', 'VISCOSA'],
            ['WAFFLE', 'JERSEY'],
        ];
    }
}

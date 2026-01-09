<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Parameter;

class MigrateToParametersSeeder extends Seeder
{
    /**
     * Inserta datos de catálogos en la tabla parameters.
     */
    public function run(): void
    {
        $this->command->info('Iniciando inserción de parámetros de catálogos...');

        $catalogues = [
            'sections' => [
                ['id' => 1, 'name' => 'BERMUDA'],
                ['id' => 2, 'name' => 'BODY'],
                ['id' => 3, 'name' => 'BUZO'],
                ['id' => 4, 'name' => 'CAMISETA'],
                ['id' => 5, 'name' => 'CAPRY'],
                ['id' => 6, 'name' => 'CARDIGAN'],
                ['id' => 7, 'name' => 'CHALECO'],
                ['id' => 8, 'name' => 'CHAQUETA'],
                ['id' => 9, 'name' => 'CONJUNTO'],
                ['id' => 10, 'name' => 'ENTERIZO'],
                ['id' => 11, 'name' => 'FALDA'],
                ['id' => 12, 'name' => 'JOGGER'],
                ['id' => 13, 'name' => 'LEGGIN'],
                ['id' => 14, 'name' => 'OVEROL'],
                ['id' => 15, 'name' => 'PANTALON'],
                ['id' => 16, 'name' => 'SHORT'],
                ['id' => 17, 'name' => 'TOP'],
                ['id' => 18, 'name' => 'VESTIDO'],
            ],
            'groups' => [
                ['id' => 1, 'name' => 'LINEA'],
                ['id' => 2, 'name' => 'PRONTA MODA'],
            ],
            'brands' => [
                ['id' => 1, 'name' => 'EVA CASTILLO'],
                ['id' => 2, 'name' => 'AMERICAN EAST'],
                ['id' => 3, 'name' => 'HERPO'],
                ['id' => 4, 'name' => 'PASSARO'],
                ['id' => 5, 'name' => 'LOHARY'],
                ['id' => 6, 'name' => 'ATUENDOS'],
                ['id' => 7, 'name' => 'LAXO'],
                ['id' => 8, 'name' => 'THE ANSWER'],
            ],
            'worlds' => [
                ['id' => 1, 'name' => 'DAMA'],
                ['id' => 2, 'name' => 'HOMBRE'],
                ['id' => 3, 'name' => 'JUNIOR'],
                ['id' => 4, 'name' => 'NIÑO'],
                ['id' => 5, 'name' => 'NIÑA'],
                ['id' => 6, 'name' => 'TEEN'],
            ],
            'fabric_bases' => [
                ['id' => 1, 'name' => 'BURDA'],
                ['id' => 2, 'name' => 'ALGODON'],
                ['id' => 3, 'name' => 'ANTIFLUIDO'],
                ['id' => 4, 'name' => 'BENGALINA'],
                ['id' => 5, 'name' => 'LYCRA'],
                ['id' => 6, 'name' => 'LYCRA ALGODON'],
                ['id' => 7, 'name' => 'PERCHADA'],
                ['id' => 8, 'name' => 'RIB'],
                ['id' => 9, 'name' => 'PUNTO ROMA'],
                ['id' => 10, 'name' => 'POLY LYCRA'],
                ['id' => 11, 'name' => 'FRANELA'],
                ['id' => 12, 'name' => 'DRILL'],
                ['id' => 13, 'name' => 'TWILL'],
                ['id' => 14, 'name' => 'PANA'],
                ['id' => 15, 'name' => 'LAME'],
                ['id' => 16, 'name' => 'MICRO CONFORT'],
                ['id' => 17, 'name' => 'PIEL DURAZNO'],
            ],
            'yarn_bases' => [
                ['id' => 1, 'name' => 'ACRÍLICO'],
                ['id' => 2, 'name' => 'ACRÍLICO ALGODÓN'],
                ['id' => 3, 'name' => 'ACRÍLICO POLIÉSTER'],
                ['id' => 4, 'name' => 'POLIÉSTER'],
                ['id' => 5, 'name' => 'SPUN'],
                ['id' => 6, 'name' => 'ALGODÓN'],
                ['id' => 7, 'name' => 'MODAL'],
                ['id' => 8, 'name' => 'CHINCHILLA'],
                ['id' => 9, 'name' => 'PELO CONEJO'],
                ['id' => 10, 'name' => 'LUREX'],
                ['id' => 11, 'name' => 'ELÁSTICO'],
            ],
            'destinations' => [
                ['id' => 1, 'name' => 'CCU'],
                ['id' => 2, 'name' => 'EJETEX'],
                ['id' => 3, 'name' => 'PASSARO'],
                ['id' => 4, 'name' => 'HERPO'],
                ['id' => 5, 'name' => 'ATUENDOS'],
                ['id' => 6, 'name' => 'LOHARY'],
            ],
        ];

        foreach ($catalogues as $name => $data) {
            Parameter::updateOrCreate(
                ['nombre' => $name],
                ['valor' => $data]
            );
            $this->command->info("✓ Insertado '{$name}': " . count($data) . " registros");
        }

        $this->command->info('¡Inserción completada exitosamente!');
    }
}

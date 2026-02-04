<?php

namespace Database\Seeders;

use App\Models\Setting;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\File;

class SettingsCatalogsSeeder extends Seeder
{
    public function run(): void
    {
        foreach ($this->parameterDefinitions() as $key => $definition) {
            Setting::updateOrCreate(
                ['kind' => 'parameter', 'key' => $key],
                [
                    'label' => $definition['label'],
                    'description' => $definition['description'] ?? null,
                    'value' => $definition['value'],
                    'status' => true,
                ]
            );
        }
    }

    private function parameterDefinitions(): array
    {
        $unitMeasures = $this->loadUnitMeasures();

        return [
            'areas' => [
                'label' => 'Áreas',
                'description' => 'Catálogo de áreas organizacionales',
                'value' => $this->mapNames([
                    'Administración',
                    'Ventas',
                    'Producción',
                    'Compras',
                    'Logística',
                    'Contabilidad',
                ]),
            ],
            'base_tela' => [
                'label' => 'Base tela',
                'value' => ['ALGODÓN', 'POLIÉSTER', 'LINO', 'SEDA', 'LANA'],
            ],
            'base_hilaza' => [
                'label' => 'Base hilaza',
                'value' => ['NYLON', 'RAYÓN', 'ACRÍLICO', 'POLIPROPILENO', 'ALGODÓN'],
            ],
            'color' => [
                'label' => 'Color',
                'value' => ['ROJO', 'VERDE', 'AZUL', 'NEGRO', 'BLANCO'],
            ],
            'lineas' => [
                'label' => 'Líneas',
                'value' => ['LÍNEA 1', 'LÍNEA 2', 'LÍNEA 3', 'LÍNEA 4', 'LÍNEA 5'],
            ],
            'tipo_puntera' => [
                'label' => 'Tipo puntera',
                'value' => ['REDONDA', 'CUADRADA', 'PUNTIAGUDA', 'ABIERTA', 'CERRADA'],
            ],
            'talla' => [
                'label' => 'Talla',
                'value' => ['XS', 'S', 'M', 'L', 'XL', 'XXL', '34', '35', '36', '37', '38', '39', '40', '41', '42', '43', '44', '45'],
            ],
            'mundo' => [
                'label' => 'Mundo',
                'value' => ['HOMBRE', 'MUJER', 'NIÑO', 'NIÑA'],
            ],
            'costo operaciones' => [
                'label' => 'Costo de operaciones',
                'value' => 406,
            ],
            'costo hilo' => [
                'label' => 'Costo de hilo',
                'value' => 140,
            ],
            'sections' => [
                'label' => 'Secciones',
                'description' => 'Catálogo de secciones de producto',
                'value' => $this->mapNames([
                    'BERMUDA',
                    'BODY',
                    'BUZO',
                    'CAMISETA',
                    'CAPRY',
                    'CARDIGAN',
                    'CHALECO',
                    'CHAQUETA',
                    'CONJUNTO',
                    'ENTERIZO',
                    'FALDA',
                    'JOGGER',
                    'LEGGIN',
                    'OVEROL',
                    'PANTALON',
                    'SHORT',
                    'TOP',
                    'VESTIDO',
                ]),
            ],
            'groups' => [
                'label' => 'Grupos',
                'value' => $this->mapNames(['LINEA', 'PRONTA MODA']),
            ],
            'brands' => [
                'label' => 'Marcas',
                'value' => $this->mapNames([
                    'EVA CASTILLO',
                    'AMERICAN EAST',
                    'HERPO',
                    'PASSARO',
                    'LOHARY',
                    'ATUENDOS',
                    'LAXO',
                    'THE ANSWER',
                ]),
            ],
            'worlds' => [
                'label' => 'Mundos',
                'value' => $this->mapNames([
                    'DAMA',
                    'HOMBRE',
                    'JUNIOR',
                    'NIÑO',
                    'NIÑA',
                    'TEEN',
                ]),
            ],
            'fabric_bases' => [
                'label' => 'Bases de tela',
                'value' => $this->mapNames([
                    'BURDA',
                    'ALGODON',
                    'ANTIFLUIDO',
                    'BENGALINA',
                    'LYCRA',
                    'LYCRA ALGODON',
                    'PERCHADA',
                    'RIB',
                    'PUNTO ROMA',
                    'POLY LYCRA',
                    'FRANELA',
                    'DRILL',
                    'TWILL',
                    'PANA',
                    'LAME',
                    'MICRO CONFORT',
                    'PIEL DURAZNO',
                ]),
            ],
            'yarn_bases' => [
                'label' => 'Bases de hilaza',
                'value' => $this->mapNames([
                    'ACRÍLICO',
                    'ACRÍLICO ALGODÓN',
                    'ACRÍLICO POLIÉSTER',
                    'POLIÉSTER',
                    'SPUN',
                    'ALGODÓN',
                    'MODAL',
                    'CHINCHILLA',
                    'PELO CONEJO',
                    'LUREX',
                    'ELÁSTICO',
                ]),
            ],
            'destinations' => [
                'label' => 'Destinos',
                'description' => 'Clientes o líneas comerciales frecuentes',
                'value' => $this->mapNames([
                    'CCU',
                    'EJETEX',
                    'PASSARO',
                    'HERPO',
                    'ATUENDOS',
                    'LOHARY',
                ]),
            ],
            'unit_measure_groups' => [
                'label' => 'Unidades de medida',
                'description' => 'Estructura completa de grupos y opciones',
                'value' => $unitMeasures,
            ],
        ];
    }

    private function mapNames(array $names, int $start = 1): array
    {
        $mapped = [];
        foreach ($names as $index => $name) {
            $mapped[] = [
                'id' => $start + $index,
                'name' => $name,
            ];
        }

        return $mapped;
    }

    private function loadUnitMeasures(): array
    {
        $path = base_path('resources/js/data/unitMeasures.json');
        if (!File::exists($path)) {
            return [];
        }

        $data = json_decode(File::get($path), true);
        return is_array($data) ? $data : [];
    }
}

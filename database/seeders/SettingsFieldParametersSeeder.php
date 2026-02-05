<?php

namespace Database\Seeders;

use App\Models\Setting;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\File;

class SettingsFieldParametersSeeder extends Seeder
{
    public function run(): void
    {
        foreach ($this->definitions() as $item) {
            Setting::updateOrCreate(
                ['kind' => $item['kind'], 'key' => $item['key']],
                $item['data']
            );
        }
    }

    private function definitions(): array
    {
        // Parámetros
        $parameters = [
            [
                'kind' => 'parameter',
                'key' => 'areas',
                'data' => [
                    'label' => 'Áreas',
                    'description' => 'Catálogo de áreas organizacionales',
                    'value' => $this->mapNames([
                        'Administración', 'Ventas', 'Producción', 'Compras', 'Logística', 'Contabilidad',
                    ]),
                    'status' => true,
                ],
            ],
            [
                'kind' => 'parameter',
                'key' => 'costo operaciones',
                'data' => [
                    'label' => 'Costo de operaciones',
                    'value' => 406,
                    'status' => true,
                ],
            ],
            [
                'kind' => 'parameter',
                'key' => 'costo hilo',
                'data' => [
                    'label' => 'Costo de hilo',
                    'value' => 140,
                    'status' => true,
                ],
            ],
            [
                    'kind' => 'parameter',
                    'key' => 'lineas',
                    'data' => [
                        'label' => 'Líneas',
                        'value' => ['LÍNEA 1', 'LÍNEA 2', 'LÍNEA 3', 'LÍNEA 4', 'LÍNEA 5'],
                        'status' => true,
                    ],
            ],
            [
                'kind' => 'parameter',
                'key' => 'secciones',
                'data' => [
                    'label' => 'Secciones',
                    'description' => 'Catálogo de secciones de producto',
                    'value' => $this->mapNames([
                        'BERMUDA', 'BODY', 'BUZO', 'CAMISETA', 'CAPRY', 'CARDIGAN', 'CHALECO', 'CHAQUETA', 'CONJUNTO', 'ENTERIZO', 'FALDA', 'JOGGER', 'LEGGIN', 'OVEROL', 'PANTALON', 'SHORT', 'TOP', 'VESTIDO',
                    ]),
                    'status' => true,
                ],
            ],
            [
                'kind' => 'parameter',
                'key' => 'marcas',
                'data' => [
                    'label' => 'Marcas',
                    'value' => $this->mapNames([
                        'EVA CASTILLO', 'AMERICAN EAST', 'HERPO', 'PASSARO', 'LOHARY', 'ATUENDOS', 'LAXO', 'THE ANSWER',
                    ]),
                    'status' => true,
                ],
            ],
            [
                'kind' => 'parameter',
                'key' => 'mundos',
                'data' => [
                    'label' => 'Mundos',
                    'value' => $this->mapNames([
                        'DAMA', 'HOMBRE', 'JUNIOR', 'NIÑO', 'NIÑA', 'TEEN',
                    ]),
                    'status' => true,
                ],
            ],
            [
                'kind' => 'parameter',
                'key' => 'tallas',
                'data' => [
                    'label' => 'Tallas',
                    'value' => ['XS', 'S', 'M', 'L', 'XL', 'XXL', '34', '35', '36', '37', '38', '39', '40', '41', '42', '43', '44', '45'],
                    'status' => true,
                ],
            ],
            [
                'kind' => 'parameter',
                'key' => 'destinos',
                'data' => [
                    'label' => 'Destinos',
                    'description' => 'Clientes o líneas comerciales frecuentes',
                    'value' => $this->mapNames([
                        'CCU', 'EJETEX', 'PASSARO', 'HERPO', 'ATUENDOS', 'LOHARY',
                    ]),
                    'status' => true,
                ],
            ],
        ];

        // Definiciones de campos
        $fields = [
            [
                'kind' => 'field',
                'key' => 'base_tela',
                'data' => [
                    'label' => 'Base tela',
                    'field_type' => 'select',
                    'options' => ['ALGODÓN', 'POLIÉSTER', 'LINO', 'SEDA', 'LANA'],
                    'position' => 1,
                    'status' => true,
                ],
            ],
            [
                'kind' => 'field',
                'key' => 'base_hilaza',
                'data' => [
                    'label' => 'Base hilaza',
                    'field_type' => 'select',
                    'options' => ['NYLON', 'RAYÓN', 'ACRÍLICO', 'POLIPROPILENO', 'ALGODÓN'],
                    'position' => 2,
                    'status' => true,
                ],
            ],
            [
                'kind' => 'field',
                'key' => 'color',
                'data' => [
                    'label' => 'Color',
                    'field_type' => 'select',
                    'options' => ['ROJO', 'VERDE', 'AZUL', 'NEGRO', 'BLANCO'],
                    'position' => 3,
                    'status' => true,
                ],
            ],
        ];

        return array_merge($parameters, $fields);
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
}

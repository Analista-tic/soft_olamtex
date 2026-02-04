<?php

namespace Database\Seeders;

use App\Models\Setting;
use Illuminate\Database\Seeder;

class SettingsFieldDefinitionsSeeder extends Seeder
{
    public function run(): void
    {
        foreach ($this->fieldDefinitions() as $key => $definition) {
            Setting::updateOrCreate(
                ['kind' => 'field', 'key' => $key],
                [
                    'label' => $definition['label'],
                    'field_type' => $definition['field_type'],
                    'options' => $definition['options'] ?? null,
                    'default_value' => $definition['default_value'] ?? null,
                    'is_required' => $definition['is_required'] ?? false,
                    'description' => $definition['description'] ?? null,
                    'position' => $definition['position'] ?? null,
                    'status' => $definition['status'] ?? true,
                    'value' => null,
                ]
            );
        }
    }

    private function fieldDefinitions(): array
    {
        $brands = $this->catalogOptions('brands');
        $worlds = $this->catalogOptions('worlds');

        return [
            'base_tela' => [
                'label' => 'Base tela',
                'field_type' => 'select',
                'options' => ['ALGODÓN', 'POLIÉSTER', 'LINO', 'SEDA', 'LANA'],
                'position' => 1,
            ],
            'base_hilaza' => [
                'label' => 'Base hilaza',
                'field_type' => 'select',
                'options' => ['NYLON', 'RAYÓN', 'ACRÍLICO', 'POLIPROPILENO', 'ALGODÓN'],
                'position' => 2,
            ],
            'color' => [
                'label' => 'Color',
                'field_type' => 'select',
                'options' => ['ROJO', 'VERDE', 'AZUL', 'NEGRO', 'BLANCO'],
                'position' => 3,
            ],
            'marca' => [
                'label' => 'Marca',
                'field_type' => 'select',
                'options' => $brands,
                'position' => 4,
            ],
            'mundo' => [
                'label' => 'Mundo',
                'field_type' => 'select',
                'options' => $worlds,
                'position' => 5,
            ],
            'lineas' => [
                'label' => 'Líneas',
                'field_type' => 'select',
                'options' => ['LÍNEA 1', 'LÍNEA 2', 'LÍNEA 3', 'LÍNEA 4', 'LÍNEA 5'],
                'position' => 6,
            ],
            'tipo_puntera' => [
                'label' => 'Tipo puntera',
                'field_type' => 'select',
                'options' => ['REDONDA', 'CUADRADA', 'PUNTIAGUDA', 'ABIERTA', 'CERRADA'],
                'position' => 7,
            ],
            'talla' => [
                'label' => 'Talla',
                'field_type' => 'select',
                'options' => ['XS', 'S', 'M', 'L', 'XL', 'XXL', '34', '35', '36', '37', '38', '39', '40', '41', '42', '43', '44', '45'],
                'position' => 8,
            ],
        ];
    }

    private function catalogOptions(string $key): array
    {
        $setting = Setting::parameters()->where('key', $key)->first();
        return $setting ? $setting->value : [];
    }
}

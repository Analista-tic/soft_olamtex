<?php

namespace Database\Seeders;

use App\Models\Inventory\AdditionalField;
use Illuminate\Database\Seeder;

class AdditionalFieldsSeeder extends Seeder
{
    /**
     * Seed the additional fields that are shared with the inventory item form.
     */
    public function run(): void
    {
        $defaultAttributes = [
            'field_type' => 'text',
            'options' => null,
            'default_value' => null,
            'description' => null,
            'is_required' => false,
            'status' => true,
        ];

        $fields = [
            [   
                'name' => 'Base tela', 
                'field_type' => 'select', 
                'options' => ['ALGODÓN', 'Poliéster', 'LINO', 'SEDA', 'LANA']
            ],
            [
                'name' => 'Base hilaza', 
                'field_type' => 'select', 
                'options' => ['NYLON', 'RAYÓN', 'ACRÍLICO', 'POLIPROPILENO', 'ALGODÓN'],
            ],
            [
                'name' => 'Color', 
                'field_type' => 'select',
                'options' => ['ROJO', 'VERDE', 'AZUL', 'NEGRO', 'BLANCO'],
            ],
            ['name' => 'Marca', 'field_type' => 'select'],
            ['name' => 'Mundo', 'field_type' => 'select'],
            [
                'name' => 'Líneas',
                'field_type' => 'select',
                'options' => ['LÍNEA 1', 'LÍNEA 2', 'LÍNEA 3', 'LÍNEA 4', 'LÍNEA 5'],
            ],
            [
                'name' => 'Tipo puntera',
                'field_type' => 'select',
                'options' => ['REDONDA', 'CUADRADA', 'PUNTIAGUDA', 'ABIERTA', 'CERRADA'],
            ],
            [
                'name' => 'Talla',
                'field_type' => 'select',
                'options' => ['XS', 'S', 'M', 'L', 'XL', 'XXL', '34', '35', '36', '37', '38', '39', '40', '41', '42', '43', '44', '45'],
            ],
        ];

        foreach ($fields as $field) {
            AdditionalField::updateOrCreate(
                ['name' => $field['name']],
                array_merge($defaultAttributes, $field)
            );
        }
    }
}


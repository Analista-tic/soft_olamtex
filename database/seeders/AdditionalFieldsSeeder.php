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
                'options' => ['Algodón', 'Poliéster', 'Lino', 'Seda', 'Lana']
            ],
            [
                'name' => 'Base hilaza', 
                'field_type' => 'select', 
                'options' => ['Nylon', 'Rayón', 'Acrílico', 'Polipropileno', 'Algodón'],
            ],
            ['name' => 'Color', 'field_type' => 'select'],
            ['name' => 'Marca', 'field_type' => 'select'],
            ['name' => 'Mundo', 'field_type' => 'select'],
            [
                'name' => 'Líneas',
                'field_type' => 'select',
                'options' => ['Línea 1', 'Línea 2', 'Línea 3', 'Línea 4', 'Línea 5'],
            ],
            [
                'name' => 'Tipo puntera',
                'field_type' => 'select',
                'options' => ['Redonda', 'Cuadrada', 'Puntiaguda', 'Abierta', 'Cerrada'],
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


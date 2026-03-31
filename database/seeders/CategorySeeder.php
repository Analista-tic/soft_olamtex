<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class CategorySeeder extends Seeder
{
    public function run(): void
    {
        $groups = [
            ['name' => 'Materia Prima'],
            ['name' => 'Insumos'],
            ['name' => 'Suministros'],
            ['name' => 'Aditamentos'],
            ['name' => 'Herramientas'],
            ['name' => 'Repuestos'],
            ['name' => 'Papelería y Aseo'],
            ['name' => 'EPP'],
            ['name' => 'Servicios'],
            ['name' => 'Maquinaria'],
            ['name' => 'Producto Terminado'],
        ];

        DB::table('category_groups')->insert($groups);

        $groupIds = DB::table('category_groups')->pluck('id', 'name');

        $subGroups = [
            // Materia Prima
            ['name' => 'Tela',   'category_group_id' => $groupIds['Materia Prima']],
            ['name' => 'Hilaza', 'category_group_id' => $groupIds['Materia Prima']],
            ['name' => 'Hilo',   'category_group_id' => $groupIds['Materia Prima']],

            // Insumos
            ['name' => 'Accesorios',  'category_group_id' => $groupIds['Insumos']],
            ['name' => 'Adorno',      'category_group_id' => $groupIds['Insumos']],
            ['name' => 'Aguja',       'category_group_id' => $groupIds['Insumos']],
            ['name' => 'Aplique',     'category_group_id' => $groupIds['Insumos']],
            ['name' => 'Bolsa',       'category_group_id' => $groupIds['Insumos']],
            ['name' => 'Botón',       'category_group_id' => $groupIds['Insumos']],
            ['name' => 'Cinta',       'category_group_id' => $groupIds['Insumos']],
            ['name' => 'Código',      'category_group_id' => $groupIds['Insumos']],
            ['name' => 'Cordón',      'category_group_id' => $groupIds['Insumos']],
            ['name' => 'Correa',      'category_group_id' => $groupIds['Insumos']],
            ['name' => 'Cremallera',  'category_group_id' => $groupIds['Insumos']],
            ['name' => 'Elástico',    'category_group_id' => $groupIds['Insumos']],
            ['name' => 'Etiqueta',    'category_group_id' => $groupIds['Insumos']],
            ['name' => 'Garra',       'category_group_id' => $groupIds['Insumos']],
            ['name' => 'General',     'category_group_id' => $groupIds['Insumos']],
            ['name' => 'Hebilla',     'category_group_id' => $groupIds['Insumos']],
            ['name' => 'Herraje',     'category_group_id' => $groupIds['Insumos']],
            ['name' => 'Hiladilla',   'category_group_id' => $groupIds['Insumos']],
            ['name' => 'Hilaza',      'category_group_id' => $groupIds['Insumos']],
            ['name' => 'Instrucción', 'category_group_id' => $groupIds['Insumos']],
            ['name' => 'Marquilla',   'category_group_id' => $groupIds['Insumos']],
            ['name' => 'Ojalete',     'category_group_id' => $groupIds['Insumos']],
            ['name' => 'Placa',       'category_group_id' => $groupIds['Insumos']],
            ['name' => 'Remache',     'category_group_id' => $groupIds['Insumos']],
            ['name' => 'Varios',      'category_group_id' => $groupIds['Insumos']],

            // Suministros
            ['name' => 'Caja',        'category_group_id' => $groupIds['Suministros']],
            ['name' => 'Cinta',       'category_group_id' => $groupIds['Suministros']],
            ['name' => 'Consumibles', 'category_group_id' => $groupIds['Suministros']],
            ['name' => 'Empaque',     'category_group_id' => $groupIds['Suministros']],
            ['name' => 'Rollo',       'category_group_id' => $groupIds['Suministros']],
            ['name' => 'Varios',      'category_group_id' => $groupIds['Suministros']],

            // Aditamentos
            ['name' => 'Accesorios maquina', 'category_group_id' => $groupIds['Aditamentos']],
            ['name' => 'Cuchilla',           'category_group_id' => $groupIds['Aditamentos']],
            ['name' => 'Paño',               'category_group_id' => $groupIds['Aditamentos']],

            // Herramientas
            ['name' => 'Chequeador',         'category_group_id' => $groupIds['Herramientas']],
            ['name' => 'Destornillador',     'category_group_id' => $groupIds['Herramientas']],
            ['name' => 'Encintador',         'category_group_id' => $groupIds['Herramientas']],
            ['name' => 'Grapadora',          'category_group_id' => $groupIds['Herramientas']],
            ['name' => 'Herramientas varias','category_group_id' => $groupIds['Herramientas']],
            ['name' => 'Medición',           'category_group_id' => $groupIds['Herramientas']],
            ['name' => 'Pistola',            'category_group_id' => $groupIds['Herramientas']],
            ['name' => 'Tijera',             'category_group_id' => $groupIds['Herramientas']],
            ['name' => 'Troquel',            'category_group_id' => $groupIds['Herramientas']],

            // Repuestos
            ['name' => 'General', 'category_group_id' => $groupIds['Repuestos']],

            // Papelería y Aseo
            ['name' => 'Bolsa de basura',    'category_group_id' => $groupIds['Papelería y Aseo']],
            ['name' => 'Bolígrafo',          'category_group_id' => $groupIds['Papelería y Aseo']],
            ['name' => 'Carpeta',            'category_group_id' => $groupIds['Papelería y Aseo']],
            ['name' => 'Cinta adhesiva',     'category_group_id' => $groupIds['Papelería y Aseo']],
            ['name' => 'Cosedora',           'category_group_id' => $groupIds['Papelería y Aseo']],
            ['name' => 'Desinfectante',      'category_group_id' => $groupIds['Papelería y Aseo']],
            ['name' => 'Envase',             'category_group_id' => $groupIds['Papelería y Aseo']],
            ['name' => 'Escritorio',         'category_group_id' => $groupIds['Papelería y Aseo']],
            ['name' => 'Esponja',            'category_group_id' => $groupIds['Papelería y Aseo']],
            ['name' => 'Jabón y detergente', 'category_group_id' => $groupIds['Papelería y Aseo']],
            ['name' => 'Limpieza pisos',     'category_group_id' => $groupIds['Papelería y Aseo']],
            ['name' => 'Marcador',           'category_group_id' => $groupIds['Papelería y Aseo']],
            ['name' => 'Resma',              'category_group_id' => $groupIds['Papelería y Aseo']],
            ['name' => 'Talonario',          'category_group_id' => $groupIds['Papelería y Aseo']],
            ['name' => 'Tecnología',         'category_group_id' => $groupIds['Papelería y Aseo']],
            ['name' => 'Tijera',             'category_group_id' => $groupIds['Papelería y Aseo']],
            ['name' => 'Tinta',              'category_group_id' => $groupIds['Papelería y Aseo']],
            ['name' => 'Toalla',             'category_group_id' => $groupIds['Papelería y Aseo']],
            ['name' => 'Varios aseo',        'category_group_id' => $groupIds['Papelería y Aseo']],
            ['name' => 'Varios papelería',   'category_group_id' => $groupIds['Papelería y Aseo']],

            // EPP
            ['name' => 'Botiquín',             'category_group_id' => $groupIds['EPP']],
            ['name' => 'Calzado',              'category_group_id' => $groupIds['EPP']],
            ['name' => 'Guantes',              'category_group_id' => $groupIds['EPP']],
            ['name' => 'Primeros auxilios',    'category_group_id' => $groupIds['EPP']],
            ['name' => 'Protección auditiva',  'category_group_id' => $groupIds['EPP']],
            ['name' => 'Protección visual',    'category_group_id' => $groupIds['EPP']],

            // Servicios
            ['name' => 'Confección', 'category_group_id' => $groupIds['Servicios']],

            // Maquinaria
            ['name' => 'Máquina', 'category_group_id' => $groupIds['Maquinaria']],

            // Producto Terminado
            ['name' => 'Bermuda',  'category_group_id' => $groupIds['Producto Terminado']],
            ['name' => 'Blusa',    'category_group_id' => $groupIds['Producto Terminado']],
            ['name' => 'Buzo',     'category_group_id' => $groupIds['Producto Terminado']],
            ['name' => 'Chaleco',  'category_group_id' => $groupIds['Producto Terminado']],
            ['name' => 'Pantalón', 'category_group_id' => $groupIds['Producto Terminado']],
        ];

        DB::table('category_sub_groups')->insert($subGroups);
    }
}

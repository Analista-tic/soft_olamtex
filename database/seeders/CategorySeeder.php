<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class CategorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Insertar grupos
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
        ];

        DB::table('category_groups')->insert($groups);

        // Obtener los IDs de los grupos
        $groupIds = DB::table('category_groups')->pluck('id', 'name');

        // Insertar subgrupos
        $subGroups = [
            // Subgrupos para "Materia Prima"
            ['name' => 'Tela', 'category_group_id' => $groupIds['Materia Prima']],
            ['name' => 'Hilaza', 'category_group_id' => $groupIds['Materia Prima']],

            // Subgrupos para "Insumos"
            ['name' => 'Etiqueta', 'category_group_id' => $groupIds['Insumos']],
            ['name' => 'Botón', 'category_group_id' => $groupIds['Insumos']],
            ['name' => 'Código', 'category_group_id' => $groupIds['Insumos']],
            ['name' => 'Cierre', 'category_group_id' => $groupIds['Insumos']],
            ['name' => 'Resina', 'category_group_id' => $groupIds['Insumos']],
            ['name' => 'Elástico', 'category_group_id' => $groupIds['Insumos']],
            ['name' => 'Cinta', 'category_group_id' => $groupIds['Insumos']],
            ['name' => 'Cordón', 'category_group_id' => $groupIds['Insumos']],
            ['name' => 'Aplique', 'category_group_id' => $groupIds['Insumos']],
            ['name' => 'Bolsa', 'category_group_id' => $groupIds['Insumos']],
            ['name' => 'Tache', 'category_group_id' => $groupIds['Insumos']],
            ['name' => 'Herraje', 'category_group_id' => $groupIds['Insumos']],
            ['name' => 'Marquilla', 'category_group_id' => $groupIds['Insumos']],
            ['name' => 'Remache', 'category_group_id' => $groupIds['Insumos']],
            ['name' => 'Hilo', 'category_group_id' => $groupIds['Insumos']],

            // Subgrupos para "Suministros"
            ['name' => 'Plastiflecha', 'category_group_id' => $groupIds['Suministros']],
            ['name' => 'Papel', 'category_group_id' => $groupIds['Suministros']],
            ['name' => 'Aceite', 'category_group_id' => $groupIds['Suministros']],
            ['name' => 'Caja', 'category_group_id' => $groupIds['Suministros']],
            ['name' => 'Cinta', 'category_group_id' => $groupIds['Suministros']],
            ['name' => 'Crema', 'category_group_id' => $groupIds['Suministros']],
            ['name' => 'Código', 'category_group_id' => $groupIds['Suministros']],
            ['name' => 'Tinta', 'category_group_id' => $groupIds['Suministros']],

            // Subgrupos para "Herramientas"
            ['name' => 'Teclado', 'category_group_id' => $groupIds['Herramientas']],

            // Subgrupos para "Repuestos"
            ['name' => 'Looper', 'category_group_id' => $groupIds['Repuestos']],
            ['name' => 'Fusible', 'category_group_id' => $groupIds['Repuestos']],
            ['name' => 'Caja', 'category_group_id' => $groupIds['Repuestos']],
            ['name' => 'Bobina', 'category_group_id' => $groupIds['Repuestos']],
            ['name' => 'Correa', 'category_group_id' => $groupIds['Repuestos']],
            ['name' => 'Motor', 'category_group_id' => $groupIds['Repuestos']],

            // Subgrupos para "Papelería y Aseo"
            ['name' => 'Bolígrafo', 'category_group_id' => $groupIds['Papelería y Aseo']],
            ['name' => 'Bolsa de basura', 'category_group_id' => $groupIds['Papelería y Aseo']],
            ['name' => 'Borrador', 'category_group_id' => $groupIds['Papelería y Aseo']],
            ['name' => 'Calculadora', 'category_group_id' => $groupIds['Papelería y Aseo']],
            ['name' => 'Candela', 'category_group_id' => $groupIds['Papelería y Aseo']],
            ['name' => 'Carpeta', 'category_group_id' => $groupIds['Papelería y Aseo']],
            ['name' => 'Cepillo', 'category_group_id' => $groupIds['Papelería y Aseo']],
            ['name' => 'Clip', 'category_group_id' => $groupIds['Papelería y Aseo']],
            ['name' => 'Corrector', 'category_group_id' => $groupIds['Papelería y Aseo']],
            ['name' => 'Cosedora', 'category_group_id' => $groupIds['Papelería y Aseo']],
            ['name' => 'Cuaderno', 'category_group_id' => $groupIds['Papelería y Aseo']],
            ['name' => 'Escoba', 'category_group_id' => $groupIds['Papelería y Aseo']],
            ['name' => 'Gancho', 'category_group_id' => $groupIds['Papelería y Aseo']],
            ['name' => 'Guante', 'category_group_id' => $groupIds['Papelería y Aseo']],
            ['name' => 'Guía', 'category_group_id' => $groupIds['Papelería y Aseo']],
            ['name' => 'Lápiz', 'category_group_id' => $groupIds['Papelería y Aseo']],
            ['name' => 'Envase', 'category_group_id' => $groupIds['Papelería y Aseo']],
            ['name' => 'Marcador', 'category_group_id' => $groupIds['Papelería y Aseo']],
            ['name' => 'Resaltador', 'category_group_id' => $groupIds['Papelería y Aseo']],
            ['name' => 'Minas', 'category_group_id' => $groupIds['Papelería y Aseo']],
            ['name' => 'Pega notas', 'category_group_id' => $groupIds['Papelería y Aseo']],
            ['name' => 'Colbon', 'category_group_id' => $groupIds['Papelería y Aseo']],
            ['name' => 'Recogedor', 'category_group_id' => $groupIds['Papelería y Aseo']],
            ['name' => 'Bisturí', 'category_group_id' => $groupIds['Papelería y Aseo']],
            ['name' => 'Resma', 'category_group_id' => $groupIds['Papelería y Aseo']],
            ['name' => 'Saca ganchos', 'category_group_id' => $groupIds['Papelería y Aseo']],
            ['name' => 'Talonario', 'category_group_id' => $groupIds['Papelería y Aseo']],
            ['name' => 'Tinta', 'category_group_id' => $groupIds['Papelería y Aseo']],
            ['name' => 'Toalla', 'category_group_id' => $groupIds['Papelería y Aseo']],
            ['name' => 'Trapero', 'category_group_id' => $groupIds['Papelería y Aseo']],

            // Subgrupos para "EPP"
            ['name' => 'Dedale', 'category_group_id' => $groupIds['EPP']],
            ['name' => 'Guante', 'category_group_id' => $groupIds['EPP']],
            ['name' => 'Tapón', 'category_group_id' => $groupIds['EPP']],

            // Subgrupos para "Servicios"
            ['name' => 'Mantenimiento', 'category_group_id' => $groupIds['Servicios']],
            ['name' => 'Transporte', 'category_group_id' => $groupIds['Servicios']],
            ['name' => 'Consultoría', 'category_group_id' => $groupIds['Servicios']],
            ['name' => 'Capacitación', 'category_group_id' => $groupIds['Servicios']],
        ];

        DB::table('category_sub_groups')->insert($subGroups);
    }
}

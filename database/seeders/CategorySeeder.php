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
        ];

        DB::table('category_groups')->insert($groups);

        // Obtener los IDs de los grupos
        $groupIds = DB::table('category_groups')->pluck('id', 'name');

        // Insertar subgrupos
        $subGroups = [
            // Subgrupos para "Insumos"
            ['name' => 'Tela', 'category_group_id' => $groupIds['Materia Prima']],
            ['name' => 'Hilaza', 'category_group_id' => $groupIds['Materia Prima']],

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
            ['name' => 'Piedra', 'category_group_id' => $groupIds['Suministros']],
            ['name' => 'Resina', 'category_group_id' => $groupIds['Suministros']],
            ['name' => 'Talco', 'category_group_id' => $groupIds['Suministros']],
            ['name' => 'Tiza', 'category_group_id' => $groupIds['Suministros']],
            ['name' => 'Zuncho', 'category_group_id' => $groupIds['Suministros']],
            ['name' => 'Alcohol', 'category_group_id' => $groupIds['Suministros']],

            // Subgrupos para "Aditamentos"
            ['name' => 'Afilador', 'category_group_id' => $groupIds['Aditamentos']],
            ['name' => 'Aguja', 'category_group_id' => $groupIds['Aditamentos']],
            ['name' => 'Caja', 'category_group_id' => $groupIds['Aditamentos']],
            ['name' => 'Carretel', 'category_group_id' => $groupIds['Aditamentos']],
            ['name' => 'Cepillo', 'category_group_id' => $groupIds['Aditamentos']],
            ['name' => 'Cuchilla', 'category_group_id' => $groupIds['Aditamentos']],
            ['name' => 'Guía', 'category_group_id' => $groupIds['Aditamentos']],
            ['name' => 'Diente', 'category_group_id' => $groupIds['Aditamentos']],
            ['name' => 'Paño', 'category_group_id' => $groupIds['Aditamentos']],
            ['name' => 'Long Jack', 'category_group_id' => $groupIds['Aditamentos']],
            ['name' => 'Sinker', 'category_group_id' => $groupIds['Aditamentos']],
            ['name' => 'Pie', 'category_group_id' => $groupIds['Aditamentos']],
            ['name' => 'Lampara', 'category_group_id' => $groupIds['Aditamentos']],

            // Subgrupos para "Herramientas"
            ['name' => 'Tijera', 'category_group_id' => $groupIds['Herramientas']],
            ['name' => 'Chequeador', 'category_group_id' => $groupIds['Herramientas']],
            ['name' => 'Pistola', 'category_group_id' => $groupIds['Herramientas']],
            ['name' => 'Calibrador', 'category_group_id' => $groupIds['Herramientas']],
            ['name' => 'Metro', 'category_group_id' => $groupIds['Herramientas']],
            ['name' => 'Mouse', 'category_group_id' => $groupIds['Herramientas']],
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
        ];

        DB::table('category_sub_groups')->insert($subGroups);
    }
}

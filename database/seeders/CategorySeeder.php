<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class CategorySeeder extends Seeder
{
    public function run(): void
    {
        DB::statement('SET NAMES utf8mb4');
        DB::statement('SET CHARACTER SET utf8mb4');

        $now = now();

        $groups = [
            ['name' => 'Materia Prima', 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Insumos', 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Suministros', 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Aditamentos', 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Herramientas', 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Repuestos', 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Papelería y Aseo', 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'EPP', 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Servicios', 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Maquinaria', 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Producto Terminado', 'created_at' => $now, 'updated_at' => $now],

        ];

        DB::table('category_groups')->insert($groups);

        $groupIds = DB::table('category_groups')->pluck('id', 'name');

        $subGroups = [
            // Materia Prima
            ['name' => 'Tela', 'category_group_id' => $groupIds['Materia Prima'], 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Hilaza', 'category_group_id' => $groupIds['Materia Prima'], 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Hilo', 'category_group_id' => $groupIds['Materia Prima'], 'created_at' => $now, 'updated_at' => $now],

            // Insumos
            ['name' => 'Accesorios', 'category_group_id' => $groupIds['Insumos'], 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Adorno', 'category_group_id' => $groupIds['Insumos'], 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Aguja', 'category_group_id' => $groupIds['Insumos'], 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Aplique', 'category_group_id' => $groupIds['Insumos'], 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Bolsa', 'category_group_id' => $groupIds['Insumos'], 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Botón', 'category_group_id' => $groupIds['Insumos'], 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Cinta', 'category_group_id' => $groupIds['Insumos'], 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Código', 'category_group_id' => $groupIds['Insumos'], 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Cordón', 'category_group_id' => $groupIds['Insumos'], 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Correa', 'category_group_id' => $groupIds['Insumos'], 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Cremallera', 'category_group_id' => $groupIds['Insumos'], 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Elástico', 'category_group_id' => $groupIds['Insumos'], 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Etiqueta', 'category_group_id' => $groupIds['Insumos'], 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Garra', 'category_group_id' => $groupIds['Insumos'], 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'General', 'category_group_id' => $groupIds['Insumos'], 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Hebilla', 'category_group_id' => $groupIds['Insumos'], 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Herraje', 'category_group_id' => $groupIds['Insumos'], 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Hiladilla', 'category_group_id' => $groupIds['Insumos'], 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Hilaza', 'category_group_id' => $groupIds['Insumos'], 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Instrucción', 'category_group_id' => $groupIds['Insumos'], 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Marquilla', 'category_group_id' => $groupIds['Insumos'], 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Ojalete', 'category_group_id' => $groupIds['Insumos'], 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Placa', 'category_group_id' => $groupIds['Insumos'], 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Remache', 'category_group_id' => $groupIds['Insumos'], 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Varios', 'category_group_id' => $groupIds['Insumos'], 'created_at' => $now, 'updated_at' => $now],

            // Suministros
            ['name' => 'Caja', 'category_group_id' => $groupIds['Suministros'], 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Cinta', 'category_group_id' => $groupIds['Suministros'], 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Consumibles', 'category_group_id' => $groupIds['Suministros'], 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Empaque', 'category_group_id' => $groupIds['Suministros'], 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Rollo', 'category_group_id' => $groupIds['Suministros'], 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Varios', 'category_group_id' => $groupIds['Suministros'], 'created_at' => $now, 'updated_at' => $now],

            // Aditamentos
            ['name' => 'Accesorios maquina', 'category_group_id' => $groupIds['Aditamentos'], 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Cuchilla', 'category_group_id' => $groupIds['Aditamentos'], 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Paño', 'category_group_id' => $groupIds['Aditamentos'], 'created_at' => $now, 'updated_at' => $now],

            // Herramientas
            ['name' => 'Chequeador', 'category_group_id' => $groupIds['Herramientas'], 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Destornillador', 'category_group_id' => $groupIds['Herramientas'], 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Encintador', 'category_group_id' => $groupIds['Herramientas'], 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Grapadora', 'category_group_id' => $groupIds['Herramientas'], 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Herramientas varias', 'category_group_id' => $groupIds['Herramientas'], 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Medición', 'category_group_id' => $groupIds['Herramientas'], 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Pistola', 'category_group_id' => $groupIds['Herramientas'], 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Tijera', 'category_group_id' => $groupIds['Herramientas'], 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Troquel', 'category_group_id' => $groupIds['Herramientas'], 'created_at' => $now, 'updated_at' => $now],

            // Repuestos
            ['name' => 'General', 'category_group_id' => $groupIds['Repuestos'], 'created_at' => $now, 'updated_at' => $now],

            // Papelería y Aseo
            ['name' => 'Bolsa de basura', 'category_group_id' => $groupIds['Papelería y Aseo'], 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Bolígrafo', 'category_group_id' => $groupIds['Papelería y Aseo'], 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Carpeta', 'category_group_id' => $groupIds['Papelería y Aseo'], 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Cinta adhesiva', 'category_group_id' => $groupIds['Papelería y Aseo'], 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Cosedora', 'category_group_id' => $groupIds['Papelería y Aseo'], 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Desinfectante', 'category_group_id' => $groupIds['Papelería y Aseo'], 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Envase', 'category_group_id' => $groupIds['Papelería y Aseo'], 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Escritorio', 'category_group_id' => $groupIds['Papelería y Aseo'], 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Esponja', 'category_group_id' => $groupIds['Papelería y Aseo'], 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Jabón y detergente', 'category_group_id' => $groupIds['Papelería y Aseo'], 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Limpieza pisos', 'category_group_id' => $groupIds['Papelería y Aseo'], 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Marcador', 'category_group_id' => $groupIds['Papelería y Aseo'], 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Resma', 'category_group_id' => $groupIds['Papelería y Aseo'], 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Talonario', 'category_group_id' => $groupIds['Papelería y Aseo'], 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Tecnología', 'category_group_id' => $groupIds['Papelería y Aseo'], 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Tijera', 'category_group_id' => $groupIds['Papelería y Aseo'], 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Tinta', 'category_group_id' => $groupIds['Papelería y Aseo'], 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Toalla', 'category_group_id' => $groupIds['Papelería y Aseo'], 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Varios aseo', 'category_group_id' => $groupIds['Papelería y Aseo'], 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Varios papelería', 'category_group_id' => $groupIds['Papelería y Aseo'], 'created_at' => $now, 'updated_at' => $now],

            // EPP
            ['name' => 'Botiquín', 'category_group_id' => $groupIds['EPP'], 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Calzado', 'category_group_id' => $groupIds['EPP'], 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Guantes', 'category_group_id' => $groupIds['EPP'], 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Primeros auxilios', 'category_group_id' => $groupIds['EPP'], 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Protección auditiva', 'category_group_id' => $groupIds['EPP'], 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Protección visual', 'category_group_id' => $groupIds['EPP'], 'created_at' => $now, 'updated_at' => $now],

            // Servicios
            ['name' => 'Confección', 'category_group_id' => $groupIds['Servicios'], 'created_at' => $now, 'updated_at' => $now],

            // Maquinaria
            ['name' => 'Máquina', 'category_group_id' => $groupIds['Maquinaria'], 'created_at' => $now, 'updated_at' => $now],

            // Producto Terminado
            ['name' => 'Bermuda', 'category_group_id' => $groupIds['Producto Terminado'], 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Blusa', 'category_group_id' => $groupIds['Producto Terminado'], 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Buzo', 'category_group_id' => $groupIds['Producto Terminado'], 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Chaleco', 'category_group_id' => $groupIds['Producto Terminado'], 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Pantalón', 'category_group_id' => $groupIds['Producto Terminado'], 'created_at' => $now, 'updated_at' => $now],

        ];

        DB::table('category_sub_groups')->insert($subGroups);
    }
}

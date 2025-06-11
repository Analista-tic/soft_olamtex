<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class ExampleDataSeeder extends Seeder
{
    public function run(): void
    {
        // Insertar warehouse de ejemplo si no existe
        $warehouseId = DB::table('warehouses')->insertGetId([
            'name' => 'Principal',
            'observation' => 'Almacén principal',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Insertar category_sub_group de ejemplo si no existe
        $categorySubGroupId = DB::table('category_sub_groups')->insertGetId([
            'name' => 'Subgrupo Ejemplo',
            'category_group_id' => DB::table('category_groups')->insertGetId([
                'name' => 'Grupo Ejemplo',
                'created_at' => now(),
                'updated_at' => now(),
            ]),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Insertar color de ejemplo si no existe
        $colorId = DB::table('colors')->insertGetId([
            'name' => 'Rojo',
            'rgb_value' => '255,0,0',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Insertar usuario de ejemplo
        $userId = DB::table('users')->insertGetId([
            'name' => 'Ejemplo Usuario',
            'email' => 'ejemplo@correo.com',
            'password' => Hash::make('password'),
            'email_verified_at' => now(),
            'remember_token' => Str::random(10),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Insertar proveedor de ejemplo si no existe
        $providerId = DB::table('contacts')->insertGetId([
            'contact_type' => 'proveedor',
            'id_type' => 'NIT',
            'id_number' => '900000001',
            'dv' => null,
            'full_name' => 'Proveedor Ejemplo',
            'person_type' => 'jurídica',
            'groups' => null,
            'municipality' => null,
            'department' => null,
            'address' => null,
            'email' => 'proveedor@ejemplo.com',
            'phone' => null,
            'additional_contacts' => null,
            'branches' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Insertar artículo de ejemplo (ajustado a los campos reales)
        $articleId = DB::table('articles')->insertGetId([
            'category_sub_group_id' => $categorySubGroupId,
            'color_id' => $colorId,
            'name' => 'Artículo de prueba',
            'reference' => 'ART-001',
            'img' => null,
            'tax' => 19.00, // Cambiado de 'impuesto' a 'tax'
            'bar_code' => 'ART-001',
            'negative_movement' => false,
            'description' => 'Descripción de artículo de prueba',
            'extra_fields' => null,
            'status' => true,
            // 'unit_measure' => 'm', // Descomenta solo si existe la columna en la BD
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Insertar relación en warehouse_article para stock y bodega
        DB::table('warehouse_article')->insert([
            'warehouse_id' => $warehouseId,
            'article_id' => $articleId,
            'quantity' => 100,
            'stock_min' => 10,
            'stock_max' => 200,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Insertar orden de compra de ejemplo
        $purchaseOrderId = DB::table('purchase_orders')->insertGetId([
            'contact_id' => $providerId,
            'date' => now(),
            'delivery_date' => now(),
            'supplier_order_number'=> 'ORD-001',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Insertar ítem de orden de compra de ejemplo
        DB::table('purchase_order_items')->insert([
            'purchase_order_id' => $purchaseOrderId,
            'article_id' => $articleId,
            'price' => 1000.00,
            'tax' => 0,
            'quantity' => 1,
            'description' => 'Ítem de prueba',
            'total' => 1000.00,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}

<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;

class FixLogisticaOrderVisibilitySeeder extends Seeder
{
    public function run(): void
    {
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // Parte 1: quitar 'ver todos ordenes compra' a logística si lo tiene
        $logistica = Role::findByName('logistica', 'web');
        if ($logistica->hasPermissionTo('ver todos ordenes compra')) {
            $logistica->revokePermissionTo('ver todos ordenes compra');
            $this->command->info('Permiso "ver todos ordenes compra" removido de logística.');
        } else {
            $this->command->info('Logística no tenía "ver todos ordenes compra". Sin cambios en permisos.');
        }

        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // Parte 2: corregir buyer_user_id de órdenes que tienen solicitud vinculada
        // Si la solicitud tiene buyer_user_id y la orden tiene uno diferente, se corrige
        $updated = DB::statement("
            UPDATE purchase_documents pd
            JOIN purchase_requests pr ON pd.purchase_request_id = pr.id
            SET pd.buyer_user_id = pr.buyer_user_id
            WHERE pd.document_type = 'purchase_order'
              AND pd.deleted_at IS NULL
              AND pr.buyer_user_id IS NOT NULL
              AND (pd.buyer_user_id IS NULL OR pd.buyer_user_id != pr.buyer_user_id)
        ");

        $count = DB::select("
            SELECT COUNT(*) as total
            FROM purchase_documents pd
            JOIN purchase_requests pr ON pd.purchase_request_id = pr.id
            WHERE pd.document_type = 'purchase_order'
              AND pd.deleted_at IS NULL
              AND pr.buyer_user_id IS NOT NULL
        ");

        $this->command->info('Órdenes corregidas con buyer_user_id de su solicitud: revisadas ' . ($count[0]->total ?? 0));

        // También propagar buyer_user_id a las facturas hijas de esas órdenes
        DB::statement("
            UPDATE purchase_documents facturas
            JOIN purchase_documents ordenes ON facturas.parent_document_id = ordenes.id
            SET facturas.buyer_user_id = ordenes.buyer_user_id
            WHERE facturas.document_type = 'purchase_invoice'
              AND facturas.deleted_at IS NULL
              AND ordenes.buyer_user_id IS NOT NULL
              AND (facturas.buyer_user_id IS NULL OR facturas.buyer_user_id != ordenes.buyer_user_id)
        ");

        $this->command->info('Facturas actualizadas con el buyer_user_id de su orden.');
    }
}

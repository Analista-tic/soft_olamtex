<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Permitir facturas sin orden de compra asociada
        DB::statement('ALTER TABLE purchase_invoices DROP FOREIGN KEY purchase_invoices_purchase_order_id_foreign');
        DB::statement('ALTER TABLE purchase_invoices MODIFY purchase_order_id BIGINT UNSIGNED NULL');
        DB::statement('ALTER TABLE purchase_invoices ADD CONSTRAINT purchase_invoices_purchase_order_id_foreign FOREIGN KEY (purchase_order_id) REFERENCES purchase_orders(id)');
    }

    public function down(): void
    {
        // Revertir a NOT NULL si se desea volver al comportamiento previo
        DB::statement('ALTER TABLE purchase_invoices DROP FOREIGN KEY purchase_invoices_purchase_order_id_foreign');
        DB::statement('ALTER TABLE purchase_invoices MODIFY purchase_order_id BIGINT UNSIGNED NOT NULL');
        DB::statement('ALTER TABLE purchase_invoices ADD CONSTRAINT purchase_invoices_purchase_order_id_foreign FOREIGN KEY (purchase_order_id) REFERENCES purchase_orders(id)');
    }
};

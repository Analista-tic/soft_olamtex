<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class FixOrderStatusWithPendingDifferenceSeeder extends Seeder
{
    public function run(): void
    {
        $fixed = 0;

        // Obtener todas las órdenes marcadas como 'invoiced'
        $orders = DB::table('purchase_documents')
            ->where('document_type', 'purchase_order')
            ->where('status', 'invoiced')
            ->whereNull('deleted_at')
            ->get();

        foreach ($orders as $order) {
            $orderItems = DB::table('purchase_document_items')
                ->where('purchase_document_id', $order->id)
                ->get();

            if ($orderItems->isEmpty()) {
                continue;
            }

            // Sumar cantidades facturadas reales (sin doble conteo)
            $invoiceIds = DB::table('purchase_documents')
                ->where('parent_document_id', $order->id)
                ->where('document_type', 'purchase_invoice')
                ->whereNull('deleted_at')
                ->pluck('id');

            $invoicedQty = DB::table('purchase_document_items')
                ->whereIn('purchase_document_id', $invoiceIds)
                ->get()
                ->reduce(function (array $carry, $item) {
                    $key = $item->item_id
                        ? 'id:' . $item->item_id
                        : 'name:' . strtolower(trim((string) ($item->item_name ?? '')));
                    $carry[$key] = ($carry[$key] ?? 0) + (float) $item->quantity;
                    return $carry;
                }, []);

            $allCovered = true;
            foreach ($orderItems as $orderItem) {
                $key = $orderItem->item_id
                    ? 'id:' . $orderItem->item_id
                    : 'name:' . strtolower(trim((string) ($orderItem->item_name ?? '')));
                if ((float) $orderItem->quantity > 0 && ($invoicedQty[$key] ?? 0) < (float) $orderItem->quantity) {
                    $allCovered = false;
                    break;
                }
            }

            if (! $allCovered) {
                DB::table('purchase_documents')
                    ->where('id', $order->id)
                    ->update(['status' => 'partially_invoiced']);
                $fixed++;
                $this->command->info("Orden #{$order->document_number} (id={$order->id}) corregida a 'parcialmente facturada'.");
            }
        }

        $this->command->info("Total órdenes corregidas: {$fixed}");
    }
}

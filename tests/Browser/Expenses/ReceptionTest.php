<?php

namespace Tests\Browser\Expenses;

use App\Models\Expenses\PurchaseDocument;
use App\Models\Inventory\Warehouse;
use App\Models\MasterData\CategorySubGroup;
use App\Models\MasterData\Contact;
use App\Models\MasterData\Item;
use Illuminate\Support\Facades\Artisan;
use Laravel\Dusk\Browser;
use Tests\Browser\Support\AuthHelper;
use Tests\DuskTestCase;

/**
 * C-07 — Recepciones de tela
 *
 * C-07a: un usuario autorizado puede registrar un rollo en una recepción
 */
class ReceptionTest extends DuskTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Artisan::call('db:seed', ['--class' => 'RolesAndPermissionsSeeder']);
        if (\App\Models\MasterData\CategorySubGroup::count() === 0) {
            Artisan::call('db:seed', ['--class' => 'CategorySeeder']);
        }
        if (Warehouse::count() === 0) {
            Artisan::call('db:seed', ['--class' => 'WarehousesSeeder']);
        }
    }

    /**
     * @test
     * Objetivo:   Verificar que un usuario puede registrar un rollo
     *             en una factura de tela pendiente de recepción,
     *             y que la recepción queda registrada correctamente.
     *
     * Prioridad:  Crítico
     */
    public function un_usuario_autorizado_puede_registrar_un_rollo_en_una_recepcion(): void
    {
        // Arrange — proveedor, ítem de tela y factura en BD
        Contact::query()->forceDelete();
        $proveedor = Contact::create([
            'contact_type' => 'proveedor',
            'id_type'      => 'nit',
            'id_number'    => '5001234567',
            'full_name'    => 'Proveedor Recepcion Test',
            'person_type'  => 'persona_juridica',
            'email'        => 'proveedor.recepcion@test.com',
        ]);

        Item::query()->forceDelete();
        $subGroupTela = CategorySubGroup::where('name', 'Tela')->first();
        $item = Item::create([
            'category_sub_group_id' => $subGroupTela->id,
            'name'                  => 'Tela Recepcion Test',
            'description'           => 'Tela para prueba de recepción',
            'item_type'             => 'product',
            'tracks_inventory'      => true,
            'status'                => true,
        ]);

        // Crear factura de tela directamente en BD en estado pending_receipt
        PurchaseDocument::where('document_type', 'purchase_invoice')
            ->where('provider_name', 'Proveedor Recepcion Test')
            ->forceDelete();

        $factura = PurchaseDocument::create([
            'document_type'           => 'purchase_invoice',
            'document_number'         => 'FAC-REC-001',
            'contact_id'              => $proveedor->id,
            'provider_name'           => $proveedor->full_name,
            'date'                    => now()->toDateString(),
            'delivery_date'           => now()->addDays(3)->toDateString(),
            'status'                  => 'pending_receipt',
            'supplier_invoice_number' => 'FAC-EXT-001',
            'items'                   => [
                [
                    'item_id'        => $item->id,
                    'name'           => $item->name,
                    'price'          => 35000,
                    'quantity'       => 5,
                    'total'          => 175000,
                    'supplier_code'  => 'COD-001',
                    'supplier_color' => 'Azul',
                    'order_width'    => 1.5,
                ],
            ],
        ]);

        // Obtener la bodega para usarla en el select
        $bodega = Warehouse::first();

        $this->browse(function (Browser $browser) use ($factura, $bodega) {
            AuthHelper::loginWithRole($browser, 'admin');

            $browser->visit('/kardex-telas/recepcion')
                    ->waitFor('[dusk="btn-ver-factura-' . $factura->id . '"]', 15)
                    ->screenshot('debug-c07-00-lista')

                    // Abre el detalle de la factura
                    ->click('[dusk="btn-ver-factura-' . $factura->id . '"]')
                    ->pause(1500)
                    ->screenshot('debug-c07-01-detalle');

            // Busca y hace click en el botón de agregar rollo del primer ítem
            $browser->script("
                const btns = Array.from(document.querySelectorAll('button'));
                const rollo = btns.find(b =>
                    b.title === 'Agregar rollo' ||
                    b.getAttribute('title')?.includes('rollo') ||
                    b.querySelector('svg[data-lucide=\"package\"]')
                );
                if (rollo) rollo.click();
            ");
            $browser->pause(1000);
            $browser->screenshot('debug-c07-02-dialog-rollo');

            // Llena el formulario del rollo
            $browser->waitFor('[dusk="rollo-input-longitud"]');
            $browser->type('[dusk="rollo-input-longitud"]', '45');
            $browser->pause(200);
            $browser->type('[dusk="rollo-input-ancho"]', '1.48');
            $browser->pause(200);

            // Fecha de entrada
            $browser->value('[dusk="rollo-input-fecha"]', now()->toDateString());
            $browser->pause(100);
            $browser->script("
                const el = document.querySelector('[dusk=\"rollo-input-fecha\"]');
                if (el) {
                    el.dispatchEvent(new Event('change', { bubbles: true }));
                    el.dispatchEvent(new Event('input', { bubbles: true }));
                }
            ");
            $browser->pause(300);

            // Ubicación — selecciona la primera bodega disponible
            $browser->click('[dusk="rollo-select-ubicacion"]');
            $browser->pause(300);
            $browser->keys('[dusk="rollo-select-ubicacion"]', \Facebook\WebDriver\WebDriverKeys::ARROW_DOWN);
            $browser->pause(200);
            $browser->keys('[dusk="rollo-select-ubicacion"]', \Facebook\WebDriver\WebDriverKeys::ENTER);
            $browser->pause(500);

            $browser->screenshot('debug-c07-03-rollo-lleno');

            // Guarda el rollo
            $browser->click('[dusk="btn-guardar-rollo"]');
            $browser->waitForText('guardado', 10);
            $browser->screenshot('debug-c07-04-rollo-guardado');

            $browser->assertSee('Tela Recepcion Test');
        });
    }
}

<?php

namespace Tests\Browser\Expenses;

use App\Models\MasterData\Contact;
use App\Models\MasterData\CategorySubGroup;
use App\Models\MasterData\Item;
use Illuminate\Support\Facades\Artisan;
use Laravel\Dusk\Browser;
use Tests\Browser\Support\AuthHelper;
use Tests\DuskTestCase;

/**
 * C-06 — Facturas de compra de tela
 *
 * C-06a: un usuario autorizado puede crear una factura de compra de tela
 */
class PurchaseInvoiceTelaTest extends DuskTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Artisan::call('db:seed', ['--class' => 'RolesAndPermissionsSeeder']);
        if (\App\Models\MasterData\CategorySubGroup::count() === 0) {
            Artisan::call('db:seed', ['--class' => 'CategorySeeder']);
        }
    }

    /**
     * @test
     * Objetivo:   Verificar que un usuario con permiso puede crear
     *             una factura de compra de tela con los campos
     *             obligatorios de tela (código, color, ancho pedido).
     *
     * Prioridad:  Crítico
     */
    public function un_usuario_autorizado_puede_crear_una_factura_de_compra_de_tela(): void
    {
        // Arrange — proveedor e ítem de tipo tela en BD de testing
        Contact::query()->forceDelete();
        $proveedor = Contact::create([
            'contact_type' => 'proveedor',
            'id_type'      => 'nit',
            'id_number'    => '6001234567',
            'full_name'    => 'Proveedor Tela Test SA',
            'person_type'  => 'persona_juridica',
            'email'        => 'proveedor.tela.test@test.com',
        ]);

        Item::query()->forceDelete();
        // Para telaMode=true necesitamos un ítem cuyo category_sub_group.name === 'Tela'
        $subGroupTela = CategorySubGroup::where('name', 'Tela')->first();
        $item = Item::create([
            'category_sub_group_id' => $subGroupTela->id,
            'name'                  => 'Tela Test SA',
            'description'           => 'Tela creada para prueba automatizada',
            'item_type'             => 'product',
            'tracks_inventory'      => true,
            'status'                => true,
        ]);

        $proveedorNombre = 'Proveedor Tela Test SA';
        $itemNombre      = 'Tela Test SA';
        $fechaFactura    = now()->format('Y-m-d');
        $fechaEntrega    = now()->addDays(7)->format('Y-m-d');

        $this->browse(function (Browser $browser) use ($proveedorNombre, $itemNombre, $fechaFactura, $fechaEntrega) {
            AuthHelper::loginWithRole($browser, 'admin');

            $browser->visit('/gastos/facturas-compra-tela')
                    ->waitFor('[dusk="btn-nueva-factura-tela"]', 15)
                    ->click('[dusk="btn-nueva-factura-tela"]')
                    ->waitUntilMissingText('Cargando', 15)
                    ->waitFor('[dusk="tela-select-proveedor"]')
                    ->pause(500);

            // Selecciona proveedor
            $browser->click('[dusk="tela-select-proveedor"]');
            $browser->pause(300);
            $browser->type('[dusk="tela-select-proveedor"]', $proveedorNombre);
            $browser->pause(800);
            $browser->keys('[dusk="tela-select-proveedor"]', \Facebook\WebDriver\WebDriverKeys::ARROW_DOWN);
            $browser->pause(300);
            $browser->keys('[dusk="tela-select-proveedor"]', \Facebook\WebDriver\WebDriverKeys::ENTER);
            $browser->pause(800);

            // Fecha de factura
            $browser->value('[dusk="tela-input-fecha-factura"]', $fechaFactura);
            $browser->pause(100);
            $browser->script("
                const el = document.querySelector('[dusk=\"tela-input-fecha-factura\"]');
                if (el) {
                    el.dispatchEvent(new Event('change', { bubbles: true }));
                    el.dispatchEvent(new Event('input', { bubbles: true }));
                }
            ");
            $browser->pause(300);

            // Fecha de entrega
            $browser->value('[dusk="tela-input-fecha-entrega"]', $fechaEntrega);
            $browser->pause(100);
            $browser->script("
                const el = document.querySelector('[dusk=\"tela-input-fecha-entrega\"]');
                if (el) {
                    el.dispatchEvent(new Event('change', { bubbles: true }));
                    el.dispatchEvent(new Event('input', { bubbles: true }));
                }
            ");
            $browser->pause(300);

            // N° factura proveedor
            $browser->waitFor('[dusk="tela-input-numero-proveedor"]');
            $browser->type('[dusk="tela-input-numero-proveedor"]', 'FAC-TELA-001');
            $browser->pause(200);

            // Ítem de tela
            $browser->waitFor('[dusk="factura-item-0-item"]');
            $browser->click('[dusk="factura-item-0-item"]');
            $browser->pause(2000);
            $browser->type('[dusk="factura-item-0-item"]', $itemNombre);
            $browser->pause(1000);
            $browser->keys('[dusk="factura-item-0-item"]', \Facebook\WebDriver\WebDriverKeys::ARROW_DOWN);
            $browser->pause(200);
            $browser->keys('[dusk="factura-item-0-item"]', \Facebook\WebDriver\WebDriverKeys::ENTER);
            $browser->pause(600);

            // Precio y cantidad
            $browser->waitFor('[dusk="factura-item-0-precio"]');
            $browser->click('[dusk="factura-item-0-precio"]');
            $browser->keys('[dusk="factura-item-0-precio"]', '35000');
            $browser->pause(200);
            $browser->type('[dusk="factura-item-0-cantidad"]', '10');
            $browser->type('[dusk="factura-item-0-descripcion"]', 'Tela para confección');

            // Campos obligatorios de tela
            $browser->type('[dusk="factura-item-0-codigo-proveedor"]', 'COD-TELA-001');
            $browser->type('[dusk="factura-item-0-color-proveedor"]', 'Azul marino');
            $browser->type('[dusk="factura-item-0-ancho"]', '1.5');

            $browser->screenshot('debug-c06-form-completo');

            // Guarda
            $browser->click('[dusk="btn-guardar-factura-tela"]');
            $browser->waitForText('creada correctamente', 10);
            $browser->screenshot('debug-c06-guardada');

            $browser->waitUntilMissingText('Cargando');
            $browser->assertSee($proveedorNombre);
        });
    }
}

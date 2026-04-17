<?php

namespace Tests\Browser\Expenses;

use App\Models\MasterData\CategorySubGroup;
use App\Models\MasterData\Contact;
use App\Models\MasterData\Item;
use Illuminate\Support\Facades\Artisan;
use Laravel\Dusk\Browser;
use Tests\Browser\Support\AuthHelper;
use Tests\DuskTestCase;

/**
 * C-04 - Ordenes de compra
 *
 * C-04a: un usuario autorizado puede crear una orden de compra
 */
class PurchaseOrderTest extends DuskTestCase
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
     *             una orden de compra con proveedor e items,
     *             y que queda visible en el listado.
     *
     * Prioridad:  Critico
     * Precondicion: Rol admin + Contact de tipo proveedor en BD
     */
    public function un_usuario_autorizado_puede_crear_una_orden_de_compra(): void
    {
        // Arrange - proveedor en base de datos de testing
        Contact::where('full_name', 'Proveedor Orden Test')->forceDelete();
        $proveedor = Contact::create([
            'contact_type' => 'proveedor',
            'id_type'      => 'cc',
            'id_number'    => '9001234567',
            'full_name'    => 'Proveedor Orden Test',
            'person_type'  => 'persona_juridica',
            'email'        => 'proveedor.orden.test@test.com',
        ]);

        $subGroup = CategorySubGroup::first();
        Item::where('name', 'Ítem Orden Test')->forceDelete();
        $item = Item::create([
            'category_sub_group_id' => $subGroup->id,
            'name'                  => 'Ítem Orden Test',
            'description'           => 'Ítem creado para prueba automatizada',
            'item_type'             => 'service',
            'tracks_inventory'      => false,
            'status'                => true,
        ]);


        $this->browse(function (Browser $browser) use ($proveedor) {
            AuthHelper::loginWithRole($browser, 'admin');

            $proveedorNombre = 'Proveedor Orden Test';
            $itemNombre = 'Ítem Orden Test';
            $deliveryDate = now()->addDays(7)->format('Y-m-d');

            $browser->visit('/gastos/ordenes-compra')
                    ->waitFor('[dusk="btn-nueva-orden"]', 15)
                    ->click('[dusk="btn-nueva-orden"]')
                    ->waitFor('[dusk="select-proveedor"]')
                    ->pause(500);

            $browser->click('[dusk="select-proveedor"]');
            $browser->pause(300);
            $browser->type('[dusk="select-proveedor"]', $proveedorNombre);
            $browser->pause(800);
            $browser->keys('[dusk="select-proveedor"]', \Facebook\WebDriver\WebDriverKeys::ARROW_DOWN);
            $browser->pause(300);
            $browser->keys('[dusk="select-proveedor"]', \Facebook\WebDriver\WebDriverKeys::ENTER);
            $browser->pause(800);
            $browser->screenshot('debug-c04-post-proveedor');

            $browser->value('[dusk="input-fecha-entrega"]', $deliveryDate);
            $browser->pause(100);

            $browser->script("
                const el = document.querySelector('[dusk=\"input-fecha-entrega\"]');
                if (el) {
                    el.dispatchEvent(new Event('change', { bubbles: true }));
                    el.dispatchEvent(new Event('input', { bubbles: true }));
                }
            ");

            $browser->pause(300);

            $browser->waitFor('[dusk="input-supplier-order"]');
            $browser->type('[dusk="input-supplier-order"]', 'PO-TEST-001');
            $browser->pause(200);

            $browser->waitFor('[dusk="orden-item-0-item"]');
            $browser->click('[dusk="orden-item-0-item"]');
            $browser->pause(300);
            $browser->type('[dusk="orden-item-0-item"]', $itemNombre);
            $browser->pause(800);
            $browser->keys('[dusk="orden-item-0-item"]', \Facebook\WebDriver\WebDriverKeys::ARROW_DOWN);
            $browser->pause(200);
            $browser->keys('[dusk="orden-item-0-item"]', \Facebook\WebDriver\WebDriverKeys::ENTER);
            $browser->pause(600);

            $browser->click('[dusk="orden-item-0-precio"]');
            $browser->keys('[dusk="orden-item-0-precio"]', '25000');
            $browser->pause(200);
            $browser->type('[dusk="orden-item-0-cantidad"]', '3');
            $browser->type('[dusk="orden-item-0-descripcion"]', 'Prueba automatizada');
            $browser->type('[dusk="orden-item-0-ancho"]', '150');

            $browser->screenshot('debug-c04-form-completo');

            $browser->click('[dusk="btn-guardar-orden"]');
            $browser->waitForText('creada correctamente', 10);
            $browser->screenshot('debug-c04-guardada');

            $browser->waitUntilMissingText('Cargando órdenes...');
            $browser->assertSee($proveedorNombre);
        });
    }
}

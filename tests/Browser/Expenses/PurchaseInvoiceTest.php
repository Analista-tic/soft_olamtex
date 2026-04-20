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
 * C-05 — Facturas de compra
 *
 * C-05a: un usuario autorizado puede crear una factura de compra
 */
class PurchaseInvoiceTest extends DuskTestCase
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
     *             una factura de compra y que queda registrada correctamente.
     *
     * Prioridad:  Crítico
     */
    public function un_usuario_autorizado_puede_crear_una_factura_de_compra(): void
    {
        // Arrange — proveedor e ítem en BD de testing
        Contact::query()->forceDelete();
        Contact::where('email', 'proveedor.factura.test@test.com')->forceDelete();
        $proveedor = Contact::create([
            'contact_type' => 'proveedor',
            'id_type'      => 'nit',
            'id_number'    => '7001234567',
            'full_name'    => 'Factura Test SA',
            'person_type'  => 'persona_juridica',
            'email'        => 'proveedor.factura.test@test.com',
        ]);

        Item::query()->forceDelete();
        Item::where('name', 'Ítem Factura Test')->forceDelete();
        $subGroup = CategorySubGroup::first();
        $item = Item::create([
	    'category_sub_group_id' => $subGroup->id,
        'name'                  => 'Factura Item SA',
        'description'           => 'Ítem creado para prueba de factura',
        'item_type'             => 'service',
        'tracks_inventory'      => false,
        'status'                => true,
    ]);

        $itemEnBD = \App\Models\MasterData\Item::where('name', 'Factura Item SA')->first();
        dump([
            'encontrado' => $itemEnBD ? 'SI' : 'NO',
            'id'         => $itemEnBD?->id,
            'status'     => $itemEnBD?->status,
            'deleted_at' => $itemEnBD?->deleted_at,
            'item_type'  => $itemEnBD?->item_type,
        ]);

        $response = $browser ?? null; // placeholder
        // Verificar el endpoint directamente
        $apiResponse = \Illuminate\Support\Facades\Http::get('http://localhost:8000/item/data', [
            'per_page' => 2000,
            'status' => 'true',
        ]);
        dump([
            'api_status' => $apiResponse->status(),
            'api_total'  => $apiResponse->json('total') ?? 'no total',
            'api_count'  => count($apiResponse->json('data') ?? []),
            'api_first'  => $apiResponse->json('data.0.name') ?? 'ninguno',
        ]);

        $proveedorNombre = 'Factura Test SA';
        $itemNombre = 'Factura Item SA';
        $fechaFactura = now()->format('Y-m-d');
        $fechaEntrega = now()->addDays(7)->format('Y-m-d');

        $this->browse(function (Browser $browser) use ($proveedorNombre, $itemNombre, $fechaFactura, $fechaEntrega) {
            AuthHelper::loginWithRole($browser, 'admin');

            $browser->visit('/gastos/facturas-compra')
                ->waitFor('[dusk="btn-nueva-factura"]', 15)
                ->click('[dusk="btn-nueva-factura"]')
                ->waitUntilMissingText('Cargando factura de compra', 15)
                ->pause(500);

            $browser->click('[dusk="factura-select-proveedor"]');
            $browser->pause(300);
            $browser->type('[dusk="factura-select-proveedor"]', $proveedorNombre);
            $browser->pause(800);
            $browser->keys('[dusk="factura-select-proveedor"]', \Facebook\WebDriver\WebDriverKeys::ARROW_DOWN);
            $browser->pause(300);
            $browser->keys('[dusk="factura-select-proveedor"]', \Facebook\WebDriver\WebDriverKeys::ENTER);
            $browser->pause(800);

            $browser->value('[dusk="factura-input-fecha-factura"]', $fechaFactura);
            $browser->pause(100);
            $browser->script("
                const el = document.querySelector('[dusk=\"factura-input-fecha-factura\"]');
                if (el) {
                    el.dispatchEvent(new Event('change', { bubbles: true }));
                    el.dispatchEvent(new Event('input', { bubbles: true }));
                }
            ");
            $browser->pause(300);

            $browser->value('[dusk="factura-input-fecha-entrega"]', $fechaEntrega);
            $browser->pause(100);
            $browser->script("
                const el = document.querySelector('[dusk=\"factura-input-fecha-entrega\"]');
                if (el) {
                    el.dispatchEvent(new Event('change', { bubbles: true }));
                    el.dispatchEvent(new Event('input', { bubbles: true }));
                }
            ");
            $browser->pause(300);

            $browser->waitFor('[dusk="factura-input-numero-proveedor"]');
            $browser->type('[dusk="factura-input-numero-proveedor"]', 'FAC-PROV-001');
            $browser->pause(200);

            $browser->waitFor('[dusk="factura-item-0-item"]');
            $browser->click('[dusk="factura-item-0-item"]');
            $browser->pause(300);
            $browser->type('[dusk="factura-item-0-item"]', $itemNombre);
            $browser->pause(800);
            $browser->screenshot('debug-c05-item-dropdown');

            $html2 = $browser->script('
                const opts = Array.from(document.querySelectorAll("[role=option]"));
                return {
                    count: opts.length,
                    texts: opts.map(o => o.textContent.trim()).slice(0, 5)
                };
            ');
            dump($html2);
            $browser->screenshot('debug-c05-item-dropdown');
            $browser->keys('[dusk="factura-item-0-item"]', \Facebook\WebDriver\WebDriverKeys::ARROW_DOWN);
            $browser->pause(200);
            $browser->keys('[dusk="factura-item-0-item"]', \Facebook\WebDriver\WebDriverKeys::ENTER);
            $browser->pause(600);

            $browser->waitFor('[dusk="factura-item-0-precio"]');
            $browser->click('[dusk="factura-item-0-precio"]');
            $browser->keys('[dusk="factura-item-0-precio"]', '50000');
            $browser->pause(200);
            $browser->type('[dusk="factura-item-0-cantidad"]', '2');
            $browser->type('[dusk="factura-item-0-descripcion"]', 'Prueba factura automatizada');

            $browser->screenshot('debug-c05-form-completo');

            $browser->click('[dusk="btn-guardar-factura"]');
            $browser->waitForText('creada correctamente', 10);
            $browser->screenshot('debug-c05-guardada');

            $browser->waitUntilMissingText('Cargando');
            $browser->assertSee($proveedorNombre);
        });
    }
}

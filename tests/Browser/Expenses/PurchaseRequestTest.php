<?php

namespace Tests\Browser\Expenses;

use App\Models\Settings\User;
use Illuminate\Support\Facades\Artisan;
use Laravel\Dusk\Browser;
use Tests\Browser\Support\AuthHelper;
use Tests\DuskTestCase;

/**
 * C-03 — Solicitudes de compra
 *
 * C-03a: un usuario autorizado puede crear una solicitud de compra en borrador
 * C-03b: una solicitud en borrador puede guardarse y enviarse a aprobación
 */
class PurchaseRequestTest extends DuskTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Artisan::call('db:seed', ['--class' => 'RolesAndPermissionsSeeder']);
    }

    // ——— C-03a ————————————————————————————————————————————————————————————————

    /**
     * @test
     * Objetivo:   Verificar que un usuario con permiso puede crear
     *             una solicitud de compra y que queda en estado Borrador.
     *
     * Prioridad:  Crítico
     * Precondición: Usuario con rol que tenga permiso 'ver telas'
     */
    public function un_usuario_autorizado_puede_crear_una_solicitud_de_compra_en_borrador(): void
    {
        $this->browse(function (Browser $browser) {
            // Arrange — usuario con permiso
            AuthHelper::loginWithRole($browser, 'admin');

            // Act — navega al módulo
            $browser->visit('/gastos/solicitudes-compra')
                    // Abre el dialog de crear
                    ->waitFor('[dusk="btn-nueva-solicitud"]')
                    ->click('[dusk="btn-nueva-solicitud"]')
                    ->waitFor('[dusk="input-proveedor"]')
                    ->screenshot('debug-01-dialog-abierto')

                    // Llena los campos del encabezado
                    ->type('[dusk="input-proveedor"]', 'Proveedor de Prueba S.A.')
                    ->type('[dusk="input-descripcion"]', 'Solicitud creada desde prueba automatizada')

                    // Agrega un ítem
                    ->screenshot('debug-02-antes-de-item')
                    ->click('[dusk="btn-agregar-item"]')
                    ->waitFor('[dusk="item-0-nombre"]')
                    ->screenshot('debug-03-item-agregado')
                    ->type('[dusk="item-0-nombre"]', 'Tela de algodón')
                    ->type('[dusk="item-0-cantidad"]', '5')
                    ->click('[dusk="item-0-precio"]')
                    ->keys('[dusk="item-0-precio"]', '12000')
                    ->type('[dusk="item-0-descripcion"]', 'Tela para confección')

                    // Guarda como borrador
                    ->click('[dusk="btn-guardar-borrador"]')
                    ->waitForText('Borrador');

            // Assert — aparece en el listado con estado Borrador
            $browser->assertSee('Proveedor de Prueba S.A.')
                    ->assertSee('Borrador');
        });
    }

    // ——— C-03b ————————————————————————————————————————————————————————————————

    /**
     * @test
     * Objetivo:   Verificar que una solicitud puede guardarse y enviarse
     *             a aprobación en un solo paso, y que el estado cambia
     *             de Borrador a Pendiente.
     *
     * Prioridad:  Crítico
     * Precondición: Usuario con rol que tenga permiso 'ver telas'
     */
    public function una_solicitud_puede_guardarse_y_enviarse_a_aprobacion(): void
    {
        $this->browse(function (Browser $browser) {
            // Arrange
            AuthHelper::loginWithRole($browser, 'admin');

            // Act
            $browser->visit('/gastos/solicitudes-compra')
                    ->waitFor('[dusk="btn-nueva-solicitud"]')
                    ->click('[dusk="btn-nueva-solicitud"]')
                    ->waitFor('[dusk="input-proveedor"]')
                    ->screenshot('debug-c03b-01-dialog-abierto')

                    ->type('[dusk="input-proveedor"]', 'Proveedor Aprobación Test')
                    ->screenshot('debug-c03b-02-proveedor-escrito')
                    ->type('[dusk="input-descripcion"]', 'Solicitud para enviar a aprobación')
                    ->screenshot('debug-c03b-03-descripcion-escrita')

                    ->click('[dusk="btn-agregar-item"]')
                    ->waitFor('[dusk="item-0-nombre"]')
                    ->screenshot('debug-c03b-04-item-agregado')
                    ->type('[dusk="item-0-nombre"]', 'Hilo resistente')
                    ->type('[dusk="item-0-cantidad"]', '10')
                    ->click('[dusk="item-0-precio"]')
                    ->keys('[dusk="item-0-precio"]', '5000')
                    ->type('[dusk="item-0-descripcion"]', 'Hilo para costura industrial')

                    // Guarda Y envía a aprobación
                    ->click('[dusk="btn-guardar-y-enviar"]')
                    ->waitForText('Pendiente');

            // Assert — el estado debe ser Pendiente, no Borrador
            $browser->assertSee('Proveedor Aprobación Test')
                    ->assertSee('Pendiente');
        });
    }
}

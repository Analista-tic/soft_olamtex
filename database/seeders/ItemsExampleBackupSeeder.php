<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Backup de los datos de ejemplo de items existentes.
 * Contiene la estructura original de ItemsSeeder y los ítems de tela de ExampleDataSeeder.
 * Ejecutar manualmente si se necesita restaurar los datos originales:
 *   php artisan db:seed --class=ItemsExampleBackupSeeder
 *
 * NOTA: Este seeder NO inserta por defecto. Solo contiene los datos de respaldo.
 * Para restaurar, descomentar el método run() y ajustar la lógica de inserción.
 */
class ItemsExampleBackupSeeder extends Seeder
{
    /**
     * Datos de respaldo - Items de ItemsSeeder (Tela algodón, lino, hilo, botón, etc.)
     */
    protected array $itemsSeederBackup = [
        [
            'name' => 'Tela algodón azul',
            'reference' => 'TEL-ALG-AZUL',
            'bar_code' => 'TEL-ALG-AZUL',
            'description' => 'Tela de algodón color azul, ancho 1.5m',
            'unit_measure' => 'm',
            'subgroup_name' => 'Tela',
            'extra_fields' => ['base_tela' => 'ALGODÓN', 'color' => 'AZUL', 'costo_operaciones' => 406, 'costo_hilo' => 140],
        ],
        [
            'name' => 'Tela lino blanco',
            'reference' => 'TEL-LINO-BLN',
            'bar_code' => 'TEL-LINO-BLN',
            'description' => 'Tela de lino color blanco, ancho 1.6m',
            'unit_measure' => 'm',
            'subgroup_name' => 'Tela',
            'extra_fields' => ['base_tela' => 'LINO', 'color' => 'BLANCO', 'costo_operaciones' => 406, 'costo_hilo' => 140],
        ],
        [
            'name' => 'Hilo poliéster negro',
            'reference' => 'HIL-POLI-NGR',
            'bar_code' => 'HIL-POLI-NGR',
            'description' => 'Hilo poliéster 2000m color negro',
            'unit_measure' => 'und',
            'subgroup_name' => 'Hilaza',
            'extra_fields' => ['base_hilaza' => 'POLIÉSTER', 'color' => 'NEGRO', 'costo_operaciones' => 406, 'costo_hilo' => 140],
        ],
        [
            'name' => 'Botón plástico rojo',
            'reference' => 'BOT-PLAS-ROJ',
            'bar_code' => 'BOT-PLAS-ROJ',
            'description' => 'Botón plástico rojo 24mm',
            'unit_measure' => 'und',
            'subgroup_name' => 'Botón',
            'extra_fields' => ['color' => 'ROJO'],
        ],
        [
            'name' => 'Etiqueta tejida blanca',
            'reference' => 'ETQ-TEJ-BLN',
            'bar_code' => 'ETQ-TEJ-BLN',
            'description' => 'Etiqueta tejida blanca con logo',
            'unit_measure' => 'und',
            'subgroup_name' => 'Etiqueta',
            'extra_fields' => null,
        ],
        [
            'name' => 'Cierre metálico plata',
            'reference' => 'CIE-MET-PLT',
            'bar_code' => 'CIE-MET-PLT',
            'description' => 'Cierre metálico color plata 15cm',
            'unit_measure' => 'und',
            'subgroup_name' => 'Cierre',
            'extra_fields' => null,
        ],
    ];

    /**
     * Datos de respaldo - Items de tela de ExampleDataSeeder (LANA Beige, ALGODÓN 100%)
     */
    protected array $exampleDataTelaBackup = [
        [
            'name' => 'Tela LANA Beige',
            'reference' => 'LANA-BEIGE',
            'bar_code' => 'TELA-LANA-001',
            'description' => 'Tela LANA Beige Genérica Químicos LÍNEA 3',
            'subgroup_name' => 'Tela',
        ],
        [
            'name' => 'Tela ALGODÓN 100%',
            'reference' => 'ALGODON-100',
            'bar_code' => 'TELA-ALGODON-001',
            'description' => 'Tela ALGODÓN 100% Premium LÍNEA 1',
            'subgroup_name' => 'Tela',
        ],
    ];

    /**
     * Artículo de prueba de ExampleDataSeeder
     */
    protected array $articuloPruebaBackup = [
        'name' => 'Artículo de prueba',
        'reference' => 'ART-001',
        'bar_code' => 'ART-001',
        'description' => 'Descripción de artículo de prueba',
        'subgroup_name' => 'Subgrupo Ejemplo',
    ];

    public function run(): void
    {
        $this->command->info('ItemsExampleBackupSeeder: Datos de respaldo cargados.');
        $this->command->info('Este seeder mantiene los arrays de backup. No inserta datos.');
        $this->command->info('Referencia: $itemsSeederBackup, $exampleDataTelaBackup, $articuloPruebaBackup');
    }

    public function getItemsSeederBackup(): array
    {
        return $this->itemsSeederBackup;
    }

    public function getExampleDataTelaBackup(): array
    {
        return $this->exampleDataTelaBackup;
    }

    public function getArticuloPruebaBackup(): array
    {
        return $this->articuloPruebaBackup;
    }
}

<?php

namespace Database\Seeders;

use App\Models\Finance\AccountingAccount;
use Illuminate\Database\Seeder;

class AccountingAccountSeeder extends Seeder
{
    /**
     * Catálogo de cuentas básico basado en el PUC colombiano.
     *
     * Estructura mínima funcional para arrancar:
     * - Clases principales (1 dígito)
     * - Grupos (2 dígitos)
     * - Cuentas (4 dígitos) - las que permiten movimiento
     */
    public function run(): void
    {
        $this->createAccounts($this->getChartOfAccounts());
    }

    private function createAccounts(array $accounts, ?int $parentId = null, int $level = 1): void
    {
        foreach ($accounts as $account) {
            $created = AccountingAccount::updateOrCreate(
                ['code' => $account['code']],
                [
                    'name' => $account['name'],
                    'account_type' => $account['type'],
                    'parent_id' => $parentId,
                    'level' => $level,
                    'allows_entries' => $account['allows_entries'] ?? false,
                    'is_active' => true,
                    'special_type' => $account['special_type'] ?? null,
                    'description' => $account['description'] ?? null,
                ]
            );

            if (isset($account['children']) && is_array($account['children'])) {
                $this->createAccounts($account['children'], $created->id, $level + 1);
            }
        }
    }

    private function getChartOfAccounts(): array
    {
        return [
            // ═══════════════════════════════════════════════════════════════════
            // CLASE 1: ACTIVO
            // ═══════════════════════════════════════════════════════════════════
            [
                'code' => '1',
                'name' => 'Activo',
                'type' => AccountingAccount::TYPE_ASSET,
                'children' => [
                    // Grupo 11: Disponible
                    [
                        'code' => '11',
                        'name' => 'Disponible',
                        'type' => AccountingAccount::TYPE_ASSET,
                        'children' => [
                            [
                                'code' => '1105',
                                'name' => 'Caja',
                                'type' => AccountingAccount::TYPE_ASSET,
                                'allows_entries' => true,
                                'special_type' => AccountingAccount::SPECIAL_CASH,
                                'description' => 'Efectivo disponible en caja',
                            ],
                            [
                                'code' => '1110',
                                'name' => 'Bancos',
                                'type' => AccountingAccount::TYPE_ASSET,
                                'allows_entries' => true,
                                'special_type' => AccountingAccount::SPECIAL_BANK,
                                'description' => 'Cuentas bancarias',
                            ],
                        ],
                    ],
                    // Grupo 13: Deudores
                    [
                        'code' => '13',
                        'name' => 'Deudores',
                        'type' => AccountingAccount::TYPE_ASSET,
                        'children' => [
                            [
                                'code' => '1305',
                                'name' => 'Clientes',
                                'type' => AccountingAccount::TYPE_ASSET,
                                'allows_entries' => true,
                                'special_type' => AccountingAccount::SPECIAL_ACCOUNTS_RECEIVABLE,
                                'description' => 'Cuentas por cobrar a clientes',
                            ],
                            [
                                'code' => '1355',
                                'name' => 'Anticipos y avances',
                                'type' => AccountingAccount::TYPE_ASSET,
                                'allows_entries' => true,
                            ],
                            [
                                'code' => '1365',
                                'name' => 'Cuentas por cobrar a trabajadores',
                                'type' => AccountingAccount::TYPE_ASSET,
                                'allows_entries' => true,
                            ],
                        ],
                    ],
                    // Grupo 14: Inventarios
                    [
                        'code' => '14',
                        'name' => 'Inventarios',
                        'type' => AccountingAccount::TYPE_ASSET,
                        'children' => [
                            [
                                'code' => '1405',
                                'name' => 'Materias primas',
                                'type' => AccountingAccount::TYPE_ASSET,
                                'allows_entries' => true,
                            ],
                            [
                                'code' => '1410',
                                'name' => 'Productos en proceso',
                                'type' => AccountingAccount::TYPE_ASSET,
                                'allows_entries' => true,
                            ],
                            [
                                'code' => '1430',
                                'name' => 'Productos terminados',
                                'type' => AccountingAccount::TYPE_ASSET,
                                'allows_entries' => true,
                            ],
                            [
                                'code' => '1435',
                                'name' => 'Mercancías no fabricadas',
                                'type' => AccountingAccount::TYPE_ASSET,
                                'allows_entries' => true,
                            ],
                        ],
                    ],
                    // Grupo 15: Propiedades, planta y equipo
                    [
                        'code' => '15',
                        'name' => 'Propiedades, planta y equipo',
                        'type' => AccountingAccount::TYPE_ASSET,
                        'children' => [
                            [
                                'code' => '1520',
                                'name' => 'Maquinaria y equipo',
                                'type' => AccountingAccount::TYPE_ASSET,
                                'allows_entries' => true,
                            ],
                            [
                                'code' => '1524',
                                'name' => 'Equipo de oficina',
                                'type' => AccountingAccount::TYPE_ASSET,
                                'allows_entries' => true,
                            ],
                            [
                                'code' => '1528',
                                'name' => 'Equipo de computación',
                                'type' => AccountingAccount::TYPE_ASSET,
                                'allows_entries' => true,
                            ],
                            [
                                'code' => '1540',
                                'name' => 'Flota y equipo de transporte',
                                'type' => AccountingAccount::TYPE_ASSET,
                                'allows_entries' => true,
                            ],
                        ],
                    ],
                ],
            ],

            // ═══════════════════════════════════════════════════════════════════
            // CLASE 2: PASIVO
            // ═══════════════════════════════════════════════════════════════════
            [
                'code' => '2',
                'name' => 'Pasivo',
                'type' => AccountingAccount::TYPE_LIABILITY,
                'children' => [
                    // Grupo 21: Obligaciones financieras
                    [
                        'code' => '21',
                        'name' => 'Obligaciones financieras',
                        'type' => AccountingAccount::TYPE_LIABILITY,
                        'children' => [
                            [
                                'code' => '2105',
                                'name' => 'Bancos nacionales',
                                'type' => AccountingAccount::TYPE_LIABILITY,
                                'allows_entries' => true,
                            ],
                        ],
                    ],
                    // Grupo 22: Proveedores
                    [
                        'code' => '22',
                        'name' => 'Proveedores',
                        'type' => AccountingAccount::TYPE_LIABILITY,
                        'children' => [
                            [
                                'code' => '2205',
                                'name' => 'Proveedores nacionales',
                                'type' => AccountingAccount::TYPE_LIABILITY,
                                'allows_entries' => true,
                                'special_type' => AccountingAccount::SPECIAL_ACCOUNTS_PAYABLE,
                                'description' => 'Cuentas por pagar a proveedores',
                            ],
                            [
                                'code' => '2210',
                                'name' => 'Proveedores del exterior',
                                'type' => AccountingAccount::TYPE_LIABILITY,
                                'allows_entries' => true,
                            ],
                        ],
                    ],
                    // Grupo 23: Cuentas por pagar
                    [
                        'code' => '23',
                        'name' => 'Cuentas por pagar',
                        'type' => AccountingAccount::TYPE_LIABILITY,
                        'children' => [
                            [
                                'code' => '2335',
                                'name' => 'Costos y gastos por pagar',
                                'type' => AccountingAccount::TYPE_LIABILITY,
                                'allows_entries' => true,
                            ],
                            [
                                'code' => '2365',
                                'name' => 'Retención en la fuente',
                                'type' => AccountingAccount::TYPE_LIABILITY,
                                'allows_entries' => true,
                            ],
                            [
                                'code' => '2367',
                                'name' => 'Impuesto a las ventas retenido',
                                'type' => AccountingAccount::TYPE_LIABILITY,
                                'allows_entries' => true,
                            ],
                            [
                                'code' => '2368',
                                'name' => 'Impuesto de industria y comercio retenido',
                                'type' => AccountingAccount::TYPE_LIABILITY,
                                'allows_entries' => true,
                            ],
                            [
                                'code' => '2370',
                                'name' => 'Retenciones y aportes de nómina',
                                'type' => AccountingAccount::TYPE_LIABILITY,
                                'allows_entries' => true,
                            ],
                        ],
                    ],
                    // Grupo 24: Impuestos por pagar
                    [
                        'code' => '24',
                        'name' => 'Impuestos, gravámenes y tasas',
                        'type' => AccountingAccount::TYPE_LIABILITY,
                        'children' => [
                            [
                                'code' => '2404',
                                'name' => 'Impuesto de renta y complementarios',
                                'type' => AccountingAccount::TYPE_LIABILITY,
                                'allows_entries' => true,
                            ],
                            [
                                'code' => '2408',
                                'name' => 'Impuesto sobre las ventas por pagar',
                                'type' => AccountingAccount::TYPE_LIABILITY,
                                'allows_entries' => true,
                            ],
                        ],
                    ],
                    // Grupo 25: Obligaciones laborales
                    [
                        'code' => '25',
                        'name' => 'Obligaciones laborales',
                        'type' => AccountingAccount::TYPE_LIABILITY,
                        'children' => [
                            [
                                'code' => '2505',
                                'name' => 'Salarios por pagar',
                                'type' => AccountingAccount::TYPE_LIABILITY,
                                'allows_entries' => true,
                            ],
                            [
                                'code' => '2510',
                                'name' => 'Cesantías consolidadas',
                                'type' => AccountingAccount::TYPE_LIABILITY,
                                'allows_entries' => true,
                            ],
                            [
                                'code' => '2515',
                                'name' => 'Intereses sobre cesantías',
                                'type' => AccountingAccount::TYPE_LIABILITY,
                                'allows_entries' => true,
                            ],
                            [
                                'code' => '2520',
                                'name' => 'Prima de servicios',
                                'type' => AccountingAccount::TYPE_LIABILITY,
                                'allows_entries' => true,
                            ],
                            [
                                'code' => '2525',
                                'name' => 'Vacaciones consolidadas',
                                'type' => AccountingAccount::TYPE_LIABILITY,
                                'allows_entries' => true,
                            ],
                        ],
                    ],
                ],
            ],

            // ═══════════════════════════════════════════════════════════════════
            // CLASE 3: PATRIMONIO
            // ═══════════════════════════════════════════════════════════════════
            [
                'code' => '3',
                'name' => 'Patrimonio',
                'type' => AccountingAccount::TYPE_EQUITY,
                'children' => [
                    [
                        'code' => '31',
                        'name' => 'Capital social',
                        'type' => AccountingAccount::TYPE_EQUITY,
                        'children' => [
                            [
                                'code' => '3105',
                                'name' => 'Capital suscrito y pagado',
                                'type' => AccountingAccount::TYPE_EQUITY,
                                'allows_entries' => true,
                            ],
                        ],
                    ],
                    [
                        'code' => '36',
                        'name' => 'Resultados del ejercicio',
                        'type' => AccountingAccount::TYPE_EQUITY,
                        'children' => [
                            [
                                'code' => '3605',
                                'name' => 'Utilidad del ejercicio',
                                'type' => AccountingAccount::TYPE_EQUITY,
                                'allows_entries' => true,
                            ],
                            [
                                'code' => '3610',
                                'name' => 'Pérdida del ejercicio',
                                'type' => AccountingAccount::TYPE_EQUITY,
                                'allows_entries' => true,
                            ],
                        ],
                    ],
                    [
                        'code' => '37',
                        'name' => 'Resultados de ejercicios anteriores',
                        'type' => AccountingAccount::TYPE_EQUITY,
                        'children' => [
                            [
                                'code' => '3705',
                                'name' => 'Utilidades acumuladas',
                                'type' => AccountingAccount::TYPE_EQUITY,
                                'allows_entries' => true,
                            ],
                            [
                                'code' => '3710',
                                'name' => 'Pérdidas acumuladas',
                                'type' => AccountingAccount::TYPE_EQUITY,
                                'allows_entries' => true,
                            ],
                        ],
                    ],
                ],
            ],

            // ═══════════════════════════════════════════════════════════════════
            // CLASE 4: INGRESOS
            // ═══════════════════════════════════════════════════════════════════
            [
                'code' => '4',
                'name' => 'Ingresos',
                'type' => AccountingAccount::TYPE_INCOME,
                'children' => [
                    [
                        'code' => '41',
                        'name' => 'Ingresos operacionales',
                        'type' => AccountingAccount::TYPE_INCOME,
                        'children' => [
                            [
                                'code' => '4135',
                                'name' => 'Comercio al por mayor y menor',
                                'type' => AccountingAccount::TYPE_INCOME,
                                'allows_entries' => true,
                            ],
                            [
                                'code' => '4145',
                                'name' => 'Industria manufacturera',
                                'type' => AccountingAccount::TYPE_INCOME,
                                'allows_entries' => true,
                            ],
                        ],
                    ],
                    [
                        'code' => '42',
                        'name' => 'Ingresos no operacionales',
                        'type' => AccountingAccount::TYPE_INCOME,
                        'children' => [
                            [
                                'code' => '4210',
                                'name' => 'Financieros',
                                'type' => AccountingAccount::TYPE_INCOME,
                                'allows_entries' => true,
                            ],
                            [
                                'code' => '4250',
                                'name' => 'Recuperaciones',
                                'type' => AccountingAccount::TYPE_INCOME,
                                'allows_entries' => true,
                            ],
                            [
                                'code' => '4295',
                                'name' => 'Diversos',
                                'type' => AccountingAccount::TYPE_INCOME,
                                'allows_entries' => true,
                            ],
                        ],
                    ],
                ],
            ],

            // ═══════════════════════════════════════════════════════════════════
            // CLASE 5: GASTOS
            // ═══════════════════════════════════════════════════════════════════
            [
                'code' => '5',
                'name' => 'Gastos',
                'type' => AccountingAccount::TYPE_EXPENSE,
                'children' => [
                    [
                        'code' => '51',
                        'name' => 'Gastos operacionales de administración',
                        'type' => AccountingAccount::TYPE_EXPENSE,
                        'children' => [
                            [
                                'code' => '5105',
                                'name' => 'Gastos de personal',
                                'type' => AccountingAccount::TYPE_EXPENSE,
                                'allows_entries' => true,
                            ],
                            [
                                'code' => '5110',
                                'name' => 'Honorarios',
                                'type' => AccountingAccount::TYPE_EXPENSE,
                                'allows_entries' => true,
                            ],
                            [
                                'code' => '5115',
                                'name' => 'Impuestos',
                                'type' => AccountingAccount::TYPE_EXPENSE,
                                'allows_entries' => true,
                            ],
                            [
                                'code' => '5120',
                                'name' => 'Arrendamientos',
                                'type' => AccountingAccount::TYPE_EXPENSE,
                                'allows_entries' => true,
                            ],
                            [
                                'code' => '5135',
                                'name' => 'Servicios',
                                'type' => AccountingAccount::TYPE_EXPENSE,
                                'allows_entries' => true,
                            ],
                            [
                                'code' => '5140',
                                'name' => 'Gastos legales',
                                'type' => AccountingAccount::TYPE_EXPENSE,
                                'allows_entries' => true,
                            ],
                            [
                                'code' => '5145',
                                'name' => 'Mantenimiento y reparaciones',
                                'type' => AccountingAccount::TYPE_EXPENSE,
                                'allows_entries' => true,
                            ],
                            [
                                'code' => '5195',
                                'name' => 'Gastos diversos',
                                'type' => AccountingAccount::TYPE_EXPENSE,
                                'allows_entries' => true,
                            ],
                        ],
                    ],
                    [
                        'code' => '52',
                        'name' => 'Gastos operacionales de ventas',
                        'type' => AccountingAccount::TYPE_EXPENSE,
                        'children' => [
                            [
                                'code' => '5205',
                                'name' => 'Gastos de personal',
                                'type' => AccountingAccount::TYPE_EXPENSE,
                                'allows_entries' => true,
                            ],
                            [
                                'code' => '5235',
                                'name' => 'Servicios',
                                'type' => AccountingAccount::TYPE_EXPENSE,
                                'allows_entries' => true,
                            ],
                        ],
                    ],
                    [
                        'code' => '53',
                        'name' => 'Gastos no operacionales',
                        'type' => AccountingAccount::TYPE_EXPENSE,
                        'children' => [
                            [
                                'code' => '5305',
                                'name' => 'Financieros',
                                'type' => AccountingAccount::TYPE_EXPENSE,
                                'allows_entries' => true,
                            ],
                            [
                                'code' => '5395',
                                'name' => 'Gastos diversos',
                                'type' => AccountingAccount::TYPE_EXPENSE,
                                'allows_entries' => true,
                            ],
                        ],
                    ],
                ],
            ],

            // ═══════════════════════════════════════════════════════════════════
            // CLASE 6: COSTO DE VENTAS
            // ═══════════════════════════════════════════════════════════════════
            [
                'code' => '6',
                'name' => 'Costo de ventas',
                'type' => AccountingAccount::TYPE_COST,
                'children' => [
                    [
                        'code' => '61',
                        'name' => 'Costo de ventas y de prestación de servicios',
                        'type' => AccountingAccount::TYPE_COST,
                        'children' => [
                            [
                                'code' => '6135',
                                'name' => 'Comercio al por mayor y menor',
                                'type' => AccountingAccount::TYPE_COST,
                                'allows_entries' => true,
                            ],
                            [
                                'code' => '6145',
                                'name' => 'Industria manufacturera',
                                'type' => AccountingAccount::TYPE_COST,
                                'allows_entries' => true,
                            ],
                        ],
                    ],
                ],
            ],
        ];
    }
}

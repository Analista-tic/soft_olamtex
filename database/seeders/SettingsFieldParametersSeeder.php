<?php

namespace Database\Seeders;

use App\Models\Settings\Setting;
use App\Services\Settings\SettingService;
use Illuminate\Database\Seeder;

class SettingsFieldParametersSeeder extends Seeder
{
    public function __construct(
        protected SettingService $settingService = new SettingService
    ) {}

    public function run(): void
    {
        foreach ($this->definitions() as $item) {
            if ($item['kind'] === 'parameter') {
                $this->seedParameter($item['key'], $item['data']);

                continue;
            }

            Setting::updateOrCreate(
                ['kind' => $item['kind'], 'key' => $item['key']],
                $item['data']
            );
        }
    }

    private function definitions(): array
    {
        // Parámetros
        // El catálogo 'fabrics' ("Telas") se retiró: duplicaba la tabla fabric_names,
        // que es la fuente real de los nombres de tela. Ningún consumidor lo leía.
        // 'fabric_colors' ("Colores de tela") también se retiró: sin ningún consumidor
        // en el frontend, era código muerto. El catálogo real de colores es el modelo
        // Color (tabla colors), usado por items.color_id.
        $parameters = [
            [
                'kind' => 'parameter',
                'key' => 'fabric_providers',
                'data' => [
                    'label' => 'Proveedores de tela',
                    'value' => $this->mapNames([
                        'N/A', 'AJTEX', 'ARCACOL', 'CAMARBU', 'CANGURO INTERNATIONAL', 'CHEVIOTO', 'COLNOTEX', 'CONFECCIONES OLEADA', 'CONFETEX', 'CORBETA TEXTILES', 'COSMOTEXTIL', 'DACCACH HERMANOS', 'E.U.', 'ELIOT', 'ESPIRITU URBANO', 'GANE MAS', 'GEOMUNDO', 'HI-TEX DE COLOMBIA', 'ICOLTEX', 'INALSI', 'JOHN URIBE', 'MAGIC PRINT', 'MANUFACTURAS ELIOT', 'NOVELTEX', 'OLAMTEX', 'PIZANTEX', 'PORTOFINO', 'PRIMATELA', 'SI S.A.S', 'STILOTEX', 'SUTEX', 'T3 TEXTILES', 'TEXELI', 'TEXTILES DEL PACÍFICO', 'TEXTILTEX', 'VENTEX', 'VISUAL TEXTIL', 'VITRAL TEXTIL', 'VIVATEX', 'YOSHA TEXTI',
                    ]),
                    'status' => true,
                ],
            ],
            [
                'kind' => 'parameter',
                'key' => 'credit_note_reason',
                'data' => [
                    'label' => 'Motivos nota credito',
                    'description' => 'Motivos configurables para notas credito.',
                    'value' => [
                        [
                            'id' => 'partial_return_or_partial_rejection',
                            'name' => 'Devolucion parcial de los bienes y/o no aceptacion parcial del servicio',
                        ],
                        [
                            'id' => 'electronic_invoice_cancellation',
                            'name' => 'Anulacion de factura electronica',
                        ],
                        [
                            'id' => 'total_or_partial_discount',
                            'name' => 'Rebaja o descuento parcial o total',
                        ],
                        [
                            'id' => 'price_adjustment',
                            'name' => 'Ajuste de precio',
                        ],
                        [
                            'id' => 'early_payment_discount',
                            'name' => 'Descuento comercial por pronto pago',
                        ],
                        [
                            'id' => 'volume_discount',
                            'name' => 'Descuento comercial por volumen de ventas',
                        ],
                    ],
                    'status' => true,
                ],
            ],
            [
                'kind' => 'parameter',
                'key' => 'debit_note_reason',
                'data' => [
                    'label' => 'Motivos nota debito',
                    'description' => 'Motivos configurables para notas debito.',
                    'value' => [
                        [
                            'id' => 'interests',
                            'name' => 'Intereses',
                        ],
                        [
                            'id' => 'receivable_charges',
                            'name' => 'Gastos por cobrar',
                        ],
                        [
                            'id' => 'value_change',
                            'name' => 'Cambio del valor',
                        ],
                        [
                            'id' => 'other',
                            'name' => 'Otros',
                        ],
                    ],
                    'status' => true,
                ],
            ],
            [
                'kind' => 'parameter',
                'key' => 'areas',
                'data' => [
                    'label' => 'Áreas',
                    'description' => 'Catálogo de áreas organizacionales',
                    'value' => $this->mapNames([
                        'Gerencia', 'Producción', 'Corte', 'Logística', 'Diseño', 'Marketing', 'Comercial', 'Tics', 'Recursos Humanos', 'Financiero', 'Desarrollo Organizacional', 'SST',
                    ]),
                    'status' => true,
                ],
            ],
            // fabric_bases migrado a tabla dedicada — gestionado desde FabricBaseService
            // yarn_bases migrado a tabla dedicada — gestionado desde YarnBaseService
            [
                'kind' => 'parameter',
                'key' => 'preparation_groups',
                'data' => [
                    'label' => 'Grupos de preparación',
                    'description' => 'Subgrupos para reagrupar operaciones dentro de la sección Preparación.',
                    'value' => $this->mapNames([
                        'DELANTERO', 'TRASERO', 'MANGA', 'CUELLO', 'BOLSILLO',
                        'PRETINA', 'PUÑO', 'CAPUCHA', 'TIRA', 'SESGO',
                    ]),
                    'status' => true,
                ],
            ],
            [
                'kind' => 'parameter',
                'key' => 'groups',
                'data' => [
                    'label' => 'Grupo',
                    'description' => 'Catálogo de grupos',
                    'value' => $this->mapNames([
                        'LÍNEA', 'MODA',
                    ]),
                    'status' => true,
                ],
            ],
            [
                'kind' => 'parameter',
                'key' => 'operation_cost',
                'data' => [
                    'label' => 'Costo de operaciones',
                    'value' => 406,
                    'status' => true,
                ],
            ],
            [
                'kind' => 'parameter',
                'key' => 'yarn_cost',
                'data' => [
                    'label' => 'Costo de hilo',
                    'value' => 140,
                    'status' => true,
                ],
            ],
            [
                'kind' => 'parameter',
                'key' => 'cutting_cost',
                'data' => [
                    'label' => 'Costo corte',
                    'value' => 350,
                    'status' => true,
                ],
            ],
            [
                'kind' => 'parameter',
                'key' => 'minute_cost',
                'data' => [
                    'label' => 'Costo minuto',
                    'value' => 120,
                    'status' => true,
                ],
            ],
            [
                'kind' => 'parameter',
                'key' => 'lines',
                'data' => [
                    'label' => 'Líneas',
                    'value' => ['LÍNEA 1', 'LÍNEA 2', 'LÍNEA 3', 'LÍNEA 4', 'LÍNEA 5'],
                    'status' => true,
                ],
            ],
            [
                'kind' => 'parameter',
                'key' => 'sections',
                'data' => [
                    'label' => 'Secciones',
                    'description' => 'Catálogo de secciones de producto',
                    'value' => $this->mapSectionsWithGarmentType([
                        ['name' => 'BERMUDA',   'garment_type' => 'inferior'],
                        ['name' => 'BODY',      'garment_type' => 'superior'],
                        ['name' => 'BUZO',      'garment_type' => 'superior'],
                        ['name' => 'CAMISETA',  'garment_type' => 'superior'],
                        ['name' => 'CAPRY',     'garment_type' => 'inferior'],
                        ['name' => 'CARDIGAN',  'garment_type' => 'superior'],
                        ['name' => 'CHALECO',   'garment_type' => 'superior'],
                        ['name' => 'CHAQUETA',  'garment_type' => 'superior'],
                        ['name' => 'CONJUNTO',  'garment_type' => 'ambos'],
                        ['name' => 'ENTERIZO',  'garment_type' => 'ambos'],
                        ['name' => 'FALDA',     'garment_type' => 'inferior'],
                        ['name' => 'JOGGER',    'garment_type' => 'inferior'],
                        ['name' => 'LEGGIN',    'garment_type' => 'inferior'],
                        ['name' => 'OVEROL',    'garment_type' => 'ambos'],
                        ['name' => 'PANTALON',  'garment_type' => 'inferior'],
                        ['name' => 'SHORT',     'garment_type' => 'inferior'],
                        ['name' => 'TOP',       'garment_type' => 'superior'],
                        ['name' => 'VESTIDO',   'garment_type' => 'ambos'],
                    ]),
                    'status' => true,
                ],
            ],
            [
                'kind' => 'parameter',
                'key' => 'brands',
                'data' => [
                    'label' => 'Marcas',
                    'value' => $this->mapNames([
                        'EVA CASTILLO', 'AMERICAN EAST', 'HERPO', 'PASSARO', 'LOHARY', 'ATUENDOS', 'LAXO', 'THE ANSWER',
                    ]),
                    'status' => true,
                ],
            ],
            [
                'kind' => 'parameter',
                'key' => 'worlds',
                'data' => [
                    'label' => 'Mundos',
                    'value' => $this->mapNames([
                        'DAMA', 'HOMBRE', 'JUNIOR', 'NIÑO', 'NIÑA', 'TEEN',
                    ]),
                    'status' => true,
                ],
            ],
            [
                'kind' => 'parameter',
                'key' => 'sizes',
                'data' => [
                    'label' => 'Tallas',
                    'value' => ['XS', 'S', 'M', 'L', 'XL', 'XXL', '34', '35', '36', '37', '38', '39', '40', '41', '42', '43', '44', '45'],
                    'status' => true,
                ],
            ],
            [
                'kind' => 'parameter',
                'key' => 'destinations',
                'data' => [
                    'label' => 'Destinos',
                    'description' => 'Clientes o líneas comerciales frecuentes',
                    'value' => $this->mapNames([
                        'CCU', 'EJETEX', 'PASSARO', 'HERPO', 'ATUENDOS', 'LOHARY', 'OLAMTEX',
                    ]),
                    'status' => true,
                ],
            ],
            [
                'kind' => 'parameter',
                'key' => 'months',
                'data' => [
                    'label' => 'Meses',
                    'value' => $this->mapNames([
                        'ENERO', 'FEBRERO', 'MARZO', 'ABRIL', 'MAYO', 'JUNIO',
                        'JULIO', 'AGOSTO', 'SEPTIEMBRE', 'OCTUBRE', 'NOVIEMBRE', 'DICIEMBRE',
                    ]),
                    'status' => true,
                ],
            ],
            [
                'kind' => 'parameter',
                'key' => 'priorities',
                'data' => [
                    'label' => 'Prioridades',
                    'value' => $this->mapNames([
                        'A', 'B', 'C', 'D',
                    ]),
                    'status' => true,
                ],
            ],
            [
                'kind' => 'parameter',
                'key' => 'process_types',
                'data' => [
                    'label' => 'Tipos de proceso',
                    'value' => $this->mapNames([
                        'CORTE',
                        'CONFECCION',
                        'TERMINACION',
                        'ESTAMPADO',
                        'LAVANDERIA',
                        'OTRO',
                    ]),
                    'status' => true,
                ],
            ],
            [
                'kind' => 'parameter',
                'key' => 'payment_methods',
                'data' => [
                    'label' => 'Formas de pago tesoreria',
                    'description' => 'Metodos de pago disponibles para comprobantes y recibos de tesoreria.',
                    'value' => [
                        ['id' => 'cash', 'name' => 'Efectivo'],
                        ['id' => 'bank_deposit', 'name' => 'Consignacion bancaria'],
                        ['id' => 'bank_transfer', 'name' => 'Transferencia'],
                        ['id' => 'check', 'name' => 'Cheque'],
                        ['id' => 'credit_card', 'name' => 'Tarjeta credito'],
                        ['id' => 'debit_card', 'name' => 'Tarjeta debito'],
                    ],
                    'status' => true,
                ],
            ],
            [
                'kind' => 'parameter',
                'key' => 'calibre',
                'data' => [
                    'label' => 'Calibre',
                    'value' => $this->mapNames([
                        '120', '75', '40', '0',
                    ]),
                    'status' => true,
                ],
            ],
            [
                'kind' => 'parameter',
                'key' => 'puntada_por_pulgada',
                'data' => [
                    'label' => 'Puntada por pulgada',
                    'value' => $this->mapNames([
                        '8', '10', '11', '12', '0',
                    ]),
                    'status' => true,
                ],
            ],
            [
                'kind' => 'parameter',
                'key' => 'thread_brands',
                'data' => [
                    'label' => 'Marcas de hilo',
                    'description' => 'Marcas de hilo para la ficha técnica de producción.',
                    'value' => $this->mapNames([
                        'SOL', 'VENUS', 'MH', 'COATS',
                    ]),
                    'status' => true,
                ],
            ],
            [
                'kind' => 'parameter',
                'key' => 'piezas',
                'data' => [
                    'label' => 'Piezas',
                    'value' => $this->mapNames([
                        'DELANTERO',
                        'TRASERO',
                        'FRENTE',
                        'ESPALDA',
                        'MANGAS',
                        'CUELLO',
                        'PRETINA',
                        'CAPOTA',
                        'PUÑO',
                        'SESGO',
                        'TIRA',
                        'BOLSILLO',
                        'TAPA',
                        'FALSO',
                    ]),
                    'status' => true,
                ],
            ],
            [
                'kind' => 'parameter',
                'key' => 'lateral',
                'data' => [
                    'label' => 'Lateral',
                    'value' => $this->mapNames([
                        'IZQUIERDA',
                        'DERECHA',
                        'AMBOS',
                        'NO APLICA',
                    ]),
                    'status' => true,
                ],
            ],
            [
                'kind' => 'parameter',
                'key' => 'hilo',
                'data' => [
                    'label' => 'Hilo',
                    'value' => $this->mapNames([
                        'Pespunte / Amarre',
                        'Filetes',
                        'Ojales',
                        'Presilla',
                        'Puntada Seguridad',
                    ]),
                    'status' => true,
                ],
            ],
            [
                'kind' => 'parameter',
                'key' => 'type_of_fabric',
                'data' => [
                    'label' => 'Tipo de tela',
                    'value' => $this->mapNames([
                        'Tela principal',
                        'Tela secundaria 1',
                        'Tela secundaria 2',
                        'Tela secundaria 3',
                        'Tela secundaria 4',
                    ]),
                    'status' => true,
                ],
            ],
        ];

        // Definiciones de campos
        // Los campos 'base_tela' y 'base_hilaza' se retiraron: eran listas de texto libre
        // que duplicaban el catálogo real de fabric_names / yarn_names y no aportaban
        // ni base ni factor. El ítem los selecciona ahora por FK (items.fabric_name_id
        // / yarn_name_id). El histórico guardado en items.extra_fields se conserva.
        $fields = [
            [
                'kind' => 'field',
                'key' => 'talla',
                'data' => [
                    'label' => 'Talla',
                    'field_type' => 'select',
                    'options' => [
                        'XS', 'S', 'M', 'L', 'XL', 'XXL',
                        '2', '3', '4', '6', '8', '9', '10', '12',
                        '14', '16', '17CM', '28', '30', '32', '00',
                        'LAXO', 'NEGRA',
                    ],
                    'position' => 4,
                    'status' => true,
                ],
            ],
            [
                'kind' => 'field',
                'key' => 'tipo',
                'data' => [
                    'label' => 'Tipo',
                    'field_type' => 'select',
                    'options' => [
                        'ADHESIVO', 'ALFILERES CABEZA', 'ARROZ',
                        'BOBINA OJALADORA JUKI 1790', 'BOLSA DE BASURA',
                        'BORDADO', 'CAJA MENOR', 'ESTAMPADA', 'FIJA',
                        'GRAPAS OFICINA',
                        'GRAPAS PARA CORTE 1/2" GRAPADORA INDUSTRIAL',
                        'GRAPAS PARA CORTE DE 1/4" INDUSTRIAL',
                        'HONGO', 'INVISIBLE', 'MANUAL', 'MEDIA BOLA',
                        'PIEDRA', 'PLANO', 'PLASTIFLECHA TRABAJO PESADO',
                        'PLASTIFLECHAS TRABAJO LIVIANO X 5000 UNDS',
                        'PLASTICO', 'PUNTILLA', 'REMISION', 'SEPARABLE',
                        'SOMBRERO', 'TEJIDA', 'TERMICO', 'TRANSPARENTE',
                    ],
                    'position' => 5,
                    'status' => true,
                ],
            ],
            [
                'kind' => 'field',
                'key' => 'tamano',
                'data' => [
                    'label' => 'Tamaño',
                    'field_type' => 'select',
                    'options' => [
                        '2X3', '3X2.5', '4"', '5 MM', '5X5', '6 MM',
                        '6CM', '6MM', '6X5', '7 MM', '7CM', '7MM',
                        '7X2.5', '8 MM', '8"', '8CM', '8MM', '9 MM',
                        '9MM', '10 MM', '10X16', '10X4', '12X16',
                        '12X50', '14L', '15L', '17 MM', '17MM', '18L',
                        '19 MM', '20L', '20MM', '24MM', '28L', '30L',
                        '30MM', '31L', '32L', '32MM', '36L', '48X100',
                        '60X35', '63"', '65X85', '70X90X10', '80MM',
                        '100X100X500',
                    ],
                    'position' => 6,
                    'status' => true,
                ],
            ],
            [
                'kind' => 'field',
                'key' => 'acabado',
                'data' => [
                    'label' => 'Acabado',
                    'field_type' => 'select',
                    'options' => [
                        'BRONCE', 'COBRE', 'DORADO', 'NIQUEL',
                        'ORO ROSADO', 'PAVON', 'PLATA',
                    ],
                    'position' => 7,
                    'status' => true,
                ],
            ],
            [
                'kind' => 'field',
                'key' => 'material',
                'data' => [
                    'label' => 'Material',
                    'field_type' => 'select',
                    'options' => [
                        'ALUMINIO', 'COBRE', 'DELRHIN',
                        'POLIESTER', 'POLIETER',
                    ],
                    'position' => 8,
                    'status' => true,
                ],
            ],
            [
                'kind' => 'field',
                'key' => 'largo',
                'data' => [
                    'label' => 'Largo',
                    'field_type' => 'select',
                    'options' => [
                        '8 CM', '10 CM', '10CM', '12 CM', '12CM',
                        '13 CM', '15 CM', '15CM', '17 CM', '20 CM',
                        '20CM', '25 CM', '25CM', '40 CM', '45CM',
                        '48 CM', '50 CM', '50CM', '55CM', '60 CM',
                        '60CM', '62CM', '65 CM', '66 CM', '70 CM',
                        '70CM', '75 CM',
                    ],
                    'position' => 9,
                    'status' => true,
                ],
            ],
            [
                'kind' => 'field',
                'key' => 'calibre',
                'data' => [
                    'label' => 'Calibre',
                    'field_type' => 'select',
                    'options' => [
                        '9', '10', '11', '12', '14', '16',
                        '18', '19', '20', '22', '200/25',
                    ],
                    'position' => 10,
                    'status' => true,
                ],
            ],
            [
                'kind' => 'field',
                'key' => 'marca',
                'data' => [
                    'label' => 'Marca',
                    'field_type' => 'select',
                    'options' => [
                        'EVA CASTILLO', 'FULL WEAR', 'HERPO', 'LAXO',
                    ],
                    'position' => 11,
                    'status' => true,
                ],
            ],
            [
                'kind' => 'field',
                'key' => 'grosor',
                'data' => [
                    'label' => 'Grosor',
                    'field_type' => 'select',
                    'options' => [
                        '2MM', '10MM', '1.15 CM', '1.20CM',
                        '1.30 CM', '1.30CM', '1.45 CM',
                    ],
                    'position' => 12,
                    'status' => true,
                ],
            ],
            [
                'kind' => 'field',
                'key' => 'puntera',
                'data' => [
                    'label' => 'Puntera',
                    'field_type' => 'select',
                    'options' => [
                        'ACETATO', 'BLANCA', 'BLANCA LETRAS',
                        'CAFE LETRAS', 'CRUDA', 'EN NIQUEL',
                        'LETR AORT', 'METALICA', 'METALICA NIQUEL',
                        'NEGRA', 'NEGRA PLASTICA', 'NIQUEL',
                    ],
                    'position' => 13,
                    'status' => true,
                ],
            ],
            [
                'kind' => 'field',
                'key' => 'medida',
                'data' => [
                    'label' => 'Medida',
                    'field_type' => 'select',
                    'options' => [
                        '2"', '4"', '4MM', '7MM', '8"', '10MM', '140CM',
                    ],
                    'position' => 14,
                    'status' => true,
                ],
            ],
            [
                'kind' => 'field',
                'key' => 'tipo_maquina',
                'data' => [
                    'label' => 'Tipo máquina',
                    'field_type' => 'select',
                    'options' => [
                        'BOTONADORA', 'COLLARIN', 'EMPRETINADORA',
                        'ENRESORTADORA', 'FILETEADORA', 'PLANA', 'PRESILLA',
                    ],
                    'position' => 15,
                    'status' => true,
                ],
            ],
            [
                'kind' => 'field',
                'key' => 'referencia_aguja',
                'data' => [
                    'label' => 'Referencia aguja',
                    'field_type' => 'select',
                    'options' => [
                        'B27', 'B63', 'DBX1', 'UY128', 'X1',
                    ],
                    'position' => 16,
                    'status' => true,
                ],
            ],
            [
                'kind' => 'field',
                'key' => 'ancho',
                'data' => [
                    'label' => 'Ancho',
                    'field_type' => 'text',
                    'options' => [],
                    'position' => 17,
                    'status' => true,
                ],
            ],
            [
                'kind' => 'field',
                'key' => 'descripcion_adicional',
                'data' => [
                    'label' => 'Descripción',
                    'field_type' => 'text',
                    'options' => [],
                    'position' => 18,
                    'status' => true,
                ],
            ],
            [
                'kind' => 'field',
                'key' => 'uso',
                'data' => [
                    'label' => 'Uso',
                    'field_type' => 'select',
                    'options' => ['CORTE'],
                    'position' => 19,
                    'status' => true,
                ],
            ],
            [
                'kind' => 'field',
                'key' => 'diseno',
                'data' => [
                    'label' => 'Diseño',
                    'field_type' => 'select',
                    'options' => [
                        'ENJOYING YOUR', 'NEW DESING', 'NEW YORK CITY',
                    ],
                    'position' => 20,
                    'status' => true,
                ],
            ],
        ];

        return array_merge($parameters, $fields);
    }

    private function seedParameter(string $key, array $data): void
    {
        $canonicalKey = $this->settingService->canonicalKey($key);
        $payload = array_merge($data, ['status' => $data['status'] ?? true]);
        $existing = $this->existingAliasFor($canonicalKey);

        if ($existing) {
            $payload['value'] = $this->mergeParameterOptions(
                $canonicalKey,
                $existing->value ?? [],
                $payload['value'] ?? []
            );
            $payload['label'] = $existing->label ?? $payload['label'] ?? null;
            if ($existing->description !== null) {
                $payload['description'] = $existing->description;
            }
        }

        Setting::updateOrCreate(
            ['kind' => 'parameter', 'key' => $canonicalKey],
            $payload
        );

        $this->pruneAliasKeys($canonicalKey);
    }

    private function existingAliasFor(string $canonical): ?Setting
    {
        $keys = $this->aliasMap()[$canonical] ?? [$canonical];

        return Setting::parameters()->whereIn('key', $keys)->first();
    }

    private function pruneAliasKeys(string $canonical): void
    {
        $keys = $this->aliasMap()[$canonical] ?? [];
        if (empty($keys)) {
            return;
        }

        Setting::parameters()
            ->whereIn('key', $keys)
            ->where('key', '<>', $canonical)
            ->delete();
    }

    private function aliasMap(): array
    {
        return [
            'sections' => ['sections', 'secciones', 'seccion', 'section'],
            'groups' => ['groups', 'grupos', 'grupo', 'group'],
            'brands' => ['brands', 'marcas', 'marca', 'brand'],
            'worlds' => ['worlds', 'mundos', 'mundo', 'world'],
            // fabric_bases y yarn_bases migrados a tablas dedicadas
            'sizes' => ['sizes', 'tallas', 'talla'],
            'destinations' => ['destinations', 'destinos', 'destino'],
            'months' => ['months', 'meses', 'mes'],
            'priorities' => ['priorities', 'prioridades', 'prioridad'],
            'process_types' => ['process_types', 'tipos_proceso', 'tipos_de_proceso', 'tipo_proceso', 'process_type', 'processes'],
            'calibre' => ['calibre', 'calibres', 'gauge'],
            'puntada_por_pulgada' => ['puntada_por_pulgada', 'puntadas_por_pulgada', 'stitches_per_inch'],
        ];
    }

    private function mergeParameterOptions(string $canonicalKey, mixed $existingValue, mixed $seedValue): mixed
    {
        $orderedDefaults = $this->orderedOptionNames()[$canonicalKey] ?? null;
        if ($orderedDefaults === null) {
            return $existingValue ?: $seedValue;
        }

        $existingRows = is_array($existingValue) ? $existingValue : [];
        $seedRows = is_array($seedValue) ? $seedValue : [];
        $byName = [];

        $capture = static function (mixed $row) use (&$byName): void {
            if (is_array($row)) {
                $name = trim((string) ($row['name'] ?? ''));
                if ($name === '' && isset($row['value'])) {
                    $name = trim((string) $row['value']);
                }
                if ($name !== '') {
                    $row['name'] = $name;
                    $byName[$name] = $row;
                }

                return;
            }

            if (is_scalar($row)) {
                $name = trim((string) $row);
                if ($name !== '') {
                    $byName[$name] = ['name' => $name];
                }
            }
        };

        foreach ($seedRows as $row) {
            $capture($row);
        }
        foreach ($existingRows as $row) {
            $capture($row);
        }

        $merged = [];
        $id = 1;

        foreach ($orderedDefaults as $name) {
            $name = (string) $name;
            $row = $byName[$name] ?? ['name' => $name];
            $row['id'] = $id++;
            $row['name'] = $name;
            $merged[] = $row;
            unset($byName[$name]);
        }

        foreach ($byName as $row) {
            $name = trim((string) ($row['name'] ?? ''));
            if ($name === '') {
                continue;
            }

            $row['id'] = $id++;
            $row['name'] = $name;
            $merged[] = $row;
        }

        return $merged;
    }

    private function orderedOptionNames(): array
    {
        return [
            'calibre' => ['120', '75', '40', '0'],
            'puntada_por_pulgada' => ['8', '10', '11', '12', '0'],
        ];
    }

    private function mapNames(array $names, int $start = 1): array
    {
        $mapped = [];
        foreach ($names as $index => $name) {
            $mapped[] = [
                'id' => $start + $index,
                'name' => $name,
            ];
        }

        return $mapped;
    }

    /**
     * Mapea secciones con allowed_garment_type (superior|inferior|ambos).
     * Cada item es ['name' => '...', 'garment_type' => '...'].
     */
    private function mapSectionsWithGarmentType(array $items, int $start = 1): array
    {
        $mapped = [];
        foreach ($items as $index => $item) {
            $mapped[] = [
                'id' => $start + $index,
                'name' => is_array($item) ? $item['name'] : (string) $item,
                'allowed_garment_type' => is_array($item) ? ($item['garment_type'] ?? 'ambos') : 'ambos',
            ];
        }

        return $mapped;
    }
}

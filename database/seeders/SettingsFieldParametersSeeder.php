<?php

namespace Database\Seeders;

use App\Models\Settings\Setting;
use App\Services\Settings\SettingService;
use Illuminate\Database\Seeder;

class SettingsFieldParametersSeeder extends Seeder
{
    public function __construct(
        protected SettingService $settingService = new SettingService()
    ) {
    }

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
        $parameters = [
                        [
                            'kind' => 'parameter',
                            'key' => 'fabrics',
                            'data' => [
                                'label' => 'Telas',
                                'value' => $this->mapNames([
                                    'BERMUDA', 'ANTARES', 'SUPERGAROTO', 'TWIZT EIB', 'ACUARIUS', 'ALICANTE PERCHA', 'ALLEGRA', 'AMAZON', 'AMOMIO FETI', 'ANTARTIDA', 'ARMANY', 'ASCALONI REPEL', 'ATENAS', 'AURORA', 'BANANA', 'BEGALINA ', 'BENGALINA', 'BENGALINA BLANCA', 'BENGALINA BUDAPEST', 'BENGALINA BYBLOS', 'BENGALINA CHEV', 'BENGALINA CHEVIOTTO', 'BENGALINA DELUXE', 'BENGALINA EU', 'BENGALINA FRANCE', 'BENGALINA HEAVY', 'BENGALINA HITEX', 'BENGALINA IMP', 'BENGALINA MANCHESTER', 'BENGALINA P.U.', 'BENGALINA PESADA', 'BENGALINA SUBL', 'BENGALINA SUBLI', 'BENGALINA SUPERFLEX', 'BENGALINA TEFFANY', 'BENGALINA TIFFANY', 'BENGALINA YOSHA', 'BENGALINA|', 'BENGALINA--MURANO', 'BENGALINEFRANCE', 'BENGALINFRANCE', 'BEVERLY STEESCH', 'BLAKE', 'BOOGIE', 'BOOMY CAMU', 'BOSSANOVA', 'BOSSE', 'BOSTONFLEX', 'BOSTONIA', 'BOWIE SHELL', 'BUDAPEST', 'BURDA', 'BURDA AFRODITA', 'BURDA ALICANTE', 'BURDA GRANADA', 'BURDA LAURA ', 'BURDA PALERMO', 'BURDA SOUL', 'BURDA SUOL', 'BURSA SOUL', 'BYBLOS', 'CAIROPLUS', 'CARDIGAN RIB', 'CARDIGANRIB', 'CAREBALI', 'CASHMERE', 'CENTA FROZEN', 'CENTAURO', 'CHALIS', 'CHAMBRAY', 'CHENOA', 'CIELO', 'CILIOSUS', 'COQUETA', 'CORDUROY', 'DENIM GINGER ', 'DOLCE', 'DRIL GAMUZADO', 'DRILL 3-755', 'DRILL ALG', 'DRILL ALGADON', 'DRILL ALGODON', 'DRILL AUSTIN', 'DRILL BERLIN', 'DRILL HALCON', 'DRILL INGLES', 'DRILL ROYAL', 'DRILL TWILL', 'DRILLBERLIN', 'DUMAS', 'ENTRETELA', 'ENTRETELA 8035 ', 'ENTRETELA ADHESIVA', 'ENTRETELA GANEMAS', 'EXITO', 'FANTÁSTICA', 'FIORENZO', 'FLATY', 'FLOW', 'FRANELA', 'GRECIA', 'GUANTANAMERA', 'HALCON', 'HARPER', 'HAWKINS', 'HEAVY', 'INDIGO', 'INDIGO MASALA', 'INTERLON ADHESIVO', 'INTERLON PESADO', 'JABÓN', 'JAZMIN', 'JERSEY LICRA', 'JOLLY ', 'KAROL GI', 'KEIRA PLUS', 'KEIRAPLUS', 'KENDALL', 'KOALITA', 'KODIE', 'KOZZA', 'LAS VEGAS NEW PERCHADO', 'LAS VEGAS PERCHADO', 'LATIAN', 'LATIN FLAGE', 'LATIN NEPPY F', 'LATIN NEPPY G', 'LATINA', 'LATTU RAWR', 'LENOVOFLEX', 'LENOVOFLEX ELIT', 'LEONOR', 'LIMON', 'LINO', 'LINO BAHAMAS', 'LINO CAPRI', 'LINO ESPECIAL', 'LINO EU', 'LINO IXTAPA', 'LINO MOJITO', 'LINO REGGAE', 'LONDON MILLENIUM', 'LOTTO', 'LOTTO IMP', 'MALE', 'MALLA ORION', 'MANAGUA', 'MANGLAR', 'MARBELLA', 'METROPOL', 'MICRO COMFORT', 'MICROCOMFORT', 'MICROFIBRA', 'MIEL ', 'MIELERO', 'MILLENIAL', 'MINIMAL', 'MINIWAFER', 'MOLLY', 'MONTEVIDEO', 'MOREL', 'MOSCHINO', 'NAOMI', 'NAUTICA', 'NOORD', 'NOVAK REPEL', 'OLIMPIA', 'PACIFIC PLUS', 'PALACETE', 'PALACETE PERCHA', 'PALADIO', 'PALADIO PERCHA', 'PALADIO PERCHADA', 'PANCAKE', 'PANDERO', 'PARACHUTE', 'PEKIN', 'PERCHADO', 'PIEL DURAZNO', 'PISTACHO', 'POLIBENGALINA', 'PONDEROSA', 'POPELINA LIMÓN', 'PRAGA', 'PRETTY', 'PUNTO BRUSH', 'RAGU', 'RIB 1', 'RIB 2', 'RIB ALICANTE NEWS', 'RIB ALLEGRA', 'RIB ARCOIRIS', 'RIB BRUSH', 'RIB CHENOA', 'RIB DELTA', 'RIB EXITO', 'RIB GUCCI', 'RIB JEREMY ', 'RIB LAS VEGAS NEWS', 'RIB LAUROS', 'RIB LUREX', 'RIB MARBELLA', 'RIB ROSALIA', 'RIB STOP', 'RIB TORONJA', 'RIB TWIZT', 'RIB UNICOLOR', 'RIB VERSACE ', 'RIB1', 'RIB2', 'RIBSIX', 'RODNEY PERCHA', 'ROSELLO PERCHA', 'ROYAL', 'SALOMÉ', 'SALSA ENMALLADA', 'SCALONI REPEL ', 'SERRANO', 'SKIM', 'SMILISH', 'SPECIAL RIB', 'SUAVESTAR', 'SUEÑOS', 'SUPER CHALÍS', 'SUPERGAROTO SILK', 'TABACO', 'TALINA', 'TARMA HABANA', 'TEFANNY', 'TEFFANY', 'TERRY', 'TIFFANY', 'TITANICA', 'TOBAGO', 'TOPACIO', 'TORONJA PERCHADA', 'TORONJA PERCHADO', 'TWILL CALIFORNIA ', 'TWIZT', 'TWIZT RIB', 'UNICOLOR', 'URBAAN SMILE', 'VALENTINA', 'VALERY', 'VEGAS NEW', 'VEGAS NEWS', 'VISCOSA', 'VULGO',
                                ]),
                                'status' => true,
                            ],
                        ],
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
                            'key' => 'fabric_colors',
                            'data' => [
                                'label' => 'Colores de tela',
                                'value' => [
                                    ['name' => 'AMARILLO', 'code' => '2552550', 'rgb' => '255,255,0'],
                                    ['name' => 'AMARILLO CLARO', 'code' => '244255129', 'rgb' => '244,255,129'],
                                    ['name' => 'AMARILLO OSCURO', 'code' => '25519334', 'rgb' => '255,193,34'],
                                    ['name' => 'AZUL', 'code' => '3169244', 'rgb' => '3,169,244'],
                                    ['name' => 'AZUL CELESTE', 'code' => '134176229', 'rgb' => '134,176,229'],
                                    ['name' => 'AZUL CLARO', 'code' => '201233252', 'rgb' => '201,233,252'],
                                    ['name' => 'AZUL ELECTRICO', 'code' => '00255', 'rgb' => '0,0,255'],
                                    ['name' => 'AZUL INDIGO', 'code' => '100126154', 'rgb' => '100,126,154'],
                                    ['name' => 'AZUL NAVY', 'code' => '254076', 'rgb' => '25,40,76'],
                                    ['name' => 'AZUL OSCURO', 'code' => '0032', 'rgb' => '0,0,32'],
                                    ['name' => 'AZUL REY', 'code' => '33181', 'rgb' => '3,3,181'],
                                    ['name' => 'BEIGE', 'code' => '207185151', 'rgb' => '207,185,151'],
                                    ['name' => 'BEIGE JASPE', 'code' => '237215189', 'rgb' => '237,215,189'],
                                    ['name' => 'BEIGE OSCURO', 'code' => '169149123', 'rgb' => '169,149,123'],
                                    ['name' => 'BLANCO', 'code' => '255255255', 'rgb' => '255,255,255'],
                                    ['name' => 'CAFÉ', 'code' => '634023', 'rgb' => '63,40,23'],
                                    ['name' => 'CAFÉ OSCURO', 'code' => '231200', 'rgb' => '23,12,0'],
                                    ['name' => 'CAMEL', 'code' => '19113861', 'rgb' => '191,138,61'],
                                    ['name' => 'CANELA', 'code' => '1128975', 'rgb' => '112,89,75'],
                                    ['name' => 'CAOBA', 'code' => '1746280', 'rgb' => '174,62,80'],
                                    ['name' => 'CEREZA', 'code' => '1972952', 'rgb' => '197,29,52'],
                                    ['name' => 'COLOR UNICO', 'code' => '', 'rgb' => ''],
                                    ['name' => 'CREMA', 'code' => '249251231', 'rgb' => '249,251,231'],
                                    ['name' => 'CRUDO', 'code' => '245245220', 'rgb' => '245,245,220'],
                                    ['name' => 'CURUBA', 'code' => '250170148', 'rgb' => '250,170,148'],
                                    ['name' => 'DURAZNO', 'code' => '255204188', 'rgb' => '255,204,188'],
                                    ['name' => 'FUCSIA', 'code' => '2550128', 'rgb' => '255,0,128'],
                                    ['name' => 'GRANATE', 'code' => '1765472', 'rgb' => '176,54,72'],
                                    ['name' => 'GRIS', 'code' => '205205205', 'rgb' => '205,205,205'],
                                    ['name' => 'GRIS CLARO', 'code' => '189189189', 'rgb' => '189,189,189'],
                                    ['name' => 'GRIS OSCURO', 'code' => '514744', 'rgb' => '51,47,44'],
                                    ['name' => 'GUAYABA', 'code' => '255134116', 'rgb' => '255,134,116'],
                                    ['name' => 'INDIGO', 'code' => '42647', 'rgb' => '4,26,47'],
                                    ['name' => 'KAKI', 'code' => '16114394', 'rgb' => '161,143,94'],
                                    ['name' => 'LAVANDA', 'code' => '12687194', 'rgb' => '126,87,194'],
                                    ['name' => 'LILA', 'code' => '199161217', 'rgb' => '199,161,217'],
                                    ['name' => 'LIMA', 'code' => '1282550', 'rgb' => '128,255,0'],
                                    ['name' => 'MAGENTA', 'code' => '19741155', 'rgb' => '197,41,155'],
                                    ['name' => 'MANDARINA', 'code' => '255204128', 'rgb' => '255,204,128'],
                                    ['name' => 'MAUVE', 'code' => '1196796', 'rgb' => '119,67,96'],
                                    ['name' => 'MENTA', 'code' => '132255255', 'rgb' => '132,255,255'],
                                    ['name' => 'MOCA', 'code' => '163128104', 'rgb' => '163,128,104'],
                                    ['name' => 'MORADO', 'code' => '14236170', 'rgb' => '142,36,170'],
                                    ['name' => 'MOSTAZA', 'code' => '21416043', 'rgb' => '214,160,43'],
                                    ['name' => 'NARANJA', 'code' => '2551280', 'rgb' => '255,128,0'],
                                    ['name' => 'NARANJA NEON', 'code' => '255138128', 'rgb' => '255,138,128'],
                                    ['name' => 'NEGRO', 'code' => '000', 'rgb' => '0,0,0'],
                                    ['name' => 'NUDE', 'code' => '250225206', 'rgb' => '250,225,206'],
                                    ['name' => 'PALO ROSA', 'code' => '234137154', 'rgb' => '234,137,154'],
                                    ['name' => 'PETROLEO', 'code' => '0150136', 'rgb' => '0,150,136'],
                                    ['name' => 'ROJO', 'code' => '25500', 'rgb' => '255,0,0'],
                                    ['name' => 'ROSA VIEJO', 'code' => '225189178', 'rgb' => '225,189,178'],
                                    ['name' => 'ROSADO', 'code' => '255203219', 'rgb' => '255,203,219'],
                                    ['name' => 'ROSADO CLARO', 'code' => '246202202', 'rgb' => '246,202,202'],
                                    ['name' => 'ROSADO NEON', 'code' => '2550028', 'rgb' => '255,0,28'],
                                    ['name' => 'SALMON', 'code' => '250128114', 'rgb' => '250,128,114'],
                                    ['name' => 'TAUPE', 'code' => '1199376', 'rgb' => '119,93,76'],
                                    ['name' => 'TERRACOTA', 'code' => '151373', 'rgb' => '151,37,3'],
                                    ['name' => 'TURQUESA', 'code' => '0206209', 'rgb' => '0,206,209'],
                                    ['name' => 'VERDE', 'code' => '212350', 'rgb' => '2,123,50'],
                                    ['name' => 'VERDE FUERTE', 'code' => '014580', 'rgb' => '0,145,80'],
                                    ['name' => 'VERDE JADE', 'code' => '135178157', 'rgb' => '135,178,157'],
                                    ['name' => 'VERDE LIMÓN', 'code' => '12819264', 'rgb' => '128,192,64'],
                                    ['name' => 'VERDE MENTA', 'code' => '185249229', 'rgb' => '185,249,229'],
                                    ['name' => 'VERDE MILITAR', 'code' => '758332', 'rgb' => '75,83,32'],
                                    ['name' => 'VERDE NEON', 'code' => '20425551', 'rgb' => '204,255,51'],
                                    ['name' => 'VERDE OLIVO', 'code' => '17518043', 'rgb' => '175,180,43'],
                                    ['name' => 'VERDE OSCURO', 'code' => '168152', 'rgb' => '16,81,52'],
                                    ['name' => 'VERDE PASTEL', 'code' => '208217179', 'rgb' => '208,217,179'],
                                    ['name' => 'VERDE PISTACHO', 'code' => '197225165', 'rgb' => '197,225,165'],
                                    ['name' => 'VERDE SALVIA', 'code' => '126158132', 'rgb' => '126,158,132'],
                                    ['name' => 'VINOTINTO', 'code' => '6000', 'rgb' => '60,0,0'],
                                    ['name' => 'VIOLETA', 'code' => '1376189', 'rgb' => '137,6,189'],
                                ],
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
                        'Administración', 'Ventas', 'Producción', 'Compras', 'Logística', 'Contabilidad',
                    ]),
                    'status' => true,
                ],
            ],
            [
                'kind' => 'parameter',
                'key' => 'fabric_bases',
                'data' => [
                    'label' => 'Bases de tela',
                    'description' => 'Cada base tiene un factor que afecta el SAM (ej: 1.0 = neutro, 1.05 = +5%)',
                    'value' => $this->mapFabricBases([
                        ['name' => 'ALGODÓN', 'factor' => 1.0],
                        ['name' => 'POLIÉSTER', 'factor' => 1.0],
                        ['name' => 'LINO', 'factor' => 1.0],
                        ['name' => 'SEDA', 'factor' => 1.0],
                        ['name' => 'LANA', 'factor' => 1.0],
                        ['name' => 'ESTBURDA', 'factor' => 1.0],
                        ['name' => 'BURDA', 'factor' => 1.0],
                        ['name' => 'ANTIFLUIDO', 'factor' => 1.0],
                        ['name' => 'FORRO MAYA', 'factor' => 1.0],
                        ['name' => 'BURDA SUBLIMADA', 'factor' => 1.0],
                        ['name' => 'BENGALINE', 'factor' => 1.0],
                        ['name' => 'BURDA VISCOSA', 'factor' => 1.0],
                        ['name' => 'LYCRA', 'factor' => 1.0],
                        ['name' => 'LYCRA ALGODÓN', 'factor' => 1.0],
                        ['name' => 'BASE SUBLIMAR', 'factor' => 1.0],
                        ['name' => 'BURDA PERCHADA', 'factor' => 1.0],
                        ['name' => 'RIB', 'factor' => 1.0],
                        ['name' => 'PUNTO ROMA', 'factor' => 1.0],
                    ]),
                    'status' => true,
                ],
            ],
            [
                'kind' => 'parameter',
                'key' => 'yarn_bases',
                'data' => [
                    'label' => 'Bases de hilaza',
                    'value' => $this->mapNames([
                        'NYLON', 'RAYÓN', 'ACRÍLICO', 'POLIPROPILENO', 'ALGODÓN',
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
                        'LÍNEA', 'MODA'
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
                    'value' => $this->mapNames([
                        'BERMUDA', 'BODY', 'BUZO', 'CAMISETA', 'CAPRY', 'CARDIGAN', 'CHALECO', 'CHAQUETA', 'CONJUNTO', 'ENTERIZO', 'FALDA', 'JOGGER', 'LEGGIN', 'OVEROL', 'PANTALON', 'SHORT', 'TOP', 'VESTIDO',
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
                        'BORDADO',
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
                        '90',
                        '180',
                        'SESGO',
                    ]),
                    'status' => true,
                ],
            ],
            [
                'kind' => 'parameter',
                'key' => 'operation_actions',
                'data' => [
                    'label' => 'Acciones de operación',
                    'description' => 'Catálogo de acciones disponibles para el módulo de operaciones.',
                    'value' => $this->mapNames([
                        'ABIR', 'ABOTONAR', 'AMARRAR', 'ATRACAR', 'BOTONAR',
                        'CABECEAR', 'CERRAR', 'COLOCAR', 'CORTAR', 'CUARTEAR',
                        'DOBLADILLAR', 'EMBOLSAR', 'EMBONAR', 'ENTALEGAR',
                        'FIJAR', 'FILETEAR', 'FUSIONAR', 'HACER', 'INSERTAR',
                        'MARCAR', 'MEDIR Y CORTAR', 'OJALAR', 'PESPUNTAR',
                        'PICAR Y VOLTEAR', 'PLANCHAR', 'PREPARAR', 'PRENDER',
                        'QUITAR', 'REVOCAR', 'REVISAR', 'SESGAR', 'TERMINAR',
                        'TIQUETEAR', 'UBICAR', 'UNIR', 'VAPORIZAR', 'VOLTEAR', 'ZURCIR',
                    ]),
                    'status' => true,
                ],
            ],
            [
                'kind' => 'parameter',
                'key' => 'operation_garment_parts',
                'data' => [
                    'label' => 'Partes de prenda',
                    'description' => 'Catálogo de partes de prenda disponibles para el módulo de operaciones.',
                    'value' => $this->mapNames([
                        'ALETILLA', 'ALETILLON', 'BOTON', 'BOLSILLO', 'CANASTA',
                        'CAPOTA', 'CARTERA', 'CHARRETERA', 'CIERRE', 'CINTURON',
                        'COLAS', 'CODIGO BARRA', 'CORDON', 'CUELLO', 'DELANTEROS',
                        'ELASTICO', 'ENTREPIERNA', 'ESPALDA', 'FAJON', 'FRENTES',
                        'GAFFETE', 'HILADILLA', 'HOMBROS', 'LATERAL', 'MANGA CORTA',
                        'MANGA LARGA', 'MANGAS', 'MOÑO', 'PERILLA', 'PRETINA',
                        'PRENDA COMPLETA', 'PUÑO', 'PUÑOS', 'RUEDO', 'SOLAPA',
                        'TAPA', 'TIRA', 'TRASEROS',
                    ]),
                    'status' => true,
                ],
            ],
            [
                'kind' => 'parameter',
                'key' => 'operation_types',
                'data' => [
                    'label' => 'Tipos de prenda',
                    'description' => 'Catálogo de tipos de prenda para el módulo de operaciones. Campo independiente, sin condicionar otros selects.',
                    'value' => $this->mapNames([
                        'SUPERIOR', 'INFERIOR', 'COMPLETO', 'ACCESORIO',
                    ]),
                    'status' => true,
                ],
            ],
            [
                'kind' => 'parameter',
                'key' => 'operation_tipo_map',
                'data' => [
                    'label' => 'Mapa tipo prenda → sección → acción → partes',
                    'description' => 'Estructura de filtrado para el formulario de operaciones. Nivel: tipo (SUPERIOR/INFERIOR) → section_id → acción → [partes].',
                    'value' => [
                        'SUPERIOR' => [
                            '1' => [ // PREPARACIÓN
                                'ABRIR'          => ['CUELLO'],
                                'CABECEAR'       => ['ESPALDA'],
                                'CERRAR'         => ['FRENTES'],
                                'CORTAR'         => ['MANGAS'],
                                'CUARTEAR'       => ['BOLSILLO'],
                                'DOBLADILLAR'    => ['PUÑOS'],
                                'EMBONAR'        => ['FAJON'],
                                'ENTALEGAR'      => ['CARTERA'],
                                'FIJAR'          => ['PERILLA'],
                                'FILETEAR'       => ['CAPOTA'],
                                'FUSIONAR'       => ['TAPA'],
                                'HACER'          => ['CHARRETERA'],
                                'MARCAR'         => [],
                                'MEDIR Y CORTAR' => [],
                                'PESPUNTAR'      => [],
                                'PICAR Y VOLTEAR'=> [],
                                'PLANCHAR'       => [],
                                'PRENDER'        => [],
                                'PREPARAR'       => [],
                                'QUITAR'         => [],
                                'REVOCAR'        => [],
                                'SESGAR'         => [],
                                'UNIR'           => [],
                                'VAPORIZAR'      => [],
                            ],
                            '2' => [ // ENSAMBLE
                                'AMARRAR'    => ['TIRA'],
                                'ATRACAR'    => ['HOMBROS'],
                                'CERRAR'     => ['MANGA CORTA'],
                                'DOBLADILLAR'=> ['MANGA LARGA'],
                                'FILETEAR'   => ['RUEDO'],
                                'HACER'      => ['COLAS'],
                                'INSERTAR'   => ['GAFFETE'],
                                'PESPUNTAR'  => ['CIERRE'],
                                'PRENDER'    => ['SOLAPA'],
                                'REVOCAR'    => ['ELASTICO'],
                                'TERMINAR'   => ['FAJON'],
                                'UNIR'       => ['PUÑO'],
                                'ZURCIR'     => ['CUELLO'],
                            ],
                            '3' => [ // TERMINACIÓN
                                'OJALAR'    => ['PRENDA COMPLETA'],
                                'BOTONAR'   => ['CORDON'],
                                'ABOTONAR'  => ['HILADILLA'],
                                'REVISAR'   => ['BOTON'],
                                'PLANCHAR'  => ['MOÑO'],
                                'COLOCAR'   => ['CANASTA'],
                                'TIQUETEAR' => ['CODIGO BARRA'],
                                'EMBOLSAR'  => [],
                                'INSERTAR'  => [],
                                'UBICAR'    => [],
                                'VOLTEAR'   => [],
                                'MARCAR'    => [],
                            ],
                        ],
                        'INFERIOR' => [
                            '1' => [ // PREPARACIÓN
                                'PESPUNTAR'      => ['PRETINA'],
                                'ATRACAR'        => ['DELANTEROS'],
                                'CERRAR'         => ['TRASEROS'],
                                'DOBLADILLAR'    => ['BOLSILLO'],
                                'ENTALEGAR'      => ['TAPA'],
                                'FIJAR'          => ['TIRA'],
                                'FILETEAR'       => ['ALETILLA'],
                                'FUSIONAR'       => ['ALETILLON'],
                                'HACER'          => [],
                                'MARCAR'         => [],
                                'MEDIR Y CORTAR' => [],
                                'PRENDER'        => [],
                                'PICAR Y VOLTEAR'=> [],
                                'SESGAR'         => [],
                                'UNIR'           => [],
                                'PLANCHAR'       => [],
                                'REVOCAR'        => [],
                                'QUITAR'         => [],
                            ],
                            '2' => [ // ENSAMBLE
                                'AMARRAR'    => ['CINTURON'],
                                'ATRACAR'    => ['LATERAL'],
                                'CERRAR'     => ['ENTREPIERNA'],
                                'DOBLADILLAR'=> ['PRETINA'],
                                'FILETEAR'   => [],
                                'HACER'      => [],
                                'INSERTAR'   => [],
                                'PESPUNTAR'  => [],
                                'PRENDER'    => [],
                                'REVOCAR'    => [],
                                'TERMINAR'   => [],
                                'UNIR'       => [],
                                'ZURCIR'     => [],
                            ],
                            '3' => [ // TERMINACIÓN
                                'OJALAR'    => ['PRENDA COMPLETA'],
                                'BOTONAR'   => ['CORDON'],
                                'ABOTONAR'  => ['HILADILLA'],
                                'REVISAR'   => ['BOTON'],
                                'PLANCHAR'  => ['MOÑO'],
                                'COLOCAR'   => ['CANASTA'],
                                'TIQUETEAR' => ['CODIGO BARRA'],
                                'EMBOLSAR'  => [],
                                'INSERTAR'  => [],
                                'UBICAR'    => [],
                                'VOLTEAR'   => [],
                                'MARCAR'    => [],
                            ],
                        ],
                    ],
                    'status' => true,
                ],
            ],
        ];

        // Definiciones de campos
        $fields = [
            [
                'kind' => 'field',
                'key' => 'base_tela',
                'data' => [
                    'label' => 'Base tela',
                    'field_type' => 'select',
                    'options' => ['ALGODÓN', 'POLIÉSTER', 'LINO', 'SEDA', 'LANA'],
                    'position' => 1,
                    'status' => true,
                ],
            ],
            [
                'kind' => 'field',
                'key' => 'base_hilaza',
                'data' => [
                    'label' => 'Base hilaza',
                    'field_type' => 'select',
                    'options' => ['NYLON', 'RAYÓN', 'ACRÍLICO', 'POLIPROPILENO', 'ALGODÓN'],
                    'position' => 2,
                    'status' => true,
                ],
            ],
            [
                'kind' => 'field',
                'key'  => 'color',
                'data' => [
                    'label'      => 'Color',
                    'field_type' => 'select',
                    'options'    => [
                        'AZUL PETROLEO', 'SALMON', 'VERDE CLARO', 'GRIS CLARO', 'KAKI',
                        'TERRACOTA', 'TAUPE', 'CAFÉ', 'BEIGE', 'VERDE OSCURO', 'VERDE LIMÓN',
                        'TURQUESA', 'NARANJA NEON', 'AZUL ELECTRICO', 'CAMEL', 'GRANATE',
                        'CEREZA', 'BEIGE OSCURO', 'BERENJENA', 'AZUL CELESTE', 'ROSADO',
                        'VINOTINTO', 'NEGRO', 'ROJO', 'AMARILLO', 'NARANJA', 'BLANCO',
                        'LILA', 'VIOLETA', 'FUCSIA', 'AZUL OSCURO', 'PALO ROSA', 'GRIS OSCURO',
                        'VERDE MILITAR', 'CRUDO', 'MOSTAZA', 'VERDE', 'AZUL REY', 'VERDE JADE',
                        'CURUBA', 'AZUL INDIGO', 'AZUL CLARO', 'ROSADO CLARO', 'ROSADO NEON',
                        'GRIS', 'VERDE FUERTE', 'AMARILLO OSCURO', 'VERDE MENTA', 'MAGENTA',
                        'VERDE NEON', 'MENTA', 'AZUL NAVY', 'NUDE', 'ROSA VIEJO', 'CAOBA',
                        'LIMA', 'VERDE PISTACHO', 'AZUL', 'PETROLEO', 'LAVANDA', 'VERDE OLIVA',
                        'DURAZNO', 'AMARILLO CLARO', 'MORADO', 'CREMA', 'MANDARINA', 'GUAYABA',
                        'CANELA', 'INDIGO', 'VERDE SALVIA', 'CAFÉ OSCURO', 'MAUVE',
                        'VERDE PASTEL', 'MARFIL', 'VERDE OSC', 'ROJA', 'GRIS PERLA',
                        'GENERAL', 'OFWHITE', 'ORANGE', 'AZUL NOCHE',
                    ],
                    'position'   => 3,
                    'status'     => true,
                ],
            ],
            [
                'kind' => 'field',
                'key'  => 'talla',
                'data' => [
                    'label'      => 'Talla',
                    'field_type' => 'select',
                    'options'    => ['XS', 'S', 'M', 'L', 'XL', 'XXL', '6', '8', '10', '12', '14', '16', '17CM', '30', '00'],
                    'position'   => 4,
                    'status'     => true,
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
            'fabric_bases' => ['fabric_bases', 'base', 'bases', 'bases_de_tela', 'base_fabrics'],
            'yarn_bases' => ['yarn_bases', 'bases_hilaza', 'bases_hilo'],
            'sizes' => ['sizes', 'tallas', 'talla'],
            'destinations' => ['destinations', 'destinos', 'destino'],
            'months' => ['months', 'meses', 'mes'],
            'priorities' => ['priorities', 'prioridades', 'prioridad'],
            'process_types' => ['process_types', 'tipos_proceso', 'tipos_de_proceso', 'tipo_proceso', 'process_type', 'processes'],
            'operation_actions' => ['operation_actions', 'acciones_operacion', 'acciones_de_operacion', 'acciones_operativas'],
            'operation_garment_parts' => ['operation_garment_parts', 'partes_prenda_operacion', 'partes_de_prenda_operacion', 'partes_operacion'],
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

    /** Bases de tela con factor que afecta el SAM (×1.0 = neutro) */
    private function mapFabricBases(array $items, int $start = 1): array
    {
        $mapped = [];
        foreach ($items as $index => $item) {
            $mapped[] = [
                'id' => $start + $index,
                'name' => is_array($item) ? ($item['name'] ?? $item) : (string) $item,
                'factor' => is_array($item) ? (float) ($item['factor'] ?? 1.0) : 1.0,
            ];
        }
        return $mapped;
    }
}

<?php

namespace Database\Seeders;

use App\Models\Setting;
use App\Services\Settings\SettingService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\File;

class SettingsFieldParametersSeeder extends Seeder
{
    public function __construct(
        protected SettingService $settingService = new SettingService()
    ) {
    }

    public function run(): void
    {
        foreach ($this->definitions() as $item) {
            Setting::updateOrCreate(
                ['kind' => $item['kind'], 'key' => $this->settingService->canonicalKey($item['key'])],
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
                            'key' => 'base_fabrics',
                            'data' => [
                                'label' => 'Bases de tela',
                                'value' => $this->mapNames([
                                    'ANTIFLUIDO', 'BARICHARA', 'BENGALINA', 'BURDA', 'BYBLOS', 'CACHEMIRE', 'CHALÍS', 'CHAMBRAY', 'CORDUROY', 'CREPÉ', 'DACRON', 'DENIM', 'DRIL', 'ENTRETELA', 'ESCUBA', 'FALSO LINO', 'FLEECE', 'FRANELA', 'GAMUZA', 'INDIGO', 'JERSEY', 'LINO', 'LYCRA', 'MALLA', 'MARBELLA', 'NYLON', 'PERCHA', 'PIEL DURAZNO', 'POLILYCRA', 'PLASTICO MINIMAL', 'POLIBENGALINA', 'POPELINA', 'PUNTIROMA', 'RIB', 'SOLAR RECYCLED', 'TEJIDO', 'TWILL', 'VISCOSA',
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
                                    ['name' => 'AZUL PETROLEO', 'code' => '0119182', 'rgb' => '0,119,182'],
                                    ['name' => 'SALMON', 'code' => '250128114', 'rgb' => '250,128,114'],
                                    ['name' => 'VERDE CLARO', 'code' => '14722233', 'rgb' => '147,222,33'],
                                    ['name' => 'GRIS CLARO', 'code' => '189189189', 'rgb' => '189,189,189'],
                                    ['name' => 'KAKI', 'code' => '16114394', 'rgb' => '161,143,94'],
                                    ['name' => 'TERRACOTA', 'code' => '151373', 'rgb' => '151,37,3'],
                                    ['name' => 'TAUPE', 'code' => '1199376', 'rgb' => '119,93,76'],
                                    ['name' => 'CAFÉ', 'code' => '634023', 'rgb' => '63,40,23'],
                                    ['name' => 'BEIGE', 'code' => '207185151', 'rgb' => '207,185,151'],
                                    ['name' => 'VERDE OSCURO', 'code' => '168152', 'rgb' => '16,81,52'],
                                    ['name' => 'VERDE LIMÓN', 'code' => '12819264', 'rgb' => '128,192,64'],
                                    ['name' => 'TURQUESA', 'code' => '0206209', 'rgb' => '0,206,209'],
                                    ['name' => 'NARANJA NEON', 'code' => '255138128', 'rgb' => '255,138,128'],
                                    ['name' => 'AZUL ELECTRICO', 'code' => '00255', 'rgb' => '0,0,255'],
                                    ['name' => 'CAMEL', 'code' => '19113861', 'rgb' => '191,138,61'],
                                    ['name' => 'GRANATE', 'code' => '1765472', 'rgb' => '176,54,72'],
                                    ['name' => 'CEREZA', 'code' => '1972952', 'rgb' => '197,29,52'],
                                    ['name' => 'BEIGE OSCURO', 'code' => '169149123', 'rgb' => '169,149,123'],
                                    ['name' => 'BERENJENA', 'code' => '945376', 'rgb' => '94,53,76'],
                                    ['name' => 'AZUL CELESTE', 'code' => '134176229', 'rgb' => '134,176,229'],
                                    ['name' => 'ROSADO', 'code' => '24054135', 'rgb' => '240,54,135'],
                                    ['name' => 'VINOTINTO', 'code' => '6000', 'rgb' => '60,0,0'],
                                    ['name' => 'NEGRO', 'code' => '000', 'rgb' => '0,0,0'],
                                    ['name' => 'ROJO', 'code' => '25500', 'rgb' => '255,0,0'],
                                    ['name' => 'AMARILLO', 'code' => '2552550', 'rgb' => '255,255,0'],
                                    ['name' => 'NARANJA', 'code' => '2551280', 'rgb' => '255,128,0'],
                                    ['name' => 'BLANCO', 'code' => '255255255', 'rgb' => '255,255,255'],
                                    ['name' => 'LILA', 'code' => '199161217', 'rgb' => '199,161,217'],
                                    ['name' => 'VIOLETA', 'code' => '1376189', 'rgb' => '137,6,189'],
                                    ['name' => 'FUCSIA', 'code' => '2550128', 'rgb' => '255,0,128'],
                                    ['name' => 'AZUL OSCURO', 'code' => '0032', 'rgb' => '0,0,32'],
                                    ['name' => 'PALO ROSA', 'code' => '234137154', 'rgb' => '234,137,154'],
                                    ['name' => 'GRIS OSCURO', 'code' => '514744', 'rgb' => '51,47,44'],
                                    ['name' => 'VERDE MILITAR', 'code' => '758332', 'rgb' => '75,83,32'],
                                    ['name' => 'CRUDO', 'code' => '245245220', 'rgb' => '245,245,220'],
                                    ['name' => 'MOSTAZA', 'code' => '21416043', 'rgb' => '214,160,43'],
                                    ['name' => 'VERDE', 'code' => '212350', 'rgb' => '2,123,50'],
                                    ['name' => 'AZUL REY', 'code' => '33181', 'rgb' => '3,3,181'],
                                    ['name' => 'VERDE JADE', 'code' => '135178157', 'rgb' => '135,178,157'],
                                    ['name' => 'CURUBA', 'code' => '250170148', 'rgb' => '250,170,148'],
                                    ['name' => 'AZUL INDIGO', 'code' => '100126154', 'rgb' => '100,126,154'],
                                    ['name' => 'AZUL CLARO', 'code' => '201233252', 'rgb' => '201,233,252'],
                                    ['name' => 'ROSADO CLARO', 'code' => '246202202', 'rgb' => '246,202,202'],
                                    ['name' => 'ROSADO NEON', 'code' => '255028', 'rgb' => '255,0,28'],
                                    ['name' => 'GRIS', 'code' => '205205205', 'rgb' => '205,205,205'],
                                    ['name' => 'VERDE FUERTE', 'code' => '014580', 'rgb' => '0,145,80'],
                                    ['name' => 'AMARILLO OSCURO', 'code' => '25519334', 'rgb' => '255,193,34'],
                                    ['name' => 'VERDE MENTA', 'code' => '185249229', 'rgb' => '185,249,229'],
                                    ['name' => 'MAGENTA', 'code' => '181321', 'rgb' => '181,3,21'],
                                    ['name' => 'VERDE NEON', 'code' => '20425551', 'rgb' => '204,255,51'],
                                    ['name' => 'ROSADO', 'code' => '255203219', 'rgb' => '255,203,219'],
                                    ['name' => 'MENTA', 'code' => '132255255', 'rgb' => '132,255,255'],
                                    ['name' => 'AZUL NAVY', 'code' => '254076', 'rgb' => '25,40,76'],
                                    ['name' => 'NUDE', 'code' => '250225206', 'rgb' => '250,225,206'],
                                    ['name' => 'ROSA VIEJO', 'code' => '225189178', 'rgb' => '225,189,178'],
                                    ['name' => 'MAGENTA', 'code' => '19741155', 'rgb' => '197,41,155'],
                                    ['name' => 'CAOBA', 'code' => '1746280', 'rgb' => '174,62,80'],
                                    ['name' => 'LIMA', 'code' => '1282550', 'rgb' => '128,255,0'],
                                    ['name' => 'VERDE PISTACHO', 'code' => '197225165', 'rgb' => '197,225,165'],
                                    ['name' => 'AZUL', 'code' => '3169244', 'rgb' => '3,169,244'],
                                    ['name' => 'PETROLEO', 'code' => '0150136', 'rgb' => '0,150,136'],
                                    ['name' => 'LAVANDA', 'code' => '12687194', 'rgb' => '126,87,194'],
                                    ['name' => 'VERDE OLIVA', 'code' => '17518043', 'rgb' => '175,180,43'],
                                    ['name' => 'DURAZNO', 'code' => '255204188', 'rgb' => '255,204,188'],
                                    ['name' => 'AMARILLO CLARO', 'code' => '244255129', 'rgb' => '244,255,129'],
                                    ['name' => 'MORADO', 'code' => '14236170', 'rgb' => '142,36,170'],
                                    ['name' => 'CREMA', 'code' => '249251231', 'rgb' => '249,251,231'],
                                    ['name' => 'MANDARINA', 'code' => '255204128', 'rgb' => '255,204,128'],
                                    ['name' => 'GUAYABA', 'code' => '255134116', 'rgb' => '255,134,116'],
                                    ['name' => 'CANELA', 'code' => '1128975', 'rgb' => '112,89,75'],
                                    ['name' => 'INDIGO', 'code' => '42647', 'rgb' => '4,26,47'],
                                    ['name' => 'VERDE SALVIA', 'code' => '126158132', 'rgb' => '126,158,132'],
                                    ['name' => 'CAFÉ OSCURO', 'code' => '23120', 'rgb' => '23,12,0'],
                                    ['name' => 'MAUVE', 'code' => '1196796', 'rgb' => '119,67,96'],
                                    ['name' => 'VERDE PASTEL', 'code' => '208217179', 'rgb' => '208,217,179'],
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
                    'value' => $this->mapNames([
                        'ALGODÓN', 'POLIÉSTER', 'LINO', 'SEDA', 'LANA',
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
                        'ORGANIZACIONAL', 'FINANCIERO', 'PRODUCCIÓN', 'COMERCIAL', 'LOGÍSTICO', 'TÉCNICO',
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
                        'CCU', 'EJETEX', 'PASSARO', 'HERPO', 'ATUENDOS', 'LOHARY',
                    ]),
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
                'key' => 'color',
                'data' => [
                    'label' => 'Color',
                    'field_type' => 'select',
                    'options' => ['ROJO', 'VERDE', 'AZUL', 'NEGRO', 'BLANCO'],
                    'position' => 3,
                    'status' => true,
                ],
            ],
        ];

        return array_merge($parameters, $fields);
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
}

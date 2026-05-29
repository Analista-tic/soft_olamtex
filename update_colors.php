<?php

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

$colors = [
    ['code' => '000', 'name' => 'UNICO',           'hex' => null],
    ['code' => '001', 'name' => 'BLANCO',           'hex' => '#FFFFFF'],
    ['code' => '002', 'name' => 'NEGRO',            'hex' => '#000000'],
    ['code' => '003', 'name' => 'AMARILLO',         'hex' => '#FFFF00'],
    ['code' => '004', 'name' => 'AZUL',             'hex' => '#03A9F4'],
    ['code' => '005', 'name' => 'ROJO',             'hex' => '#FF0000'],
    ['code' => '006', 'name' => 'GRIS CLARO',       'hex' => '#BDBDBD'],
    ['code' => '007', 'name' => 'GRIS OSCURO',      'hex' => '#332F2C'],
    ['code' => '008', 'name' => 'AZUL CLARO',       'hex' => '#C9E9FC'],
    ['code' => '009', 'name' => 'AZUL OSCURO',      'hex' => '#000020'],
    ['code' => '010', 'name' => 'MOSTAZA',          'hex' => '#D6A02B'],
    ['code' => '011', 'name' => 'VERDE MILITAR',    'hex' => '#4B5320'],
    ['code' => '012', 'name' => 'IVORY',            'hex' => '#FFFAE6'],
    ['code' => '013', 'name' => 'TERRACOTA',        'hex' => '#972503'],
    ['code' => '014', 'name' => 'CAMEL',            'hex' => '#BF8A3D'],
    ['code' => '015', 'name' => 'BEIGE',            'hex' => '#CFB997'],
    ['code' => '016', 'name' => 'VINOTINTO',        'hex' => '#3C0000'],
    ['code' => '017', 'name' => 'GRIS PLOMO',       'hex' => '#696E73'],
    ['code' => '018', 'name' => 'INDIGO CLARO',     'hex' => '#647E9A'],
    ['code' => '020', 'name' => 'PALO DE ROSA',     'hex' => '#EA899A'],
    ['code' => '021', 'name' => 'CHOCOLATE',        'hex' => '#5C3317'],
    ['code' => '022', 'name' => 'MILITAR',          'hex' => '#5A6937'],
    ['code' => '023', 'name' => 'GRANATE',          'hex' => '#B03648'],
    ['code' => '024', 'name' => 'GRIS',             'hex' => '#CDCDCD'],
    ['code' => '025', 'name' => 'CRUDO',            'hex' => '#F5F5DC'],
    ['code' => '026', 'name' => 'MENTA',            'hex' => '#84FFFF'],
    ['code' => '027', 'name' => 'DORADO',           'hex' => '#D2AF32'],
    ['code' => '030', 'name' => 'PLATEADO',         'hex' => '#BEBEC3'],
    ['code' => '032', 'name' => 'CAFÉ',             'hex' => '#784623'],
    ['code' => '033', 'name' => 'VERDE',            'hex' => '#027B32'],
    ['code' => '034', 'name' => 'MORADO',           'hex' => '#8E24AA'],
    ['code' => '035', 'name' => 'AGUA MARINA',      'hex' => '#66CDAA'],
    ['code' => '036', 'name' => 'AZUL REY',         'hex' => '#0303B5'],
    ['code' => '037', 'name' => 'CREMA',            'hex' => '#F9FBE7'],
    ['code' => '038', 'name' => 'FUCSIA CLARO',     'hex' => '#FAB9C3'],
    ['code' => '039', 'name' => 'FUCSIA',           'hex' => '#FF0080'],
    ['code' => '040', 'name' => 'LILA',             'hex' => '#C7A1D9'],
    ['code' => '041', 'name' => 'NARANJA',          'hex' => '#FF8000'],
    ['code' => '042', 'name' => 'VERDE OLIVO',      'hex' => '#AFB42B'],
    ['code' => '043', 'name' => 'SALMÓN',           'hex' => '#FA8072'],
    ['code' => '045', 'name' => 'GRIS JASPE',       'hex' => '#A5A09B'],
    ['code' => '047', 'name' => 'ROSADO',           'hex' => '#FFCBDB'],
    ['code' => '048', 'name' => 'VERDE MENTA',      'hex' => '#B9F9E5'],
    ['code' => '049', 'name' => 'MOCA',             'hex' => '#A38068'],
    ['code' => '050', 'name' => 'TURQUESA',         'hex' => '#00CED1'],
    ['code' => '052', 'name' => 'BEIGE JASPE',      'hex' => '#EDD7BD'],
    ['code' => '053', 'name' => 'TAUPE',            'hex' => '#775D4C'],
    ['code' => '054', 'name' => 'BERENJENA',        'hex' => '#8C289B'],
    ['code' => '055', 'name' => 'CORAL',            'hex' => '#FA785F'],
    ['code' => '056', 'name' => 'VERDE JADE',       'hex' => '#87B29D'],
    ['code' => '057', 'name' => 'VERDE PETRÓLEO',   'hex' => '#009688'],
    ['code' => '058', 'name' => 'AZUL PETRÓLEO',    'hex' => '#2896AF'],
    ['code' => '059', 'name' => 'INDIGO OSCURO',    'hex' => '#041A2F'],
    ['code' => '061', 'name' => 'KAKI',             'hex' => '#A18F5E'],
    ['code' => '062', 'name' => 'MANDARINA',        'hex' => '#FFCC80'],
    ['code' => '063', 'name' => 'AZUL NAVY',        'hex' => '#19284C'],
    ['code' => '067', 'name' => 'VERDE ESMERALDA',  'hex' => '#199B55'],
    ['code' => '068', 'name' => 'AZUL CELESTE',     'hex' => '#86B0E5'],
    ['code' => '070', 'name' => 'VIOLETA',          'hex' => '#8906BD'],
    ['code' => '071', 'name' => 'CURUBA',           'hex' => '#FAAA94'],
    ['code' => '072', 'name' => 'DURAZNO',          'hex' => '#FFCCBC'],
    ['code' => '076', 'name' => 'ROSADO CLARO',     'hex' => '#F6CACA'],
    ['code' => '080', 'name' => 'CAOBA',            'hex' => '#AE3E50'],
    ['code' => '081', 'name' => 'LIMA',             'hex' => '#80FF00'],
    ['code' => '082', 'name' => 'MAGENTA',          'hex' => '#C5299B'],
    ['code' => '083', 'name' => 'VERDE OSCURO',     'hex' => '#105134'],
    ['code' => '084', 'name' => 'VERDE PISTACHO',   'hex' => '#C5E1A5'],
    ['code' => '086', 'name' => 'LAVANDA',          'hex' => '#7E57C2'],
    ['code' => '087', 'name' => 'AMARILLO CLARO',   'hex' => '#F4FF81'],
    ['code' => '088', 'name' => 'MALVA',            'hex' => '#C7A1D9'],
    ['code' => '089', 'name' => 'CAFÉ OSCURO',      'hex' => '#170C00'],
    ['code' => '091', 'name' => 'GUAYABA',          'hex' => '#FF8674'],
    ['code' => '092', 'name' => 'CANELA',           'hex' => '#70594B'],
    ['code' => '093', 'name' => 'VERDE SALVIA',     'hex' => '#7E9E84'],
    ['code' => '094', 'name' => 'MAUVE',            'hex' => '#774360'],
    ['code' => '095', 'name' => 'ROSA VIEJO',       'hex' => '#E1BDB2'],
    ['code' => '097', 'name' => 'NUDE',             'hex' => '#FAE1CE'],
    ['code' => '099', 'name' => 'AZUL ELECTRICO',   'hex' => '#0000FF'],
];

$updated = 0;
$inserted = 0;
$skipped = [];

foreach ($colors as $c) {
    // Buscar por nombre exacto
    $row = DB::table('colors')->whereRaw('UPPER(name) = ?', [strtoupper($c['name'])])->first();

    // Si no encuentra por nombre, buscar por hex
    if (!$row && $c['hex']) {
        $row = DB::table('colors')->whereRaw('UPPER(hexadecimal) = ?', [strtoupper($c['hex'])])->whereNull('code')->first();
    }

    if ($row) {
        DB::table('colors')->where('id', $row->id)->update([
            'code'       => $c['code'],
            'updated_at' => now(),
        ]);
        echo "ACTUALIZADO: {$c['code']} → {$row->name}" . PHP_EOL;
        $updated++;
    } else {
        $skipped[] = $c['code'] . ' - ' . $c['name'];
    }
}

echo PHP_EOL;
echo "Actualizados : {$updated}" . PHP_EOL;
echo "No encontrados: " . count($skipped) . PHP_EOL;
foreach ($skipped as $s) {
    echo "  {$s}" . PHP_EOL;
}

echo PHP_EOL . 'Con código ahora: ' . DB::table('colors')->whereNotNull('code')->count() . PHP_EOL;

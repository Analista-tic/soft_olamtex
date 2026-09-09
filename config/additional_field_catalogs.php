<?php

use App\Models\MasterData\Color;
use App\Models\MasterData\FabricName;
use App\Models\MasterData\YarnName;

// Catálogos del sistema que pueden alimentar las opciones de un campo adicional
// (settings.source_type = 'catalog'). Cada entrada declara el modelo, la columna
// que se usa como valor visible/guardado, y el scope de "activo" a aplicar (null si
// el modelo no distingue activos/inactivos). Añadir un catálogo nuevo aquí es lo
// único que hace falta para que quede disponible en el selector de origen del campo.
return [
    'fabric_names' => [
        'label' => 'Nombres de tela',
        'model' => FabricName::class,
        'value_column' => 'nombre',
        'active_scope' => 'active',
    ],
    'yarn_names' => [
        'label' => 'Nombres de hilaza',
        'model' => YarnName::class,
        'value_column' => 'nombre',
        'active_scope' => 'active',
    ],
    'colors' => [
        'label' => 'Colores',
        'model' => Color::class,
        'value_column' => 'name',
        'active_scope' => null,
    ],
];

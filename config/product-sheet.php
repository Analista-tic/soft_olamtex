<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Procesos para Control de lotes (UI - tabs)
    |--------------------------------------------------------------------------
    |
    | Lista ordenada de TODOS los procesos posibles. Todos los tabs se muestran
    | siempre para consistencia visual y preparar futura restricción por roles.
    | El flujo real de cada orden depende de ProductSheet (product_sheet_processes).
    |
    */
    'batch_control_processes' => [
        'I&D',
        'Tejido',
        'Corte',
        'Logística',
        'Estampado',
        'Desarrollo de muestras',
        'Muestras',
        'Confección',
        'Lavandería',
        'Terminación',
        'Inspección y aprobación',
        'Bodega',
    ],

    /*
    |--------------------------------------------------------------------------
    | Procesos obligatorios (flujo mínimo)
    |--------------------------------------------------------------------------
    |
    | Todas las órdenes deben pasar por estos procesos. Si el producto no los
    | tiene en su ficha, se incluyen igual en el flujo.
    |
    */
    'batch_control_mandatory_processes' => [
        'Muestras',
        'Confección',
        'Terminación',
        'Bodega',
    ],

    /*
    |--------------------------------------------------------------------------
    | Aliases de nombres (ProductSheet → batch_control)
    |--------------------------------------------------------------------------
    |
    | Mapeo para alinear nombres de procesos en ProductSheet con los de batch
    | control (p. ej. "Muestra" en ficha → "Muestras" en control de lotes).
    |
    */
    'batch_control_process_aliases' => [
        'Muestra' => 'Muestras',
        'Estampando' => 'Estampado',
        'Bordado' => 'Estampado',
        'Sublimado' => 'Estampado',
        'Desarrollo de Muestras' => 'Desarrollo de muestras',
    ],

    /*
    |--------------------------------------------------------------------------
    | Procesos permitidos por tipo de ficha
    |--------------------------------------------------------------------------
    |
    | Define qué procesos puede tener cada tipo de ProductSheet.
    | MUESTRA: solo el proceso "Muestras" (construye el listado operacional).
    | PRODUCTO: todos los procesos productivos excepto "Muestras".
    |
    */
    'processes_allowed_by_type' => [
        // MUESTRA: procesos previos a producción + Muestras (donde termina el flujo).
        // Excluye "Desarrollo de muestras" (es de PRODUCTO) y todo lo posterior a Muestras.
        'muestra' => ['I&D', 'Tejido', 'Corte', 'Logística', 'Estampado', 'Muestras'],
        // PRODUCTO: todo excepto "Muestras" (que es exclusivo del flujo muestra).
        'producto' => [
            'I&D', 'Tejido', 'Corte', 'Logística', 'Estampado',
            'Desarrollo de muestras', 'Confección', 'Lavandería', 'Terminación',
            'Inspección y aprobación', 'Bodega',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Procesos obligatorios por tipo (flujo mínimo)
    |--------------------------------------------------------------------------
    |
    | Procesos que se incluyen siempre en el flujo, independiente de los
    | seleccionados en la ficha, según el tipo de ProductSheet.
    |
    */
    'batch_control_mandatory_processes_by_type' => [
        'muestra'  => ['Muestras'],
        'producto' => ['Desarrollo de muestras', 'Confección', 'Terminación', 'Bodega'],
    ],

];

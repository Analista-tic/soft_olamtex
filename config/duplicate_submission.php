<?php

return [
    // Protección contra doble envío (doble clic / reintento) en las altas `*.store`.
    'enabled' => true,

    // Segundos durante los que una petición idéntica del mismo usuario se rechaza tras un envío exitoso.
    'window_seconds' => 4,

    // Segundos máximos que se retiene el bloqueo mientras la primera petición aún se procesa.
    'processing_seconds' => 60,

    // Apagado por defecto en tests (varios crean registros idénticos seguidos); las pruebas de la regla lo activan.
    'in_tests' => false,
];

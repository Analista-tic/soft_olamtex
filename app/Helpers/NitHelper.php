<?php

namespace App\Helpers;

class NitHelper
{
    /**
     * Calcula el digito de verificacion (DV) de un NIT colombiano.
     * Algoritmo oficial DIAN.
     *
     * @param  string|int  $nit  Numero de identificacion sin DV, puntos ni guiones
     */
    public static function calcularDV(string|int $nit): int
    {
        $primos = [3, 7, 13, 17, 19, 23, 29, 37, 41, 43, 47, 53, 59, 67, 71];

        $nitStr = str_pad((string) $nit, 15, '0', STR_PAD_LEFT);
        $nitStr = substr($nitStr, -15);

        $suma = 0;
        for ($i = 0; $i < 15; $i++) {
            $suma += (int) $nitStr[$i] * $primos[14 - $i];
        }

        $residuo = $suma % 11;

        if ($residuo === 0) {
            return 0;
        }

        if ($residuo === 1) {
            return 1;
        }

        return 11 - $residuo;
    }
}

<?php

namespace App\Services;

use App\Models\Parameter;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

class ParameterService
{
    /**
     * Obtiene una lista de parámetros por nombre.
     * Usa caché para mejorar el rendimiento.
     *
     * @param string $name Nombre del parámetro (ej: 'worlds', 'yarn_bases')
     * @return Collection
     */
    public static function getList(string $name): Collection
    {
        $parameter = Cache::remember("param_{$name}", 3600, function () use ($name) {
            return Parameter::where('nombre', $name)->first();
        });
        
        return collect($parameter?->valor ?? []);
    }

    /**
     * Actualiza o crea un parámetro y limpia el caché.
     *
     * @param string $name Nombre del parámetro
     * @param array $value Valor del parámetro
     * @return Parameter
     */
    public static function updateList(string $name, array $value): Parameter
    {
        $parameter = Parameter::updateOrCreate(
            ['nombre' => $name],
            ['valor' => $value]
        );

        Cache::forget("param_{$name}");

        return $parameter;
    }

    /**
     * Limpia el caché de un parámetro específico.
     *
     * @param string $name Nombre del parámetro
     * @return void
     */
    public static function clearCache(string $name): void
    {
        Cache::forget("param_{$name}");
    }

    /**
     * Limpia todo el caché de parámetros.
     *
     * @return void
     */
    public static function clearAllCache(): void
    {
        $parameterNames = [
            'worlds',
            'yarn_bases',
            'fabric_bases',
            'sections',
            'groups',
            'brands',
            'destinations',
        ];

        foreach ($parameterNames as $name) {
            Cache::forget("param_{$name}");
        }
    }
}

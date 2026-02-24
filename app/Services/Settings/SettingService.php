<?php

namespace App\Services\Settings;

use App\Models\Settings\Setting;
use Illuminate\Support\Collection;

class SettingService
{
    private const CATALOG_KEY_ALIASES = [
        'sections' => ['sections', 'secciones', 'seccion', 'section'],
        'groups' => ['groups', 'grupos', 'grupo', 'group'],
        'brands' => ['brands', 'marcas', 'marca', 'brand'],
        'worlds' => ['worlds', 'mundos', 'mundo', 'world'],
        'fabric_bases' => ['fabric_bases', 'bases_de_tela', 'bases', 'base', 'base_fabrics'],
        'yarn_bases' => ['yarn_bases', 'bases_hilaza', 'bases_hilo'],
        'sizes' => ['sizes', 'tallas', 'talla'],
        'lines' => ['lines', 'lineas', 'linea'],
        'destinations' => ['destinations', 'destinos', 'destino'],
        'months' => ['months', 'meses', 'mes'],
        'priorities' => ['priorities', 'prioridades', 'prioridad'],
        'process_types' => ['process_types', 'tipos_proceso', 'tipos_de_proceso', 'tipo_proceso', 'process_type', 'processes'],
        'operational_lists' => ['operational_lists', 'listados_operacionales', 'listado_operacional', 'listado_operacional'],
    ];

    /**
     * Return the available options for a parameter setting.
     *
     * @param string $key
     * @return Collection<int, array{id: string|int, name: string}>
     */
    public function getCatalogOptions(string $key): Collection
    {
        foreach ($this->keyCandidates($key) as $candidate) {
            $options = $this->getParameterOptions($candidate);
            if ($options->isNotEmpty()) {
                return $options;
            }
        }

        return collect();
    }

    /**
     * Obtiene una opción del catálogo por id con todos sus campos (id, name, factor, factor_base, etc.).
     * Útil para leer factor de base tela ligado al tipo de tela.
     *
     * @param  string  $key  Clave del catálogo (ej. fabric_bases)
     * @param  int|string  $id  Id de la opción
     * @return array|null Opción completa o null si no existe
     */
    public function getCatalogOptionById(string $key, int|string $id): ?array
    {
        foreach ($this->keyCandidates($key) as $candidate) {
            $setting = Setting::parameters()
                ->where('key', $candidate)
                ->where('status', true)
                ->first();

            if (!$setting || !is_array($setting->value)) {
                continue;
            }

            foreach ($setting->value as $item) {
                if (!is_array($item) || !array_key_exists('id', $item)) {
                    continue;
                }
                $itemId = $item['id'];
                if ($itemId == $id || (string) $itemId === (string) $id) {
                    return $item;
                }
            }
        }

        return null;
    }

    /**
     * Devuelve las bases de tela con factor para el cálculo del SAM.
     * Incluye id, name, factor (default 1.0), nombre, codigo.
     * Se usa en listados operacionales donde el factor afecta SAM unitario.
     */
    public function getFabricBasesWithFactor(): Collection
    {
        foreach ($this->keyCandidates('fabric_bases') as $candidate) {
            $setting = Setting::parameters()
                ->where('key', $candidate)
                ->where('status', true)
                ->first();

            if (!$setting || !is_array($setting->value)) {
                continue;
            }

            return collect($setting->value)
                ->map(function (mixed $item) {
                    if (!is_array($item) || !array_key_exists('id', $item)) {
                        return null;
                    }
                    $name = $item['name'] ?? $item['nombre'] ?? (string) $item['id'];
                    $factor = isset($item['factor']) ? (float) $item['factor']
                        : (isset($item['factor_base']) ? (float) $item['factor_base']
                            : (isset($item['factor_base_tela']) ? (float) $item['factor_base_tela'] : 1.0));
                    return [
                        'id' => $item['id'],
                        'name' => (string) $name,
                        'nombre' => (string) $name,
                        'codigo' => $item['codigo'] ?? $item['code'] ?? null,
                        'factor' => $factor > 0 ? $factor : 1.0,
                        'factor_base' => $factor,
                        'factor_base_tela' => $factor,
                    ];
                })
                ->filter()
                ->values();
        }

        return collect();
    }

    /**
     * Return the raw options stored for the provided key (no alias resolution).
     */
    private function getParameterOptions(string $key): Collection
    {
        $setting = Setting::parameters()
            ->where('key', $key)
            ->where('status', true)
            ->first();

        if (!$setting) {
            return collect();
        }

        $value = $setting->value;
        if (!is_array($value)) {
            return collect();
        }

        return collect($value)
            ->map(function (mixed $item) {
                if (is_array($item) && array_key_exists('id', $item) && array_key_exists('name', $item)) {
                    return [
                        'id' => $item['id'],
                        'name' => (string) $item['name'],
                    ];
                }

                $label = is_string($item) ? $item : (string) $item;

                return [
                    'id' => $label,
                    'name' => $label,
                ];
            })
            ->values();
    }

    private function keyCandidates(string $key): array
    {
        return self::CATALOG_KEY_ALIASES[$key] ?? [$key];
    }

    public function canonicalKey(string $key): string
    {
        foreach (self::CATALOG_KEY_ALIASES as $canonical => $aliases) {
            if ($canonical === $key || in_array($key, $aliases, true)) {
                return $canonical;
            }
        }

        return $key;
    }
}

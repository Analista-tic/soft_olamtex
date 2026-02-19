<?php

namespace App\Services\Settings;

use App\Models\Setting;
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

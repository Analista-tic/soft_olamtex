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
        'fabric_colors' => ['fabric_colors', 'colores', 'colors'],
        'unit_measures' => ['unit_measures', 'unit_measure_catalog', 'unidades_medida', 'unidades_de_medida', 'unidad_medida'],
        'thread_brands' => ['thread_brands', 'marcas_hilo', 'marcas_de_hilo'],
        'preparation_groups' => ['preparation_groups', 'grupos_preparacion', 'grupo_preparacion'],
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
     * Return catalog options with code when available (e.g. fabric_colors).
     * Used for thread color selection where both name and code are needed.
     *
     * @return Collection<int, array{id: string|int, name: string, code?: string}>
     */
    public function getCatalogOptionsWithCode(string $key): Collection
    {
        foreach ($this->keyCandidates($key) as $candidate) {
            $setting = Setting::parameters()
                ->where('key', $candidate)
                ->where('status', true)
                ->first();

            if (!$setting || !is_array($setting->value)) {
                continue;
            }

            $value = $setting->value;
            return collect($value)
                ->map(function (mixed $item) {
                    if (is_array($item)) {
                        $name = isset($item['name']) ? (string) $item['name'] : null;
                        $id = $item['id'] ?? $name ?? $item['code'] ?? null;
                        $code = isset($item['code']) ? (string) $item['code'] : null;
                        if ($name !== null && $id !== null) {
                            $row = ['id' => $id, 'name' => $name];
                            if ($code !== null) {
                                $row['code'] = $code;
                            }
                            return $row;
                        }
                    }
                    $label = is_string($item) ? $item : (string) $item;
                    return ['id' => $label, 'name' => $label];
                })
                ->filter()
                ->values();
        }

        return collect();
    }

    /**
     * Like getCatalogOptions but preserves additional fields
     * (e.g. allowed_garment_type for sections, factor for fabric_bases).
     *
     * @return Collection<int, array{id: string|int, name: string, ...}>
     */
    public function getCatalogOptionsRich(string $key): Collection
    {
        foreach ($this->keyCandidates($key) as $candidate) {
            $options = $this->getParameterOptionsRich($candidate);
            if ($options->isNotEmpty()) {
                return $options;
            }
        }

        return collect();
    }

    /**
     * Like getParameterOptions but keeps known extra fields.
     */
    private function getParameterOptionsRich(string $key): Collection
    {
        $setting = Setting::parameters()
            ->where('key', $key)
            ->where('status', true)
            ->first();

        if (!$setting || !is_array($setting->value)) {
            return collect();
        }

        $extraFields = ['allowed_garment_type', 'factor', 'factor_base', 'code'];

        return collect($setting->value)
            ->map(function (mixed $item) use ($extraFields) {
                if (is_array($item)) {
                    $name = isset($item['name']) ? (string) $item['name'] : null;
                    $id = $item['id'] ?? $name ?? $item['code'] ?? null;
                    if ($name !== null && $id !== null) {
                        $row = ['id' => $id, 'name' => $name];
                        foreach ($extraFields as $extra) {
                            if (array_key_exists($extra, $item)) {
                                $row[$extra] = $item[$extra];
                            }
                        }
                        return $row;
                    }
                }
                $label = is_string($item) ? $item : (string) $item;
                return ['id' => $label, 'name' => $label];
            })
            ->values();
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
                if (is_array($item)) {
                    $name = isset($item['name']) ? (string) $item['name'] : null;
                    $id = $item['id'] ?? $name ?? $item['code'] ?? null;
                    if ($name !== null && $id !== null) {
                        return [
                            'id' => $id,
                            'name' => $name,
                        ];
                    }
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

    /**
     * Agrega una nueva opción a un catálogo de parámetros.
     * Lee el JSON actual, genera un nuevo id, agrega la opción y persiste.
     *
     * @return array{id: int, name: string} La opción creada
     */
    public function addCatalogOption(string $key, string $name): array
    {
        $canonical = $this->canonicalKey($key);

        // Buscar el Setting que contiene el catálogo
        $setting = null;
        foreach ($this->keyCandidates($canonical) as $candidate) {
            $setting = Setting::parameters()
                ->where('key', $candidate)
                ->where('status', true)
                ->first();
            if ($setting) break;
        }

        if (!$setting) {
            throw new \RuntimeException("No se encontró el catálogo '{$canonical}' en Settings.");
        }

        $current = is_array($setting->value) ? $setting->value : [];

        // Verificar duplicados por nombre
        foreach ($current as $item) {
            if (is_array($item) && isset($item['name']) && mb_strtolower(trim($item['name'])) === mb_strtolower(trim($name))) {
                return ['id' => $item['id'], 'name' => (string) $item['name']];
            }
        }

        // Calcular siguiente id numérico
        $maxId = 0;
        foreach ($current as $item) {
            if (is_array($item) && isset($item['id']) && is_numeric($item['id'])) {
                $maxId = max($maxId, (int) $item['id']);
            }
        }
        $newId = $maxId + 1;

        $newOption = ['id' => $newId, 'name' => trim($name)];

        // Secciones de producto: incluir allowed_garment_type por defecto
        if ($canonical === 'sections') {
            $newOption['allowed_garment_type'] = 'ambos';
        }

        $current[] = $newOption;

        $setting->value = $current;
        $setting->save();

        return $newOption;
    }

    /**
     * Obtiene una lista de strings almacenada como JSON en la tabla settings.
     * Usada para listas parametrizables como operation_actions, operation_garment_parts.
     */
    public function getStringList(string $key): array
    {
        $setting = Setting::parameters()
            ->where('key', $key)
            ->where('status', true)
            ->first();
        if (!$setting) return [];
        $val = $setting->getAttribute('value');
        if (!is_array($val)) return [];
        return array_values(array_filter($val, 'is_string'));
    }

    /**
     * Persiste una lista de strings como JSON en la tabla settings.
     * Crea el registro si no existe.
     */
    public function setStringList(string $key, array $values): void
    {
        $clean = array_values(array_unique(array_filter(array_map('strval', $values))));
        $setting = Setting::parameters()->where('key', $key)->first();
        if ($setting) {
            $setting->value = $clean;
            $setting->save();
        } else {
            Setting::create([
                'key'    => $key,
                'value'  => $clean,
                'type'   => 'parameters',
                'status' => true,
            ]);
        }
    }

    /**
     * Obtiene un parámetro numérico por clave.
     * Usado para operation_cost, yarn_cost, cutting_cost, minute_cost, etc.
     */
    public function getNumericParameter(string $key, float $default = 0.0): float
    {
        $setting = Setting::parameters()
            ->where('key', $key)
            ->where('status', true)
            ->first();

        if (!$setting) {
            return $default;
        }

        $raw = $setting->getAttribute('value');
        if (is_array($raw)) {
            $raw = $raw['value'] ?? $raw[0] ?? null;
        }

        return is_numeric($raw) ? (float) $raw : $default;
    }
}

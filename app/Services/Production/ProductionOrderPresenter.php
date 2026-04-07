<?php

namespace App\Services\Production;

use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection as SupportCollection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Clase de presentación para ProductionOrder.
 *
 * Contiene helpers de transformación y resolución visual que NO son lógica de negocio
 * sino utilidades de presentación extraídas de TechnicalDataSheetController.
 */
class ProductionOrderPresenter
{
    /**
     * Extrae el tipo de OP (MU, MUT, MF, MFT) de un código OP.
     */
    public function extractOpType(string $opCode): string
    {
        $code = strtoupper(trim($opCode));

        if (str_starts_with($code, 'MUT')) return 'MUT';
        if (str_starts_with($code, 'MU'))  return 'MU';
        if (str_starts_with($code, 'MFT')) return 'MFT';

        return 'MF';
    }

    /**
     * Busca un catálogo por ID en una colección.
     */
    public function findCatalogById(EloquentCollection|SupportCollection $catalogs, mixed $id): ?array
    {
        return $catalogs->first(fn(array $item) => (string) ($item['id'] ?? '') === (string) $id);
    }

    /**
     * Resuelve la URL pública de un dibujo plano (flat drawing).
     *
     * Maneja múltiples formatos de ruta (Storage, URLs absolutas, rutas relativas)
     * y busca variaciones del path en el disco público.
     */
    public function resolveFlatDrawingUrl(?string $rawPath): ?string
    {
        if (!is_string($rawPath) || trim($rawPath) === '') {
            return null;
        }

        $path = str_replace('\\', '/', trim($rawPath));
        if (preg_match('/^https?:\/\//i', $path) || preg_match('/^(data:|blob:)/i', $path)) {
            return $path;
        }

        $normalized = ltrim($path, '/');
        if (Str::startsWith($normalized, 'storage/')) {
            $normalized = Str::after($normalized, 'storage/');
        }

        $candidates = [$normalized];
        if (Str::startsWith($normalized, 'product-sheets/flat-sketches/')) {
            $candidates[] = Str::replaceFirst('product-sheets/flat-sketches/', 'product_sheets/', $normalized);
        }
        if (Str::startsWith($normalized, 'product_sheets/')) {
            $candidates[] = Str::replaceFirst('product_sheets/', 'product-sheets/flat-sketches/', $normalized);
        }

        foreach ($candidates as $candidate) {
            if ($candidate !== '' && Storage::disk('public')->exists($candidate)) {
                return $this->storageUrl($candidate);
            }
        }

        return $this->storageUrl($normalized);
    }

    /**
     * Construye la URL pública de Storage para una ruta dada.
     */
    public function storageUrl(string $path): string
    {
        $path = ltrim(str_replace('\\', '/', $path), '/');
        $base = request()->getSchemeAndHttpHost();

        return rtrim($base, '/') . '/storage/' . ltrim($path, '/');
    }

    /**
     * Retorna el texto trimmed o null si está vacío.
     */
    public static function nullableText(mixed $value): ?string
    {
        if (!is_string($value)) {
            return null;
        }
        $text = trim($value);

        return $text !== '' ? $text : null;
    }

    /**
     * Normaliza un valor de tallas (sizes) que puede venir como:
     * - null
     * - string JSON
     * - string separado por comas
     * - array asociativo {size: bool}
     * - array secuencial
     *
     * Siempre retorna un array secuencial de strings.
     */
    public function normalizeSizes(mixed $sizes): array
    {
        if ($sizes === null) {
            return [];
        }

        $extractFromArray = function (array $value): array {
            // Si es un array asociativo (objeto JSON con keys => valores booleanos)
            if (array_keys($value) !== range(0, count($value) - 1)) {
                return array_values(array_filter(
                    array_map(static fn($key) => trim((string) $key), array_keys($value)),
                    static fn($key) => $key !== '' && !empty($value[$key])
                ));
            }

            // Si es un array secuencial simple
            return array_values(array_filter(
                array_map(static fn($item) => is_string($item) || is_numeric($item) ? trim((string) $item) : '', $value),
                static fn($item) => $item !== ''
            ));
        };

        if (is_array($sizes)) {
            return $extractFromArray($sizes);
        }

        if (is_string($sizes)) {
            $trimmed = trim($sizes);
            if ($trimmed === '') {
                return [];
            }

            $decoded = json_decode($trimmed, true);
            if (is_array($decoded)) {
                return $extractFromArray($decoded);
            }

            return array_values(array_filter(
                array_map('trim', explode(',', $trimmed)),
                static fn($item) => $item !== ''
            ));
        }

        return [];
    }
}

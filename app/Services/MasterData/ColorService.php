<?php

namespace App\Services\MasterData;

use App\Models\MasterData\Color;
use App\Models\MasterData\Item;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ColorService
{
    public function getAll(): Collection
    {
        return Color::select('id', 'name', 'rgb', 'hexadecimal', 'description')
            ->orderBy('name')->get();
    }

    /**
     * `code` no se pide en ningún formulario (ni el alta rápida desde el ítem ni la
     * pantalla de Configuración > Colores) — se asigna aquí de forma automática y
     * consecutiva. Sin esto el color queda con `code` null y
     * ItemReferenceSequenceService::generate() falla al construir la referencia
     * automática de un ítem que use ese color recién creado.
     */
    public function create(array $data): Color
    {
        return DB::transaction(function () use ($data) {
            $normalized = $this->normalizeColorValues($data);
            $normalized['code'] = $this->nextCode();

            return Color::create($normalized);
        });
    }

    private function nextCode(): string
    {
        $maxCode = (int) Color::query()->lockForUpdate()->max('code');
        $next = $maxCode + 1;

        if ($next > 999) {
            throw ValidationException::withMessages([
                'code' => 'Se alcanzó el máximo de códigos de color disponibles (999).',
            ]);
        }

        return str_pad((string) $next, 3, '0', STR_PAD_LEFT);
    }

    public function update(Color $color, array $data): Color
    {
        $color->update($this->normalizeColorValues($data));

        return $color->fresh();
    }

    /**
     * Mantiene RGB y hexadecimal como dos representaciones del mismo color.
     * El hex es la fuente de verdad cuando llegan ambos; si solo llega RGB,
     * se deriva el hexadecimal para que ambos campos queden siempre completos.
     */
    private function normalizeColorValues(array $data): array
    {
        $hexadecimal = strtoupper(trim((string) ($data['hexadecimal'] ?? '')));
        $rgb = trim((string) ($data['rgb'] ?? ''));

        if ($hexadecimal !== '') {
            if (! preg_match('/^#[0-9A-F]{6}$/', $hexadecimal)) {
                throw ValidationException::withMessages([
                    'hexadecimal' => 'El hexadecimal debe tener el formato #RRGGBB.',
                ]);
            }

            $rgb = implode(',', [
                hexdec(substr($hexadecimal, 1, 2)),
                hexdec(substr($hexadecimal, 3, 2)),
                hexdec(substr($hexadecimal, 5, 2)),
            ]);
        } elseif ($rgb !== '') {
            $parts = array_map('intval', explode(',', $rgb));
            if (count($parts) !== 3 || count(array_filter($parts, static fn (int $value): bool => $value < 0 || $value > 255)) > 0) {
                throw ValidationException::withMessages([
                    'rgb' => 'El RGB debe contener tres valores entre 0 y 255.',
                ]);
            }

            $rgb = implode(',', $parts);
            $hexadecimal = sprintf('#%02X%02X%02X', ...$parts);
        } else {
            $rgb = null;
            $hexadecimal = null;
        }

        $data['rgb'] = $rgb;
        $data['hexadecimal'] = $hexadecimal;

        return $data;
    }

    public function delete(Color $color): void
    {
        $itemCount = Item::where('color_id', $color->id)->count();

        if ($itemCount > 0) {
            $label = $itemCount === 1
                ? '1 ítem asignado'
                : "{$itemCount} ítems asignados";

            throw new \RuntimeException(
                "No se puede eliminar el color \"{$color->name}\" porque tiene {$label}. "
                .'Reasigne o quite el color de esos ítems antes de eliminarlo.'
            );
        }

        $color->delete();
    }
}

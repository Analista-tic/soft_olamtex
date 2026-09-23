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
            $normalized['code'] = $this->resolveCode($data['code'] ?? null);

            return Color::create($normalized);
        });
    }

    /**
     * Calcula el siguiente código consecutivo SIN reservarlo (sin lock, sin
     * escribir nada). Se usa para precargar la sugerencia editable en el
     * formulario de creación rápida; si dos usuarios lo abren a la vez pueden
     * ver el mismo número sugerido, pero solo uno se lo queda: el que no lo
     * modifique se resuelve por nextCode() con lock al momento de guardar.
     */
    public function previewNextCode(): string
    {
        return $this->buildNextCode(Color::query()->max('code'));
    }

    /**
     * Resuelve el código a persistir: si el caller mandó uno manualmente lo
     * valida (numérico, hasta 3 dígitos) y verifica unicidad antes de
     * normalizarlo; si no, reserva el siguiente consecutivo con lock.
     */
    private function resolveCode(?string $code): string
    {
        $code = trim((string) ($code ?? ''));

        if ($code === '') {
            return $this->nextCode();
        }

        if (! preg_match('/^\d{1,3}$/', $code)) {
            throw ValidationException::withMessages([
                'code' => 'El código debe ser numérico de hasta 3 dígitos.',
            ]);
        }

        $normalized = str_pad($code, 3, '0', STR_PAD_LEFT);

        if (Color::query()->where('code', $normalized)->exists()) {
            throw ValidationException::withMessages([
                'code' => 'Ya existe un color con ese código.',
            ]);
        }

        return $normalized;
    }

    private function nextCode(): string
    {
        return $this->buildNextCode(Color::query()->lockForUpdate()->max('code'));
    }

    private function buildNextCode(?string $max): string
    {
        $next = ((int) $max) + 1;

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

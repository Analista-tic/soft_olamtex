<?php

namespace App\Services\MasterData;

use App\Models\MasterData\CategoryGroup;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CategoryGroupService
{
    /**
     * `code` no se pide en el combobox de creación rápida ("Nuevo grupo" desde el
     * form de Item o el diálogo de subgrupo) — se asigna aquí de forma automática y
     * consecutiva, igual que ColorService::create(). Sin esto el grupo queda con
     * `code` null y ItemReferenceSequenceService::generate() falla al construir la
     * referencia automática de un ítem que use un subgrupo de este grupo.
     */
    public function create(array $data): CategoryGroup
    {
        return DB::transaction(function () use ($data) {
            $data['code'] = $this->resolveCode($data['code'] ?? null);

            return CategoryGroup::create($data);
        });
    }

    /**
     * Calcula el siguiente código consecutivo SIN reservarlo (sin lock, sin
     * escribir nada) — útil para sugerencias en formularios.
     */
    public function previewNextCode(): string
    {
        return $this->buildNextCode(CategoryGroup::query()->max('code'));
    }

    private function resolveCode(?string $code): string
    {
        $code = trim((string) ($code ?? ''));

        if ($code === '') {
            return $this->nextCode();
        }

        if (! preg_match('/^\d{1,2}$/', $code)) {
            throw ValidationException::withMessages([
                'code' => 'El código debe ser numérico de hasta 2 dígitos.',
            ]);
        }

        $normalized = str_pad($code, 2, '0', STR_PAD_LEFT);

        if (CategoryGroup::query()->where('code', $normalized)->exists()) {
            throw ValidationException::withMessages([
                'code' => 'Ya existe un grupo con ese código.',
            ]);
        }

        return $normalized;
    }

    private function nextCode(): string
    {
        return $this->buildNextCode(CategoryGroup::query()->lockForUpdate()->max('code'));
    }

    private function buildNextCode(?string $max): string
    {
        $next = ((int) $max) + 1;

        if ($next > 99) {
            throw ValidationException::withMessages([
                'code' => 'Se alcanzó el máximo de códigos de grupo disponibles (99).',
            ]);
        }

        return str_pad((string) $next, 2, '0', STR_PAD_LEFT);
    }
}

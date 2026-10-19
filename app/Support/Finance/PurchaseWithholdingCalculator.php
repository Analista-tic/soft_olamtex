<?php

namespace App\Support\Finance;

use App\Models\Finance\Retention;

/**
 * Cálculo de retenciones de una factura de compra según la normativa colombiana.
 *
 * Base por defecto (cuando el usuario no la ajusta a mano):
 *  - IVA (ReteIVA, Estatuto Tributario art. 437-1): se calcula sobre el valor del IVA de la operación.
 *  - FUENTE (ReteFuente, art. 365 y ss.), ICA (por mil, acuerdos municipales) y demás: sobre el valor de la
 *    operación antes de IVA (subtotal).
 * Los porcentajes y las bases mínimas (UVT) los define quien configura cada retención; aquí solo se aplica la base.
 */
class PurchaseWithholdingCalculator
{
    public const TYPE_IVA = 'IVA';

    public static function isIvaType(?string $type): bool
    {
        return strtoupper(trim((string) $type)) === self::TYPE_IVA;
    }

    public static function defaultBase(?string $type, float $subtotal, float $iva): float
    {
        return self::isIvaType($type) ? $iva : $subtotal;
    }

    /**
     * Devuelve cada retención con su tipo, la base efectiva y el valor calculado. Un base_amount explícito
     * (ajustado por el usuario) se respeta; si es null se usa la base por defecto del tipo. Se conserva
     * base_amount null cuando es la base por defecto para que se recalcule si cambian los ítems.
     *
     * @param  array<int, mixed>  $withholdings
     * @return list<array<string, mixed>>
     */
    public function normalize(array $withholdings, float $subtotal, float $iva): array
    {
        $rows = [];

        foreach ($withholdings as $withholding) {
            if (! is_array($withholding)) {
                continue;
            }

            $name = trim((string) ($withholding['name'] ?? ''));
            $retention = $this->findRetention($withholding, $name);
            $type = $retention?->type ?? ($withholding['type'] ?? null);
            $percentage = round((float) ($withholding['percentage'] ?? 0), 2);

            $custom = isset($withholding['base_amount']) && is_numeric($withholding['base_amount']) && (float) $withholding['base_amount'] > 0;
            $base = $custom
                ? round((float) $withholding['base_amount'], 2)
                : round(self::defaultBase($type, $subtotal, $iva), 2);

            $rows[] = [
                'name' => $name,
                'percentage' => $percentage,
                'type' => $type,
                'retention_id' => $retention?->id,
                'base_amount' => $custom ? $base : null,
                'base' => $base,
                'value' => round(($base * $percentage) / 100, 2),
            ];
        }

        return $rows;
    }

    /** @param  array<string, mixed>  $withholding */
    private function findRetention(array $withholding, string $name): ?Retention
    {
        if (isset($withholding['retention_id']) && is_numeric($withholding['retention_id'])) {
            $byId = Retention::query()->find((int) $withholding['retention_id']);
            if ($byId) {
                return $byId;
            }
        }

        return $name !== '' ? Retention::query()->where('name', $name)->first() : null;
    }
}

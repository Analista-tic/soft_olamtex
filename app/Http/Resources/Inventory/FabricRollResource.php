<?php

namespace App\Http\Resources\Inventory;

use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Carbon;

class FabricRollResource extends JsonResource
{
    public function toArray($request): array
    {
        $document = $this->purchaseDocumentItem?->document;
        $partialAvailability = $this->quantity && $this->partial_output !== null
            ? $this->quantity - $this->partial_output
            : null;

        return [
            'id' => $this->id,
            'roll' => $this->roll_number,
            'location' => $this->location,
            'fabric' => $this->purchaseDocumentItem?->item?->name
                ?? $this->purchaseDocumentItem?->item_name
                ?? 'Tela',
            'color' => $this->purchaseDocumentItem?->supplier_color ?? 'N/A',
            'colorCode' => $this->deriveColorCode($this->purchaseDocumentItem?->supplier_color),
            'width' => $this->formatMeters($this->roll_width),
            'rollQty' => $this->formatMeters($this->quantity),
            'partialOutput' => $this->formatMeters($this->partial_output),
            'exitQuantity' => $this->formatMeters($this->actual_output),
            'partialAvailability' => $this->formatMeters($partialAvailability),
            'realAvailability' => $this->formatMeters($this->available_quantity),
            'orderNumber' => $document?->supplier_order_number
                ?? $document?->document_number
                ?? 'N/A',
            'provider' => $document?->supplier?->full_name
                ?? $document?->provider_name
                ?? 'Proveedor',
            'createdAt' => $document?->created_at
                ? Carbon::parse($document->created_at)->format('d M Y')
                : null,
            'creator' => $document?->creator?->name,
            'status' => $this->is_depleted ? 'depleted' : 'available',
        ];
    }

    protected function formatMeters(?float $value): string
    {
        if ($value === null) {
            return '0 m';
        }

        $formatted = number_format($value, 2, '.', '');
        $normalized = rtrim(rtrim($formatted, '0'), '.');

        return "{$normalized} m";
    }

    protected function deriveColorCode(?string $color): string
    {
        if (!$color) {
            return '#9CA3AF';
        }

        return '#' . substr(md5($color), 0, 6);
    }
}

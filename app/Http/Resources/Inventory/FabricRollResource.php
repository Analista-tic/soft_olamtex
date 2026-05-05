<?php

namespace App\Http\Resources\Inventory;

use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Carbon;

class FabricRollResource extends JsonResource
{
    public function toArray($request): array
    {
        $document = $this->purchaseDocumentItem?->document;
        $partial = (float) ($this->partial_output ?? 0);
        $actual  = (float) ($this->actual_output ?? 0);
        $partialAvailability = $this->quantity
            ? max(0.0, $this->quantity - $actual - $partial)
            : null;
        $fabricItem = $this->item ?? $this->purchaseDocumentItem?->item;
        $color = $this->internal_color_name
            ?? $this->supplier_color
            ?? $this->purchaseDocumentItem?->supplier_color;

        return [
            'id' => $this->id,
            'fabric_item_id' => $fabricItem?->id,
            'purchase_document_item_id' => $this->purchase_document_item_id,
            'roll' => $this->roll_number,
            'location' => $this->location,
            'fabric' => $fabricItem?->name
                ?? $this->purchaseDocumentItem?->item_name
                ?? $this->description
                ?? 'Tela',
            'color' => $color ?? 'N/A',
            'color_code' => $this->deriveColorCode($color),
            'width' => $this->formatWidth($this->roll_width),
            'width_value' => $this->roll_width,
            'roll_qty' => $this->formatMeters($this->quantity),
            'roll_qty_value' => $this->quantity,
            'partial_output' => $this->formatMeters($this->partial_output),
            'partial_output_value' => $this->partial_output,
            'partial_availability' => $this->formatMeters($partialAvailability),
            'partial_availability_value' => $partialAvailability,
            'exit_quantity' => $this->formatMeters($this->actual_output),
            'exit_quantity_value' => $this->actual_output,
            'real_availability' => $this->formatMeters($this->available_quantity),
            'real_availability_value' => $this->available_quantity,
            'order_number' => $document?->supplier_order_number
                ?? $document?->document_number
                ?? 'N/A',
            'provider' => $document?->supplier?->full_name
                ?? $document?->provider_name
                ?? 'Proveedor',
            'price_per_meter' => $this->purchaseDocumentItem?->price ? (float) $this->purchaseDocumentItem->price : null,
            'created_at' => $document?->created_at
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

    protected function formatWidth(?float $value): string
    {
        if ($value === null) {
            return '0 m';
        }

        return number_format($value, 2, ',', '.') . ' m';
    }

    protected function deriveColorCode(?string $color): string
    {
        if (!$color) {
            return '#9CA3AF';
        }

        return '#' . substr(md5($color), 0, 6);
    }
}

<?php

namespace App\Http\Resources\Inventory;

use App\Models\Textil\FabricRoll;
use App\Support\Textil\FabricRollBalance;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Carbon;

class FabricRollResource extends JsonResource
{
    public function toArray($request): array
    {
        $document = $this->purchaseDocumentItem?->document;
        $partialAvailability = $this->quantity ? FabricRollBalance::free($this->resource) : null;
        $realAvailability = max(0.0, FabricRollBalance::real($this->resource));
        $rollPrice = (float) ($this->price_per_meter ?? 0) > 0
            ? (float) $this->price_per_meter
            : ($this->purchaseDocumentItem?->price ? (float) $this->purchaseDocumentItem->price : null);
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
            'received_quantity_value' => $this->received_quantity ?? $this->quantity,
            'length_review_status' => $this->length_review_status ?? FabricRoll::LENGTH_REVIEW_NONE,
            'partial_output' => $this->formatMeters($this->partial_output),
            'partial_output_value' => $this->partial_output,
            'partial_availability' => $this->formatMeters($partialAvailability),
            'partial_availability_value' => $partialAvailability,
            'exit_quantity' => $this->formatMeters($this->actual_output),
            'exit_quantity_value' => $this->actual_output,
            'real_availability' => $this->formatMeters($realAvailability),
            'real_availability_value' => $realAvailability,
            'order_number' => $document?->supplier_order_number
                ?? $document?->document_number
                ?? 'N/A',
            'provider' => $document?->supplier?->full_name
                ?? $document?->provider_name
                ?? 'Proveedor',
            // Precio propio del rollo (editable); si no tiene, el de su línea de factura.
            'price_per_meter' => $rollPrice,
            'created_at' => $document?->created_at
                ? Carbon::parse($document->created_at)->format('d M Y')
                : null,
            'creator' => $document?->creator?->name,
            'status' => $this->is_depleted ? 'depleted' : 'available',
            'tolerance_status' => $this->tolerance_status ?? 'none',
            'tolerance_reason' => $this->tolerance_reason,
            'is_restricted' => $this->tolerance_status === FabricRoll::TOLERANCE_REJECTED && $this->resolved_at === null,
            'restriction_reason' => $this->tolerance_status === FabricRoll::TOLERANCE_REJECTED ? $this->tolerance_reason : null,
            'invoice_number' => $document?->document_number,
            'credit_note_number' => $this->relationLoaded('creditNoteLinks')
                ? ($this->creditNoteLinks->last()?->creditNote?->document_number ?: null)
                : null,
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

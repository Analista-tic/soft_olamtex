<?php

namespace App\Http\Resources\Textil;

use Illuminate\Http\Resources\Json\JsonResource;

class FabricRollReceptionResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'roll_number' => $this->roll_number,
            'purchase_document_item_id' => $this->purchase_document_item_id,
            'item_id' => $this->item_id,
            'quantity' => $this->toFloat($this->quantity),
            'roll_width' => $this->toFloat($this->roll_width),
            'location' => $this->location,
            'description' => $this->description,
            'partial_output' => $this->toFloat($this->partial_output),
            'actual_output' => $this->toFloat($this->actual_output),
            'entry_date' => optional($this->entry_date)->format('Y-m-d'),
            'available_quantity' => $this->toFloat($this->available_quantity),
            'is_depleted' => (bool) $this->is_depleted,
            'approval_status' => $this->approval_status,
            'tolerance_status' => $this->tolerance_status ?? 'none',
            'tolerance_reason' => $this->tolerance_reason,
            'resolved_at' => optional($this->resolved_at)->toISOString(),
            'written_off_at' => optional($this->written_off_at)->toISOString(),
            'credit_note_number' => $this->linkedCreditNoteNumber(),
            'created_at' => optional($this->created_at)->toISOString(),
            'updated_at' => optional($this->updated_at)->toISOString(),
        ];
    }

    /** Número de la nota crédito enlazada más reciente (solo si la relación está cargada). */
    protected function linkedCreditNoteNumber(): ?string
    {
        if (! $this->relationLoaded('creditNoteLinks')) {
            return null;
        }

        $number = $this->creditNoteLinks->last()?->creditNote?->document_number;

        return $number !== null && $number !== '' ? (string) $number : null;
    }

    protected function toFloat(mixed $value): float
    {
        return round((float) $value, 2);
    }
}

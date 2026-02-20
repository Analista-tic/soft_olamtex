<?php

namespace App\Http\Resources\Production;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductOperationListResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'product_sheet_id' => $this->product_sheet_id,
            'operation_id' => $this->operation_id,
            'machine_id' => $this->machine_id,
            'operation_section_id' => $this->operation_section_id,
            'sequence_order' => $this->sequence_order,
            'is_active' => $this->is_active,
            
            // Tiempos
            'optimal_time' => round($this->optimal_time, 4),
            'sam' => round($this->sam, 4),
            'production_time' => round($this->production_time, 4),
            'actual_time' => $this->actual_time ? round($this->actual_time, 4) : null,
            'variance' => $this->variance ? round($this->variance, 2) : null,
            
            // Factores
            'operator_efficiency_factor' => round($this->operator_efficiency_factor, 2),
            'machine_supplement_factor' => round($this->machine_supplement_factor, 2),
            
            // Campos calculados adicionales
            'units_per_hour_100' => $this->production_time > 0 
                ? round(60 / $this->production_time, 2) 
                : 0,
            
            'unit_cost' => $this->when(
                $request->has('labor_cost_per_minute'),
                fn() => round($this->production_time * $request->input('labor_cost_per_minute', 0), 4)
            ),
            
            // Metadata
            'notes' => $this->notes,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
            
            // Relaciones
            'operation' => $this->whenLoaded('operation', function () {
                return [
                    'id' => $this->operation->id,
                    'name' => $this->operation->name,
                    'action' => $this->operation->action,
                    'garment_part' => $this->operation->garment_part,
                ];
            }),
            
            'machine' => $this->whenLoaded('machine', function () {
                return [
                    'id' => $this->machine->id,
                    'code' => $this->machine->code,
                    'name' => $this->machine->name,
                    'supplement_factor' => $this->machine->supplement_factor,
                ];
            }),
            
            'operation_section' => $this->whenLoaded('operationSection', function () {
                return [
                    'id' => $this->operationSection->id,
                    'name' => $this->operationSection->name,
                    'type' => $this->operationSection->type,
                ];
            }),
            
            'created_by' => $this->whenLoaded('createdBy', function () {
                return [
                    'id' => $this->createdBy->id,
                    'name' => $this->createdBy->name,
                ];
            }),
        ];
    }
}

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
        $samBase = $this->sam_base ?? $this->sam ?? $this->optimal_time ?? 0;
        $samTotalUnitario = $this->sam_total_unitario;
        $samTotalOperacion = $this->sam_total_operacion;

        return [
            'id' => $this->id,
            'product_sheet_id' => $this->product_sheet_id,
            'operation_id' => $this->operation_id,
            'machine_id' => $this->machine_id,
            'operation_section_id' => $this->operation_section_id,
            'sequence' => $this->sequence,
            'fabric_base_id' => $this->fabric_base_id,
            'quantity' => $this->quantity ?? 1,
            'is_active' => $this->is_active,

            // Tiempos y SAM
            'sam_base' => is_numeric($samBase) ? round((float) $samBase, 4) : null,
            'optimal_time' => $this->optimal_time ? round($this->optimal_time, 4) : null,
            'sam' => $this->sam ? round($this->sam, 4) : null,
            'sam_total_unitario' => is_numeric($samTotalUnitario) ? round((float) $samTotalUnitario, 4) : null,
            'sam_total_operacion' => is_numeric($samTotalOperacion) ? round((float) $samTotalOperacion, 4) : null,
            'fabric_base_factor' => $this->fabric_base_factor ? round($this->fabric_base_factor, 4) : null,
            'production_time' => $this->production_time ? round($this->production_time, 4) : null,
            'actual_time' => $this->actual_time ? round($this->actual_time, 4) : null,
            'variance' => $this->variance ? round($this->variance, 2) : null,
            
            // Factores
            'operator_efficiency_factor' => round($this->operator_efficiency_factor, 4),
            'machine_supplement_factor' => round($this->machine_supplement_factor, 4),
            
            // Campos calculados adicionales
            'units_per_hour_100' => $this->production_time > 0 
                ? round(60 / $this->production_time, 2) 
                : 0,
            
            'unit_cost' => $this->when(
                $request->has('labor_cost_per_minute'),
                fn() => round($this->production_time * $request->input('labor_cost_per_minute', 0), 4)
            ),
            
            // Metadata
            'grupo_preparacion' => $this->grupo_preparacion,
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
            
        ];
    }
}

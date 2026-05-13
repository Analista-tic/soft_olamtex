<?php

namespace App\Models\Production;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class TechnicalSheetSupply extends Model
{
    use SoftDeletes;

    protected $table = 'technical_sheet_supplies';

    protected $fillable = [
        'production_order_id',
        'uid',
        'name',
        'stage',
        'sequence',
        'display_label',
        'item_id',
        'requested_name',
        'supplier',
        'description',
        'moda_linea',
        'uom',
        'qty_per_unit',
        'unit_cost',
        'internal_request_id',
        'purchase_request_id',
        'document_url',
        'notes',
        'is_draft',
        'size_measurements',
    ];

    protected $casts = [
        'sequence'            => 'integer',
        'item_id'             => 'integer',
        'internal_request_id' => 'integer',
        'purchase_request_id' => 'integer',
        'qty_per_unit'        => 'float',
        'unit_cost'           => 'float',
        'is_draft'            => 'boolean',
        'size_measurements'   => 'array',
        'moda_linea'          => 'string',
    ];

    public function productionOrder(): BelongsTo
    {
        return $this->belongsTo(ProductionOrder::class, 'production_order_id');
    }

    /** Serializa al formato array que espera el frontend (manteniendo keys camelCase). */
    public function toFrontendArray(): array
    {
        return [
            'id'                => $this->uid,
            'name'              => $this->name,
            'stage'             => $this->stage,
            'sequence'          => $this->sequence,
            'displayLabel'      => $this->display_label,
            'itemId'            => $this->item_id,
            'requestedName'     => $this->requested_name,
            'supplier'          => $this->supplier,
            'description'       => $this->description,
            'modaLinea'         => $this->moda_linea ?? 'NA',
            'uom'               => $this->uom,
            'qtyPerUnit'        => (float) $this->qty_per_unit,
            'unitCost'          => (float) $this->unit_cost,
            'internalRequestId' => $this->internal_request_id,
            'purchaseRequestId' => $this->purchase_request_id,
            'documentUrl'       => $this->document_url,
            'notes'             => $this->notes,
            'isDraft'           => (bool) $this->is_draft,
            'sizeMeasurements'  => $this->size_measurements ?? null,
            'createdAt'         => $this->created_at?->toISOString(),
            'updatedAt'         => $this->updated_at?->toISOString(),
        ];
    }
}

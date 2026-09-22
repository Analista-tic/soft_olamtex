<?php

namespace App\Models\Expenses;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PurchaseDocumentItem extends Model
{
    use HasFactory;

    protected $table = 'purchase_document_items';

    protected $fillable = [
        'purchase_document_id',
        'item_id',
        'item_name',
        'quantity',
        'price',
        'tax',
        'total',
        'total_tax',
        'supplier_code',
        'supplier_color',
        'order_width',
        'description',
    ];

    protected $casts = [
        'quantity'    => 'decimal:3',
        'price'       => 'decimal:2',
        'tax'         => 'decimal:2',
        'total'       => 'decimal:2',
        'total_tax'   => 'decimal:2',
        'order_width' => 'decimal:3',
    ];

    public function document()
    {
        return $this->belongsTo(PurchaseDocument::class, 'purchase_document_id');
    }

    public function item()
    {
        return $this->belongsTo(\App\Models\MasterData\Item::class, 'item_id');
    }

    // Alias para compatibilidad con vistas que usaban `article`
    public function article()
    {
        return $this->item();
    }

    public function receptions()
    {
        return $this->hasMany(PurchaseDocumentItemReception::class, 'purchase_document_item_id');
    }
}

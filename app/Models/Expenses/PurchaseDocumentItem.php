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
        'quantity'    => 'float',
        'price'       => 'float',
        'tax'         => 'float',
        'total'       => 'float',
        'total_tax'   => 'float',
        'order_width' => 'float',
    ];

    public function document()
    {
        return $this->belongsTo(PurchaseDocument::class, 'purchase_document_id');
    }

    public function item()
    {
        return $this->belongsTo(\App\Models\Inventory\Item::class, 'item_id');
    }

    // Alias para compatibilidad con vistas que usaban `article`
    public function article()
    {
        return $this->item();
    }
}

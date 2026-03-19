<?php

namespace App\Models\Income;

use App\Models\MasterData\Item;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SaleDocumentItem extends Model
{
    use HasFactory;

    protected $table = 'sale_document_items';

    protected $fillable = [
        'sale_document_id',
        'item_id',
        'item_name',
        'quantity',
        'price',
        'discount',
        'tax',
        'total',
        'total_tax',
        'supplier_code',
        'supplier_color',
        'order_width',
        'description',
    ];

    protected $casts = [
        'quantity' => 'float',
        'price' => 'float',
        'discount' => 'float',
        'tax' => 'float',
        'total' => 'float',
        'total_tax' => 'float',
        'order_width' => 'float',
    ];

    public function document()
    {
        return $this->belongsTo(SaleDocument::class, 'sale_document_id');
    }

    public function item()
    {
        return $this->belongsTo(Item::class, 'item_id');
    }

    public function article()
    {
        return $this->item();
    }
}

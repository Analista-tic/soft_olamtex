<?php

namespace App\Models\Expenses;

use App\Models\Expenses\PurchaseDocumentItem;
use App\Models\Inventory\FabricRoll;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class PurchaseInvoiceItem extends PurchaseDocumentItem
{
    use HasFactory;

    protected $table = 'purchase_document_items';

    public function fabricRolls()
    {
        return $this->hasMany(FabricRoll::class, 'purchase_document_item_id');
    }
}

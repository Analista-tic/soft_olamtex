<?php

namespace App\Models\Textil;

use Illuminate\Database\Eloquent\Model;

class FabricOrderItem extends Model
{
	protected $fillable = [
		'fabric_order_id',
		'product_id',
		'quantity',
		'unit_price',
		'status',
	];

	public function fabricOrder()
	{
		return $this->belongsTo(FabricOrder::class, 'fabric_order_id');
	}
}
